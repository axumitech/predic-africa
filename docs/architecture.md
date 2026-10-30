# Architecture officielle

Laravel 13, React/Inertia, authentification par session et rôles trader/admin. La landing officielle reprend la structure et les styles de l’ancienne landing : header, hero, cartes, étapes, FAQ, calcul de retour et support. Elle utilise exclusivement des liens réels, le catalogue publié et des statistiques SQL. Aucun moteur historique, compte rapide, chatbot factice, téléphone USSD ou stockage local de solde n’est chargé.

## Fonds et journal

Le portefeuille officiel conserve la devise choisie à l’inscription (XAF, XOF, CDF, KES, RWF, UGX, ZMW ou SLE). `payment_wallets.balance` et le compte utilisateur de `accounts` sont mis à jour ensemble, dans la même transaction, via `Ledger`. Chaque mouvement comporte deux écritures de somme nulle, la contrepartie est `treasury` pour XAF et `treasury:<devise>` pour les autres monnaies. Les fonds disponibles sont vérifiés avant débit ; ils restent engagés dans les positions jusqu’à résolution. Les gains et remboursements empruntent le même journal. Les montants sont des entiers en unités mineures ; les écritures portent une devise. Voir [le moteur de change](fx.md) pour la précision, les confirmations expirables et les taux datés.

Les confirmations NDSGPay créditent ce journal et le portefeuille atomiquement. `payment_movements` relie propriétaire, paiement éventuel et transaction comptable : les mises/gains possèdent une transaction comptable mais pas de paiement prestataire. Les anciens mouvements NDSGPay gardent leurs montants et identifiants ; une écriture d’ouverture reprend seulement leur solde réellement comptabilisé, sans recréditer les fonds.

`Ledger` verrouille l’utilisateur, les comptes par ID puis le portefeuille. Le trading verrouille le marché avant l’utilisateur ; le règlement verrouille tous les participants avant la première écriture. Les références, empreintes et contraintes SQL empêchent les doubles effets. `ledger:reconcile` contrôle les comptes et les paires d’écritures ; `payments:audit` contrôle le portefeuille contre tous ses mouvements, paiements et trading compris.

L’ancien POST `/wallet` est supprimé. Aucun formulaire navigateur ne peut créer arbitrairement des fonds. Les tests injectent leurs fixtures uniquement via un script isolé qui refuse les bases applicatives.

## Paiements et exploitation

Voir [NDSGPay](ndsgpay.md) pour la signature, l’inbox durable, les confirmations GET, les quotas SQL, la reprise et les incidents. Les paiements réels nécessitent configuration explicite et activation ; les payouts restent fermés. La suppression de l’interface de démonstration n’active pas un compte prestataire.

## Migration de l’existant

La migration `2026_10_29_000003_retire_demo_and_unify_wallet` conserve les anciens comptes, écritures, transactions et positions dans `retired_records` avant leur retrait des tables actives. Les soldes de crédits historiques ne sont jamais convertis en XAF. Les seuls soldes repris viennent des portefeuilles NDSGPay. Les marchés préremplis identifiables sont archivés ; les autres marchés ouverts retournent à la modération pour revue avant mise monétaire. Les comptes utilisateurs et les conversations de support restent conservés.

Effectuer cette migration en maintenance, après sauvegarde. Son rollback automatique est interdit : restaurer mécaniquement les anciens crédits sur des fonds réels serait dangereux. Une récupération exige une procédure revue à partir des sauvegardes et archives. Les fichiers retirés sont conservés sous `legacy/`, hors build et hors routes. Les modifications locales antérieures de ces sources y sont préservées.

## Prédictions

L’ancien service Python produisait des scores aléatoires. Il est retiré de `services/` et conservé dans `legacy/retired-ai/`. Le connecteur Laravel reste disponible pour un futur service réel, désactivé par défaut ; il exige explicitement `simulation=false`, y compris pour la génération et les réponses cachées. Les sources déclarées synthétiques ou non qualifiées sont refusées. Les propositions importées restent soumises aux validations et à la modération ; aucune donnée fictive n’est publiée automatiquement.

## Tests

PHPUnit force une base SQLite en mémoire et un chemin de cache distinct. `Tests\TestCase` refuse de migrer une base applicative en cas de configuration inattendue. Les tests navigateur possèdent leur propre fichier SQLite et aucun appel NDSGPay réel. Les tests MySQL/InnoDB utilisent une base éphémère et observent les attentes de verrous ; SQLite ne prouve pas le comportement concurrent en production.

Les conditions commerciales, les capacités opérateurs et les engagements d’exploitation doivent être validés avant ouverture publique. Les textes ne revendiquent ni retraits disponibles ni support de devises/opérateurs non confirmés.

## Vérifications de la bascule

Le 29 octobre 2026 : 144 tests PHPUnit réussis (1 095 assertions), TypeScript et build Vite réussis, parcours navigateur validés de 320 à 1 920 px. Les captures de la landing ont été vérifiées sur mobile et ordinateur. Le test de migration isolé confirme que 9 000 anciens crédits sont archivés tandis que 1 000 XAF existants sont conservés, sans conversion ni double crédit. MySQL 9.6.0 / REPEATABLE READ : quatre courses paiements et huit scénarios trading/règlement réussis, journaux rapprochés. Aucun appel de paiement réel ni déploiement.

Incident local pendant le premier lancement de PHPUnit : un cache de configuration préexistant a dirigé RefreshDatabase vers `database/database.sqlite`, qui a été réinitialisée. Les comptes/paiements locaux antérieurs ne sont plus présents et nécessitent une sauvegarde pour récupération ; leur restauration n’a pas pu être réalisée. L’isolation PHPunit, le chemin de cache distinct et le refus explicite de toute base autre que SQLite en mémoire ont été ajoutés après constat. Cet incident est distinct du test de migration contrôlé et de son archivage.
