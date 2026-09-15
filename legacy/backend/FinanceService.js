/**
 * @file FinanceService.js
 * @description Service financier gérant les dépôts Mobile Money (webhooks) 
 * et la résolution des marchés avec calcul des bénéfices et prélèvement des frais.
 */

const { Pool } = require('pg');

const dbPool = new Pool({
    connectionString: process.env.DATABASE_URL
});

class FinanceService {
    
    /**
     * Traite un dépôt d'argent via Mobile Money (Webhook).
     * Déduit 3% de frais de flux (frais d'opérateur/plateforme) et crédite le solde.
     * 
     * @param {number} userId - ID de l'utilisateur.
     * @param {number} rawAmount - Montant brut déposé (ex: 10000 XOF).
     * @param {string} txReference - Référence de transaction externe de l'opérateur.
     * @returns {Promise<Object>} Statut du dépôt.
     */
    static async processMobileMoneyDeposit(userId, rawAmount, txReference) {
        if (rawAmount <= 0) throw new Error("Le montant du dépôt doit être positif.");

        const client = await dbPool.connect();

        try {
            await client.query('BEGIN');

            const FEE_PERCENTAGE = 0.03;
            const feeAmount = rawAmount * FEE_PERCENTAGE;
            const netAmount = rawAmount - feeAmount;

            // Récupération et verrouillage du wallet
            const walletRes = await client.query(`
                SELECT id FROM wallets WHERE user_id = $1 FOR UPDATE
            `, [userId]);

            if (walletRes.rowCount === 0) throw new Error("Wallet introuvable pour cet utilisateur.");
            const walletId = walletRes.rows[0].id;

            // Mise à jour atomique du solde
            await client.query(`
                UPDATE wallets SET balance = balance + $1 WHERE id = $2
            `, [netAmount, walletId]);

            // Insertion des traces de transaction
            // Trace du crédit net
            await client.query(`
                INSERT INTO transactions (wallet_id, type, amount, reference) 
                VALUES ($1, 'DEPOSIT', $2, $3)
            `, [walletId, netAmount, `MM_DEP_${txReference}`]);

            // Trace des frais (pour la comptabilité interne)
            await client.query(`
                INSERT INTO transactions (wallet_id, type, amount, reference) 
                VALUES ($1, 'FEE', $2, $3)
            `, [walletId, -feeAmount, `MM_FEE_${txReference}`]);

            await client.query('COMMIT');

            return {
                success: true,
                rawAmount,
                feeAmount,
                netAmountCredited: netAmount
            };

        } catch (error) {
            await client.query('ROLLBACK');
            console.error("[FinanceService] Erreur lors du dépôt Mobile Money: ", error.message);
            throw error;
        } finally {
            client.release();
        }
    }

    /**
     * Résout un marché, paie les investisseurs gagnants et prélève 5% de Profit Fee.
     * 
     * @param {number} marketId - ID du marché à résoudre.
     * @param {('YES'|'NO')} winningOutcome - L'issue qui a gagné.
     * @returns {Promise<Object>} Résumé de la résolution.
     */
    static async resolveMarket(marketId, winningOutcome) {
        const client = await dbPool.connect();

        try {
            await client.query('BEGIN');

            // 1. Verrouiller le marché et vérifier son statut
            const marketRes = await client.query(`
                SELECT status FROM markets WHERE id = $1 FOR UPDATE
            `, [marketId]);

            if (marketRes.rowCount === 0) throw new Error("Marché introuvable.");
            if (marketRes.rows[0].status !== 'ACTIVE') throw new Error("Le marché n'est plus actif.");

            // 2. Mettre à jour le statut du marché
            await client.query(`
                UPDATE markets 
                SET status = 'RESOLVED', winning_outcome = $1 
                WHERE id = $2
            `, [winningOutcome, marketId]);

            // 3. Récupérer toutes les positions gagnantes pour ce marché
            const winningPositions = await client.query(`
                SELECT p.user_id, p.shares, p.total_invested, w.id as wallet_id
                FROM positions p
                JOIN wallets w ON p.user_id = w.user_id
                WHERE p.market_id = $1 AND p.outcome = $2
                FOR UPDATE OF w -- Verrouille les wallets pour éviter les accès concurrents
            `, [marketId, winningOutcome]);

            const PROFIT_FEE_PERCENTAGE = 0.05;
            let totalPayouts = 0;
            let totalFeesCollected = 0;

            // 4. Boucle de paiement pour chaque gagnant
            for (const position of winningPositions.rows) {
                const shares = parseFloat(position.shares);
                const invested = parseFloat(position.total_invested);
                
                // Le gain brut correspond au nombre de parts (1 part = 1 XOF à la résolution)
                const grossPayout = shares;
                const netProfit = grossPayout - invested;

                let fee = 0;
                // La taxe de 5% ne s'applique QUE sur les bénéfices purs
                if (netProfit > 0) {
                    fee = netProfit * PROFIT_FEE_PERCENTAGE;
                }

                const netPayout = grossPayout - fee;

                // Créditer le wallet de l'utilisateur
                await client.query(`
                    UPDATE wallets SET balance = balance + $1 WHERE id = $2
                `, [netPayout, position.wallet_id]);

                // Tracer la transaction de résolution
                await client.query(`
                    INSERT INTO transactions (wallet_id, type, amount, reference) 
                    VALUES ($1, 'RESOLUTION', $2, $3)
                `, [position.wallet_id, netPayout, `Payout Mkt ${marketId}`]);

                if (fee > 0) {
                    // Tracer la collecte des frais (lié au compte utilisateur pour l'audit)
                    await client.query(`
                        INSERT INTO transactions (wallet_id, type, amount, reference) 
                        VALUES ($1, 'FEE', $2, $3)
                    `, [position.wallet_id, -fee, `Profit Fee Mkt ${marketId}`]);
                }

                totalPayouts += netPayout;
                totalFeesCollected += fee;
            }

            // 5. Annuler les ordres OPEN/PARTIAL restants dans le carnet d'ordres
            // Note: Les fonds de ces ordres limités étaient verrouillés. Ils doivent être remboursés.
            const openOrders = await client.query(`
                SELECT id, user_id, price, remaining_quantity 
                FROM orders 
                WHERE market_id = $1 AND status IN ('OPEN', 'PARTIAL')
            `, [marketId]);

            for (const order of openOrders.rows) {
                const refundAmount = parseFloat(order.price) * parseFloat(order.remaining_quantity);
                
                await client.query(`
                    UPDATE wallets SET balance = balance + $1 WHERE user_id = $2
                `, [refundAmount, order.user_id]);

                await client.query(`
                    UPDATE orders SET status = 'CANCELLED' WHERE id = $1
                `, [order.id]);
            }

            await client.query('COMMIT');

            return {
                success: true,
                winnersCount: winningPositions.rowCount,
                totalPayouts,
                totalFeesCollected,
                cancelledOrdersCount: openOrders.rowCount
            };

        } catch (error) {
            await client.query('ROLLBACK');
            console.error("[FinanceService] Erreur lors de la résolution du marché: ", error.message);
            throw error;
        } finally {
            client.release();
        }
    }
}

module.exports = FinanceService;
