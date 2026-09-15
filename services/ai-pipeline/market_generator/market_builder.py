import random
from datetime import datetime, timedelta

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
        "expiry_date": datetime.utcnow() + timedelta(days=random.randint(7, 30)),
        "total_pool": random.randint(10000, 500000)
    }
    
    if cat == "crypto":
        market["title"] = "Le Bitcoin atteindra-t-il les 100k$ d'ici la fin de ce mois ?"
        market["description"] = f"Basé sur l'actualité : '{topic['headline']}'. Ce marché se résout à 'Oui' si le BTC/USD dépasse 100,000$ sur Binance."
    elif cat == "politics":
        market["title"] = "Le candidat Démocrate remportera-t-il la Maison Blanche en 2028 ?"
        market["description"] = f"Basé sur l'actualité : '{topic['headline']}'. Résolution selon les résultats officiels des élections."
    elif cat == "cinema":
        market["title"] = "Le film de C. Nolan remportera-t-il l'Oscar du Meilleur Film ?"
        market["description"] = f"Basé sur l'actualité : '{topic['headline']}'. Résolution le soir de la cérémonie des Oscars."
    else:
        market["title"] = "La Réserve Fédérale (FED) annoncera-t-il une baisse de taux ?"
        market["description"] = f"Basé sur l'actualité : '{topic['headline']}'. Résolution le jour de la prochaine annonce du FOMC."

    return market
