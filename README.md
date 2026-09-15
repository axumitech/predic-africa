# PredicAfrica — plateforme de démonstration

Application Laravel 13 / Inertia 3 / React 19 / TypeScript. L’espace principal dispose désormais de comptes authentifiés et de données persistantes en base. Les mises utilisent des **cotes fixes et des crédits fictifs**, sans argent réel, conformément au périmètre retenu.

## Démarrer

Prérequis : PHP 8.4+, Composer, Node.js 22.12+ et npm. SQLite suffit pour le développement.

```bash
composer install
# Sur une nouvelle installation uniquement :
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Sur cette installation, `.env`, les dépendances et la base existent déjà ; les nouvelles migrations ont été appliquées. Ne remplacez pas le `.env` existant. Ouvrir http://127.0.0.1:8000 et créer un compte. Le solde initial est nul ; le portefeuille permet des dépôts simulés.

Pour le développement, `composer run dev` démarre Laravel et Vite.

### Premier administrateur et catalogue

Créez d’abord un compte via `/register`, puis remplacez l’adresse ci-dessous par celle de ce compte :

```bash
php artisan platform:admin votre-adresse@example.com
php artisan platform:seed votre-adresse@example.com
```

La première commande attribue les droits administrateur à un compte existant. La deuxième ajoute six marchés explicitement fictifs, ouverts pour sept jours. Elle ne modifie pas les marchés déjà présents. Aucun mot de passe administrateur par défaut n’est fourni.

Vous pouvez aussi créer vos marchés depuis `/creator`, puis les publier depuis leur fiche avec un compte administrateur.

## Pages et parcours

| Page | Fonctionnement |
| --- | --- |
| `/` | Accueil responsive |
| `/register`, `/login` | Inscription, connexion par session, limitation des tentatives |
| `/markets` | Catalogue paginé, recherche, filtres catégorie et statut |
| `/markets/{id}` | Règles, échéance, cotes, volume, prise de position et résultat |
| `/creator` | Proposition de marchés Oui/Non et suivi de ses propositions |
| `/positions` | Positions privées, cotes verrouillées, gains potentiels et résultats |
| `/wallet` | Solde persistant, dépôts et retraits simulés |
| `/transactions` | Journal privé avec référence unique et pagination |
| `/support`, `/support/{id}` | Tickets, conversation, fermeture et réouverture ; vue globale pour les admins |
| `/notifications` | Notifications persistantes et lecture |
| `/profile` | Modification du nom, e-mail et mot de passe avec vérification du mot de passe actuel |
| `/predictions` | Radar IA avec état d’indisponibilité récupérable |
| `/admin` | Indicateurs issus de la base et équilibre du journal |
| `/admin/markets` | File de modération et accès aux résolutions |
| `/admin/users` | Suspension et réactivation des accès |
| `/admin/pipeline` | Consultation, génération et importation de suggestions IA |
| `/help`, `/terms`, `/privacy`, `/data` | Aide et informations décrivant la démonstration |
| `/demo` | Prototype historique conservé séparément |

Les pages privées vérifient l’authentification ; les pages et mutations administrateur vérifient le rôle côté serveur. Un utilisateur ne peut ni choisir son rôle à l’inscription ni consulter les tickets ou les transactions d’un autre compte.

## Règles de la démonstration

- Unité unique : crédit fictif entier, sans conversion monétaire.
- Mise de 100 à 1 000 000 crédits. Cotes de 1,01 à 10,00, enregistrées en centièmes.
- Retour du gagnant = `floor(mise × cote_en_centièmes / 100)`, mise incluse ; aucune commission.
- Les positions sont engagées jusqu’à la résolution ; pas de revente ni de carnet P2P.
- Les prises de position exigent un marché publié dont l’échéance n’est pas dépassée.
- Une résolution Oui/Non exige une échéance atteinte et une source. Une annulation est possible avant l’échéance et rembourse toutes les mises.
- Débit, position, écritures et notifications sont validés dans une transaction commune.
- Les dépôts, retraits et mises ont une référence unique. La répétition du même ordre ne crée pas de double débit. Réutiliser la référence pour un ordre différent est refusé.
- Le journal comporte deux écritures opposées par opération. Le compte de contrepartie de la simulation peut être négatif ; les soldes utilisateurs ne peuvent pas l’être.
- Une résolution déjà enregistrée ne verse pas les gains une seconde fois.

## Pipeline Python facultatif

```bash
cd services/ai-pipeline
python3 -m venv .venv
source .venv/bin/activate
python -m pip install -r requirements.txt
python -m uvicorn main:app --host=127.0.0.1 --port=8101
```

Configurer `AI_PIPELINE_URL`, `AI_PIPELINE_TIMEOUT` et `AI_PIPELINE_CACHE_SECONDS` dans `.env` si nécessaire. Python utilise le port **8101**, distinct des ports Laravel 8000/8001. Ne configurez jamais `AI_PIPELINE_URL` vers Laravel. Les lectures ont un délai maximal de 1 seconde ; après un échec, les appels sont suspendus pendant `AI_PIPELINE_RETRY_SECONDS` (30 secondes par défaut), puis reprennent automatiquement. Une seule alerte concise est journalisée par pause. `AI_PIPELINE_ENABLED=false` permet de désactiver les appels si Python n’est pas utilisé. Les réponses indisponibles restent des HTTP 503 avec `Retry-After`, et le catalogue local reste accessible. Le pipeline conserve sa propre base SQLite ; il doit rester sur une interface privée.

La génération depuis `/admin/pipeline` appelle le service existant. L’importation accepte seulement les suggestions Oui/Non valides et non expirées, fixe les cotes côté serveur, évite les doublons et crée un marché **en attente**. La publication reste une décision administrateur. L’indisponibilité du pipeline ne bloque pas les autres modules.

Le pipeline utilise toujours des données et probabilités simulées. Aucun entraînement de modèle réel, ingestion de source contractuelle ni garantie prédictive n’est ajouté.

## Vérifications

```bash
php artisan test
npm run typecheck
npm run build
npm run test:e2e
composer validate --no-check-publish
```

Les tests PHP utilisent SQLite en mémoire et des réponses HTTP simulées. Ils couvrent notamment les permissions, le journal, les doubles soumissions, les gains, les remboursements, la confidentialité et le pipeline.

Les tests navigateur nécessitent Google Chrome. Ils créent leur propre base SQLite temporaire et leur serveur local sur un port libre, sans écrire dans la base de développement. Captures dans `test-results/`.

Pour vérifier le prototype historique, démarrer Laravel sur le port 8010 puis lancer `npm run test:e2e:legacy` ; `TEST_BASE_URL` peut remplacer cette adresse.

## Organisation et limites

Voir [docs/architecture.md](docs/architecture.md). L’accueil reprend les composants, animations et styles d’origine. Le moteur historique est chargé sur l’accueil et sur `/demo` pour leurs interactions de démonstration ; les pages métier restent connectées au serveur. L’espace principal utilise React/Inertia sans mutations DOM historiques ni soldes dans `localStorage`.

Les paiements Mobile Money réels, le P2P, la conversion multidevise, le SMS/USSD opérateur, la réinitialisation de mot de passe par e-mail, les événements temps réel et les tests de charge PostgreSQL ne sont pas implémentés dans le nouvel espace. Les fonctions de langue, région et USSD fictif du prototype restent sur `/demo`. Les documents d’origine dans `docs/original` décrivent une ambition produit plus large que cette démonstration.

Cette livraison n’est pas une certification de production : les conditions de l’exploitant, les politiques de conservation et les intégrations externes nécessitent un chantier distinct. Les soldes et comptes du prototype ne sont pas importés automatiquement.
