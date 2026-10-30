import random
from datetime import datetime, timedelta, timezone

def build_market_from_topic(topic):
    """
    Simule l'analyse LLM (ex: ChatGPT / Gemini) qui transforme une actualité
    en un marché prédictif structuré.
    """
    print(f"[Market Builder] Création d'un marché à partir de : {topic['category']}...")
    
    cat = topic['category']
    market = {
        "category": cat,
        "options": ["Oui", "Non"],
        "initial_odds": [round(random.uniform(1.5, 3.5), 2), round(random.uniform(1.5, 3.5), 2)],
        "expiry_date": datetime.now(timezone.utc).replace(tzinfo=None) + timedelta(days=random.randint(7, 30)),
        "total_pool": random.randint(10000, 500000)
    }
    
    labels = {"crypto": "crypto", "politics": "politique", "cinema": "cinéma", "economy": "économie"}
    market["title"] = f"Démo {labels.get(cat, 'société')} : le scénario fictif sera-t-il confirmé ?"
    market["description"] = (
        "Proposition entièrement fictive, sans information réelle ni prédiction évaluée. "
        "Oui si le procès-verbal de simulation établi à l'échéance confirme le scénario ; "
        "Non s'il l'infirme. Source : procès-verbal de test fourni par l'administrateur. "
        "Annuler et rembourser si le test est abandonné. Vérifier ces règles avant publication."
    )
    return market
