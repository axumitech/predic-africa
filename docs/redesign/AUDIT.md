# Audit préalable — refonte PredicAfrica

## Stack et structure
Laravel 13 / PHP 8.3+, Inertia 3, React 19, TypeScript strict, Vite 7 ; CSS natif, sans Tailwind. Pages chargées à la demande. SQLite local. Services métier répartis en Identity, Markets, Trading, Payments, Ledger, Settlement, Support, Notifications, Predictions. Pipeline FastAPI indépendant et simulé.

## Inventaire exhaustif des vues applicatives
| Entrée | Vue / données | Accès |
|---|---|---|
| `/` | Platform > OriginalHome > Prototype (moteur local complet) | public |
| `/demo` | Prototype (landing, login fictif, workspace historique) | public, local uniquement |
| `/login`, `/register` | Login, Register / formulaires Inertia | invité |
| `/help`, `/terms`, `/privacy`, `/data` | PublicInfo dans Platform | public |
| `/dashboard` | Dashboard / statistiques propres au compte, dernières opérations, marchés ouverts | trader ; admin redirigé |
| `/markets`, `/markets/{id}` | catalogue filtré, détail et position | compte actif, contrôle visibilité du marché |
| `/creator` | formulaire + propositions du compte | compte actif |
| `/positions` | positions privées paginées | compte actif |
| `/wallet`, `/transactions` | solde, simulation et journal privés | compte actif |
| `/notifications`, `/profile` | notifications, identité et mot de passe | compte actif |
| `/support`, `/support/{id}` | tickets privés ; ensemble des tickets pour admin | propriétaire / admin |
| `/predictions` | données pipeline simulées / indisponibilité | compte actif |
| `/admin` | pilotage et intégrité comptable | admin |
| `/admin/markets`, `/admin/markets/{id}` | modération et résolution | admin |
| `/admin/users` | suspension / réactivation | admin |
| `/admin/pipeline` | génération et import contrôlés | admin |
| erreurs | Error : 403,404,419,429,503 ; 500 à compléter | selon réponse |

Prototype : vues dashboard, my-bets, transactions, creator, backoffice, users-management, customer-support ; modales recharge, retrait, position, CGU, confidentialité, données, nouveau ticket, réponse ; simulateurs calculateur, USSD et chat. Conservation de ces parcours, isolation explicite de leurs données locales.

42 routes applicatives recensées avant travaux. Les POST existants couvrent identité, création, mise, portefeuille, support, notifications et administration. API historiques `/api/predictions` et `/api/markets` préservées.

## Modèles / invariants
User : nom, email, mot de passe haché, rôle, suspension. Accounts et ledger : crédits entiers, écritures équilibrées, références idempotentes. Markets : question, règles, catégorie, statut, échéance, cotes fixes, source finale. Positions : mise, cote d’entrée, retour potentiel, statut. Support : sujet, statut ouvert/fermé, messages. Pas de pays, devise monétaire, opérateur, priorité, assignation, historique des cotes, dernière connexion ou audit administratif complet dans ces tables.

Rôles actuels : trader et admin uniquement. Un trader peut proposer un marché ; l’admin peut modérer et suspendre. Aucun contrôle créateur/modérateur/superadmin distinct. Ne pas rebaptiser les droits ni exposer un changement de rôle sans définition de permissions.

## Duplication / architecture
`Platform.tsx` concentre de nombreuses vues ; ActionForm, Panel, MarketCards, Status et Pager réutilisables. Login/Register partagent des classes mais dupliquent leur structure. Les styles historiques + platform + workspace + responsive se superposent. Prototype : manipulations DOM et textes FR/EN, données locales et écouteurs, isolation via createDemoScope. Nouvelle landing React autonome nécessaire pour cesser de charger tout le moteur sur `/`.

## Pourquoi /demo ouvre la landing
La route passe `mode=demo`, mais Prototype ignore cette prop ; AppWorkspace a display:none et le moteur n’appelle pas enterApp à l’initialisation. Correction : entrée explicite dans l’espace simulé, notice persistante et choix de rôle de démonstration sans authentification serveur.

## Incohérences constatées
- Landing historique : chiffres 12M+, 8 pays, identités et volumes locaux, échéances expirées ; pas des métriques serveur.
- Promesses P2P/revente, commission 2 %, cotes changeantes : incompatibles avec le moteur principal à cote fixe, sans revente ni commission.
- Mobile Money, PIN, USSD : simulations locales uniquement ; aucun paiement ou accès opérateur réel.
- Coordonnées `predicafrika.com`, adresse Kinshasa/téléphone Sénégal et garanties de chiffrement bancaire non vérifiées : ne pas présenter comme informations d’exploitation validées.
- Alt « Wegame Logo » erroné.
- Formulaires partagés : erreurs globales sans associations ARIA ni focus du premier champ.
- Actions administratives immédiates sans confirmation UI. Les validations et permissions serveur existent.
- Absence de routes mot de passe oublié/réinitialisation/vérification email : ne pas afficher de fausse réussite.

## Plan de livraison
Tokens CSS et composants accessibles documentés ; source de vérité du contenu FR/EN ; landing React avec catalogue et compteurs serveur ; /demo explicite ; layout public/légal/erreurs ; consolidation des formulaires et confirmations ; enrichissement des lectures backend sans modifier les mutations ; filtres/tri/vues catalogue ; positions filtrées ; résumé portefeuille et détails de transactions ; panneau de position responsive ; navigation compacte/recherche ; vues admin et support cohérentes. Tests métier existants, tests UI existants adaptés, responsive et rapport final.

Site distant demandé : https://predicafrica.com/ inaccessible depuis l’outil de consultation pendant l’audit. Les constats ci-dessus proviennent du dépôt local, pas d’une inspection prétendument réussie de la production.
