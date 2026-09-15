from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker

# Pour le développement local, nous utilisons SQLite.
# En production (ex: sur un VPS), vous pourrez changer cette URL pour PostgreSQL :
# SQLALCHEMY_DATABASE_URL = "postgresql://user:password@localhost/predicafrika"
from pathlib import Path
import os

SQLALCHEMY_DATABASE_URL = os.getenv("AI_DATABASE_URL", "sqlite:///" + str(Path(__file__).resolve().parents[1] / "predicafrika.db"))

engine = create_engine(
    SQLALCHEMY_DATABASE_URL, connect_args={"check_same_thread": False} if SQLALCHEMY_DATABASE_URL.startswith("sqlite") else {} # check_same_thread needed for SQLite
)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

Base = declarative_base()

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
