# Change, portefeuilles et participations multidevises

## Contrat monétaire

Chaque utilisateur conserve un portefeuille personnel dans la devise choisie à
l’inscription. Un dépôt NDSGPay crédite cette même devise ; il ne dépend pas du
service de change. Les mises et gains sont fixés dans la monnaie du portefeuille.
Une annulation restitue exactement la mise débitée, même si les taux ont changé.

Les marchés utilisent XAF comme unité de référence pour leurs limites et leurs
statistiques globales. La mise minimale équivaut à 100 XAF ; le plafond équivaut
à 1 000 000 XAF. Une mise saisie en monnaie locale est normalisée en XAF avec
arrondi inférieur pour ces statistiques. Le gain natif vaut la mise native
multipliée par la cote, avec arrondi inférieur à l’unité mineure. Les deux montants
natifs, la cote, les équivalents XAF et le taux sont conservés dans la confirmation.
Le taux de référence n’exécute pas un achat de devises auprès d’une banque.

Les volumes convertis des cartes de marchés sont indicatifs et portent `≈`.
Les mises, gains et historiques personnels affichent les montants natifs conservés.
Le règlement n’appelle jamais une API de change et ne recalcule pas un ancien gain.

## Unités et journal

| Devises | Décimales | Exemple stocké pour un montant de 1 234,56 |
| --- | --- | --- |
| CDF, KES, ZMW, SLE | 2 | 123456 unités mineures |
| XAF, XOF, RWF, UGX | 0 | Fraction non nulle refusée |

Précision des codes : [référence des unités monétaires](https://docs.adyen.com/development-resources/currency-codes),
[agence ISO 4217 SIX](https://www.six-group.com/en/products-services/financial-information/market-reference-data/data-standards.html).
Les francs XAF historiques ne sont pas multipliés par 100.

`Money` utilise `brick/math`, déclaré comme dépendance directe. Aucun calcul de
fonds ne passe par un float. `ExactJson` conserve les nombres décimaux externes
sous forme de chaînes avant décodage. Les requêtes NDSGPay envoient les montants
en unités majeures décimales ; les réponses sont comparées exactement au montant
mineur et à la devise attendus avant tout crédit.

Comptes, transactions, écritures et mouvements portent une devise. La contrepartie
XAF historique reste `treasury` ; les autres sont `treasury:CDF`, `treasury:XOF`, etc.
Les soldes ne sont jamais compensés entre monnaies. Les vues d’administration
affichent les soldes par devise ; le graphique administratif existant porte sur
les opérations XAF. Les statistiques personnelles restent dans la devise native.
`ledger:reconcile` vérifie aussi la cohérence des devises.

## Source de taux

Le connecteur utilise [CurrencyAPI `/v3/latest`](https://currencyapi.com/docs/latest/)
en base XAF pour les [huit codes pris en charge](https://currencyapi.com/docs/currency-list/).
L’API publie une date de mise à jour. Sa fréquence dépend de l’offre : le service
gratuit est quotidien, les offres supérieures peuvent atteindre la minute.
**Consulter plus souvent un taux quotidien ne le transforme pas en taux temps réel.**

Configuration serveur :

```dotenv
CURRENCYAPI_KEY=cle_a_renseigner_localement
```

Cette clé n’est pas une clé NDSGPay. Ne pas l’envoyer dans le chat, dans une réponse
Inertia, dans le frontend ou dans une variable `VITE_*`. Aucun compte fournisseur
ni abonnement n’est créé automatiquement.

Le scheduler vérifie chaque heure si une récupération est due, lorsque
`FX_REFRESH_ENABLED=true`. `RateSync` limite les appels fournisseur à un par 24 heures
(valeur par défaut). Mettre `FX_REFRESH_ENABLED=false` pour suspendre les appels
fournisseur. Le verrou SQL limite aussi les commandes manuelles à un appel par
24 heures, y compris après une erreur, pour maîtriser le quota. Reconstruire le cache de
configuration après modification. La commande manuelle `fx:refresh` reste
disponible pour un contrôle ponctuel autorisé.

Une table de contrôle SQL borne les
appels concurrents et les reprises ; les erreurs 429 respectent un délai de reprise.
Les réponses non conformes, futures, trop anciennes, non positives ou portant sur
une autre base ne publient aucun nouveau taux. Les anciennes observations sont
conservées et une nouvelle lecture ne rajeunit jamais leur date d’origine.

Un taux quotidien doit dater de moins de 48 heures (un cycle de publication
et une marge maximale d’un cycle) pour autoriser une nouvelle
participation hors XAF. Une confirmation est valable au plus 60 secondes, et
jamais au-delà de la fraîcheur du taux source. Une panne suspend les nouvelles
participations concernées ; elle n’empêche ni la confirmation d’un dépôt natif,
ni un gain ou un remboursement déjà engagé. XAF continue sans service externe.
Il n’existe aucun taux fictif ou taux saisi silencieusement. Le taux quotidien
provient du fournisseur ; un taux vieux de 48 heures ou plus est refusé.

Le navigateur relit le dernier taux validé sur le serveur chaque minute et à la
demande. Ces lectures n’interrogent pas CurrencyAPI. L’interface distingue taux
daté, estimation et confirmation monétaire.

## Confirmation et idempotence

`POST /markets/{id}/quote` produit une confirmation liée au compte, au marché,
au choix Oui/Non, à la mise native, à la cote, à la référence et au taux enregistrés.
La soumission finale porte son identifiant opaque. Le serveur refuse un changement
d’intention, une cote modifiée, une confirmation expirée ou une autre propriété.
Une empreinte HMAC protège ses champs immuables. Les clés Laravel antérieures
de `APP_PREVIOUS_KEYS` restent acceptées pour les positions déjà enregistrées
pendant une rotation ; conserver les clés nécessaires aux positions ouvertes.

Le débit, la position et la consommation de confirmation sont atomiques. Le rejeu
de la même référence retrouve la position, y compris après expiration du taux ou
résolution. Une référence réutilisée avec un autre contenu est refusée. Le règlement
contrôle la confirmation conservée et le débit comptable avant crédit, sans dépendre
de la disponibilité d’un taux courant.

## Activation réelle

1. Sauvegarder la base et appliquer la migration
   `2026_10_29_000005_multicurrency_and_fx`. Elle refuse de deviner la dénomination
   de fonds historiques hors XAF inattendus. Son rollback automatique est interdit.
2. Créer/configurer un compte CurrencyAPI avec une offre dont la fréquence et le
   quota correspondent à une lecture quotidienne, puis renseigner la clé serveur.
3. Configurer dans `NDSGPAY_CAPABILITIES` uniquement les tuples activés par NDSGPay
   sur le compte marchand, avec leurs formats téléphoniques confirmés. Le catalogue
   public n’est pas une preuve d’activation marchande. Les contrôles pays/devise du
   portefeuille s’appliquent avant tout appel de création.
4. Conserver le worker `payments`, le scheduler Laravel, les clés et le webhook
   décrits dans [le guide NDSGPay](ndsgpay.md). Les payouts restent désactivés.

Exemple de tuple **uniquement si confirmé pour le marchand** :

```json
{"country":"COD","currency":"CDF","correspondent":"AIRTEL_COD","calling_code":"243","national_lengths":[9]}
```

Les limites de dépôt applicatives sont actuellement de 100 à 1 000 000 en unités
majeures natives (`config/payments.php`) ; les limites et fractions réellement
acceptées par chaque opérateur doivent être confirmées avec le prestataire.

```sh
php artisan fx:refresh
php artisan fx:status
php artisan payments:health
php artisan payments:audit
php artisan ledger:reconcile
# Exploitation : scheduler existant via cron, ou localement :
php artisan schedule:work
```

Sans clé ou sans source suffisamment fraîche, `fx:status` signale `UNAVAILABLE`
pour les devises étrangères. Aucun test ne remplace ces prérequis de production.

## Vérifications

`MulticurrencyTest` couvre les unités exactes, la source, les quotas, les réponses
invalides et anciennes, les sept devises hors XAF, les dépôts natifs, les confirmations
falsifiées/expirées, les gains/remboursements hors ligne, la rotation de clé et les
agrégats natifs/référence. Les régressions XAF restent couvertes.

`npm run test:fx` utilise une base temporaire, des taux volontairement fictifs et
un routeur de test qui intercepte tout appel NDSGPay. Il valide la recharge CDF,
les centimes, l’expiration et le renouvellement, l’achat, la confirmation mobile,
la fermeture sur taux ancien et le règlement. Il ne produit aucun paiement réel.

Les tests de concurrence MySQL observent des attentes de verrous, notamment pour
la double consommation d’une confirmation CDF, le double gain au centime et le
règlement commun de participants XAF/CDF. Voir [les instructions](../tests/concurrency/README.md).

Vérification du 29 octobre 2026 : 201 tests PHP / 1 751 assertions, TypeScript,
build Vite, parcours navigateur FX et XAF, interactions UX et onze courses de
trading/règlement MySQL plus les quatre courses de paiement réussis. La migration
locale a été appliquée après sauvegarde SQLite ; les empreintes des soldes,
transactions et positions préexistants sont identiques avant/après migration.
Le rapprochement local est équilibré. Le contrôle `fx:status` signale une clé
CurrencyAPI absente : aucun flux de taux réels n’a donc été activé localement.
