# Pays et devise à l’inscription

## Source du catalogue

Vérifié le 29 octobre 2026 : la [documentation publique NDSGPay](https://ndsgpay.ndsgservices.com/docs.html)
donne des exemples et demande de contacter le support pour les opérateurs activés
sur le compte marchand. Le code HTML public du [client NDSGPay](https://ndsgpay.ndsgservices.com/app.html),
section « Correspondants disponibles », contient le catalogue suivant, accessible
sans session ni appel aux données d’un marchand : **12 pays, 8 devises**.
Il ne confirme pas un treizième pays ni USD.

| Pays | Code utilisé par NDSGPay | Devise |
| --- | --- | --- |
| Bénin | BEN | XOF |
| Cameroun | CMR | XAF |
| Congo-Brazzaville | COG | XAF |
| Côte d’Ivoire | CIV | XOF |
| Gabon | GAB | XAF |
| Kenya | KEN | KES |
| Ouganda | UGA | UGX |
| République démocratique du Congo | COD | CDF |
| Rwanda | RWA | RWF |
| Sénégal | SEN | XOF |
| Sierra Leone | SLE | SLE |
| Zambie | ZMB | ZMW |

Le catalogue revu est centralisé dans `config/regions.php`. Il sert au formulaire
et à la validation serveur ; l’inscription ne dépend pas d’un appel réseau à NDSGPay.
Il faut vérifier la source avant de modifier la liste.

## Comportement

- Pays et devise sont obligatoires, sans choix initial implicite.
- Le pays filtre les devises proposées. Un changement de pays efface la devise
  précédente, même si les deux pays partagent une monnaie.
- Le serveur refuse les valeurs inconnues, les paramètres mal formés et les couples
  incompatibles. Le navigateur ne décide pas de la devise comptable.
- Une transaction crée `users.country`, le compte comptable à zéro et un
  `payment_wallets` personnel à zéro dans la devise choisie.
- La devise fait autorité dans `payment_wallets.currency`. Elle n’est pas dupliquée
  sur `users`. Deux utilisateurs en XAF ont des soldes distincts.
- Pays et devise sont consultables dans le profil. Le formulaire de modification
  du profil ne permet pas de les changer ni de convertir un solde.
- Les comptes existants gardent leur portefeuille et leurs fonds. La migration
  ajoute seulement un pays nullable : aucune résidence n’est déduite des soldes.

## Limite monétaire actuelle

L’inscription dans une devise du catalogue **n’active pas ses paiements**.
Les capacités marchandes restent définies dans `NDSGPAY_CAPABILITIES`.
Les opérateurs visibles sont filtrés par pays et devise du compte, et le service
revérifie cette concordance avant toute création de paiement chez NDSGPay.
Les comptes historiques sans pays conservent leur comportement XAF.

Le journal actif est désormais séparé par devise. Les dépôts natifs et les
participations hors XAF sont pris en charge ; ces dernières nécessitent un taux
récent et une confirmation serveur. Les marchés conservent XAF comme référence
statistique. Voir [le moteur de change](fx.md) pour les unités, la source, les
règles de gain et d’arrondi, les confirmations expirables et l’activation réelle.
Les retraits restent fermés.

## Vérification

`tests/Feature/RegistrationRegionsTest.php` couvre les 12 couples du catalogue,
les champs absents et falsifiés, l’isolation des soldes, l’immuabilité via le profil,
le filtrage des opérateurs, le rejet des opérations incompatibles et la conservation
des fonds historiques pendant la migration.

`npm run test:e2e` vérifie les sélecteurs obligatoires, leur dépendance, une création
RDC/CDF jusqu’au profil et au portefeuille, puis le parcours XAF existant.
Les tests utilisent uniquement des bases temporaires ; les tests PHP interdisent
les appels prestataire non simulés. Les captures de l’inscription se trouvent dans
`test-results/register-region-{390,1440}.png`.
