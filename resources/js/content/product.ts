/** Product facts shared by the official landing and account pages. */
export const product = {
    name: 'PredicAfrica', domain: 'predicafrica.com', unit: 'XAF',
    facts: {
        fr: {
            fixed: 'Les cotes sont fixes. Votre cote d’entrée est conservée jusqu’au résultat.',
            payout: 'Le retour potentiel inclut la mise et est arrondi au franc inférieur. Aucune commission de marché n’est appliquée actuellement.',
            resale: 'La mise reste engagée jusqu’à la résolution. La revente et la sortie anticipée ne sont pas disponibles.',
            payments: 'Les dépôts passent par NDSGPay lorsque le service et votre opérateur sont activés. Le solde est crédité après confirmation. Les retraits ne sont pas disponibles actuellement.',
            resolution: 'Les règles figurent sur chaque marché. Un administrateur enregistre le résultat et sa source ; une annulation rembourse les mises.',
            privacy: 'Le mot de passe est haché, les numéros de paiement sont chiffrés et vos opérations privées sont protégées par les contrôles d’accès du serveur.',
            region: 'Le portefeuille utilise XAF. Seuls les pays et opérateurs explicitement activés sont proposés lors du dépôt.',
            history: 'La cote d’entrée est conservée dans chaque position. Il n’existe pas de marché secondaire.',
        },
        en: {
            fixed: 'Odds are fixed. Your entry odds remain unchanged until settlement.',
            payout: 'Potential return includes your stake and is rounded down to whole francs. No market commission is currently applied.',
            resale: 'Stakes remain committed until settlement. Resale and early exit are not available.',
            payments: 'Deposits use NDSGPay when the service and your operator are enabled. Funds become available after confirmation. Withdrawals are currently unavailable.',
            resolution: 'Rules are shown on each market. An administrator records the outcome and its source; cancellation refunds stakes.',
            privacy: 'Passwords are hashed, payment phone numbers are encrypted, and private transactions are protected by server-side access checks.',
            region: 'Wallets use XAF. Only explicitly enabled countries and operators are offered for deposits.',
            history: 'Your entry odds are recorded with each position. There is no secondary market.',
        },
    },
};
export const faq = {
    fr: [
        ['Qu’est-ce que PredicAfrica ?', 'Une plateforme de marchés de prédictions à cote fixe sur l’actualité africaine. Chaque question propose deux résultats : Oui ou Non.'],
        ['Comment alimenter mon portefeuille ?', product.facts.fr.payments],
        ['Comment est calculé mon retour ?', product.facts.fr.payout],
        ['Puis-je revendre une position ?', product.facts.fr.resale],
        ['Comment les marchés sont-ils résolus ?', product.facts.fr.resolution],
        ['Quels pays et devises sont pris en charge ?', product.facts.fr.region],
        ['Comment suivre un paiement en attente ?', 'Retrouvez sa référence dans votre portefeuille. Une opération en attente ne doit pas être relancée ; le statut est vérifié côté serveur. Contactez le support si une vérification est nécessaire.'],
        ['Mes données sont-elles protégées ?', product.facts.fr.privacy],
    ],
    en: [
        ['What is PredicAfrica?', 'A fixed-odds prediction market platform focused on African current affairs. Every question has two outcomes: Yes or No.'],
        ['How do I fund my wallet?', product.facts.en.payments],
        ['How is my return calculated?', product.facts.en.payout],
        ['Can I resell a position?', product.facts.en.resale],
        ['How are markets settled?', product.facts.en.resolution],
        ['Which countries and currencies are supported?', product.facts.en.region],
        ['How do I track a pending payment?', 'Find its reference in your wallet. Do not restart a pending transaction; the server checks its status. Contact support if verification is required.'],
        ['How is my data protected?', product.facts.en.privacy],
    ],
};
