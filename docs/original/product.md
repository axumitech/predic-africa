# Document des Exigences Produit (PRD) : Plateforme PredicAfrika

## 1. Résumé Exécutif
**PredicAfrika** est la première plateforme de bourse de prédictions et d'opinion P2P (Peer-to-Peer) dédiée au marché africain et à sa diaspora. Combinant la puissance des marchés financiers décentralisés d'opinion et l'intelligence artificielle explicable (XAI), PredicAfrika permet aux utilisateurs de prendre position sur des événements réels (politique, sports, musique, cinéma, économie locale, crypto), de négocier des parts de prédiction et de liquider leurs gains de manière instantanée via les réseaux de Mobile Money (Orange Money, MTN MoMo, Wave, Airtel Money, Moov). 

La plateforme intègre un moteur IA autonome (FastAPI & Scikit-Learn/XGBoost) qui ingère les statistiques sportives et les tendances d'actualités pour détecter les opportunités de valeur (*Value Bets*) et générer automatiquement de nouveaux marchés prédictifs. Un backoffice administrateur assure la modération, la résolution des résultats et la gestion des liquidités.

---

## 2. Personas Utilisateurs Cibles

### 2.1 Le Trader d'Opinions / Utilisateur Grand Public (Afrique & Diaspora)
* **Profil Démographique** : Jeunes adultes, passionnés de sport, de politique africaine ou de pop-culture, résidant en Afrique de l'Ouest/Centrale ou dans la diaspora.
* **Objectif** : Monétiser leurs convictions, négocier sur des marchés d'actualité locale ou sportive, et profiter des suggestions d'opportunités générées par l'IA.
* **Points de Douleur** : Inaccessibilité des bourses traditionnelles ; manque de transparence des bookmakers classiques ; absence d'intégrations fluides avec le Mobile Money local.

### 2.2 Le Backoffice Admin / Modérateur de la Bourse
* **Profil Démographique** : Équipe interne d'opérations financières et de modération de contenu de PredicAfrika.
* **Objectif** : Superviser les marchés actifs, valider les marchés générés par l'IA, arbitrer et résoudre les issues des prédictions (déclaration des gagnants), et superviser le paiement des pools.
* **Points de Contact** : Dashboard Backoffice avec gestion de la file d'attente des marchés, paramétrage des taux de conversion multi-devises et contrôle du pipeline IA.

### 2.3 L'Ingénieur IA / Data Scientist
* **Profil Démographique** : Développeurs Python et spécialistes en Machine Learning.
* **Objectif** : Automatiser l'ingestion de données (API-Football, scraping de news), affiner la précision des prédictions (RandomForest/XGBoost), calculer le Value Bet edge et garantir des explications lisibles via SHAP.

---

## 3. Modules Fonctionnels & Exigences Clés

### 3.1 Bourse d'Opinions P2P & Interface Client
* **Sélecteur Régional & Multi-Devises** : Adaptation instantanée des devises (XOF, XAF, GHS, KES, etc.) et des modes de paiement selon le pays sélectionné.
* **Catalogue de Marchés d'Opinion** : Catégorisation dynamique (Sport, Politique, Musique, Économie, Crypto). Affichage des cotes temps réel (*Oui/Non* ou *Options Multiples*), du volume d'échange et de la date d'expiration.
* **Passage d'Ordre & Carnet de Position** : Interface d'achat/vente de parts de prédiction avec calcul en temps réel du gain potentiel.
* **Portefeuille Mobile Money** : Crédit et retrait de fonds instantanés par SMS/OTP via Orange Money, MTN MoMo, Wave, Airtel Money et Moov.

### 3.2 Moteur de Inférence IA & Value Bet Radar
* **Scraping & Ingestion Automatisée** : Collecte périodique des actualités et statistiques (via `APScheduler`).
* **Détection de Value Bet** : Comparaison entre la probabilité calculée par le modèle IA et la probabilité implicite des cotes des bookmakers (définition d'un edge $\ge 10\%$).
* **Explicabilité IA (XAI)** : Génération automatique d'un texte d'explication lisible par l'utilisateur explicitant les raisons de la prédiction IA.
* **Générateur Automatique de Marchés** : Transformation autonome des tendances d'actualités (*Trending Topics*) en marchés d'opinion prêts à être cotés.

### 3.3 Backoffice Administrateur
* **Valideur de Marchés IA** : Approbation ou rejet des marchés créés automatiquement par le scraper et le builder d'IA.
* **Module de Résolution & Clôture** : Saisie du résultat officiel d'un marché clôturé entraînant le calcul et la distribution automatique des pools aux gagnants.
* **Monitoring du Pipeline IA** : Déclenchement manuel de l'ingestion, réentraînement des modèles et consultation des logs du serveur FastAPI.

---

## 4. Métriques de Succès & Indicateurs Clés de Performance (KPIs)
* **Volume Global de Négociation (GMV)** : >50 Millions XOF échangés mensuellement sur la bourse.
* **Précision du Modèle IA** : >68% de taux de réussite sur les prédictions sportives recommandées.
* **Taux de Détection Value Bet** : Au moins 15% des marchés sportifs identifiés comme présentant un avantage de côte positif pour l'utilisateur.
* **Temps de Transaction Mobile Money** : <20 secondes pour le traitement d'un dépôt ou d'un retrait.
