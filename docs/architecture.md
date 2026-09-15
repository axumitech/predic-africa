# Architecture implémentée

## Périmètre

Monolithe Laravel, pages Inertia/React, SQLite local. Les domaines applicatifs sont persistants, à cote fixe, en crédits de démonstration. Le prototype autonome et ses anciens composants sont conservés sur `/demo`.

| Domaine | Implémentation |
| --- | --- |
| Identity | `app/Modules/Identity` : inscription, connexion, déconnexion, profil et suspension ; middleware `ActiveAccount` |
| Markets | `app/Modules/Markets` : propositions, validation, publication/rejet ; critères et cotes immuables après soumission |
| Trading | `app/Modules/Trading` : prises de position Oui/Non, cote serveur, échéance et idempotence |
| Ledger | `app/Modules/Ledger/Ledger.php` : montants entiers, paires d’écritures équilibrées et solde matérialisé |
| Payments | `app/Modules/Payments` : dépôts/retraits de crédits fictifs, sans adaptateur opérateur réel |
| Settlement | `app/Modules/Settlement` : résolution, gains, annulation et remboursements atomiques |
| Support | `app/Modules/Support` : tickets et messages avec contrôle du propriétaire ou du rôle admin |
| Notifications | `app/Modules/Notifications` : notifications persistées dans les transactions métier ; lecture privée |
| Predictions | `app/Modules/Predictions` : passerelle Python, cache, délais, génération admin et import contrôlé |

## Données et invariants

Les migrations ajoutent `accounts`, `ledger_transactions`, `ledger_entries`, `markets`, `positions`, `support_tickets`, `support_messages` et `platform_notifications`. Les références d’opération et les sources d’importation sont uniques.

Chaque opération comptable écrit une transaction, deux écritures de somme nulle et met à jour les deux soldes. Le compte `simulation` constitue la contrepartie. Sa capacité négative sert seulement à la création et à la destruction de crédits fictifs. Aucun argent client réel ne doit être stocké dans ce moteur.

Les chemins financiers sont enveloppés dans `DB::transaction(..., 3)`. Les positions et résolutions verrouillent le marché, puis les utilisateurs et comptes dans un ordre stable. Les montants et paiements sont recalculés côté serveur. Les mises stockent leur cote et leur retour potentiel ; les changements de données côté navigateur ne les modifient pas.

Une référence d’ordre est associée à une empreinte du compte, du type, du montant et du contexte. Un appel identique retrouve l’opération ; une référence réutilisée avec un contenu différent est refusée. La résolution verrouille le marché et son état final empêche les doubles versements. Les tests SQLite couvrent ces invariants de manière séquentielle ; la concurrence et la montée en charge PostgreSQL restent à mesurer.

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
