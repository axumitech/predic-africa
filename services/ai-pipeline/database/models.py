from sqlalchemy import Column, Integer, String, Float, DateTime, Boolean, ForeignKey
from sqlalchemy.orm import relationship
from datetime import datetime
from .database import Base

class Match(Base):
    __tablename__ = "matches"

    id = Column(Integer, primary_key=True, index=True)
    domain = Column(String, index=True) # e.g., "football", "basketball", "crypto"
    home_team = Column(String, index=True)
    away_team = Column(String, index=True)
    match_date = Column(DateTime)
    status = Column(String, default="upcoming") # upcoming, finished, live
    
    # Odds from bookmakers
    home_odds = Column(Float, nullable=True)
    draw_odds = Column(Float, nullable=True)
    away_odds = Column(Float, nullable=True)
    
    # Results (populated after match finishes)
    home_score = Column(Integer, nullable=True)
    away_score = Column(Integer, nullable=True)
    
    predictions = relationship("Prediction", back_populates="match")

class Prediction(Base):
    __tablename__ = "predictions"

    id = Column(Integer, primary_key=True, index=True)
    match_id = Column(Integer, ForeignKey("matches.id"))
    created_at = Column(DateTime, default=datetime.utcnow)
    
    # Our AI outputs
    predicted_winner = Column(String) # home, away, draw
    confidence = Column(Float) # e.g., 0.65 for 65%
    
    # Value Bet Detection
    is_value_bet = Column(Boolean, default=False)
    
    # XAI (Explainability)
    explanation = Column(String) # e.g., "Home team has won last 5 matches."
    
    match = relationship("Match", back_populates="predictions")

class GenericMarket(Base):
    __tablename__ = "generic_markets"

    id = Column(Integer, primary_key=True, index=True)
    title = Column(String, index=True)
    description = Column(String)
    category = Column(String, index=True) # politics, crypto, entertainment, economy
    
    # Store options and odds as JSON strings for simplicity in SQLite
    # e.g., options: '["Oui", "Non"]', odds: '[1.90, 1.90]'
    options_json = Column(String) 
    odds_json = Column(String)
    
    expiry_date = Column(DateTime)
    status = Column(String, default="active")
    total_pool = Column(Float, default=0.0)
    created_at = Column(DateTime, default=datetime.utcnow)

