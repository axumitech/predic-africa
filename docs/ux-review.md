# Revue UX/UI — PredicAfrica

## Périmètre et constats

Stack conservée : Laravel 13, Inertia 3, React 19, TypeScript, CSS natif et composants internes. Aucun Tailwind, aucune nouvelle dépendance UI. Identité marine/or conservée, ainsi que la structure de la landing d’origine.

L’audit du code, des routes et des premières captures a révélé : typographies secondaires trop petites, deux styles de tableaux concurrents, caption mal composée sur téléphone, navigation latérale trop longue en faible hauteur, faible contraste de certaines actions publiques, en-tête mobile déséquilibré, formulaires étirés et modales à défilement global. Les tests clavier ont aussi révélé une sortie de focus possible et une propagation d’Échap entre modales imbriquées.

## Checklist des pages

Chaque ligne utilise les composants partagés revus et est incluse dans la matrice navigateur à 360, 390, 430, 768, 1024 et 1440 px.

| État | Page / route | Travail principal |
| --- | --- | --- |
| Revue | Accueil `/` | Design marine/or préservé, header mobile recomposé, menu en panneau accessible, hero et cartes fluides, textes lisibles, FAQ clavier, calcul et support. |
| Revue | Connexion `/login` | Composition mobile en une colonne, contraste, champs 52 px, visibilité du mot de passe, retour d’erreur et chargement. |
| Revue | Inscription `/register` | Même langage de contrôles, aide et confirmation lisibles, composition enrichie sur ordinateur sans hauteur rigide. |
| Revue | Conditions `/terms` | Largeur de lecture, sommaire tactile et sticky sur grand écran, titres et action contrastée. |
| Revue | Confidentialité `/privacy` | Même composition documentaire et navigation clavier. |
| Revue | Données `/data` | Même composition documentaire, textes longs et liens. |
| Revue | Aide `/help` | Étapes lisibles et sommaire adapté au mobile. |
| Revue | Tableau de bord `/dashboard` | Montants avec unité, cartes hiérarchisées, contenu regroupé, état sans activité. |
| Revue | Catalogue `/markets` | Recherche et filtres, grille/liste, cartes lisibles, actions et favoris tactiles. |
| Revue | Marché `/markets/{id}` | Questions et règles longues, marché ouvert/résolu, ordre en panneau sur téléphone, confirmation imbriquée. |
| Revue | Positions `/positions` | Cartes sur téléphone, tableau sur écran large, filtres et statuts. |
| Revue | Transactions `/transactions` | Opération priorisée, montants identifiés, tableau adaptable, référence ouvrant un panneau. |
| Revue | Portefeuille `/wallet` | Solde/statistiques regroupés, paiement à côté sur ordinateur, statut indisponible clair, suivi conservé même service désactivé. |
| Revue | Création `/creator` | Formulaire structuré, champs à largeur utile, aide latérale et historique des propositions. |
| Revue | Support `/support` | Demandes et création distinctes, sujets longs, état vide. |
| Revue | Conversation `/support/{id}` | Messages à défilement local, formulaire accessible, informations client adaptées, lecture trader et admin. |
| Revue | Notifications `/notifications` | Différenciation lue/nouvelle et action globale désactivée lorsque tout est lu. |
| Revue | Profil `/profile` | Formulaire structuré, indications de sécurité, mots de passe affichables, résumé du compte. |
| Revue | Radar `/predictions` | État indisponible explicite, bouton d’actualisation avec état occupé. |
| Revue | Pilotage `/admin` | Hiérarchie et cartes cohérentes avec l’espace trader, priorités et outils lisibles. |
| Revue | Modération `/admin/markets` | Filtres, liste mobile et tableau, actions à portée du clavier. |
| Revue | Marché admin `/admin/markets/{id}` | Proposition en attente et marché ouvert, formulaire de décision et confirmation. |
| Revue | Utilisateurs `/admin/users` | Composant tableau unifié, rôles et accès lisibles, libellés non coupés, confirmation de suspension. |
| Revue | Pipeline `/admin/pipeline` | État indisponible et formulaires reprenant les mêmes contrôles. |
| Revue | Erreurs 403 / 404 | Identité de marque, explication lisible et actions de récupération. |

## Choix d’implémentation

- Tokens de marque et durée de transition partagés ; surfaces légères, typographie plus lisible, rayons et espacements cohérents. Le CSS des composants, de la landing, du workspace et de l’authentification est organisé par responsabilité avec le mobile comme base.
- Les colonnes sont ajoutées progressivement à 640, 768, 1024 et 1280 px. Les images de marque possèdent leurs dimensions intrinsèques ; aucune nouvelle image ni bibliothèque lourde.
- Retrait du `overflow-x: hidden` global. Les cellules et titres se composent réellement ; les tableaux denses peuvent défiler dans leur propre région nommée et focalisable au clavier.
- Cartes de données sur téléphone et tableaux sur les écrans plus larges, avec rôles et labels préservés. Les boutons/badges de statut ne se coupent pas au milieu des mots.
- Navigation mobile et filtres utilisent le même Drawer. La sidebar possède un seul espace de navigation défilable pour conserver le compte et la déconnexion accessibles sur les fenêtres peu hautes.
- Les modales natives conservent focus, fermeture et états occupés. Seul le corps défile ; les actions restent dans le footer. Un verrou de défilement partagé gère les empilements ; Tab/Maj+Tab bouclent, Échap ne ferme que le panneau supérieur et le focus est restauré.
- Les formulaires conservent leurs routes, validations et valeurs. Labels/aides/erreurs sont associés, les claviers numériques/téléphoniques sont indiqués et les actions occupées sont désactivées.
- États vides, erreur, succès, hors ligne et indisponible traités dans le même langage visuel. Les retraits ne sont pas présentés comme disponibles et aucune intégration n’est activée par les changements visuels.
- Transitions courtes ; `prefers-reduced-motion` est pris en compte. Aucun contenu essentiel ne dépend d’un hover ou d’une animation.

## Vérification reproductible et isolation

```sh
npm run typecheck
npm run build
php artisan test --compact
npm run test:e2e
npm run test:ux
```

`tests/browser/ux.mjs` crée un fichier SQLite dans un répertoire temporaire `predic-e2e-ux-*`, utilise un chemin de cache de configuration propre et configure paiements/pipeline désactivés. Les fixtures refusent tout autre environnement ou base. Le serveur est arrêté et la base temporaire supprimée à la fin. Aucun enregistrement de la base locale ou du serveur de production n’est modifié.

La matrice produit `test-results/ux/report.json` et ses captures PNG (fichiers ignorés par Git). Elle couvre 222 combinaisons rôle/page/largeur : 48 publiques, 90 trader, 48 administrateur et 36 états sans activité, soit 28 URL distinctes avec les variantes de fiches. Contrôles de titre, chargement réel, absence de débordement et absence de masquage global du débordement ; captures de chaque combinaison et inspection visuelle de pages représentatives.

Vérifications supplémentaires : 639, 767, 1023, 1280 et 1920 px ; fenêtres 844 × 390 et 1024 × 600 ; textes et identifiants très longs ; données remplies et vides ; navigation FR/EN de la landing ; sommaires et FAQ ; Tab, Maj+Tab, retour de focus et Échap ; menu et filtres ; référence de transaction ; modales imbriquées ; erreur de mot de passe ; chargement lent/bouton désactivé ; hors ligne ; sidebar réduite ; confirmation administrative ; défilement clavier local des tableaux. Les confirmations financières du test UX sont annulées ; le test E2E existant utilise uniquement son journal isolé.

## Limites de vérification

Les contrôles navigateur sont réalisés dans Chrome headless sur macOS, avec viewport redimensionné et préférence de mouvement réduit. Ils ne constituent pas un test sur un téléphone ou une tablette physique. Safari, Firefox, VoiceOver/TalkBack, clavier virtuel réel, réseau mobile et zoom système restent à vérifier séparément. Les polices/icônes externes sont bloquées dans les tests : les captures montrent aussi le fonctionnement avec les fontes de repli.

Les erreurs 403 et 404 sont vérifiées par de vraies réponses de l’application de test. Les autres statuts d’erreur partagent le composant retravaillé sans avoir chacun été provoqué en navigateur. Le formulaire de dépôt et les permissions sont conservés ; aucune collecte ni payout réel, aucun opérateur ni compte marchand n’a été testé ou activé. Il n’existe pas de commande ESLint dans ce projet ; le typage et le build sont les vérifications statiques disponibles.

## Résultats obtenus le 29 octobre 2026

- Typage TypeScript et build Vite réussis ; aucune nouvelle dépendance.
- 144 tests PHP réussis, 1 095 assertions, sur SQLite en mémoire.
- Parcours E2E existant réussi : inscription, connexion, fixtures isolées de financement, création, modération, position, persistance, support et navigation responsive.
- 222 contrôles page/largeur réussis, 28 URL, zéro erreur JavaScript et aucun débordement de page détecté. Les tableaux peuvent défiler localement sans masquer le problème au niveau du body.
- Contrôles d’interaction complémentaires réussis : chargement lent, bouton désactivé, mode hors ligne, Tab/Maj+Tab, Échap, focus restauré, modales imbriquées, filtres, FR/EN, sommaire, références, sidebar et tableaux au clavier.
- `git diff --check` et syntaxe du script PHP de fixtures validés. Aucun déploiement ni appel financier réel.

Captures représentatives : [portefeuille sur ordinateur](../test-results/ux/trader-_wallet-1440.png), [positions sur mobile](../test-results/ux/trader-_positions-390.png), [administration](../test-results/ux/admin-_admin_users-1440.png). Les captures sont générées à partir de fixtures et ne décrivent pas les comptes locaux.
