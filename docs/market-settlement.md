# Résolution et règlement des marchés

## Diagnostic

Laravel 13 sert le backend, Inertia et React l’interface. La prise de position verrouillait déjà le marché puis l’utilisateur, refusait les nouvelles mises à l’échéance et validait le devis FX. Les portefeuilles sont débités à l’entrée. Le journal en partie double enregistre la contrepartie de trésorerie dans la devise du portefeuille. Le retour brut contractuel inclut la mise. La cote est stockée en centièmes et le calcul existant utilise des unités entières avec les règles d’arrondi de `FixedOdds` et `Money`. Le règlement existant ne prélève aucune commission supplémentaire.

Avant cette modification, l’échéance ne persistait aucun changement de statut. Les seules sorties d’un marché publié étaient la résolution ou l’annulation manuelle. Le scheduler importait des calendriers officiels pour proposer des brouillons. Le pipeline de prédictions et les calendriers ne fournissaient pas d’attestation de résultat exploitable pour un paiement. Le règlement chargeait toutes les positions dans une transaction HTTP et utilisait une référence aléatoire par crédit. Le verrou du marché et l’atomicité protégeaient le parcours normal contre les doubles crédits. Une erreur sur une position annulait tous les paiements du marché et nécessitait une nouvelle intervention.

## Fonctionnement

Les brouillons existants utilisent `pending` et la modération peut les publier ou les rejeter. Le scheduler ferme les marchés `open` à l’échéance. Lorsque la fin de la période d’observation est connue et atteinte, le marché passe en `pending_resolution`. Un marché historique sans fin d’événement reste `closed` jusqu’à une décision administrative.

Une décision OUI ou NON validée donne l’état `resolved`, puis `settling` et enfin `settled`. Une annulation autorisée programme un remboursement de la mise et aboutit à `cancelled`. Les anciens `resolved` sans date de résolution et `void` conservent leur sens terminal. L’état technique d’échec est enregistré séparément. Une divergence suspend le traitement en `disputed`. Une réponse en attente ou reportée est revérifiée après 15 minutes. Une erreur retarde la tentative suivante avec un délai exponentiel. Après huit erreurs, une relance administrative motivée est requise. Le système journalise les erreurs persistantes dès la cinquième tentative.

Les workers traitent jusqu’à 100 positions par transaction. Ils verrouillent le marché puis les participants dans l’ordre des identifiants. Chaque crédit a une référence déterministe unique et chaque position a au plus une écriture de règlement. Le crédit, l’écriture de règlement, la notification et le statut de la position sont atomiques. Le marché devient terminal après vérification des écritures et des montants. Le crédit est interne au portefeuille. Un retrait externe reste géré par le parcours NDSGPay.

## Contrat automatique

L’opérateur déclare les endpoints HTTPS de confiance par identifiant dans `config/market_resolution.php`. La configuration initiale est vide. Le contrôle automatique des attestations est désactivé par défaut. Après validation des adaptateurs et des contrats en préproduction, définir `MARKET_AUTOMATIC_RESOLUTION_ENABLED=true`, reconstruire le cache de configuration et redémarrer le worker. Chaque endpoint est un adaptateur contrôlé qui calcule un fait à partir des sources officielles applicables au contrat. Il doit notamment gérer les unités, la période, le fuseau horaire, les règles de compétition ou la série de prix selon l’événement. Un même identifiant de condition doit garder son sens après publication. Exemples de conditions à implémenter chez un adaptateur selon la catégorie : vainqueur au temps réglementaire, valeur d’un indicateur dans une publication donnée, résultat certifié d’un scrutin, maximum d’une série horaire sur une fenêtre UTC. L’audit n’a identifié aucun adaptateur de résultat officiel prêt à déployer dans ce dépôt.

Configurer le brouillon avant sa publication.

```sh
php artisan markets:configure-resolution 123 --actor=7 \
  --event-ends-at=2026-11-30T21:00:00Z \
  --contract='{"providers":["ligue","federation"],"event":"finale-123","condition":"vainqueur-temps-reglementaire-v1","yes_value":"A","no_value":"B","on_cancel":"void","on_tie":"review"}'
```

L’endpoint reçoit les paramètres `event` et `condition`. La réponse JSON doit répéter ces deux identifiants. Les statuts possibles sont `pending`, `postponed`, `final`, `tie` et `cancelled`. Un résultat final porte une `value` contractuelle. Un résultat décisif contient aussi `official_url` en HTTPS et `verified_at` en UTC, au format `YYYY-MM-DDTHH:mm:ssZ`. Cette date est celle de la vérification effective auprès de la source, après la fin d’observation et au plus une heure avant la consultation. Toutes les sources configurées doivent fournir une décision concordante. Le moteur conserve les attestations dans l’historique de la décision. Pour une résolution manuelle, l’administrateur doit consulter la source officielle et saisir la preuve et la justification dans le champ prévu.

## Déploiement et exploitation

1. Tester la migration additive sur une copie isolée. Vérifier les processus de sauvegarde et de restauration.
2. Appliquer `php artisan migrate --force`. Déployer les assets frontend et redémarrer les workers.
3. Utiliser une file asynchrone persistante et un cache partagé pour les verrous des jobs. Superviser `php artisan queue:work --queue=markets --tries=1 --timeout=60`. Configurer `retry_after` au-dessus de 60 secondes. Le worker paiements peut rester dédié à sa file. Le timer systemd existant exécute chaque minute un worker borné dédié à la file `markets`. Si un worker permanent est installé, définir `MARKET_SCHEDULER_WORKER=false` pour désactiver le worker du scheduler.
4. Exécuter `php artisan schedule:run` chaque minute. Vérifier la présence de `markets:process` avec `php artisan schedule:list`.
5. Contrôler la file, les logs structurés, les traitements en échec et les marchés bloqués. Brancher les logs de niveau erreur sur l’alerte d’exploitation. Le tableau d’administration donne les montants dus et crédités par devise.
6. Exécuter `php artisan markets:audit` pour lister les dossiers historiques ou bloqués et `php artisan ledger:reconcile` hors écritures concurrentes. Vérifier la source historique, le débit, la cote, la devise et les écritures existantes avant d’autoriser un règlement. Un marché terminal avec positions ouvertes exige une investigation dédiée.

Pour une correction officielle après règlement, consigner la preuve et la justification via l’action de correction historique. La procédure opérationnelle consiste à vérifier la source, rapprocher les positions et soldes, chiffrer les écarts par devise et obtenir l’autorisation financière avant toute écriture compensatoire. La décision initiale et les écritures historiques restent consultables. L’outil enregistre le signalement. Il n’exécute aucune compensation.

En cas de retour arrière, arrêter le scheduler et le worker marchés. Conserver le nouveau schéma et les écritures. Une ancienne version applicative peut ignorer les nouveaux statuts et ne doit être remise en service qu’après préparation d’un parcours compatible.

## Marché pilote CAF

L’adaptateur intégré `caf_match_report` lit le rapport de la CAF pour RD Congo – Sierra Leone du 12 novembre 2026, troisième journée des qualifications à la CAN 2027. La migration rattache le rapport au contrat historique uniquement si son identifiant de calendrier, son échéance, son titre et ses règles correspondent et si le marché est fermé sans décision. L’heure de 18 h 30 UTC constitue une borne de traitement. Le rapport officiel fait foi pour le résultat. L’attestation conserve le score et l’empreinte du document récupéré.

Pour rejouer le parcours en environnement de test isolé avec le rapport officiel en direct et un portefeuille fictif.

```sh
PREDIC_CAF_STAGING_LIVE=1 php artisan test --filter=test_caf_pilot_settles_once_from_the_official_match_report
```

## Raccordement des matchs retour CAF

Le calendrier officiel fixe les rencontres du 17 novembre 2026. Le raccordement reste individuel. L’adaptateur vérifie la date du rapport, les deux équipes et l’unicité du score, puis remet le score dans l’ordre du contrat, même si le rapport présente le vainqueur en premier.

| Marché | Identifiant | Variable d’URL du rapport |
| --- | --- | --- |
| Sierra Leone – RD Congo | afcon-2027-md4-sle-cod-2026-11-17 | CAF_REPORT_SLE_COD_MD4 |
| Gambie – Côte d’Ivoire | afcon-2027-md4-gmb-civ-2026-11-17 | CAF_REPORT_GMB_CIV_MD4 |
| Niger – Maroc | afcon-2027-md4-ner-mar-2026-11-17 | CAF_REPORT_NER_MAR_MD4 |
| Lesotho – Gabon | afcon-2027-md4-les-gab-2026-11-17 | CAF_REPORT_LES_GAB_MD4 |

Après publication du rapport final, confirmer qu’il couvre le match et le temps réglementaire. Configurer son URL CAF dans une préproduction avec une copie du contrat et exécuter `php artisan markets:caf-check <event>`. Comparer le score, la décision et l’URL avec le rapport. Ensuite, renseigner la même URL en production, reconstruire le cache de configuration et relancer la commande avec `--activate`. La commande relit la source et ne rattache que le marché fermé, sans résultat ni règle existante, dont le contrat correspond. Vérifier le résultat et les écritures après le passage du worker. En cas de report ou d’annulation, appliquer le parcours administratif prévu au contrat.
