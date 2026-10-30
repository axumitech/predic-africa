# NDSGPay : architecture et exploitation

Contrat : documentation fournie dans cette conversation, octobre 2026. Aucune capacité prestataire supplémentaire supposée. Laravel 13 / authentification par session, rôles trader et admin, Inertia/React, queue SQL et scheduler Laravel. Aucun tenant n’existe dans ce projet ; la propriété est vérifiée par utilisateur. Un ajout de tenancy devra modifier les clés, requêtes et policies avant activation.

## Périmètre et invariants

Les fonds réels dans leur devise native sont enregistrés dans `payment_wallets` et le journal double entrée. Ce solde finance les positions ; les crédits historiques ont été retirés du parcours actif et archivés. Le besoin métier disponible est un rechargement volontaire à montant variable : la règle serveur autorise un montant natif entre les bornes applicatives configurées (100 à 1 000 000 unités majeures par défaut). Le navigateur choisit ce montant autorisé ; il ne fixe aucun prix de commande. Le propriétaire vient de la session et pays/devise/opérateur des capacités serveur confirmées. Pour vendre un produit à prix fixe, il faudra un modèle de commande serveur, un montant figé et une clé d’opération liée à cette commande : ne pas réutiliser aveuglément ce parcours wallet.

Les montants sont stockés en unités mineures exactes, avec une devise dans le
journal. XAF reste en francs entiers ; CDF, KES, ZMW et SLE utilisent deux décimales.
Le connecteur envoie les montants décimaux majeurs au prestataire et conserve
exactement les décimales des réponses avant leur comparaison. Les capacités
marchandes et le pays/devise du portefeuille sont contrôlés avant création.
Voir [le moteur de change](fx.md) et [le catalogue d’inscription](registration-regions.md)
pour les sources, la précision, les confirmations et les prérequis d’activation.

Les nouvelles créations de payouts sont fermées dans la policy, le service et l’interface. Il n’existe actuellement ni permission de payout, ni MFA/réauthentification dédiée, ni validation de bénéficiaire. Les anciens retraits déjà initiés restent rapprochables ; une réservation historique n’est libérée qu’après un FAILED confirmé par GET. Avant toute ouverture des payouts : permission dédiée, réauthentification/MFA, bénéficiaire vérifié, plafonds par opération/période, double validation configurable, tests concurrents de réservation et procédure frais marchand. Aucun frais exact ni endpoint de solde n’est inventé.

Invariants SQL : référence locale unique, empreinte HMAC des paramètres normalisés, clé active unique, identifiant fournisseur unique (restriction globale plus forte que son périmètre ; une seule configuration marchand), verrou utilisateur avant écriture financière, verrou paiement, verrou portefeuille et mouvements uniques `(payment_id, effect)`. Un seul rechargement non final par utilisateur est autorisé, même si le navigateur change d’UUID. Cette règle est volontairement prudente pour ce wallet. Le journal de mouvements est append-only dans les services ; les droits SQL d’exploitation doivent interdire ses modifications manuelles. Il n’est pas à lui seul une preuve cryptographique d’immutabilité contre un administrateur de base.

## États et composants

| État local | Sens et traitement |
| --- | --- |
| initiating | Création locale persistée ; un seul processus est autorisé à envoyer le POST. |
| pending | Identifiant reçu, attente de vérification ; aucun crédit. |
| awaiting_confirmation | Notification reçue/association opérateur, vérification GET encore nécessaire. |
| initiation_unknown | Issue incertaine ; aucune nouvelle création automatique. |
| completed | Succès vérifié par GET, effet financier atomique. |
| failed | Échec vérifié par GET, ou refus local certain avant tout appel prestataire. |

`provider_status` conserve le dernier statut prestataire validé ; `local_state` porte la décision métier. `status` est maintenu pour compatibilité. `initiated_at`, `confirmed_at`, `last_checked_at`, `next_check_at`, compte d’essais, erreur nettoyée, identifiant de corrélation et lien `business_type/business_id` sont persistés. Un statut inconnu n’est jamais un succès. Un délai dépassé devient une anomalie, jamais un échec financier.

- `Ndsgpay` : chemins fermés, hôte de configuration HTTPS avec chemin `/api`, Bearer serveur, JSON, vérification TLS, redirections interdites, timeouts 3/8 secondes. Pas de double `/api/api`, identifiants limités aux caractères sûrs avant construction d’URL. Aucun retry POST.
- `ProviderBudget` : cadence partagée par hash de clé API et catégorie GET/POST, sous verrou SQL, indépendante du cache/processus. Espacement conservateur de 1 010 ms pour les créations et 510 ms pour les lectures. Les candidats sans budget sont différés/refusés localement ; Retry-After reçu est persisté. Les horloges des nœuds doivent être synchronisées (NTP).
- `StorePaymentRequest`, `Capabilities`, `PaymentPolicy` : validation, normalisation téléphone, autorisation propriétaire. Les formats proviennent de l’indicatif et des longueurs nationales explicitement configurés, pas d’une regex censée couvrir tous les pays.
- `Payments` : initiation et application centralisée des états vérifiés. Bail SQL de 30 secondes avec token de fencing avant GET, puis vérification du token dans la transaction financière. Les résultats des anciens workers sont sans effet après perte de bail. Un statut final ne régresse pas ; une contradiction est journalisée.
- `WebhookSignature` / `WebhookController` : vérification HMAC sur le corps brut avant décodage, parsing strict, timestamp entier, tolérance 300 secondes, SHA-256 hex et comparaison constante, contrôle du marchand uniquement si un identifiant attendu est configuré, corps maximal 16 Kio. Le proxy doit aussi limiter la taille avant chargement PHP. Seuls les champs fiables type/ID/statut final sont conservés.
- `payment_inbox` : persistance avant réponse 2xx ; déduplication fournisseur + périmètre local du compte + type + ID + statut. Le webhook ne fait aucun appel réseau et ne crédite rien. Si SQL échoue, pas d’accusé positif. Les identifiants inconnus sont conservés et réassociés plus tard, y compris webhook précoce.
- `Inbox`, `Recovery`, `ProcessPayment` : traitement hors requête HTTP. La découverte périodique des lignes persistées remplace la dépendance à un dispatch après commit ; panne avant dispatch ou worker tué = reprise ultérieure depuis SQL. Une redelivery de job est sans effet financier supplémentaire. Le nombre d’essais par job est 1 ; les nouvelles tentatives sont planifiées durablement avec backoff exponentiel borné à six heures et jitter. Aucun âge maximal ne supprime une opération en attente.
- `payment_outbox` / `Outbox` : événement final inséré dans la même transaction que crédit/état. La notification locale et sa marque de livraison partagent ensuite une autre transaction SQL. Une future livraison externe devra disposer d’un consommateur idempotent ; ne pas ajouter un simple appel réseau entre ces deux écritures.

Les lectures réseau ne se font pas pendant les transactions financières. Les erreurs 400, 401, 403, 404, 429, 5xx et réseau ont des codes distincts. Une consultation défaillante ne remplace jamais l’état métier précédent. Les erreurs de création envoyées au prestataire sont conservées prudemment comme incertaines, même 400/401/403, en attendant une preuve exploitable ; cela augmente les interventions mais évite une réémission fondée sur un rejet mal interprété. Les refus locaux avant appel peuvent être clos sans mouvement.

## Configuration sans secrets

Le catalogue `config/ndsgpay_operators.php` contient les 24 opérateurs transmis,
répartis dans 12 pays et 8 devises. Il fournit les noms lisibles dans le formulaire
de recharge. Seuls les codes présents dans `NDSGPAY_CAPABILITIES` pour le pays
et la devise du portefeuille sont proposés. Ajouter une ligne au catalogue
n'active pas cet opérateur sur le compte marchand.

Pour activer un code confirmé par NDSGPay, ajouter son tuple à
`NDSGPAY_CAPABILITIES` dans le `.env` serveur, en précisant aussi l'indicatif et
les longueurs nationales confirmées. Ensuite exécuter `php artisan config:cache`
et `php artisan queue:restart`. Ne pas déduire les formats téléphoniques de la
seule liste des opérateurs.

`.env.example` contient uniquement des valeurs vides/fictives. Sur le serveur, injecter les secrets via l’environnement ou le gestionnaire de secrets, jamais `VITE_*` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://predicafrica.com
QUEUE_CONNECTION=database
NDSGPAY_ENABLED=false
NDSGPAY_BASE_URL=https://ndsgpay-backend.vercel.app/api
NDSGPAY_SECRET_KEY=
NDSGPAY_WEBHOOK_SECRET=
NDSGPAY_MERCHANT_ID=
NDSGPAY_CAPABILITIES='[]'
```

Exemple de structure, **pas une confirmation que cet opérateur est activé** :

```json
[{"country":"GAB","correspondent":"AIRTEL_GAB","currency":"XAF","calling_code":"241","national_lengths":[8]}]
```

Renseigner chaque tuple uniquement après confirmation NDSGPay. Un même code correspondant ne doit pas désigner deux tuples ; l’application refuse une sélection ambiguë. L’ancienne variable `NDSGPAY_CORRESPONDENTS` seule ne permet plus l’activation. Garder `NDSGPAY_ENABLED=false` tant que secrets, capacités, URL publique, workers et scheduler ne sont pas configurés.

Les numéros sont chiffrés avec Laravel Crypt et ne sont jamais renvoyés au frontend ; l’inbox ne conserve pas le payload complet. La même APP_KEY et les clés précédentes doivent être disponibles aux workers pendant une rotation. L’empreinte HMAC d’idempotence utilise APP_KEY : avant rotation, terminer/quarantainer les opérations actives et prévoir la migration des empreintes ou la conservation d’une clé d’empreinte stable. Sauvegarder les clés séparément des sauvegardes chiffrées ; perte de clé = impossibilité de déchiffrer les bénéficiaires. Définir une politique de conservation et d’anonymisation des téléphones en fonction des obligations comptables et du besoin de rapprochement.

### Identifiant marchand facultatif

`NDSGPAY_MERCHANT_ID` peut rester vide. Les appels API utilisent la clé secrète
et les webhooks exigent toujours la signature HMAC du corps brut et un timestamp
valide. Si un ID est renseigné, l'en-tête `X-NDSGPay-Merchant` doit lui correspondre.
Sans ID configuré, cet en-tête n'est ni une identité fiable ni une clé de routage :
il ne change pas la déduplication ni le paiement associé. Une seule configuration
de compte NDSGPay est prise en charge. L'inbox stocke alors un périmètre vide et
les nouveaux paiements un `merchant_id` SQL nul ; aucun changement de schéma requis.

Un événement signé ne crédite aucun fonds : seul le GET authentifié sur l'ID
fournisseur déjà associé au paiement local peut confirmer le statut, le montant
exact et la devise. Un ID inconnu n'entraîne ni création de paiement ni crédit.
Ne pas changer le périmètre marchand pendant des opérations en cours : les anciens
paiements d'un autre périmètre restent bloqués et nécessitent une reprise auditée.

## Installation / workers / webhook

Aucun déploiement ni paiement réel n’est effectué par les tests. Procédure à réaliser sur le serveur après sauvegarde et revue :

```sh
php artisan migrate --force
php artisan config:cache
php artisan queue:work --queue=payments --tries=1 --timeout=20
php artisan payments:health
php artisan payments:audit
```

Superviser le worker avec redémarrage automatique et exécuter `php artisan queue:restart` après mise à jour/configuration. Garder `retry_after` de la queue supérieur au timeout worker et au bail (base actuelle : 90 secondes, timeout 20, bail 30). Utiliser une base SQL partagée InnoDB pour les écritures financières. Configurer un cache partagé pour les verrous du scheduler en multinœud ; les contraintes et verrous financiers restent SQL. Un seul scheduler actif est recommandé ; une double exécution reste tolérée par les protections métier.

Cron, chaque minute depuis le répertoire applicatif :

```cron
* * * * * cd /chemin/predic-africa && php artisan schedule:run >> /var/log/predic-scheduler.log 2>&1
```

`payments:reconcile` découvre inbox, outbox et paiements dus chaque minute. `payments:audit` vérifie les soldes contre les mouvements chaque heure. Enregistrer **https://predicafrica.com/webhooks/ndsgpay** dans les paramètres NDSGPay, avec le même secret côté serveur (identifiant marchand facultatif). Déployer réellement le DNS/HTTPS et cette route avant activation : localhost est inaccessible depuis le fournisseur. Seule cette route est exemptée de CSRF/session, les formulaires wallet restent protégés. Vérifier TLS côté frontal et synchronisation horaire. La conception évite les appels lents au webhook, mais la limite de cinq secondes doit aussi être contrôlée en environnement de déploiement sous charge et pendant les incidents SQL.

Après contrôles et accord d’activation : `NDSGPAY_ENABLED=true`, reconstruire le cache et redémarrer les workers. `payments:health` ne contacte pas NDSGPay et ne prouve ni la validité des secrets ni l’activation marchand. Un test réel de faible montant exige une autorisation distincte et un numéro contrôlé.

## Surveillance et incidents

Surveiller : `payments:health` (code non nul si configuration incomplète ou anomalies ouvertes), `payment_anomalies`, longueur/ancienneté des inbox/outbox non traitées, paiements en attente depuis plus de 30 minutes, `failed_jobs`, heartbeat du scheduler et processus worker. Acheminer les logs structurés `payment_anomaly` vers le dispositif d’alerte de l’hébergement ; aucune destination email/Slack n’a été supposée/configurée. Les journaux applicatifs n’incluent ni Bearer, ni signature, ni corps prestataire, ni téléphone. Configurer également les traces HTTP/APM/proxy pour exclure Authorization, cookies, corps et paramètres de paiement. Les logs SQL/debug doivent rester désactivés en production.

Ne jamais modifier un solde ou supprimer un mouvement pour « corriger » une anomalie.

1. **Initiation inconnue sans ID** : stopper toute relance, conserver référence locale/corrélation, date UTC, montant/devise, marchand et numéro accessible uniquement à l’opérateur autorisé. Comparer au dashboard NDSGPay et ouvrir un dossier support. Les GET documentés ne permettent pas de rechercher une transaction par référence locale. Ne jamais inventer un ID ou libérer un ancien retrait sur timeout.
2. **ID retrouvé et propriété prouvée** : une personne disposant des accès serveur et d’un compte admin actif peut associer l’ID. La commande contrôle aussi par GET l’ID, le montant, la devise, l’opérateur et le téléphone ; action et dossier sont audités. Elle ne crédite pas directement : la reprise durable confirme ensuite l’opération.

```sh
php artisan payments:attach UUID_LOCAL ID_PRESTATAIRE --actor=ID_ADMIN --case=SUPPORT-123
php artisan payments:reconcile
```

3. **Panne de queue/dispatch** : rétablir worker/scheduler, exécuter `payments:reconcile`. Les lignes SQL non traitées seront retrouvées. Les tentatives automatiques ne renvoient jamais le POST de création. La base jobs peut contenir des duplications de travail pendant une longue panne : surveiller/purger les jobs obsolètes après analyse, sans supprimer les inbox/outbox sources.
4. **Contradiction finale** : conserver le résultat financier initial, relever `final_contradiction`/`webhook_get_contradiction` et investiguer auprès de NDSGPay. Une notification contradictoire n’inverse pas automatiquement une écriture. Pour un dépôt historiquement FAILED devenu réellement COMPLETED, une résolution explicite réexécute le GET, utilise les mêmes contrôles/mouvements uniques et audite l’intervention :

```sh
php artisan payments:accept-late-deposit UUID_LOCAL --actor=ID_ADMIN --case=SUPPORT-456
```

Le succès tardif est crédité au portefeuille réel ; aucune commande fictive expirée n’est livrée. La répétition de cette commande ne recrédite rien. Les autres contradictions (notamment payout historique après restitution, ou COMPLETED devenu FAILED) nécessitent une décision financière séparée et une écriture compensatoire revue ; aucune correction automatique non justifiée n’est proposée.

5. **401/403** : vérifier clé, compte activé et marchand, sans réémettre une création incertaine. **404 GET** : anomalie d’association/compte, ne pas considérer l’opération comme échouée. **429** : respecter le prochain essai persistant/Retry-After et vérifier qu’aucun autre système ne consomme la même clé. Le limiteur couvre cette application, pas les applications externes utilisant la clé. **Mismatch montant/devise** : aucun crédit, investiguer ; aucune valeur du webhook ne remplace les données locales.
6. **Migration d’opérations anciennes** : migration additive, téléphone historique chiffré, soldes/mouvements conservés. Les anciens terminaux sont signalés `legacy_confirmation_unverified` et ne sont jamais recrédités. Utiliser `payments:attach` avec le même ID pour rattacher explicitement le marchand des anciennes lignes, puis rapprocher. Ne supprimer l’alerte qu’après revue comptable. Les clés uniques historiques d’ID fournisseur restent globales ; une future gestion de plusieurs comptes marchands exige une migration de périmètre et de wallets.

Les anomalies persistent comme piste d’investigation. Après résolution, consigner le dossier dans l’audit et `resolved_by/resolved_at` via une procédure DBA revue ; aucune interface de résolution ouverte aux utilisateurs n’est exposée. Le compte DBA peut altérer les données : limiter ses droits, tracer les accès et effectuer les sauvegardes.

## Points à confirmer avec NDSGPay avant production

- Compte marchand validé, ID marchand facultatif, codes exacts et tuples pays/opérateur/devise autorisés ; aucune déduction RDC/CDF/USD.
- Format exact des identifiants, montants GET, formats téléphoniques et statuts retournés par chaque opérateur.
- Périmètre des quotas pour payouts et partage de quotas entre opérations/comptes ; limites métier et frais réels.
- Support futur d’idempotence prestataire, recherche par référence marchande et procédure de récupération après création sans réponse.
- Comportement d’erreur garantissant qu’un 400/401/403/429 n’a pas créé d’opération ; réconciliation des contradictions finales.
- Renouvellement du timestamp de signature lors des relivraisons et rotation des secrets ; la tolérance reste 300 secondes.

Risque résiduel : une panne entre l’exécution du POST chez NDSGPay et la sauvegarde locale de son ID ne peut pas être éliminée sans capacité prestataire documentée. La protection locale prévient la réémission automatique mais ne garantit pas à elle seule une création unique chez le prestataire. Les files, la base, l’horloge et les procédures opérateur peuvent aussi subir des incidents ; ce système privilégie l’absence de fausse confirmation et une résolution traçable.

## Vérification reproductible

```sh
php artisan test --compact
npm run typecheck
npm run build
PREDIC_TEST_MYSQL_SOCKET=/socket/mysql-test.sock php tests/concurrency/payments.php
```

Les tests utilisent `Http::fake()` et interdisent le réseau non simulé. Les tests feature SQLite ne prouvent pas le verrouillage concurrent. Le script MySQL crée/supprime sa propre base aléatoire, refuse de démarrer sans socket de test explicite et observe deux attentes de verrous dans InnoDB. Voir aussi `tests/concurrency/README.md`. Les paiements réels, tests de charge, autres moteurs SQL, fonctionnement TLS externe et validation du compte marchand ne sont pas couverts par ces résultats.

Résultats obtenus localement le 29 octobre 2026 : **144 tests PHPUnit réussis, 1 124 assertions**, TypeScript et build Vite réussis. MySQL **9.6.0 / InnoDB / REPEATABLE READ** : quatre courses avec attentes de verrous observées réussies, puis rapprochement soldes/journal réussi. Migration additive et retour/upgrade des données historiques vérifiés ; migration appliquée à la base locale, audit financier local sans écart. `payments:health` signale comme attendu l’absence de credentials/capacités et d’URL HTTPS locale. Aucune activation, aucun déploiement ni appel réel NDSGPay.

Documentation Laravel officielle consultée pour cette implémentation : [client HTTP](https://laravel.com/framework/docs/13.x/http-client), [queues](https://laravel.com/framework/docs/13.x/queues), [validation et Form Requests](https://laravel.com/framework/docs/13.x/validation), [autorisations](https://laravel.com/framework/docs/13.x/authorization), [protection CSRF](https://laravel.com/framework/docs/13.x/csrf), [scheduler](https://laravel.com/framework/docs/13.x/scheduling), [chiffrement et rotation de clés](https://laravel.com/framework/docs/13.x/encryption). Le limiteur métier SQL complète les protections Laravel pour coordonner la clé prestataire entre workers.

## Évolution : portefeuille officiel et suppression de l’ancien mode

La migration `000003_retire_demo_and_unify_wallet` remplace la séparation expérimentale décrite dans les premières sections : le portefeuille XAF alimente désormais les positions et reçoit les gains/remboursements via `Ledger`, dans la même transaction SQL. Les deux soldes (`accounts` et `payment_wallets`) doivent rester égaux. `payment_movements` possède `user_id` et `ledger_transaction_id` ; `payment_id` est nullable pour les écritures de trading. L’audit inclut tous les mouvements du propriétaire.

Les anciennes écritures non monétaires sont archivées, pas converties. Une ouverture comptable reprend uniquement les fonds NDSGPay déjà présents, sans nouveau mouvement prestataire. Le POST `/wallet` et les générateurs de crédits sont supprimés. Les payouts demeurent fermés, et les secrets/capacités restent nécessaires. Voir [architecture officielle](architecture.md) pour le plan de bascule et de sauvegarde.
