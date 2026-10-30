# Centre de contrôle superadmin

Entrée : `/superadmin`. Le rôle `superadmin` hérite des accès d'administration
et ajoute le pilotage de l'exploitation. Un administrateur ordinaire ne peut
ni ouvrir ce centre ni suspendre un superadmin. L'inscription publique et le
profil ne peuvent pas attribuer de rôle privilégié.

## Commandes disponibles

- Suspension des nouveaux dépôts, avec message visible dans le portefeuille.
- Maintenance de l'application : accès superadmin, connexion, déconnexion et
  webhook conservés ; les autres pages applicatives répondent HTTP 503.
- Activation des opérateurs parmi les 24 codes du catalogue, et plafonds natifs
  des recharges. Activer un code dans l'application ne lui donne pas de droits
  supplémentaires chez NDSGPay.
- Suspension de la récupération automatique des taux ; demande manuelle soumise
  au même délai fournisseur de 24 h.
- File de paiements, anomalies, jobs échoués, dernier signal du scheduler et du
  worker. Les indicateurs sont relus toutes les 30 secondes sur une page visible.
- Programmation du rapprochement existant sans recréer de dépôt fournisseur.
- Journal des modifications, auteur, justification et valeurs avant/après en SQL.

Chaque écriture exige le mot de passe actuel et une justification. La révision
SQL empêche l'écrasement d'une configuration par une page ancienne. Les paramètres
sont lus dans SQL au point d'utilisation : aucun cache de configuration à vider
ou redémarrage de worker nécessaire après un changement dans le centre.

Fermer les dépôts ou un opérateur ne bloque pas la confirmation des transactions
déjà initiées : signature HMAC, GET serveur authentifié, concordance ID/montant/
devise et unicité du crédit restent obligatoires. Les vérifications opérateur,
plafonds et état des dépôts sont revérifiés sous verrou avant une nouvelle
création. Les limites globales portent sur des unités majeures dans chaque devise.

Les clés restent dans l'environnement serveur ; l'interface n'expose que leur
présence. Les interrupteurs de l'environnement `NDSGPAY_ENABLED` et
`FX_REFRESH_ENABLED` restent prioritaires. Ce centre ne fournit ni terminal,
ni exécution de commandes arbitraires, ni modification manuelle des soldes ou
ouverture des retraits.

## Attribution et exploitation

Sur le serveur, après création du compte via `/register` :

```sh
sudo -u deploy php artisan platform:superadmin adresse@example.com
```

La promotion est auditée et révoque les sessions précédentes. Le compte conserve
son mot de passe ; une nouvelle connexion est requise. La commande refuse un
compte absent ou suspendu. `platform:admin` ne rétrograde pas un superadmin.
Aucun identifiant privilégié ou mot de passe n'est créé dans les seeders.

Déployé le 30 octobre 2026 pour le compte demandé par le propriétaire. Sauvegarde
privée avant mise à jour : `/var/backups/predicafrica-release-8732dd95090811f1`,
avec `database.at-cutover.dump` réalisé en maintenance. Les quatre comptes,
portefeuilles et clés existants ont été conservés ; 24 opérateurs configurés,
dépôts ouverts et maintenance inactive après publication.

## Validation

238 tests PHP / 2 060 assertions, TypeScript et build réussis. Parcours Chrome
mobile/bureau : connexion superadmin, pause, modifications successives avec
révision, opérateurs, maintenance/réouverture et journal. Copie PostgreSQL :
migrations, paramètres et audit atomiques, crédit CDF après webhook signé/GET
pendant la suspension, idempotence et rapprochements XAF/CDF. Aucun paiement réel
créé ni réglage d'exploitation modifié pour les vérifications de production.

Tests : `tests/Feature/InfrastructureTest.php`,
`node tests/browser/infrastructure.mjs`, `tests/concurrency/postgres-smoke.php`.
Les tests navigateur et PostgreSQL exigent des bases isolées ; ne jamais les
pointer sur la base applicative. Les anciennes migrations monétaires et le
journal superadmin ne doivent pas être annulés pour restaurer un ancien code.
