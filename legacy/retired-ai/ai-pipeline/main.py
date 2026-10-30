from fastapi import FastAPI, Depends, Request, HTTPException
from sqlalchemy.orm import Session
from fastapi.middleware.cors import CORSMiddleware
from database.database import get_db
from database.models import Match, Prediction, GenericMarket
from scheduler import start_scheduler
from contextlib import asynccontextmanager
import json
import os
import hmac
from datetime import datetime, timezone
from ipaddress import ip_address
from market_generator.news_scraper import fetch_trending_topics
from market_generator.market_builder import build_market_from_topic
from market_generator.publisher import publish_market

@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: Start the background scheduler
    print("Démarrage de l'API et du Scheduler...")
    models_scheduler = None
    if os.getenv("AI_PIPELINE_SCHEDULER_ENABLED", "true").lower() == "true":
        models_scheduler = start_scheduler()
    else:
        from database.database import Base, engine
        Base.metadata.create_all(bind=engine)
    try:
        yield
    finally:
        if models_scheduler:
            models_scheduler.shutdown(wait=False)
        print("Arrêt de l'API...")

app = FastAPI(title="Predicafrika AI API", lifespan=lifespan)

# Allow CORS for the frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=[origin.strip() for origin in os.getenv("AI_PIPELINE_CORS_ORIGINS", "").split(",") if origin.strip()],
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["Content-Type", "X-Pipeline-Token"],
)

def require_generation_access(request: Request):
    expected = os.getenv("AI_PIPELINE_TOKEN", "")
    if expected:
        supplied = request.headers.get("X-Pipeline-Token", "")
        if not hmac.compare_digest(supplied.encode(), expected.encode()):
            raise HTTPException(status_code=403, detail="Generation access denied")
        return
    # Preserve local demo setup. Never trust forwarded headers here. A reverse
    # proxy can appear local: configure a token before placing one in front.
    try:
        local = request.client is not None and ip_address(request.client.host).is_loopback
    except ValueError:
        local = False
    if not local:
        raise HTTPException(status_code=403, detail="Configure AI_PIPELINE_TOKEN for non-loopback access")


def as_utc(value):
    if value is None:
        return None
    return value.replace(tzinfo=timezone.utc) if value.tzinfo is None else value.astimezone(timezone.utc)


@app.get("/")
def read_root():
    return {"message": "Bienvenue sur l'API IA de Predicafrika"}

@app.get("/api/predictions")
def get_latest_predictions(domain: str = None, db: Session = Depends(get_db)):
    """
    Retourne les derniers matchs avec leurs prédictions IA.
    """
    query = db.query(Match, Prediction).join(Prediction, Match.id == Prediction.match_id).filter(Match.status == "upcoming", Match.match_date > datetime.now(timezone.utc).replace(tzinfo=None))
    
    if domain:
        query = query.filter(Match.domain == domain)
        
    results = query.order_by(Match.match_date.asc()).limit(10).all()
    
    response = []
    for match, prediction in results:
        response.append({
            "id": match.id,
            "domain": match.domain,
            "match_date": as_utc(match.match_date),
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
                "explanation": "Simulation aléatoire non évaluée. " + (prediction.explanation or ""),
                "method": "random_demo"
            }
        })
        
    return {"status": "success", "data": response, "simulation": True, "source": "random_demo"}

@app.get("/api/markets")
def get_latest_markets(domain: str = None, db: Session = Depends(get_db)):
    """
    Retourne les derniers marchés prédictifs génériques (Crypto, Politique...).
    """
    query = db.query(GenericMarket).filter(GenericMarket.status == "active", GenericMarket.expiry_date > datetime.now(timezone.utc).replace(tzinfo=None))
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
            "expiry": as_utc(market.expiry_date),
            "generated_at": as_utc(market.created_at),
            "simulation": True,
            "totalPool": market.total_pool,
            "status": market.status
        })
    return {"status": "success", "data": response, "simulation": True, "source": "random_demo"}

@app.post("/api/markets/generate")
def generate_markets(db: Session = Depends(get_db), access=Depends(require_generation_access)):
    """
    Déclenche manuellement le générateur de marchés.
    """
    try:
        topics = fetch_trending_topics()
        for topic in topics:
            market_data = build_market_from_topic(topic)
            publish_market(db, market_data)
        # One generation request is a single batch, never a partial commit.
        db.commit()
    except Exception:
        db.rollback()
        raise HTTPException(status_code=503, detail="Simulated generation failed; batch rolled back") from None
    return {"status": "success", "message": f"{len(topics)} suggestions simulées générées.", "simulation": True}
