import random

def fetch_trending_topics():
    """
    Simule la récupération d'actualités tendances via des APIs (NewsAPI, Twitter API, CoinGecko, etc.)
    """
    print("[News Scraper] Recherche des tendances actuelles...")
    
    # Mock topics
    topics = [
        {"category": "crypto", "headline": "Le Bitcoin s'approche de son plus haut historique, poussé par les annonces institutionnelles."},
        {"category": "politics", "headline": "Les élections présidentielles américaines s'annoncent très serrées selon les derniers sondages."},
        {"category": "cinema", "headline": "Le prochain film de Christopher Nolan très attendu aux Oscars cette année."},
        {"category": "economy", "headline": "La FED pourrait baisser ses taux directeurs lors de la prochaine réunion."}
    ]
    
    # Select 2 random topics to generate markets from
    selected = random.sample(topics, 2)
    print(f"[News Scraper] {len(selected)} tendances sélectionnées.")
    return selected
