<?php

// Explicit opt-in: only a caller-selected MySQL socket, and a database created
// by this run. No .env database is migrated, cleared, or dropped.
use App\Models\User;
use App\Modules\Ledger\Ledger;
use App\Modules\Settlement\ResolveMarket;
use App\Modules\Trading\PlacePosition;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function boot(string $socket, string $database): void
{
    putenv('APP_ENV=testing');
    putenv('CACHE_STORE=array');
    putenv('SESSION_DRIVER=array');
    $app = require __DIR__.'/../../bootstrap/app.php';
    check(! $app->configurationIsCached(), 'Remove cached config before this isolated test.');
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
        'database.connections.mysql.unix_socket' => $socket, 'database.connections.mysql.database' => $database,
        'database.connections.mysql.username' => getenv('PREDIC_TEST_MYSQL_USER') ?: 'root',
        'database.connections.mysql.password' => getenv('PREDIC_TEST_MYSQL_PASSWORD') ?: '',
        'cache.default' => 'array', 'session.driver' => 'array']);
    DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
}

function lineFrom($stream): string
{
    $line = '';
    $deadline = microtime(true) + 20;
    while (microtime(true) < $deadline) {
        $read = [$stream];
        $write = $except = [];
        if (stream_select($read, $write, $except, 1) > 0) {
            $part = fgets($stream);
            check($part !== false, 'Worker exited without a complete response.');
            $line .= $part;
            if (str_ends_with($line, "\n")) {
                return trim($line);
            }
        }
    }
    throw new RuntimeException('Worker response timeout.');
}

$socket = getenv('PREDIC_TEST_MYSQL_SOCKET');
if (! $socket) {
    fwrite(STDERR, "SKIP: set PREDIC_TEST_MYSQL_SOCKET to an isolated MySQL test instance.\n");
    exit(2);
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $database = getenv('PREDIC_CONCURRENCY_DATABASE');
        check((bool) preg_match('/^predic_concurrency_[a-f0-9]{16}$/D', $database ?: ''), 'Invalid isolated database name.');
        boot($socket, $database);
        $job = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
        $marketLocks = 0;
        $paused = false;
        DB::listen(function ($query) use ($job, &$marketLocks, &$paused) {
            if (str_contains($query->sql, 'from `markets`') && str_contains($query->sql, 'for update')) {
                $marketLocks++;
            }
            if (($job['pause_after_user_lock'] ?? false) && ! $paused
                && str_contains($query->sql, 'from `users`') && str_contains($query->sql, 'for update')) {
                $paused = true;
                echo "USER_LOCKED\n";
                check(trim(fgets(STDIN)) === 'RESUME', 'Missing lock barrier release.');
            }
        });
        echo "READY\n";
        check(trim(fgets(STDIN)) === 'GO', 'Missing barrier.');
        if ($job['operation'] === 'place') {
            app(PlacePosition::class)->place($job['user'], $job['market'], $job['outcome'], $job['amount'], $job['reference']);
        } else {
            app(ResolveMarket::class)->resolve($job['market'], $job['result'], 'Source du test concurrent isolé');
        }
        echo json_encode(['status' => 'ok', 'market_locks' => $marketLocks])."\n";
        exit(0);
    } catch (ValidationException $exception) {
        echo json_encode(['status' => 'rejected', 'code' => 422, 'market_locks' => $marketLocks])."\n";
        exit(0);
    } catch (HttpException $exception) {
        echo json_encode(['status' => 'rejected', 'code' => $exception->getStatusCode(), 'market_locks' => $marketLocks])."\n";
        exit(0);
    } catch (Throwable $exception) {
        // Never print connection strings or credentials in failure output.
        echo json_encode(['status' => 'error', 'class' => $exception::class, 'code' => $exception->getCode()])."\n";
        exit(1);
    }
}

$control = new PDO('mysql:unix_socket='.$socket, getenv('PREDIC_TEST_MYSQL_USER') ?: 'root', getenv('PREDIC_TEST_MYSQL_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'predic_concurrency_'.bin2hex(random_bytes(8));
$control->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
putenv("PREDIC_CONCURRENCY_DATABASE=$database");

function race(array $jobs, PDO $control, string $table, int $id): array
{
    $workers = [];
    DB::beginTransaction();
    try {
        DB::table($table)->where('id', $id)->lockForUpdate()->firstOrFail();
        foreach ($jobs as $job) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', base64_encode(json_encode($job, JSON_THROW_ON_ERROR))],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Unable to start worker.');
            $workers[] = [$process, $pipes];
        }
        foreach ($workers as [, $pipes]) {
            check(lineFrom($pipes[1]) === 'READY', 'Worker did not reach start barrier.');
        }
        foreach ($workers as [, $pipes]) {
            fwrite($pipes[0], "GO\n");
            fflush($pipes[0]);
        }
        // Prove overlap by observing InnoDB waits while this connection holds
        // the gate. A simultaneous launch alone is not proof of contention.
        $overlap = false;
        $deadline = microtime(true) + 3;
        do {
            $waiting = (int) $control->query('SELECT COUNT(DISTINCT REQUESTING_ENGINE_TRANSACTION_ID) FROM performance_schema.data_lock_waits')->fetchColumn();
            if ($waiting >= 2) {
                $overlap = true;
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        DB::commit();
        check($overlap, 'Two concurrent InnoDB waiters were not observed.');
        $results = [];
        foreach ($workers as [, $pipes]) {
            $result = json_decode(lineFrom($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
            check(in_array($result['status'], ['ok', 'rejected'], true), 'Unexpected worker error: '.json_encode($result));
            check(($result['market_locks'] ?? 0) === 1, 'Unexpected transaction retry in controlled race.');
            $results[] = $result['status'];
        }

        return $results;
    } finally {
        if (DB::transactionLevel()) {
            DB::rollBack();
        }
        foreach ($workers as [$process, $pipes]) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }
}

function fixture(int $funds = 1000): array
{
    $user = User::factory()->create();
    DB::transaction(fn () => app(Ledger::class)->transfer($user->id, (string) Str::uuid(), 'Dépôt simulé', $funds, true));
    $market = DB::table('markets')->insertGetId(['creator_id' => $user->id, 'title' => 'Test de concurrence isolé',
        'description' => 'Marché fictif pour tests.', 'category' => 'Sport', 'status' => 'open',
        'closes_at' => now()->addDay(), 'yes_odds' => 235, 'no_odds' => 170, 'created_at' => now(), 'updated_at' => now()]);

    return [$user->id, $market];
}

function placeJob(int $user, int $market, int $amount = 100, ?string $reference = null): array
{
    return ['operation' => 'place', 'user' => $user, 'market' => $market, 'amount' => $amount, 'outcome' => 'yes', 'reference' => $reference ?? (string) Str::uuid()];
}

function resolveJob(int $market, string $result): array
{
    return ['operation' => 'resolve', 'market' => $market, 'result' => $result];
}

function balance(int $user): int
{
    return (int) DB::table('accounts')->where('user_id', $user)->value('balance');
}

try {
    boot($socket, $database);
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated migrations failed.');
    $engine = DB::selectOne('SELECT VERSION() AS version, @@transaction_isolation AS isolation_level');
    echo "MySQL $engine->version / $engine->isolation_level\n";

    [$user, $market] = fixture();
    $job = placeJob($user, $market, 100, (string) Str::uuid());
    check(race([$job, $job], $control, 'markets', $market) === ['ok', 'ok'], 'Duplicate placement failed.');
    check(balance($user) === 900 && DB::table('positions')->where('market_id', $market)->count() === 1, 'Duplicate bet charged twice.');
    echo "PASS duplicate bet (observed lock waits)\n";

    [$user, $first] = fixture(100);
    [, $second] = fixture();
    $results = race([placeJob($user, $first), placeJob($user, $second)], $control, 'users', $user);
    sort($results);
    check($results === ['ok', 'rejected'] && balance($user) === 0, 'Concurrent overspend.');
    check(DB::table('positions')->where('user_id', $user)->count() === 1, 'Overspend created two positions.');
    echo "PASS two markets / one balance (observed lock waits)\n";

    foreach (['yes', 'void'] as $result) {
        [$user, $market] = fixture();
        app(PlacePosition::class)->place($user, $market, 'yes', 101, (string) Str::uuid());
        DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
        check(race([resolveJob($market, $result), resolveJob($market, $result)], $control, 'markets', $market) === ['ok', 'ok'], 'Duplicate settlement failed.');
        check(balance($user) === ($result === 'yes' ? 1136 : 1000), 'Duplicate payout or refund.');
        echo "PASS duplicate $result settlement (observed lock waits)\n";
    }

    [$user, $market] = fixture();
    app(PlacePosition::class)->place($user, $market, 'yes', 100, (string) Str::uuid());
    DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
    $results = race([resolveJob($market, 'yes'), resolveJob($market, 'no')], $control, 'markets', $market);
    sort($results);
    $result = DB::table('markets')->where('id', $market)->value('result');
    check($results === ['ok', 'rejected'] && balance($user) === ($result === 'yes' ? 1135 : 900), 'Conflicting result accepted.');
    echo "PASS conflicting results (observed lock waits)\n";

    [$user, $market] = fixture();
    $results = race([placeJob($user, $market), resolveJob($market, 'void')], $control, 'markets', $market);
    check($results[1] === 'ok' && balance($user) === 1000, 'Bet vs cancellation lost money.');
    check(DB::table('positions')->where('market_id', $market)->where('status', 'open')->count() === 0, 'Open position after cancellation.');
    echo "PASS bet vs cancellation (observed lock waits)\n";

    // Distinct markets, overlapping users: resolution must lock every user
    // before taking the shared simulation account.
    [$firstUser, $firstMarket] = fixture();
    [$secondUser, $secondMarket] = fixture();
    foreach ([$firstMarket, $secondMarket] as $market) {
        foreach ([$firstUser, $secondUser] as $user) {
            app(PlacePosition::class)->place($user, $market, 'yes', 100, (string) Str::uuid());
        }
        DB::table('markets')->where('id', $market)->update(['closes_at' => now()->subSecond()]);
    }
    check(race([resolveJob($firstMarket, 'yes'), resolveJob($secondMarket, 'void')], $control, 'users', $firstUser) === ['ok', 'ok'], 'Overlapping settlements failed.');
    check(balance($firstUser) === 1135 && balance($secondUser) === 1135, 'Overlapping settlements inconsistent.');
    echo "PASS two settlements / overlapping users (observed lock waits)\n";

    // Force the former cycle: the later participant has a bet on another
    // market holding its user lock; settlement must wait BEFORE its first
    // payout takes the simulation account. Then let the bet reach Ledger.
    [$earlyUser, $settledMarket] = fixture();
    [$lateUser, $betMarket] = fixture();
    foreach ([$earlyUser, $lateUser] as $user) {
        app(PlacePosition::class)->place($user, $settledMarket, 'yes', 100, (string) Str::uuid());
    }
    DB::table('markets')->where('id', $settledMarket)->update(['closes_at' => now()->subSecond()]);
    $jobs = [placeJob($lateUser, $betMarket) + ['pause_after_user_lock' => true], resolveJob($settledMarket, 'yes')];
    $workers = [];
    try {
        foreach ($jobs as $index => $job) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', base64_encode(json_encode($job, JSON_THROW_ON_ERROR))],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            check(is_resource($process), 'Unable to start cycle worker.');
            $workers[] = [$process, $pipes];
            check(lineFrom($pipes[1]) === 'READY', 'Cycle worker not ready.');
            fwrite($pipes[0], "GO\n");
            fflush($pipes[0]);
            if ($index === 0) {
                check(lineFrom($pipes[1]) === 'USER_LOCKED', 'Bet did not hold participant lock.');
            }
        }
        $deadline = microtime(true) + 3;
        $waiting = false;
        do {
            $waiting = (int) $control->query("SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID WHERE l.OBJECT_NAME = 'users'")->fetchColumn() > 0;
            if ($waiting) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        check($waiting, 'Settlement did not wait for the later participant.');
        fwrite($workers[0][1][0], "RESUME\n");
        fflush($workers[0][1][0]);
        foreach ($workers as [, $pipes]) {
            $result = json_decode(lineFrom($pipes[1]), true, flags: JSON_THROW_ON_ERROR);
            check($result['status'] === 'ok' && $result['market_locks'] === 1, 'Former lock cycle required a retry or failed: '.json_encode($result));
        }
    } finally {
        foreach ($workers as [$process, $pipes]) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
    }
    check(balance($earlyUser) === 1135 && balance($lateUser) === 1035, 'Bet/settlement balances inconsistent.');
    echo "PASS forced settlement vs other-market bet / no transaction retry\n";

    check(Artisan::call('ledger:reconcile') === 0, 'Final ledger reconciliation failed.');
    check(DB::table('accounts')->whereNotNull('user_id')->where('balance', '<', 0)->count() === 0, 'Negative balance.');
    echo "PASS ledger reconciliation and nonnegative user balances\n";
} finally {
    DB::disconnect();
    $control->exec("DROP DATABASE `$database`");
    echo "Isolated test database removed.\n";
}
