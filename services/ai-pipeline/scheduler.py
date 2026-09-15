import time
from apscheduler.schedulers.background import BackgroundScheduler
from database.database import SessionLocal, engine, Base
from database import models
from ingestion.football import fetch_and_save_upcoming_matches
from models.predictor import generate_predictions

# Create tables if they don't exist
models.Base.metadata.create_all(bind=engine)

def pipeline_job():
    print(f"\n[{time.strftime('%Y-%m-%d %H:%M:%S')}] --- DÉBUT DU PIPELINE ---")
    db = SessionLocal()
    try:
        # 1. Fetch new data
        fetch_and_save_upcoming_matches(db)
        
        # 2. Run AI models on new data
        generate_predictions(db)
        
        print(f"[{time.strftime('%Y-%m-%d %H:%M:%S')}] --- FIN DU PIPELINE ---\n")
    except Exception as e:
        print(f"[ERREUR PIPELINE] {e}")
    finally:
        db.close()

def start_scheduler():
    scheduler = BackgroundScheduler()
    # En production, cela serait "hours=1". 
    # Pour le test, on va mettre "minutes=1" ou on exécute manuellement une fois au démarrage.
    scheduler.add_job(pipeline_job, 'interval', minutes=60)
    scheduler.start()
    print("Planificateur (Scheduler) démarré. Le pipeline s'exécutera toutes les heures.")
    
    # Exécuter une fois immédiatement au démarrage
    pipeline_job()

if __name__ == "__main__":
    start_scheduler()
    
    # Garder le script en vie si on le lance directement
    try:
        while True:
            time.sleep(2)
    except (KeyboardInterrupt, SystemExit):
        pass
