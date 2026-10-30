# Installation PWA

Le manifeste est servi à `/manifest.webmanifest`, le service worker à `/sw.js`.
Publier les fichiers de `public/` (dont `icons/` et `offline.html`) et le build Vite
avec `npm run build`. Le domaine de production doit être en HTTPS. Localhost est
accepté pour les essais. Ne pas servir `sw.js` avec un cache HTTP immuable ; son
contenu doit pouvoir être revérifié lors des prochaines visites.

Le bouton « Installer l’application » est accessible dans le menu mobile et le
pied de page, ainsi que sur la page d’accueil publique. Il utilise la demande
native quand le navigateur l’offre et affiche les instructions sinon. L’installation
reste un choix de l’utilisateur. Le bouton disparaît en mode autonome et après
l’événement d’installation.

Les icônes PNG sont dérivées de `public/PA_Favicon_512x512.svg` avec
`node scripts/generate-pwa-icons.mjs`. L’icône maskable inclut une marge de sécurité.
L’icône Apple mesure 180 × 180 pixels.

Le service worker ne met en cache que la page hors connexion et ses icônes publiques.
Les pages authentifiées, soldes, marchés, réponses Inertia, requêtes POST et appels
prestataire ne sont jamais mis en cache par ce worker. Il ne rejoue aucune opération.
Sans réseau, une navigation complète affiche `offline.html` ; l’application déjà
ouverte conserve sa gestion de perte de connexion habituelle. Une erreur HTTP
serveur n’est pas remplacée par une fausse page hors connexion.

Pour actualiser les ressources publiques mises en cache, incrémenter le nom
`predicafrica-pwa-v1` dans `public/sw.js`. Seuls les anciens caches portant ce préfixe
sont supprimés. Aucun rechargement forcé n’interrompt une transaction en cours.

Validation : `node tests/browser/wallet-transfers.mjs` vérifie le manifeste via Chrome,
les icônes, l’enregistrement et le contrôle du worker, le prompt simulé, les
instructions alternatives, le masquage du bouton après installation, le contenu du
cache, la navigation hors connexion et le retour en ligne. Le test n’installe rien
sur l’ordinateur. Une vérification sur appareils réels reste nécessaire pour valider
les écrans système d’installation iOS et Android.

Référence : [installation PWA (MDN)](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Guides/Making_PWAs_installable).

## Invitation automatique

Une invitation apparaît après environ six secondes, hors mode autonome. Elle attend
la fin de l’introduction, évite les boîtes de dialogue ouvertes et les champs ou
formulaires utilisés. Fermer l’invitation reporte la prochaine proposition de sept
jours sur ce navigateur ; le bouton manuel reste accessible.

Le guide distingue iPhone/iPad, Android, Safari sur Mac et les navigateurs de bureau.
Le prompt natif est proposé lorsqu’il est disponible, sinon les étapes manuelles
sont affichées. L’événement `appinstalled`, le mode autonome et l’action
« Je l’ai déjà installée » mémorisent l’installation sur ce navigateur.

La détection d’une installation depuis un onglet ordinaire n’est pas universelle,
notamment sur iOS : l’action de confirmation manuelle évite de proposer à nouveau
l’installation lorsque le navigateur ne peut pas la signaler. Une nouvelle demande
native d’installation permet de reconnaître une désinstallation sur les navigateurs
compatibles. Voir [les limites de détection (MDN)](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/How_to/Create_a_standalone_app)
et [les instructions iPhone (Apple)](https://support.apple.com/fr-ne/guide/iphone/iph42ab2f3a7/27/ios/27).
