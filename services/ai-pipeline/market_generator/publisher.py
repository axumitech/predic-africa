import json
from sqlalchemy.orm import Session
from database.models import GenericMarket

def publish_market(db: Session, market_data: dict):
    """
    Enregistre le marché généré dans la base de données.
    """
    print(f"[Publisher] Publication du marché : {market_data['title']}")
    
    market = GenericMarket(
        title=market_data["title"],
        description=market_data["description"],
        category=market_data["category"],
        options_json=json.dumps(market_data["options"]),
        odds_json=json.dumps(market_data["initial_odds"]),
        expiry_date=market_data["expiry_date"],
        status="active",
        total_pool=market_data["total_pool"]
    )
    
    db.add(market)
    db.commit()
    db.refresh(market)
    return market
