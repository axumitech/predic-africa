# Concurrence Trading / Settlement

Test explicite sur une **instance MySQL de test isolée**, sans utiliser la base du `.env`. Il crée une base `predic_concurrency_<aléatoire>`, applique les migrations, lance plusieurs processus PHP, rapproche le journal puis supprime uniquement cette base. Ne pas pointer ce test vers un serveur de production.

Prérequis : PHP avec PDO MySQL, `proc_open` disponible, MySQL/InnoDB avec `performance_schema` actif, compte de test autorisé à créer/supprimer sa base et à lire `performance_schema.data_lock_waits` et `data_locks`. Aucune configuration Laravel mise en cache.

Depuis la racine Laravel :

```sh
PREDIC_TEST_MYSQL_SOCKET=/chemin/instance-test/mysql.sock php tests/concurrency/trading-settlement.php
```

`PREDIC_TEST_MYSQL_USER` et `PREDIC_TEST_MYSQL_PASSWORD` configurent les accès si nécessaire (défaut root sans mot de passe, adapté seulement à une instance temporaire sans réseau). Aucune dépendance installée par le script. Sans socket explicite : SKIP, code 2. Échec de connexion, d’invariant ou de scénario : code non nul ; succès : code 0.

Les workers passent une barrière de départ. Sept scénarios constatent deux transactions concurrentes attendant réellement leurs verrous dans InnoDB ; un huitième impose une mise tenant le verrou du participant dont la résolution a besoin, puis la laisse poursuivre sur un autre marché. Une seule acquisition du marché par worker est attendue, pour éviter qu’une reprise automatique ne masque un interblocage dans ces entrelacements contrôlés.

Couverture : double mise, solde insuffisant partagé entre marchés, doubles gains/remboursements, résultats contradictoires, mise contre annulation, deux règlements avec participants communs, règlement contre mise sur un autre marché. Rapprochement final et non-négativité des utilisateurs vérifiés. Exécution de référence : MySQL 9.6.0/InnoDB, isolation REPEATABLE READ, huit scénarios réussis. Ces tests ne mesurent pas le débit et ne valident pas PostgreSQL.

Pour reproduire localement avec MySQL déjà installé, initialiser un nouveau `datadir` temporaire avec `mysqld --no-defaults --initialize-insecure`, puis démarrer ce datadir avec `--no-defaults --skip-networking --mysqlx=OFF`, un socket, un pid-file et un log-error placés dans le même répertoire temporaire. Arrêter cette instance via `mysqladmin --no-defaults --socket=<socket-test> -uroot shutdown` à la fin. Ne jamais réutiliser un datadir existant.
