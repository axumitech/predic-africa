/**
 * @file OrderBookService.js
 * @description Moteur d'appariement (Matching Engine) pour les ordres limités.
 * Implémente la logique "Price-Time Priority".
 */

const { Pool } = require('pg');

const dbPool = new Pool({
    connectionString: process.env.DATABASE_URL
});

class OrderBookService {
    
    /**
     * Traite un ordre limité et l'apparie avec les ordres opposés du carnet.
     * Logique de marché binaire : Un achat de 'YES' à prix P est apparié avec un achat de 'NO' à prix (1 - P) ou plus.
     * 
     * @param {number} marketId - ID du marché.
     * @param {number} userId - ID de l'utilisateur.
     * @param {('YES'|'NO')} outcomeType - L'issue désirée.
     * @param {number} limitPrice - Prix maximum que l'utilisateur est prêt à payer (ex: 0.60).
     * @param {number} quantity - Nombre de parts (shares) demandées.
     * @returns {Promise<Object>} Statut de l'exécution de l'ordre.
     */
    static async processLimitOrder(marketId, userId, outcomeType, limitPrice, quantity) {
        if (limitPrice <= 0 || limitPrice >= 1) throw new Error("Le prix doit être entre 0 et 1.");
        if (quantity <= 0) throw new Error("La quantité doit être positive.");

        const client = await dbPool.connect();

        try {
            await client.query('BEGIN');

            const oppositeOutcome = outcomeType === 'YES' ? 'NO' : 'YES';
            let remainingQuantity = parseFloat(quantity);
            let totalCost = 0;

            // 1. Déduction préventive maximale du wallet (pour garantir l'ordre)
            // L'utilisateur doit avoir (limitPrice * quantity) au minimum.
            const maxCost = limitPrice * remainingQuantity;
            const walletRes = await client.query(`
                UPDATE wallets 
                SET balance = balance - $1 
                WHERE user_id = $2 
                RETURNING id
            `, [maxCost, userId]);

            if (walletRes.rowCount === 0) throw new Error("Fonds insuffisants ou portefeuille introuvable.");
            const buyerWalletId = walletRes.rows[0].id;

            // 2. Recherche des ordres opposés compatibles (Price-Time Priority)
            // Maker condition : limitPrice(taker) + limitPrice(maker) >= 1.00
            // On veut les ordres Maker qui ont le prix le plus HAUT (car ils paient plus, donc le Taker paie moins).
            // D'où le tri DESC sur le prix.
            const matchingOrdersQuery = `
                SELECT id, user_id, price, remaining_quantity 
                FROM orders 
                WHERE market_id = $1 
                  AND outcome = $2 
                  AND status IN ('OPEN', 'PARTIAL')
                  AND (price + $3) >= 1.00
                ORDER BY price DESC, created_at ASC
                FOR UPDATE
            `;
            
            const oppositeOrders = await client.query(matchingOrdersQuery, [marketId, oppositeOutcome, limitPrice]);

            // 3. Boucle d'exécution partielle (Partial fills)
            for (const makerOrder of oppositeOrders.rows) {
                if (remainingQuantity <= 0) break;

                const makerPrice = parseFloat(makerOrder.price);
                const makerQty = parseFloat(makerOrder.remaining_quantity);
                
                // Le Taker bénéficie du prix du Maker (price improvement)
                const execPrice = 1.00 - makerPrice;
                const tradeQty = Math.min(remainingQuantity, makerQty);

                // Mise à jour de l'ordre Maker
                const newMakerQty = makerQty - tradeQty;
                const makerStatus = newMakerQty === 0 ? 'FILLED' : 'PARTIAL';

                await client.query(`
                    UPDATE orders 
                    SET remaining_quantity = $1, status = $2 
                    WHERE id = $3
                `, [newMakerQty, makerStatus, makerOrder.id]);

                // Distribution des parts
                // Pour le Taker
                await client.query(`
                    INSERT INTO positions (user_id, market_id, outcome, shares, total_invested) 
                    VALUES ($1, $2, $3, $4, $5)
                    ON CONFLICT (user_id, market_id, outcome) 
                    DO UPDATE SET shares = positions.shares + $4, total_invested = positions.total_invested + $5
                `, [userId, marketId, outcomeType, tradeQty, tradeQty * execPrice]);

                // L'argent du Maker a déjà été déduit lors de la création de son ordre.
                // Le maker reçoit ses parts.
                await client.query(`
                    INSERT INTO positions (user_id, market_id, outcome, shares, total_invested) 
                    VALUES ($1, $2, $3, $4, $5)
                    ON CONFLICT (user_id, market_id, outcome) 
                    DO UPDATE SET shares = positions.shares + $4, total_invested = positions.total_invested + $5
                `, [makerOrder.user_id, marketId, oppositeOutcome, tradeQty, tradeQty * makerPrice]);

                remainingQuantity -= tradeQty;
                totalCost += (tradeQty * execPrice);
            }

            // 4. Gestion du surplus / Création de l'ordre résiduel
            let orderStatus = 'FILLED';
            if (remainingQuantity > 0) {
                orderStatus = remainingQuantity === quantity ? 'OPEN' : 'PARTIAL';
                
                // Insertion de l'ordre (partie non comblée) dans le carnet
                await client.query(`
                    INSERT INTO orders (market_id, user_id, outcome, price, quantity, remaining_quantity, status) 
                    VALUES ($1, $2, $3, $4, $5, $6, $7)
                `, [marketId, userId, outcomeType, limitPrice, quantity, remainingQuantity, orderStatus]);
                
                // Le coût des parts non comblées reste bloqué (au limitPrice)
                totalCost += (remainingQuantity * limitPrice);
            }

            // 5. Remboursement si exécution à un meilleur prix (Price Improvement)
            const refund = maxCost - totalCost;
            if (refund > 0) {
                await client.query(`
                    UPDATE wallets SET balance = balance + $1 WHERE id = $2
                `, [refund, buyerWalletId]);
            }

            // Historique de transaction
            if (totalCost > 0) {
                await client.query(`
                    INSERT INTO transactions (wallet_id, type, amount, reference) 
                    VALUES ($1, 'TRADE', $2, $3)
                `, [buyerWalletId, -totalCost, `Limit Order ${orderStatus} on Market ${marketId}`]);
            }

            await client.query('COMMIT');
            
            return {
                success: true,
                status: orderStatus,
                filledQuantity: quantity - remainingQuantity,
                actualCost: totalCost
            };

        } catch (error) {
            await client.query('ROLLBACK');
            console.error("[OrderBookService] Erreur lors de l'appariement: ", error.message);
            throw error;
        } finally {
            client.release();
        }
    }
}

module.exports = OrderBookService;
