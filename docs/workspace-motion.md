# Entrée et navigation de l’espace connecté

L’entrée affiche une séquence bleu nuit et or : révélation du logo, halos,
orbite, grille en perspective et traits lumineux. Elle dure environ 2,3 secondes,
se ferme avec Échap ou « Passer l’animation » et ne se rejoue pas entre les pages
Inertia. Un rechargement complet la rejoue. La préférence système de réduction
des animations désactive les mouvements et raccourcit l’entrée à environ 220 ms.
Le contenu derrière l’entrée est inerte ; le focus et le défilement sont restaurés
à sa fermeture.

Sur ordinateur, la sidebar passe de 268 à 88 px en 400 ms. Le logo horizontal
laisse place au symbole PA ; les libellés s’effacent et les icônes restent
accessibles. Le choix est conservé localement par compte. Les bulles de libellé
apparaissent au survol et au focus clavier hors de la zone défilante. Échap,
le défilement ou la navigation les ferment. Profil et déconnexion restent présents
dans les deux états. Sur mobile, le tiroir de navigation existant est conservé.

Validation : `npm run test:motion` utilise Chrome et une base SQLite temporaire,
sans appel au prestataire de paiement. Il vérifie la durée, le passage immédiat,
le focus, la préférence de mouvement réduit, l’interpolation de largeur, les
bulles, la persistance et les espaces trader/admin sur ordinateur et mobile.
Les captures sont générées dans `test-results/motion/`.
