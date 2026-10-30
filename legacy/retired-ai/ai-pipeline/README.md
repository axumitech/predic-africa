# Pipeline Python de démonstration

Ce fournisseur FastAPI/SQLAlchemy génère des suggestions et scores **aléatoires**, sans entraînement ni source contractuelle. Laravel reste le monolithe métier : import en attente, publication manuelle, mises et règlement locaux. `publisher.py` enregistre uniquement des suggestions dans la base Python.

## Démarrer en local

Python 3.10+ ; environnement de test utilisé : Python 3.12.14.

```sh
cd services/ai-pipeline
python3 -m venv .venv
. .venv/bin/activate
python -m pip install -r requirements.txt
python -m uvicorn main:app --host=127.0.0.1 --port=8101
```

Ne pas utiliser le port Laravel 8000 pour AI_PIPELINE_URL. La base Python est séparée : `AI_DATABASE_URL` choisit son emplacement ; par défaut, `predicafrika.db` dans ce dossier. Les tables sont créées au démarrage, plus à l’import du module scheduler. Le scheduler de démonstration s’exécute une fois au démarrage puis chaque heure ; `AI_PIPELINE_SCHEDULER_ENABLED=false` désactive ces tâches. Son arrêt est demandé à la fermeture de l’application.

## Accès et contrat

- GET `/api/markets` : dix suggestions actives non expirées, éventuellement filtrées par `domain`. Date d’expiration et date de génération en UTC explicite ; options Oui/Non et cotes de simulation.
- GET `/api/predictions` : dix matchs futurs simulés avec scores aléatoires, filtre `domain` facultatif. Les explications sont explicitement fictives.
- POST `/api/markets/generate` : génération d’un lot atomique de suggestions. Si une insertion échoue, toutes les suggestions de cette requête sont annulées ; HTTP 503 sans détail interne. Aucun marché Laravel publié.

Les lectures exposent `simulation: true` et `source: random_demo`. Les dates naïves historiques de cette base sont interprétées comme UTC. Aucun score ne constitue une prédiction évaluée.

`AI_PIPELINE_TOKEN` protège la génération par l’en-tête `X-Pipeline-Token`. Lorsqu’il est défini, il est exigé même en loopback. Utiliser le même secret dans l’environnement Python et dans le `.env` Laravel ; Python ne charge pas automatiquement le `.env` de Laravel. Ne jamais ajouter sa valeur au dépôt. Laravel envoie ce jeton via `config/predictions.php`.

Pour préserver la démonstration locale, sans jeton configuré seules les connexions loopback peuvent générer ; les autres reçoivent 403. Les en-têtes Forwarded/X-Forwarded-For ne sont pas utilisés pour autoriser une requête. **Configurer un jeton avant tout proxy**, car celui-ci peut apparaître local. Maintenir ce fournisseur sur une interface privée : les lectures ne sont pas authentifiées et aucun quota Python n’est ajouté dans ce lot.

CORS n’autorise aucune origine navigateur par défaut. `AI_PIPELINE_CORS_ORIGINS` accepte une liste explicite d’origines séparées par des virgules si nécessaire ; aucun besoin pour Laravel côté serveur. Les cookies inter-origines ne sont pas autorisés.

## Tests isolés

```sh
python -m pip install -r requirements-test.txt
python tests/test_api.py
```

Les tests utilisent un fichier SQLite temporaire, le client ASGI FastAPI et des pannes injectées ; ils n’ouvrent pas la base existante et ne démarrent pas de scheduler réel. `httpx` est une dépendance de test pour TestClient, pas une nouvelle dépendance métier. Sept tests : accès par jeton, repli local, rollback de lot, fraîcheur et UTC du catalogue, libellés simulés et filtrage des matchs, CORS, cycle de vie scheduler.

Les dépendances runtime restent non épinglées comme dans le dépôt initial. Le lot a été testé avec FastAPI 0.148.0, SQLAlchemy 2.1.5, APScheduler 3.11.3 et httpx 0.28.1 ; Starlette avertit que son support de httpx est déprécié. Cela n’est pas une validation de toutes les versions possibles de requirements.txt ni du serveur uvicorn en production.

## Limites

Pas de modèle validé, ingestion réelle, résolution officielle ni paiement externe. Une nouvelle requête de génération crée un nouveau lot ; un timeout Laravel ne prouve pas que Python n’a rien enregistré. Aucun rejeu automatique POST ajouté. Un seul processus scheduler est prévu pour cette démonstration ; plusieurs workers pourraient dupliquer les tâches. Les JSON historiques malformés, la rétention, les quotas directs, le verrouillage des versions et les volumes importants demandent un chantier séparé.

La copie historique `ai_pipeline/` au parent du dépôt Laravel n’est pas modifiée. Vérifier quelle copie est réellement lancée à l’URL configurée avant déploiement.
