import random
from datetime import datetime, timedelta
from sqlalchemy.orm import Session
from database.models import Match

def fetch_and_save_upcoming_matches(db: Session):
    """
    Mock function to simulate fetching data from an external API (like API-Football).
    In a real scenario, this would use `requests.get("api_url", headers=...)`
    """
    print("[Ingestion] Récupération des nouveaux matchs de football...")
    
    teams = ["Paris SG", "Marseille", "Lyon", "Monaco", "Lille", "Rennes"]
    
    new_matches = []
    # Génère 3 matchs factices pour aujourd'hui
    for _ in range(3):
        home = random.choice(teams)
        away = random.choice([t for t in teams if t != home])
        
        # Simuler les cotes des bookmakers (implied probability sum > 1.0 due to margin)
        home_odds = round(random.uniform(1.2, 4.0), 2)
        away_odds = round(random.uniform(1.5, 5.0), 2)
        draw_odds = round(random.uniform(2.5, 4.0), 2)
        
        match = Match(
            domain="football",
            home_team=home,
            away_team=away,
            match_date=datetime.utcnow() + timedelta(hours=random.randint(1, 48)),
            status="upcoming",
            home_odds=home_odds,
            draw_odds=draw_odds,
            away_odds=away_odds
        )
        new_matches.append(match)
        db.add(match)
        
    db.commit()
    print(f"[Ingestion] {len(new_matches)} matchs ajoutés en base de données.")
    return new_matches
