# Concurrence Trading / Settlement

Test explicite sur une **instance MySQL de test isolée**, sans utiliser la base du `.env`. Il crée une base `predic_concurrency_<aléatoire>`, applique les migrations, lance plusieurs processus PHP, rapproche le journal puis supprime uniquement cette base. Ne pas pointer ce test vers un serveur de production.

Prérequis : PHP avec PDO MySQL, `proc_open` disponible, MySQL/InnoDB avec `performance_schema` actif, compte de test autorisé à créer/supprimer sa base et à lire `performance_schema.data_lock_waits` et `data_locks`. Aucune configuration Laravel mise en cache.

Depuis la racine Laravel :

```sh
PREDIC_TEST_MYSQL_SOCKET=/chemin/instance-test/mysql.sock php tests/concurrency/trading-settlement.php
```

`PREDIC_TEST_MYSQL_USER` et `PREDIC_TEST_MYSQL_PASSWORD` configurent les accès si nécessaire (défaut root sans mot de passe, adapté seulement à une instance temporaire sans réseau). Aucune dépendance installée par le script. Sans socket explicite : SKIP, code 2. Échec de connexion, d’invariant ou de scénario : code non nul ; succès : code 0.

Les workers passent une barrière de départ. Sept scénarios constatent deux transactions concurrentes attendant réellement leurs verrous dans InnoDB ; un huitième impose une mise tenant le verrou du participant dont la résolution a besoin, puis la laisse poursuivre sur un autre marché. Une seule acquisition du marché par worker est attendue, pour éviter qu’une reprise automatique ne masque un interblocage dans ces entrelacements contrôlés.

Couverture : double mise, solde insuffisant partagé entre marchés, doubles gains/remboursements, résultats contradictoires, mise contre annulation, deux règlements avec participants communs, règlement contre mise sur un autre marché. Le parcours multidevise ajoute la double consommation d’une confirmation CDF, le double règlement d’un gain CDF au centime et un règlement mixte XAF/CDF avec contreparties séparées. Rapprochement final et non-négativité des utilisateurs vérifiés. Exécution de référence : MySQL 9.6.0/InnoDB, isolation REPEATABLE READ ; les onze courses réussissent avec attentes de verrous observées. Ces tests ne mesurent pas le débit et ne valident pas PostgreSQL.

Pour reproduire localement avec MySQL déjà installé, initialiser un nouveau `datadir` temporaire avec `mysqld --no-defaults --initialize-insecure`, puis démarrer ce datadir avec `--no-defaults --skip-networking --mysqlx=OFF`, un socket, un pid-file et un log-error placés dans le même répertoire temporaire. Arrêter cette instance via `mysqladmin --no-defaults --socket=<socket-test> -uroot shutdown` à la fin. Ne jamais réutiliser un datadir existant.

## NDSGPay

```sh
PREDIC_TEST_MYSQL_SOCKET=/chemin/instance-test/mysql.sock php tests/concurrency/payments.php
```

Même isolation que ci-dessus : base éphémère dédiée, appels prestataire entièrement simulés, socket explicite requis. Couvre double création avec même UUID (un seul POST), UUID différents sur un dépôt actif, double rapprochement GET, course inbox webhook contre rapprochement, et égalité soldes/journal. Chaque course prouve deux transactions en attente dans InnoDB. Les payouts sont fermés et ne sont pas présentés comme testés. Aucun secret réel nécessaire.

## Compatibilité PostgreSQL

`postgres-smoke.php` valide les migrations et un parcours séquentiel CDF/XAF sur
une copie isolée. Il refuse toute base dont le nom n'est pas
`predic_activation_<16 caractères hexadécimaux>`, exige `APP_ENV=testing`, aucune
configuration mise en cache et un nom identique dans `PREDIC_TEST_PG_DATABASE`,
la configuration Laravel et `current_database()`. Préparer/restaurer la copie
avant l'appel ; le script ne crée ni ne supprime la base. Utiliser un stockage
Laravel distinct et interdire les appels réels aux fournisseurs. Ne jamais
pointer ce script sur la base applicative.

Contrôles : migrations depuis l'ancien schéma, dépôt CDF exact au centime,
répétition sans double création ni double crédit, confirmation et règlement
mixte XAF/CDF, rejet des taux anciens et rapprochements des journaux. Ce parcours
ne démontre pas les courses concurrentes PostgreSQL ; celles décrites plus haut
ont été vérifiées sur MySQL uniquement.
