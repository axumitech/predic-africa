<?php

// Explicit opt-in: only a caller-selected MySQL socket, and a database created
// by this run. No .env database is migrated, cleared, or dropped.
use App\Models\User;
use App\Modules\Payments\Inbox;
use App\Modules\Payments\Payments;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    config(['payments.enabled' => true, 'payments.secret_key' => 'fake-test-secret', 'payments.webhook_secret' => 'fake-hook-secret', 'payments.merchant_id' => 'merchant-test',
        'payments.capabilities' => [['country' => 'GAB', 'correspondent' => 'AIRTEL_GAB', 'currency' => 'XAF', 'calling_code' => '241', 'national_lengths' => [8]]]]);
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        DB::table('test_provider_calls')->insert(['method' => $request->method()]);

        return $request->method() === 'POST'
            ? Http::response(['success' => true, 'depositId' => 'provider-'.Str::uuid(), 'status' => 'INITIATED'])
            : Http::response(['success' => true, 'deposit' => ['depositId' => basename($request->url()), 'amount' => 1000, 'currency' => 'XAF', 'status' => 'COMPLETED']]);
    });
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
        echo "READY\n";
        check(trim(fgets(STDIN)) === 'GO', 'Missing barrier.');
        if ($job['operation'] === 'create') {
            app(Payments::class)->create($job['user'], $job['data']);
        } elseif ($job['operation'] === 'inbox') {
            app(Inbox::class)->process($job['id']);
        } else {
            app(Payments::class)->refresh(DB::table('payments')->find($job['id']));
        }
        echo json_encode(['status' => 'ok'])."\n";
        exit(0);
    } catch (ValidationException $exception) {
        echo json_encode(['status' => 'rejected'])."\n";
        exit(0);
    } catch (Throwable $exception) {
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

function data(): array
{
    return ['kind' => 'deposit', 'amount' => 1000, 'reference' => (string) Str::uuid(), 'correspondent' => 'AIRTEL_GAB', 'msisdn' => '24177000000'];
}

try {
    boot($socket, $database);
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated migrations failed.');
    DB::statement('CREATE TABLE test_provider_calls (id BIGINT AUTO_INCREMENT PRIMARY KEY, method VARCHAR(10) NOT NULL) ENGINE=InnoDB');
    $engine = DB::selectOne('SELECT VERSION() AS version, @@transaction_isolation AS isolation_level');
    echo "MySQL $engine->version / $engine->isolation_level\n";

    $user = User::factory()->create();
    $job = ['operation' => 'create', 'user' => $user->id, 'data' => data()];
    check(race([$job, $job], $control, 'users', $user->id) === ['ok', 'ok'], 'Duplicate creation failed.');
    check(DB::table('payments')->count() === 1 && DB::table('test_provider_calls')->where('method', 'POST')->count() === 1, 'Duplicate POST escaped.');
    echo "PASS concurrent identical creation: one provider POST (observed lock waits)\n";

    DB::table('payment_rate_limits')->delete();
    $other = User::factory()->create();
    $results = race([['operation' => 'create', 'user' => $other->id, 'data' => data()], ['operation' => 'create', 'user' => $other->id, 'data' => data()]], $control, 'users', $other->id);
    sort($results);
    check($results === ['ok', 'rejected'] && DB::table('payments')->where('user_id', $other->id)->count() === 1, 'Different UUID bypassed active topup.');
    echo "PASS concurrent distinct UUID: active operation blocks duplicate collection\n";

    DB::table('payment_rate_limits')->delete();
    $payment = DB::table('payments')->where('user_id', $user->id)->first();
    check(race([['operation' => 'refresh', 'id' => $payment->id], ['operation' => 'refresh', 'id' => $payment->id]], $control, 'payments', $payment->id) === ['ok', 'ok'], 'Concurrent refresh failed.');
    check(DB::table('payment_movements')->where('payment_id', $payment->id)->count() === 1, 'Double credit.');
    check((int) DB::table('payment_wallets')->where('user_id', $user->id)->value('balance') === 1000, 'Wrong balance.');
    echo "PASS concurrent GET reconciliation: one credit (observed lock waits)\n";

    DB::table('payment_rate_limits')->delete();
    $payment = DB::table('payments')->where('user_id', $other->id)->first();
    $inbox = DB::table('payment_inbox')->insertGetId(['dedupe_key' => hash('sha256', 'race-event'), 'merchant_id' => 'merchant-test', 'kind' => 'deposit', 'provider_id' => $payment->provider_id, 'provider_status' => 'COMPLETED', 'received_at' => now(), 'next_attempt_at' => now()]);
    check(race([['operation' => 'refresh', 'id' => $payment->id], ['operation' => 'inbox', 'id' => $inbox]], $control, 'payments', $payment->id) === ['ok', 'ok'], 'Webhook/GET race failed.');
    check(DB::table('payment_movements')->where('payment_id', $payment->id)->count() === 1, 'Race double credit.');
    check((int) DB::table('payment_wallets')->where('user_id', $other->id)->value('balance') === 1000, 'Race wrong balance.');
    echo "PASS webhook versus reconciliation: one financial effect (observed lock waits)\n";
    check(DB::table('payment_wallets')->sum('balance') === DB::table('payment_movements')->sum('amount'), 'Financial journal mismatch.');
    echo "PASS wallet / immutable movement reconciliation\n";
} finally {
    DB::disconnect();
    $control->exec("DROP DATABASE `$database`");
    echo "Isolated test database removed.\n";
}
