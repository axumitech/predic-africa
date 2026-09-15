# Predicafrika - Serveur IA (Data Pipeline & API)

Bienvenue dans la documentation développeur du moteur d'Intelligence Artificielle de **Predicafrika**.
Ce service est conçu comme un pipeline de données autonome chargé de récupérer les statistiques, d'entraîner/exécuter des modèles prédictifs, et d'exposer ces prédictions via une API REST.

---

## 🏗️ Architecture du Système

Le système est écrit en **Python 3.10+** et suit une architecture modulaire :

- **FastAPI** : Fournit le serveur web asynchrone et expose les prédictions (Port 8000).
- **SQLAlchemy** : ORM pour la gestion de la base de données. Actuellement configuré pour `SQLite` (dev), prêt à migrer vers `PostgreSQL` (prod).
- **APScheduler** : Le planificateur (Cron) qui gère l'exécution des tâches d'arrière-plan (récupération de données, inférence).
- **Scikit-Learn / SHAP (Mocks actuels)** : Moteur d'inférence pour la détection des probabilités et la génération des explications (XAI).

### Structure des dossiers

```text
predicafrika/
└── ai_pipeline/
    ├── main.py                # Point d'entrée de l'API FastAPI et démarrage du Scheduler
    ├── scheduler.py           # Configuration des tâches planifiées (ex: toutes les heures)
    ├── requirements.txt       # Dépendances Python
    ├── database/
    │   ├── database.py        # Configuration SQLAlchemy (Engine, Session)
    │   └── models.py          # Modèles de tables (Match, Prediction)
    ├── ingestion/
    │   └── football.py        # Connecteurs API externes (API-Football, Sportmonks, etc.)
    └── models/
        └── predictor.py       # Logique de Machine Learning, Value Bet, et XAI
```

---

## 🚀 Guide de Démarrage Rapide (Local)

### 1. Prérequis
- Python 3.10 ou supérieur
- Un environnement virtuel (`venv` ou `conda`) recommandé.

### 2. Installation

Ouvrez un terminal, placez-vous dans le répertoire `ai_pipeline` et créez votre environnement virtuel :

```bash
cd ai_pipeline
python3 -m venv venv
source venv/bin/activate  # Sur Windows : venv\Scripts\activate
```

Installez les dépendances du projet :

```bash
pip install -r requirements.txt
```

### 3. Lancer le serveur

Pour démarrer l'API et activer simultanément le planificateur de tâches :

```bash
python -m uvicorn main:app --host=127.0.0.1 --port=8101 --reload
```

- L'API sera accessible sur : `http://127.0.0.1:8101`
- La documentation interactive de l'API (Swagger UI) : `http://127.0.0.1:8101/docs`

> [!NOTE]  
> Dès le lancement, `APScheduler` va exécuter une première fois le pipeline (Ingestion -> Prédiction). La base de données SQLite (`predicafrika.db`) sera générée automatiquement à la racine de `ai_pipeline`.

---

## 🛠️ Comment contribuer au Pipeline ?

### Ajouter une nouvelle source de données (Ingestion)
1. Créez un nouveau fichier dans `ingestion/` (ex: `crypto.py`).
2. Implémentez votre fonction de requêtage (ex: via l'API CoinGecko).
3. Importez cette fonction dans `scheduler.py` et ajoutez-la à la fonction `pipeline_job()`.

### Modifier le modèle prédictif (IA)
Le moteur d'IA se trouve dans `models/predictor.py`.
Actuellement, c'est un mock qui simule l'inférence. Pour intégrer un vrai modèle :
1. Entraînez votre modèle séparément (ex: un `.pkl` ou un `.joblib` généré par XGBoost).
2. Chargez ce modèle au démarrage dans `predictor.py`.
3. Remplacez le mock `random.choice(outcomes)` par `model.predict()`.
4. Intégrez l'explainer SHAP pour générer le champ texte `explanation`.

### Configuration de la base de données (Passage en Prod)
Dans `database/database.py`, modifiez l'URL de connexion pour pointer vers PostgreSQL :
```python
SQLALCHEMY_DATABASE_URL = "postgresql://mon_user:mon_mot_de_passe@localhost/predicafrika"
```
*Note : Assurez-vous d'avoir installé `psycopg2-binary` via pip.*

---

## 🔌 API Endpoint principal

### `GET /api/predictions`

Retourne les dernières prédictions générées par l'IA.

**Paramètres optionnels :**
- `domain` (str) : Filtrer par domaine (ex: `football`, `basketball`, `crypto`).

**Exemple de réponse JSON :**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "domain": "football",
      "home_team": "Paris SG",
      "away_team": "Lyon",
      "odds": {
        "home": 1.45,
        "draw": 3.5,
        "away": 4.1
      },
      "prediction": {
        "winner": "home",
        "confidence": 0.75,
        "is_value_bet": true,
        "explanation": "Paris SG a remporté 4 de ses 5 dernières rencontres à domicile."
      }
    }
  ]
}
```

---

*Développé pour l'équipe Predicafrika.*
