from fastapi import FastAPI, Depends
from sqlalchemy.orm import Session
from fastapi.middleware.cors import CORSMiddleware
from database.database import get_db
from database.models import Match, Prediction, GenericMarket
from scheduler import start_scheduler
from contextlib import asynccontextmanager
import json
from market_generator.news_scraper import fetch_trending_topics
from market_generator.market_builder import build_market_from_topic
from market_generator.publisher import publish_market

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: Start the background scheduler
    print("Démarrage de l'API et du Scheduler...")
    start_scheduler()
    yield
    # Shutdown
    print("Arrêt de l'API...")

app = FastAPI(title="Predicafrika AI API", lifespan=lifespan)

# Allow CORS for the frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"], # For dev. In prod, restrict to domain.
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.get("/")
def read_root():
    return {"message": "Bienvenue sur l'API IA de Predicafrika"}

@app.get("/api/predictions")
def get_latest_predictions(domain: str = None, db: Session = Depends(get_db)):
    """
    Retourne les derniers matchs avec leurs prédictions IA.
    """
    query = db.query(Match, Prediction).join(Prediction, Match.id == Prediction.match_id)
    
    if domain:
        query = query.filter(Match.domain == domain)
        
    results = query.order_by(Match.match_date.asc()).limit(10).all()
    
    response = []
    for match, prediction in results:
        response.append({
            "id": match.id,
            "domain": match.domain,
            "match_date": match.match_date,
            "home_team": match.home_team,
            "away_team": match.away_team,
            "odds": {
                "home": match.home_odds,
                "draw": match.draw_odds,
                "away": match.away_odds
            },
            "prediction": {
                "winner": prediction.predicted_winner,
                "confidence": prediction.confidence,
                "is_value_bet": prediction.is_value_bet,
                "explanation": prediction.explanation
            }
        })
        
    return {"status": "success", "data": response}

@app.get("/api/markets")
def get_latest_markets(domain: str = None, db: Session = Depends(get_db)):
    """
    Retourne les derniers marchés prédictifs génériques (Crypto, Politique...).
    """
    query = db.query(GenericMarket)
    if domain:
        query = query.filter(GenericMarket.category == domain)
        
    results = query.order_by(GenericMarket.created_at.desc()).limit(10).all()
    
    response = []
    for market in results:
        response.append({
            "id": market.id,
            "category": market.category,
            "title": market.title,
            "description": market.description,
            "options": json.loads(market.options_json),
            "odds": json.loads(market.odds_json),
            "expiry": market.expiry_date,
            "totalPool": market.total_pool,
            "status": market.status
        })
    return {"status": "success", "data": response}

@app.post("/api/markets/generate")
def generate_markets(db: Session = Depends(get_db)):
    """
    Déclenche manuellement le générateur de marchés.
    """
    topics = fetch_trending_topics()
    new_markets = []
    
    for topic in topics:
        market_data = build_market_from_topic(topic)
        market = publish_market(db, market_data)
        new_markets.append(market)
        
    return {"status": "success", "message": f"{len(new_markets)} marchés générés avec succès."}

