import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { request } from 'playwright';

// Real HTTP clients with separate encrypted cookies and DB-backed sessions.
// Reuse the isolated fixture's safety guard; never migrate the app's database.
const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-identity-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: join(temp, 'unused-config.php'), DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', AI_PIPELINE_ENABLED: 'false' };
for (const args of [['artisan', 'migrate', '--force'], ['tests/browser/setup.php']]) {
    const result = spawnSync('php', args, { env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
}
const socket = createServer();
await new Promise((resolve, reject) => { socket.once('error', reject); socket.listen(0, '127.0.0.1', resolve); });
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const baseURL = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { env, cwd: join(process.cwd(), 'public'), stdio: 'ignore' });
const clients = [];
async function client() {
    const context = await request.newContext({ baseURL, extraHTTPHeaders: { Accept: 'application/json' } });
    clients.push(context);
    return context;
}
async function post(context, url, form) {
    const cookies = (await context.storageState()).cookies;
    const csrf = cookies.find(cookie => cookie.name === 'XSRF-TOKEN');
    assert.ok(csrf, 'Missing CSRF cookie');
    return context.post(url, { form, headers: { 'X-XSRF-TOKEN': decodeURIComponent(csrf.value) }, maxRedirects: 0 });
}
async function login(context, email, password) {
    assert.equal((await context.get('/login')).status(), 200);
    assert.equal((await post(context, '/login', { email, password })).status(), 302);
}
try {
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch(`${baseURL}/up`)).ok) { ready = true; break; } } catch {}
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(ready, 'HTTP test server unavailable');
    const current = await client();
    const old = await client();
    const admin = await client();
    await current.get('/register');
    assert.equal((await post(current, '/register', { name: 'Identity HTTP', email: 'identity@example.test', password: 'identity-password-123', password_confirmation: 'identity-password-123', country: 'GAB', currency: 'XAF' })).status(), 302);
    await login(old, 'identity@example.test', 'identity-password-123');
    await login(admin, 'admin@example.test', 'e2e-password-123');
    let response = await current.get('/profile');
    assert.equal(response.status(), 200);
    const initialPage = (await response.text()).match(/<script data-page="app" type="application\/json">([\s\S]*?)<\/script>/);
    assert.ok(initialPage, 'Missing Inertia bootstrap page');
    const user = JSON.parse(initialPage[1]).props.auth;
    assert.equal(user.role, 'trader');
    assert.ok(response.headers()['cache-control'].includes('no-store'));
    assert.equal((await post(current, '/profile', { name: user.name, email: user.email, current_password: 'identity-password-123', password: 'identity-password-456', password_confirmation: 'identity-password-456' })).status(), 302);
    assert.equal((await current.get('/wallet')).status(), 200);
    assert.equal((await old.get('/wallet')).status(), 401);
    console.log('PASS: password rotation preserves current HTTP session and revokes a second client.');

    await login(old, user.email, 'identity-password-456');
    assert.equal((await post(admin, `/admin/users/${user.id}`, { suspended: '1' })).status(), 302);
    response = await current.get('/', { headers: { Accept: 'text/html', 'X-Inertia': 'true' } });
    assert.equal(response.status(), 403);
    const props = (await response.json()).props;
    assert.equal(props.auth, undefined);
    assert.equal(props.balance, undefined);
    assert.equal((await post(admin, `/admin/users/${user.id}`, { suspended: '0' })).status(), 302);
    // The second client made no request during suspension: revocation must not
    // depend on it having visited the application while suspended.
    assert.equal((await old.get('/wallet')).status(), 401);
    await login(current, user.email, 'identity-password-456');
    assert.equal((await current.get('/wallet')).status(), 200);
    assert.equal((await current.get('/admin/users')).status(), 403);
    console.log('PASS: suspension hides public shared props; reactivation requires a fresh login for old clients.');

    const csrfFailure = await current.post('/profile', { form: { name: 'CSRF attack' } });
    assert.equal(csrfFailure.status(), 419);
    assert.equal((await post(current, '/logout', {})).status(), 302);
    assert.equal((await current.get('/wallet')).status(), 401);
    console.log('PASS: CSRF protection, role authorization and logout over real HTTP.');
} finally {
    for (const context of clients) await context.dispose();
    if (server.exitCode === null) {
        const exited = new Promise(resolve => server.once('exit', resolve));
        server.kill('SIGTERM');
        await exited;
    }
    await rm(temp, { recursive: true, force: true });
}
