# PredicAfrica

Application officielle de marchés de prédictions à cote fixe : Laravel 13, React et Inertia. La landing conserve le design d’origine et présente les données publiées. Les parcours métier utilisent exclusivement le serveur et un portefeuille personnel dans la devise choisie à l’inscription.

## Installation locale

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
composer dev
```

Ne pas remplacer un `.env` existant. En cas de migration d’une installation ancienne, sauvegarder la base et passer en maintenance avant migration : l’historique non monétaire est archivé et les marchés ouverts reviennent en modération. Les soldes historiques ne sont pas convertis en argent réel. Le rollback automatique de cette bascule est volontairement refusé.

Les comptes sont créés via `/register`, avec un solde nul. Aucun compte prérempli ou générateur de solde n’est livré. Pour donner le rôle administrateur à un compte existant :

```sh
php artisan platform:admin votre-adresse@example.com
```

## Parcours

- `/` : landing officielle, catalogue et FAQ.
- `/login`, `/register` : authentification réelle.
- `/dashboard`, `/markets`, `/positions` : catalogue et positions.
- `/wallet`, `/transactions` : portefeuille natif et journal financier par devise.
- `/support`, `/notifications`, `/profile` : espace personnel.
- `/admin`, `/admin/markets`, `/admin/users` : pilotage et modération.

Les dépôts passent par NDSGPay, après activation explicite et confirmation serveur. Les retraits sont actuellement fermés. La route historique `/demo` et le POST de recharge interne `/wallet` n’existent plus. Le code retiré est conservé sous `legacy/`, exclu du build applicatif.

## Taux de change et devises

Voir [le guide du change](docs/fx.md) : source CurrencyAPI côté serveur, taux datés,
confirmations expirables, unités mineures exactes et journaux séparés. Les taux
réels exigent une clé et une offre adaptée à la fréquence choisie. Les paiements
restent limités aux capacités marchandes explicitement activées.

```sh
php artisan fx:refresh
php artisan fx:status
npm run test:fx
```

## NDSGPay et déploiement

Suivre [le guide NDSGPay](docs/ndsgpay.md) : clés serveur, identifiant marchand facultatif, tuples pays/opérateur/devise confirmés, webhook HTTPS, worker `payments` et scheduler. Aucun secret ne doit être placé dans React ou une variable `VITE_*`. La suppression de l’ancien mode ne suffit pas à activer les paiements.

```sh
php artisan payments:health
php artisan payments:audit
php artisan ledger:reconcile
```

L’inbox/outbox et la réconciliation sont durables. Un paiement dont l’initiation reste incertaine n’est jamais réémis automatiquement.

Le service Python à sorties aléatoires n’est plus une dépendance officielle. Le connecteur de prédictions est désactivé par défaut et n’accepte que des réponses explicitement qualifiées non synthétiques. Les modifications antérieures du service restent préservées dans `legacy/retired-ai/`.

## Vérifications

```sh
php artisan config:clear
php artisan test --compact
php tests/concurrency/official-cutover.php
npm run typecheck
npm run build
npm run test:e2e
```

PHPUnit et les tests navigateur disposent de bases isolées. Aucun paiement réel n’est exécuté. Pour les tests concurrents MySQL, voir [tests/concurrency/README.md](tests/concurrency/README.md).

[Architecture](docs/architecture.md) · [Paiements et incidents](docs/ndsgpay.md)
