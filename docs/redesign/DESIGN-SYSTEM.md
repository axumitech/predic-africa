# Design system PredicAfrica

## Fondations

`resources/css/tokens.css` : palette navy/or, couleurs sémantiques, typographie Inter/Poppins avec repli système, espacements 4/8 px, rayons, ombres et focus. Aucun ajout de dépendance ou de Tailwind.
`components.css` : composants `ds-*`, BEM pour éléments/variantes. `landing.css` : espace public `pa-*`. `workspace.css` : adaptations de l’espace existant `p-studio`.

## API des composants

Les composants React sont exportés par `resources/js/components/ui.tsx`, sauf MarketCard (module plateforme).

| Composants | Variantes et comportement |
| --- | --- |
| Button / IconButton | primary, secondary, quiet, danger ; loading, disabled ; nom accessible obligatoire pour IconButton |
| Input / SearchInput | label, hint, error ; association id/aria-describedby, invalidité explicite |
| PasswordInput | révélation contrôlée ; état pressed ; autocomplete fourni par le consommateur |
| Select / Textarea | label réel ; attributs HTML natifs transmis |
| Checkbox / Radio / Switch | label visible, focus natif ; Switch expose le rôle switch |
| Tabs | groupe de filtres à boutons pressed ; pas un faux tablist sans panneaux |
| Badge / StatusBadge | neutral, success, danger, warning, info ; état toujours nommé textuellement |
| Tooltip / Dropdown | affichage au focus/survol ; dropdown natif details/summary |
| Modal / Drawer | dialog natif modal, focus piégé par le navigateur, Escape, restauration du focus, blocage du scroll ; modal/drawer/sheet |
| ConfirmationModal | action primaire/destructive, récapitulatif, traitement en cours ; retour sélectionné par défaut |
| Alert / Toast | info/error/success/warning ; alert pour erreur, status pour information |
| Skeleton | alternative « chargement », mouvement réduit pris en compte |
| EmptyState / ErrorState | titre, description, action/retry facultatifs |
| Pagination | liens Inertia, page courante et dernière page |
| DataTable | colonnes génériques, caption, clés stables ; cartes mobiles avec libellés |
| StatCard / WalletCard | données fournies par le parent ; aucune statistique générée |
| MarketCard | marché issu du serveur, cote fixe et probabilité implicite normalisée ; favori local par compte |
| PositionCard / TransactionItem | composition réutilisable, état textuel, ligne cliquable facultative |
| ProbabilityBar | valeur dynamique bornée ; alternative textuelle Oui/Non |
| TrendChart | valeurs réelles fournies, courbe et points, détail texte, état sans données |

`ActionForm` conserve useForm/Inertia. Il associe les erreurs aux champs, replace le focus sur le premier champ invalide et empêche les doubles soumissions pendant le traitement. Les mots de passe ne figurent jamais dans le récapitulatif. Les confirmations de dépôt, retrait, mise, proposition, modération et résolution précèdent le POST ; les validations serveur restent autoritaires.

`ConnectionStatus` annonce connexion interrompue et attente supérieure à quatre secondes. Il ne relance jamais automatiquement une mutation. `RenderBoundary` remplace une erreur de rendu par un écran de récupération.

## Données et localisation

`content/product.ts` centralise les règles vérifiées du produit et la FAQ en FR/EN. `content/landing.ts` contient les textes principaux de l’accueil. `content/locale.tsx` fournit le contexte extensible de langue et sa préférence locale. Les pages privées et légales restent principalement françaises : leur traduction complète est une étape distincte.

Le graphique du dashboard représente les montants d’opérations par jour (7 jours, agrégation serveur), pas la rentabilité. La courbe de probabilité demandée n’est pas inventée : aucune série historique de cotes n’existe dans ce moteur à cote fixe.

## Accessibilité et vérification

Boutons de composants à 44 px minimum, champs mobiles à 16 px, focus visible, textes d’état au-delà de la couleur, respects des safe areas et de prefers-reduced-motion. Vérifications de rendu et navigation effectuées ; cela ne constitue pas une certification WCAG exhaustive ni un audit avec lecteur d’écran.
