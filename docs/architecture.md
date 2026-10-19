# Architecture implémentée

## Périmètre

Monolithe Laravel, pages Inertia/React, SQLite local. Les domaines applicatifs sont persistants, à cote fixe, en crédits de démonstration. Le prototype autonome et ses anciens composants sont conservés sur `/demo`.

| Domaine | Implémentation |
| --- | --- |
| Identity | `IdentityController` : inscription/session/profil/suspension ; `SessionAccess` : liaison de version et révocation locale ; `ActiveAccount` dans le groupe web avant partage Inertia |
| Markets | `app/Modules/Markets` : propositions, validation, publication/rejet ; critères et cotes immuables après soumission |
| Trading | `app/Modules/Trading/PlacePosition.php` : opération transactionnelle de mise ; `FixedOdds` : bornes et arrondi entier ; contrôleur HTTP : validation de requête |
| Ledger | `app/Modules/Ledger/Ledger.php` : montants entiers, paires d’écritures équilibrées et solde matérialisé |
| Payments | `app/Modules/Payments` : dépôts/retraits de crédits fictifs, sans adaptateur opérateur réel |
| Settlement | `app/Modules/Settlement/ResolveMarket.php` : résolution, gains et remboursements atomiques ; contrôleur HTTP : rôle admin et validation |
| Support | `app/Modules/Support` : tickets et messages avec contrôle du propriétaire ou du rôle admin |
| Notifications | `app/Modules/Notifications` : notifications persistées dans les transactions métier ; lecture privée |
| Predictions | `app/Modules/Predictions` : passerelle Python, cache, délais, génération admin et import contrôlé |

## Données et invariants

Les migrations ajoutent `accounts`, `ledger_transactions`, `ledger_entries`, `markets`, `positions`, `support_tickets`, `support_messages` et `platform_notifications`. Les références d’opération et les sources d’importation sont uniques.

Chaque opération comptable écrit une transaction, deux écritures de somme nulle et met à jour les deux soldes. Le compte `simulation` constitue la contrepartie. Sa capacité négative sert seulement à la création et à la destruction de crédits fictifs. Aucun argent client réel ne doit être stocké dans ce moteur.

Les chemins financiers sont enveloppés dans `DB::transaction(..., 3)`. Les mises et résolutions verrouillent d’abord leur marché. La résolution verrouille tous ses participants par identifiant avant tout transfert ; les comptes sont ensuite verrouillés par Ledger. Le verrou de marché sérialise les écritures de positions de ce marché : la lecture des positions ne prend pas de verrou de plage, qui bloquerait des insertions sur d’autres marchés sous InnoDB. Les montants et paiements sont recalculés côté serveur. Les mises stockent leur cote et leur retour potentiel ; les changements de données côté navigateur ne les modifient pas.

Une référence d’ordre est associée à une empreinte du compte, du type, du montant et du contexte. Un appel identique retrouve l’opération ; une référence réutilisée avec un contenu différent est refusée. La résolution verrouille le marché et son état final empêche les doubles versements. Les tests SQLite couvrent les invariants séquentiels et les rollbacks. Huit scénarios concurrents ont été vérifiés avec des processus PHP distincts sur MySQL 9.6/InnoDB en REPEATABLE READ, dont un entrelacement imposé entre mise et résolution sur des marchés différents. Voir `docs/audit-2026-10-19.md` et `tests/concurrency/README.md`. Le moteur de production reste à confirmer ; PostgreSQL et la montée en charge ne sont pas validés.

## Interface

- `resources/js/pages/Platform.tsx` : écrans du nouvel espace et formulaires par parcours.
- `resources/js/features/platform/Layout.tsx` : navigation, compte, portefeuille et pied de page.
- `resources/js/features/platform/ui.tsx` : formulaires Inertia, erreurs, tableaux de marchés et pagination.
- `resources/js/features/platform/platform.css` : adaptateurs de mise en page, utilisant les couleurs et composants de la feuille de style d’origine.
- `resources/js/pages/Error.tsx` : pages 403, 404, 419, 429 et 503.
- `app/Http/Controllers/PlatformController.php` : données paginées des pages, filtrées selon les droits.

Les pages sont résolues dynamiquement par nom. Les styles d’origine sont partagés par toutes les pages. Le moteur historique est limité à l’accueil et à `/demo` ; les pages métier utilisent React/Inertia. Les tableaux sont paginés ; les mots de passe ne sont jamais transmis dans les propriétés Inertia. Les traitements métier refusés retournent des erreurs dans le formulaire.

## Pipeline et importation

Les API historiques `/api/predictions` et `/api/markets` restent des lectures de démonstration. `/admin/pipeline/generate` est authentifié, réservé à l’administrateur et limité en fréquence. Les imports sont relus depuis Python côté serveur ; un client ne peut pas substituer des cotes dans sa requête. Ils doivent être binaires, valides et non expirés. La publication n’est jamais automatique.

Le modèle Python reste simulé. Les routes locales n’exposent ni lecture arbitraire des logs serveur ni commandes système ni fausse action de réentraînement.

## Éléments restant hors périmètre de cette démonstration

Paiements réels, devises réelles, matching P2P, revente, SMS/USSD opérateur, traduction du nouvel espace, mails de récupération, outbox et diffusion temps réel, Redis/Horizon/Reverb, entraînement IA réel, tests de charge et déploiement production. Le prototype conserve ses fonctions FR/EN, régions et simulateur USSD séparément.

L’architecture cible initiale dans `docs/original` reste une référence produit ; elle ne doit pas être interprétée comme une liste de garanties livrées.

## Contrats Trading / Settlement (lot du 19 octobre 2026)

`PlacePosition::place(userId, marketId, outcome, amount, reference)` valide les bornes et l’issue, relit la suspension sous verrou utilisateur, puis demande au Ledger le débit idempotent. Le rejeu vérifie la position liée ; un débit orphelin ne recrée jamais une position. `FixedOdds::payout` impose les bornes existantes et l’arrondi inférieur sans flottants.

`ResolveMarket::resolve(marketId, result, source)` est une opération interne appelée après autorisation admin par le contrôleur. Elle vérifie les transitions, l’échéance (sauf annulation), les cotes verrouillées, le retour potentiel et la correspondance au débit initial. Un état incohérent est refusé sans réparation implicite. Résultat, positions, journal, soldes et notifications restent dans une seule transaction ; une erreur annule tout le règlement. Un rejeu de même résultat conserve la source initiale ; une correction de résultat exige un futur parcours explicite de compensation.

Aucune écriture de position ne doit contourner le verrou de marché ; cela conditionne la sécurité de la lecture sans verrou de plage. Les trois reprises transactionnelles existantes sont conservées pour les conflits SQL. L’ensemble des positions est encore chargé en mémoire et la contrepartie simulation reste partagée : aucune capacité de charge garantie. Aucune migration ni intégration de paiement réelle n’est introduite.

## Identity et autorisations (lot 3 du 19 octobre 2026)

Les rôles HTTP admis restent `trader` et `admin`. Leur attribution n’est acceptée ni à l’inscription ni dans le profil ; la commande serveur `platform:admin` reste le mécanisme existant de promotion. Les Gates Laravel `admin`, `view-market` et `access-ticket`, définies dans AppServiceProvider, centralisent les règles. Aucun bypass global des propriétaires pour les administrateurs : leurs positions, transactions et notifications restent personnelles ; leur accès étendu au support et à la modération est explicite.

`ActiveAccount` relit l’utilisateur depuis la base avant les pages publiques et privées du groupe web, puis vérifie suspension, rôle et version d’accès avant les propriétés Inertia. `users.auth_version` est incrémenté dans la transaction de changement de mot de passe ou de suspension effective. La session stocke la version de sa dernière authentification. Une ancienne session ne redevient pas valide après réactivation, quel que soit le stockage de session. Les sessions préexistantes sans version ne sont admises que pour un utilisateur de version zéro. Une révocation vide la session locale et renouvelle son identifiant et le jeton CSRF ; le contrôle est effectué à la prochaine requête, pas par notification distante.

Le profil exige le mot de passe actuel vérifié sur la ligne verrouillée ; le changement de mot de passe renouvelle aussi le remember_token et conserve uniquement la session courante avec sa nouvelle version. Le changement d’e-mail efface email_verified_at sans activer de vérification par e-mail. La suspension est idempotente ; acteur et cible sont verrouillés par identifiant puis les droits de l’acteur relus, et l’auto-suspension est refusée. Les dépôts/retraits relisent également la suspension sous verrou utilisateur.

Les tentatives de connexion sont limitées à six par IP et dix par identifiant d’e-mail normalisé pour le compteur, par minute. L’e-mail du compte n’est pas réécrit ; la clé du compteur utilise son hash. La génération pipeline a son propre préfixe de quota, indépendant des mutations courantes. Les réponses Inertia et erreurs sont marquées `private, no-store`. La déconnexion et les révocations signalent `clearHistory` à Inertia ; ce signal ne constitue pas une garantie de purge de toutes les données déjà reçues par le navigateur.

Déploiement : appliquer la migration additive `2026_10_19_000001_add_auth_version_to_users.php` avant le code utilisant la colonne. Elle conserve les comptes et affecte zéro par défaut. Aucun e-mail, solde ou rôle existant n’est migré. Pour un retour arrière, restaurer d’abord l’ancien code ; conserver la colonne évite de perdre l’historique de révocation. La suppression optionnelle de cette colonne exige de purger les sessions avant une éventuelle réintroduction du versionnement. Voir le suivi d’audit pour les limites, notamment les requêtes déjà en cours et les tests de concurrence non réalisés dans ce lot.
