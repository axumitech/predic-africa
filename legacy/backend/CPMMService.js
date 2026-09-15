/**
 * @file CPMMService.js
 * @description Service gérant la liquidité automatisée (Automated Market Maker) 
 * selon la formule du produit constant (x * y = k).
 */

const { Pool } = require('pg');

// Initialisation du pool PostgreSQL (doit être configuré via variables d'environnement)
const dbPool = new Pool({
    connectionString: process.env.DATABASE_URL
});

class CPMMService {
    
    /**
     * Exécute un achat de parts dans le pool CPMM de manière transactionnelle.
     * 
     * @param {number} marketId - L'ID du marché.
     * @param {number} userId - L'ID de l'utilisateur.
     * @param {number} amountInvested - Le montant investi (ex: 5000 XOF).
     * @param {('YES'|'NO')} outcomeType - L'issue choisie par l'utilisateur.
     * @returns {Promise<Object>} Résultat de la transaction.
     */
    static async buyShares(marketId, userId, amountInvested, outcomeType) {
        if (amountInvested <= 0) {
            throw new Error("Le montant investi doit être strictement positif.");
        }

        const client = await dbPool.connect();

        try {
            // 1. Début de la transaction SQL
            await client.query('BEGIN');

            // 2. Débit du portefeuille de l'utilisateur avec vérification de solde
            // La contrainte CHECK (balance >= 0) sur la table wallets s'assurera 
            // que si le solde est insuffisant, une exception sera levée.
            const walletRes = await client.query(`
                UPDATE wallets 
                SET balance = balance - $1 
                WHERE user_id = $2 
                RETURNING id, balance
            `, [amountInvested, userId]);

            if (walletRes.rowCount === 0) {
                throw new Error("Portefeuille utilisateur introuvable.");
            }
            const walletId = walletRes.rows[0].id;

            // 3. Verrouillage du pool CPMM pour éviter les race conditions (SELECT ... FOR UPDATE)
            const poolRes = await client.query(`
                SELECT yes_pool, no_pool, k_constant 
                FROM cpmm_pools 
                WHERE market_id = $1 
                FOR UPDATE
            `, [marketId]);

            if (poolRes.rowCount === 0) {
                throw new Error("Pool de liquidité introuvable pour ce marché.");
            }

            let { yes_pool, no_pool, k_constant } = poolRes.rows[0];
            yes_pool = parseFloat(yes_pool);
            no_pool = parseFloat(no_pool);
            k_constant = parseFloat(k_constant);

            // 4. Algorithme CPMM (x * y = k)
            // Mécanisme : Le système mint [amountInvested] parts OUI et NON.
            // L'utilisateur garde les parts de l'issue choisie et vend les parts opposées au pool.
            let userSharesReceived = 0;
            let newYesPool = yes_pool;
            let newNoPool = no_pool;

            if (outcomeType === 'YES') {
                // Utilisateur ajoute amountInvested au NO_pool (il revend ses parts NON)
                newNoPool = no_pool + amountInvested;
                // Le nouveau pool YES est calculé pour maintenir k constant
                newYesPool = k_constant / newNoPool;
                // Les parts YES retirées du pool sont données à l'utilisateur
                const sharesFromPool = yes_pool - newYesPool;
                // L'utilisateur gagne ses parts mintées (amountInvested) + parts du pool
                userSharesReceived = amountInvested + sharesFromPool;
            } else {
                // Logique inversée pour achat de parts NON
                newYesPool = yes_pool + amountInvested;
                newNoPool = k_constant / newYesPool;
                const sharesFromPool = no_pool - newNoPool;
                userSharesReceived = amountInvested + sharesFromPool;
            }

            // 5. Mise à jour du pool de liquidité
            await client.query(`
                UPDATE cpmm_pools 
                SET yes_pool = $1, no_pool = $2 
                WHERE market_id = $3
            `, [newYesPool, newNoPool, marketId]);

            // 6. Mise à jour du portefeuille de parts (Position) de l'utilisateur (Upsert)
            await client.query(`
                INSERT INTO positions (user_id, market_id, outcome, shares, total_invested) 
                VALUES ($1, $2, $3, $4, $5)
                ON CONFLICT (user_id, market_id, outcome) 
                DO UPDATE SET 
                    shares = positions.shares + $4,
                    total_invested = positions.total_invested + $5
            `, [userId, marketId, outcomeType, userSharesReceived, amountInvested]);

            // 7. Enregistrement de la transaction financière (Audit Trail)
            await client.query(`
                INSERT INTO transactions (wallet_id, type, amount, reference) 
                VALUES ($1, 'TRADE', $2, $3)
            `, [walletId, -amountInvested, `CPMM Buy ${outcomeType} Market ${marketId}`]);

            // 8. Validation finale de la transaction
            await client.query('COMMIT');

            return {
                success: true,
                sharesReceived: userSharesReceived,
                newPoolState: { yes_pool: newYesPool, no_pool: newNoPool }
            };

        } catch (error) {
            // En cas d'erreur (fonds insuffisants, crash mathématique), on annule TOUT
            await client.query('ROLLBACK');
            console.error("[CPMMService] Transaction échouée: ", error.message);
            throw error;
        } finally {
            // Libération de la connexion (crucial pour éviter le pool exhaustion)
            client.release();
        }
    }
}

module.exports = CPMMService;
