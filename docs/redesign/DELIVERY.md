# Livraison de la refonte — 18 septembre 2026

## Ce qui est livré

- Accueil React indépendant du moteur historique, identité navy/or, FR/EN, menu mobile modal, marchés ouverts issus de la base, calculateur, FAQ et explications du mode démonstration.
- `/demo` ouvre directement l’espace du prototype. Ses parcours trader/admin, achats, support et persistance restent accessibles.
- Dashboard personnel et pilotage administratif distincts, largeur fluide, sidebar rétractable, recherche des marchés, navigation mobile, métriques et activité sur 7 jours issues de la base.
- Catalogue : recherche, catégorie, statut, échéance, tri par date/volume/participants, grille/liste, favoris locaux par compte, filtres dans un panneau mobile.
- Fiche marché : règles/source, dates, volume/participants, statut, panneau sticky desktop et bottom sheet mobile, estimation et récapitulatif avant validation.
- Positions : filtres par état, recherche, date de prise et échéance, tableau transformé en cartes mobiles.
- Portefeuille : disponible/engagé/gains/dépôts/retraits réels de démonstration ; confirmation de la simulation.
- Transactions : recherche par référence, filtre par type, détails dans un drawer ; état « comptabilisée » pour les lignes réellement enregistrées.
- Administration : tableau des marchés avec auteur et agrégats, confirmation des décisions, soldes et dates d’inscription des comptes, confirmation de suspension/réactivation.
- Support : liste, conversation et informations du client autorisées par le serveur ; navigation progressive sur mobile.
- Inscription : robustesse indicative du mot de passe ; identité et layout de connexion préservés.
- Pages légales/aide : lecture et sommaire sticky. Erreur 500 ajoutée aux écrans existants 403/404/419/429/503.
- États de chargement, attente lente, offline, erreurs de formulaires et récupération d’erreur React.

## Fichiers

### Créés

- `docs/redesign/AUDIT.md` — audit préalable, inventaire et décisions.
- `docs/redesign/DESIGN-SYSTEM.md` — variantes, accessibilité et règles de composition.
- `docs/redesign/DELIVERY.md` — ce bilan.
- `resources/css/tokens.css`, `components.css`, `landing.css`.
- `resources/js/components/ui.tsx`, `ConnectionStatus.tsx`, `RenderBoundary.tsx`.
- `resources/js/content/product.ts`, `locale.tsx`, `landing.ts`.
- `resources/js/features/platform/Landing.tsx`, `MarketExplorer.tsx`, `Records.tsx`, `PublicInfo.tsx`.

### Modifiés

- `app/Http/Controllers/PlatformController.php` — lectures filtrées, agrégats et données d’affichage ; règles de mutation conservées.
- `routes/web.php` — accueil relié au contrôleur pour ses vrais marchés.
- `bootstrap/app.php` — redirection authentifiée vers dashboard et écran 500.
- `resources/js/app.tsx` — contexte de langue et états globaux.
- `resources/js/pages/Platform.tsx`, `Prototype.tsx`, `Register.tsx`, `Error.tsx`.
- `resources/js/features/platform/ui.tsx`, `Layout.tsx`, `Dashboard.tsx`, `workspace.css`.
- `resources/js/features/prototype/engine.js` — entrée directe et correction de contenus trompeurs.
- Composants historiques : libellés de marque/domaine/code fictif dans `AppLanding.tsx`, `AppLogin.tsx`, `AppWorkspace.tsx`, `ViewUsersManagement.tsx`, `ModalRecharge.tsx`, `ModalWithdraw.tsx`.
- `tests/Feature/PlatformTest.php`, `ExampleTest.php`, `PredictionApiTest.php`.
- `tests/browser/platform.mjs`, `smoke.mjs`.
- `public/build/manifest.json` et bundles Vite régénérés.

Les routes, services métier, validations financières, idempotence et contrôles d’accès ne sont pas remplacés. Le prototype est toujours chargé à la demande sur `/demo` ; son bundle d’environ 217 Ko brut n’est plus nécessaire sur l’accueil.

## Incohérences corrigées ou rendues explicites

- `/demo` affichait la landing car l’initialisation ignorait le mode transmis.
- « Wegame Logo » et `predicafrika.com` corrigés dans le prototype.
- Les fausses promesses de commission de 2 %, revente et chiffrement bancaire ont été retirées des traductions actives du prototype. Les règles de l’espace principal sont centralisées.
- Les formulaires de simulation indiquent un code fictif ; aucune notification réelle sur le téléphone n’est prétendue.
- « Investisseurs payés » affichait une somme de crédits : remplacé par « Gains distribués ».
- Les variations de cote, pays et devises monétaires ne sont pas inventés.
- Les données de démonstration de `/demo` restent historiques/locales, distinctes des marchés serveur. Les captures emploient exclusivement une base de test isolée.

## Validation

- Backend : **31 tests, 453 assertions**, succès. Inclut permissions, données privées, idempotence, résolution/remboursements, pipeline indisponible, agrégats et filtres nouveaux.
- TypeScript : `npm run typecheck`, succès.
- Production : `npm run build`, succès.
- Navigateur principal : `npm run test:e2e`, succès ; inscription, connexion, dépôt, proposition, confirmation, modération, mise, persistance, support, pages et bottom sheet. Aucune erreur JavaScript lors du scénario.
- Prototype historique : `npm run test:e2e:legacy`, succès ; trader, support, achat, persistance, admin, mobile.
- Matrice de débordement : **320, 375, 390, 430, 768, 1024, 1280, 1366, 1440 et 1920 px** pour accueil, connexion, inscription, dashboard, catalogue, positions, portefeuille, transactions, création, support, profil et aide.
- Inventaire des routes exporté dans `test-results/routes.json` ; protections administratives vérifiées par la suite backend.
- Captures desktop/mobile et contrôle visuel des vues principales. Sidebar rétractable contrôlée dans le navigateur.
- Contraste renforcé pour les textes secondaires, liens Oui/Non et retours estimés. Pas de certification WCAG exhaustive ni validation sur appareils physiques.

## Captures

Voir `test-results/` :

| Vue | Desktop | Mobile |
| --- | --- | --- |
| Accueil | `redesign-home-1440.png` | `redesign-home-390.png` |
| Connexion | `redesign-login-1440.png` | `redesign-login-390.png` |
| Inscription | `redesign-register-1440.png` | `redesign-register-390.png` |
| Dashboard | `redesign-dashboard-1440.png` | `redesign-dashboard-390.png` |
| Catalogue | `redesign-markets-1440.png` | `redesign-markets-390.png` |
| Portefeuille | `redesign-wallet-1440.png` | `redesign-wallet-390.png` |
| Administration | `redesign-admin-desktop.png` | navigation commune adaptée |
| Confirmation de mise | — | `redesign-order-mobile.png` |

## Limites et fonctions non livrées

1. **Rôles** : les permissions existantes restent `trader` et `admin`. Création et modération ont des parcours distincts ; Créateur, Modérateur et SuperAdmin ne sont pas de nouveaux rôles serveur. Une matrice de droits reste nécessaire pour les introduire sans élargissement implicite des accès.
2. **Récupération et vérification d’e-mail** : pas de flux existant ni de messagerie configurée dans le produit audité. Aucun faux écran de succès ajouté. Connexion/inscription/profil restent fonctionnels.
3. **Paiements réels, pays/devises, retraits en attente** : pas de backend opérateur. Les simulations sont conservées et clairement nommées ; aucun champ de vrai PIN ajouté.
4. **Historique de cotes, audit administratif, dernière connexion, assignation/priorité/pièces jointes du support, export** : données ou fonctionnalités absentes. Aucun contenu inventé ni bouton sans fonction.
5. **Internationalisation** : accueil et faits/FAQ bilingues ; traduction complète des écrans privés et légaux encore à réaliser. Certains composants historiques conservent leurs textes locaux.
6. **Prototype** : moteur DOM impératif encore isolé dans `/demo`. La migration React totale de ses modules historiques n’est pas terminée ; les fonctions existantes sont conservées.
7. **Favoris** : stockage local lié au compte dans ce navigateur, sans synchronisation multiappareil ni page de favoris serveur.
8. **Statut de production** : aucune mise en ligne effectuée. Le site public distant n’a pas pu être inspecté ; les vérifications portent sur le projet local. Coordonnées de l’exploitant et mentions commerciales restent à fournir avant publication.
