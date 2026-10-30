# Déploiement du 30 octobre 2026

Application mise à jour dans `/var/www/predicafrica` pour `https://predicafrica.com`.
Les cinq migrations du 29 octobre ont été appliquées après validation sur une
copie PostgreSQL isolée. Les trois utilisateurs existants sont conservés ; huit
enregistrements de démonstration sont conservés dans `retired_records`.

## Configuration appliquée

- Dépôts activés, retraits désactivés, identifiant marchand facultatif.
- 24 tuples pays/opérateur/devise issus de la liste fournie par le propriétaire.
  Cette configuration locale ne prouve pas les autorisations côté fournisseur.
- Téléphones internationaux : GAB +241/8 chiffres, CMR +237/9, COG +242/9,
  BEN +229/10, CIV +225/10, SEN +221/9, COD +243/9, KEN +254/9,
  RWA +250/9, UGA +256/9, ZMB +260/9, SLE +232/8.
  Le préfixe national 01 du Bénin est conservé après +229 ; le zéro national du
  Gabon est retiré après +241, conformément aux plans de numérotation.
- Queue `database`, worker `predicafrica-payments.service`.
- Timer `predicafrica-scheduler.timer` chaque minute ; le scheduler vérifie la
  récupération FX chaque heure, avec au maximum un appel fournisseur par 24 h.
- Taux quotidiens reçus avec date source `2026-10-29T23:59:59Z`, acceptés moins
  de 48 h. La licence d'utilisation commerciale reste celle de l'abonnement
  fournisseur ; changer la cadence ne change pas les conditions de l'offre.
- `APP_DEBUG=false`, URL HTTPS, horloge synchronisée et limite Nginx du webhook
  fixée à 16 Kio. APP_KEY et clés fournisseurs conservées à l'identique.

## Vérifications

Suite locale : 211 tests, 1 806 assertions. Copie PostgreSQL : migrations,
webhook signé sans identifiant marchand, crédit CDF après GET, idempotence,
participations/règlement XAF et CDF et rapprochements validés.

Production : pages publiques et build frontend HTTP 200 ; webhook non signé
et JSON signé malformé HTTP 400, corps trop volumineux HTTP 413. Formulaire
portefeuille actif pour les trois comptes existants, encore en XAF sans pays
historique attribué (six opérateurs XAF proposés). Les nouvelles inscriptions
choisissent leur pays et leur devise. Aucun pays ou changement de devise n'a été
imposé aux anciens utilisateurs.

`payments:health` au vert, aucun job en échec, aucune anomalie et aucun écart
comptable. Aucun paiement réel créé durant le déploiement. Le GET avec une
référence fournisseur inconnue répond HTTP 404 ; ce contrôle ne démontre pas
la validation KYC ou les autorisations de chaque opérateur côté NDSGPay.

## Exploitation et sauvegarde

Sauvegarde privée serveur :
`/var/backups/predicafrica-release-f527ae823c2793be` (répertoire root 0700).
Contient le code précédent, l'environnement, la configuration Nginx et deux
exports PostgreSQL, dont `database.at-cutover.dump` réalisé en maintenance.
Ne jamais publier ce dossier ou ses fichiers : ils contiennent des secrets.

Les bases de validation et leurs fichiers `.env` temporaires ont été supprimés.
Les services sont activés au démarrage. Après une modification de configuration :

```sh
cd /var/www/predicafrica
sudo -u deploy php artisan config:cache
sudo systemctl restart predicafrica-payments.service
sudo -u deploy php artisan payments:health
```

Pour suspendre de nouvelles recharges, passer `NDSGPAY_ENABLED=false` puis
reconstruire le cache. Ne pas restaurer une ancienne base après un dépôt réel
sans rapprochement : une restauration pourrait effacer des confirmations ou
des crédits reçus après la sauvegarde. Les migrations monétaires ne proposent
pas de rollback automatique.
