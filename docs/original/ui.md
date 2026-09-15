# Document de Spécification Design UX/UI : PredicAfrika

## 1. Philosophie Design & Fondations Esthétiques
**PredicAfrika** s'appuie sur une esthétique moderne et dynamique combinant un mode sombre immersif (*Dark Mode*), des effets de glassmorphisme, des accents néon (vert émeraude `#10B981` et violet vibrant `#8B5CF6`) et une typographie expressive. Le design est pensé en approche *Mobile-First*, garantissant une fluidité parfaite sur smartphones tout en offrant un dashboard riche sur écran large.

---

## 2. Design Tokens & Architecture de Thème

### 2.1 Palette de Couleurs (Variables Personnalisées CSS)
* **Couleur de Fond Principale** : Nuit Sombre (`--bg-primary: #0B0F19`, `--bg-secondary: #111827`, `--bg-card: #1F2937`)
* **Accents Branding & IA** : Vert Émeraude / Value Bet (`--accent-green: #10B981`), Violet Neon / Intelligence IA (`--accent-purple: #8B5CF6`), Or / Gains (`--accent-gold: #F59E0B`)
* **Hiérarchie Textuelle** : Blanc Pur (`--text-primary: #F9FAFB`), Gris Muted (`--text-secondary: #9CA3AF`), Gris Subtil (`--text-dark: #6B7280`)
* **Badges & Statuts** :
  * Value Bet : Fond Vert Translucide (`background: rgba(16, 185, 129, 0.15); color: #10B981;`)
  * En Cours : Bleu Info (`--info: #3B82F6`)
  * Clôturé / Terminé : Gris Neutre (`--muted: #4B5563`)
* **Bordures & Ombres** :
  * `--border-subtle: rgba(255, 255, 255, 0.08)`
  * `--shadow-card: 0 10px 25px -5px rgba(0, 0, 0, 0.5)`
  * `--radius-card: 16px`, `--radius-btn: 10px`

### 2.2 Typographie
* **Polices Google Fonts** : 
  * Titres & En-têtes : **Outfit** (Weights: 600, 700, 800)
  * Corps de texte & Données : **Inter** (Weights: 400, 500, 600)
* **Échelle Typographique** :
  * Hero Title : `2.5rem` (`40px`), `font-weight: 800`, gradient de texte
  * Titres de Section / Carte : `1.25rem` (`20px`), `font-weight: 700`
  * Cotes & Chiffres Clés : `1.125rem` (`18px`), `font-weight: 700`, police monospace ou tabulaire
  * Corps de Texte : `0.9375rem` (`15px`), `line-height: 1.6`

---

## 3. Layouts UI Principaux & Spécifications des Composants

### 3.1 Header Public & Sélecteur Régional
* **Barre Supérieure Flottante** : Logo avec icône SVG vectorielle `PredicAfrica`, navigation par ancres, boutons de sélection de langue (`FR`/`EN`) et sélecteur de pays avec drapeaux interactifs.
* **Bouton d'Accès Rapide** : CTI principal *Accéder à la Bourse* et bouton secondaire *Backoffice Admin*.

### 3.2 Landing Hero & Cartes d'Illustration en Relief
* **Bannière Principale** : Titre accrocheur avec badge *Bourse d'Opinions P2P*, métriques clés en temps réel (Volume échangé en XOF, Nombre de pays, Temps de rafraîchissement).
* **Mock Cards de Prédiction** : Cartes visuelles animées présentant des exemples de marchés d'actualité (ex: *Présidentielle Sénégal*, *Grammy Award pour Burna Boy*) avec boutons d'action rapide sur les cotes.

### 3.3 Dashboard Bourse & Grille de Cartes de Marché (*Market Cards*)
* **Barre de Filtre par Catégorie** : Onglets horizontaux (*Tout*, *Football*, *Politique*, *Musique*, *Crypto*, *Économie*).
* **Anatomie de la Carte de Marché (Market Card)** :
  1. En-tête : Tag de catégorie coloré, indicateur de temps restant avant expiration.
  2. Corps : Intitulé clair du marché ou de la rencontre sportive, illustration ou badges d'équipes.
  3. Bloc Cotes & Boutons d'Achat : Boutons *Oui @ 1.85* et *Non @ 2.10* avec effet hover lumineux.
  4. Badge Value Bet IA (si applicable) : Indicateur étincelant avec pourcentage d'edge de l'IA (ex: `⚡ Value Bet IA (+12% edge)`).
  5. Pied de Carte : Volume total investi dans la pool (ex: `Pool: 450,000 XOF`).

### 3.4 Modal d'Explicabilité IA (XAI Drawer / Modal)
* **Composant Explicatif** : Clic sur le badge Value Bet ouvrant un panneau latéral ou un modal détaillant l'analyse du modèle ML :
  * Pourcentage de confiance de l'IA.
  * Facteurs explicatifs SHAP sous forme de puces informatives (ex: *Avantage domicile (+15%)*, *Forme récente des joueurs*, *Historique des confrontations*).

### 3.5 Modal d'Ordre de Bourse & Paiement Mobile Money
* **Interface de Transaction** :
  * Choix du montant de l'investissement (boutons rapides: `1 000`, `5 000`, `25 000` XOF).
  * Calculateur dynamique du rendement potentiel.
  * Choix de l'opérateur Mobile Money (Orange Money, Wave, MTN MoMo, Airtel, Moov) avec saisie du numéro de téléphone et bouton de confirmation OTP.

### 3.6 Interface Backoffice Admin & Table de Modération
* **Tableau de Bord de Modération** : Vue sous forme de tableau répertoriant les marchés générés par l'IA en attente de validation (*Accepter* / *Rejeter*).
* **Formulaire de Clôture** : Interface de sélection du résultat gagnant d'un marché expiré déclenchant le paiement automatique.
