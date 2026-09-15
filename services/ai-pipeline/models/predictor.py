import random
from sqlalchemy.orm import Session
from database.models import Match, Prediction

def generate_predictions(db: Session):
    """
    Simule l'inférence d'un modèle d'IA (ex: RandomForest/XGBoost) sur les matchs
    qui n'ont pas encore de prédictions.
    """
    print("[AI Engine] Lancement de l'inférence sur les nouveaux matchs...")
    
    # Récupérer les matchs sans prédiction
    matches_without_predictions = db.query(Match).outerjoin(Prediction).filter(Prediction.id == None).all()
    
    if not matches_without_predictions:
        print("[AI Engine] Aucun nouveau match à prédire.")
        return []
        
    new_preds = []
    for match in matches_without_predictions:
        # 1. Prédiction (Mock d'un modèle de ML)
        outcomes = ["home", "away", "draw"]
        predicted_winner = random.choice(outcomes)
        confidence = round(random.uniform(0.40, 0.85), 2)
        
        # 2. Détection de Value Bet
        # Comparaison de la proba de notre IA vs proba implicite du bookmaker
        # Proba implicite = 1 / cote
        is_value_bet = False
        implied_prob = 0.0
        
        if predicted_winner == "home" and match.home_odds:
            implied_prob = 1 / match.home_odds
        elif predicted_winner == "away" and match.away_odds:
            implied_prob = 1 / match.away_odds
        elif predicted_winner == "draw" and match.draw_odds:
            implied_prob = 1 / match.draw_odds
            
        if confidence > implied_prob * 1.10: # On considère que c'est de la valeur si on a 10% d'edge
            is_value_bet = True
            
        # 3. Explicabilité (Mock de SHAP values)
        explanations = [
            f"Avantage historique : {match.home_team} a remporté 4 de ses 5 dernières rencontres à domicile.",
            f"Forme récente : Les statistiques offensives de {match.away_team} sont en hausse (xG +0.3).",
            f"Blessures : Absence d'un joueur clé chez {match.home_team if predicted_winner == 'away' else match.away_team}.",
            f"Analyse tactique : Le style de jeu de {match.home_team} pose souvent problème à la défense de {match.away_team}."
        ]
        
        prediction = Prediction(
            match_id=match.id,
            predicted_winner=predicted_winner,
            confidence=confidence,
            is_value_bet=is_value_bet,
            explanation=random.choice(explanations)
        )
        
        db.add(prediction)
        new_preds.append(prediction)
        
    db.commit()
    print(f"[AI Engine] {len(new_preds)} prédictions générées et enregistrées.")
    return new_preds
