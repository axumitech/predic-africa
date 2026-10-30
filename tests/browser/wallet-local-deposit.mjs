import assert from 'node:assert/strict';
import { mkdtemp, writeFile, mkdir } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-fx-transfers-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', LOG_CHANNEL: 'stderr', APP_CONFIG_CACHE: join(temp, 'unused-config.php'),
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', QUEUE_CONNECTION: 'sync',
    AI_PIPELINE_ENABLED: 'false', FX_REFRESH_ENABLED: 'false', CURRENCYAPI_KEY: '', NDSGPAY_ENABLED: 'true', NDSGPAY_SECRET_KEY: 'isolated-test-key', NDSGPAY_WEBHOOK_SECRET: 'isolated-test-hook', NDSGPAY_CAPABILITIES: JSON.stringify([{ country: 'COD', currency: 'CDF', correspondent: 'AIRTEL_COD', calling_code: '243', national_lengths: [9] }]) };
const php = args => { const r = spawnSync('php', args, { env, encoding: 'utf8' }); assert.equal(r.status, 0, r.stdout + r.stderr); };
php(['artisan', 'migrate', '--force']);
php(['tests/browser/wallet-transfers-fixtures.php']);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../tests/browser/fx-router.php'], { cwd: join(process.cwd(), 'public'), env, stdio: 'pipe' });
let output = ''; server.stdout.on('data', c => output += c); server.stderr.on('data', c => output += c);
let browser;
try {
    for (let i = 0; i < 60; i++) { try { if ((await fetch(base)).ok) break; } catch {} await new Promise(resolve => setTimeout(resolve, 150)); }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, reducedMotion: 'reduce' });
    await context.addInitScript(() => { localStorage.setItem('pa:pwa-remind-after', String(Date.now() + 604800000)); });
    await context.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, r => r.abort());
    const page = await context.newPage(); page.setDefaultTimeout(12000);
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.goto(base + '/login');
    await page.getByLabel('Adresse e-mail').fill('transfers@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('transfer-password-123');
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL(/dashboard$/);
    await page.goto(base + '/wallet');
    await page.locator('.workspace-splash').waitFor({ state: 'detached' });
    await page.getByRole('button', { name: 'Portefeuille suivant', exact: true }).click();
    await page.getByLabel('Devise du nouveau portefeuille').selectOption('CDF');
    await page.getByRole('button', { name: 'Créer un portefeuille' }).click();
    await page.getByText('Portefeuille créé et sélectionné.', { exact: true }).waitFor();
    const form = page.locator('form').filter({ has: page.getByLabel('Pays et opérateur') });
    assert.deepEqual(await form.locator('select, input').evaluateAll(elements => elements.map(e => e.name)), ['correspondent', 'msisdn', 'amount']);
    await page.getByLabel('Pays et opérateur').selectOption('AIRTEL_COD');
    await page.getByLabel('Numéro Mobile Money').fill('081 000 0000');
    await form.getByLabel('Montant en CDF', { exact: true }).fill('1234.56');
    await page.getByRole('button', { name: 'Continuer le dépôt', exact: true }).click();
    await page.getByRole('button', { name: 'Valider définitivement', exact: true }).click();
    await page.getByText('Demande initiée. Confirmez sur votre téléphone. Le paiement attend sa vérification.', { exact: true }).waitFor();
    await page.getByText('En attente de confirmation', { exact: true }).waitFor();
    php(['-r', `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $p = Illuminate\\Support\\Facades\\DB::table('payments')->sole(); if ($p->local_state !== 'pending' || $p->correspondent !== 'AIRTEL_COD' || Illuminate\\Support\\Facades\\Crypt::decryptString($p->phone_encrypted) !== '243810000000') { throw new RuntimeException('Incorrect deposit'); }`]);
    await mkdir('test-results/wallet-local-deposit', { recursive: true });
    await page.screenshot({ path: 'test-results/wallet-local-deposit/rdc-local.png', fullPage: true });
    assert.deepEqual(errors, []);
    console.log('PASS: operator first; RDC local 081 000 0000 normalized to 243810000000; provider accepted; deposit pending.');
} catch (e) { console.error(output.slice(-5000)); throw e; }
finally { await browser?.close(); server.kill(); }
