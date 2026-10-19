/** Verified against the existing ledger/trading services. No commercial promises. */
export const product = {
    name: 'PredicAfrica', domain: 'predicafrica.com', unit: 'crédits',
    facts: {
        fr: {
            demo: 'Démonstration · Crédits fictifs, sans valeur monétaire',
            fixed: 'Les cotes sont fixes. Votre cote d’entrée est conservée jusqu’au résultat.',
            payout: 'Le retour potentiel inclut la mise, arrondi à l’entier inférieur. Aucune commission n’est appliquée.',
            resale: 'La mise reste engagée jusqu’à la résolution. La revente et la sortie anticipée ne sont pas disponibles.',
            payments: 'Les dépôts et retraits sont simulés. Aucun opérateur Mobile Money n’est sollicité.',
            resolution: 'Les règles figurent sur chaque marché. Un administrateur enregistre le résultat et sa source ; une annulation rembourse les mises.',
            privacy: 'Le mot de passe est haché. Les positions, opérations et conversations sont protégées par les contrôles d’accès du serveur.',
            region: 'Les comptes utilisent des crédits de démonstration. Aucun pays ni devise monétaire n’est associé au compte.',
            history: 'Aucun historique de cotes n’est enregistré : ce moteur fonctionne à cote fixe.',
        },
        en: {
            demo: 'Demo · Fictional credits with no monetary value',
            fixed: 'Odds are fixed. Your entry odds remain unchanged until settlement.',
            payout: 'Potential return includes the stake, rounded down to whole credits. No commission is charged.',
            resale: 'Stakes remain committed until settlement. Resale and early exit are not available.',
            payments: 'Deposits and withdrawals are simulated. No Mobile Money provider is contacted.',
            resolution: 'Rules are shown on each market. An administrator records the outcome and its source; cancellation refunds stakes.',
            privacy: 'Passwords are hashed. Positions, transactions and conversations are protected by server-side access checks.',
            region: 'Accounts use demo credits. No country or monetary currency is linked to the account.',
            history: 'No odds history is recorded: this engine uses fixed odds.',
        },
    },
};
export const faq = {
    fr: [
        ['Qu’est-ce que PredicAfrica ?', 'Un espace pour explorer des questions sur l’actualité africaine et exprimer vos convictions. La version actuelle permet de prendre des positions Oui/Non avec des crédits fictifs.'],
        ['Est-ce que je peux utiliser de l’argent réel ?', product.facts.fr.payments],
        ['Comment est calculé mon retour ?', product.facts.fr.payout],
        ['Puis-je revendre une position ?', product.facts.fr.resale],
        ['Comment les marchés sont-ils résolus ?', product.facts.fr.resolution],
        ['Le simulateur USSD est-il relié à mon opérateur ?', 'Non. Le prototype présente un parcours USSD dans votre navigateur. Il ne compose aucun numéro et ne transmet aucun PIN à un opérateur.'],
    ],
    en: [
        ['What is PredicAfrica?', 'A place to explore questions about African current affairs and express your convictions. This version supports Yes/No positions using fictional credits.'],
        ['Can I use real money?', product.facts.en.payments],
        ['How is my return calculated?', product.facts.en.payout],
        ['Can I resell a position?', product.facts.en.resale],
        ['How are markets settled?', product.facts.en.resolution],
        ['Does the USSD simulator connect to my carrier?', 'No. The prototype demonstrates a USSD flow in your browser. It does not dial a number or send any PIN to a carrier.'],
    ],
};
