import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: join(temp, 'unused-config.php'), DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', AI_PIPELINE_URL: 'http://127.0.0.1:9' };
function php(args) {
    const result = spawnSync('php', args, { env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
}
php(['artisan', 'migrate', '--force']);
php(['tests/browser/setup.php']);
const socket = createServer();
await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { env, cwd: join(process.cwd(), 'public'), stdio: 'pipe' });
let output = '';
server.stdout.on('data', chunk => { output += chunk; });
server.stderr.on('data', chunk => { output += chunk; });
let browser;
let page;
const errors = [];
try {
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(base)).ok) break; } catch {}
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('pageerror', error => errors.push(error.message));
    await context.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, route => route.abort());
    await context.route('**/api/predictions', route => route.fulfill({ status: 503, json: { status: 'unavailable', data: [] } }));
    await context.route('**/api/markets', route => route.fulfill({ status: 503, json: { status: 'unavailable', data: [] } }));
    await page.goto(base);
    await page.locator('.landing-hero').waitFor();
    assert.equal(await page.locator('.landing-header img').getAttribute('src'), '/HOR_WG.png');
    assert.match(await page.locator('.hero-title').innerText(), /Bourse de Prédictions en Afrique/);
    assert.equal(await page.locator('#btn-enter-bourse').evaluate(el => getComputedStyle(el).backgroundColor), 'rgb(245, 183, 0)');
    await page.locator('#btn-enter-bourse').click();
    await page.waitForURL('**/login');
    await page.getByRole('link', { name: 'Pas encore de compte' }).click();
    await mkdir('test-results', {recursive:true});
    for (const width of [320,375,390,430,768,1024,1280,1366,1440,1920]) {
        await page.setViewportSize({width,height:900});
        for (const route of ['register','login']) {
            await page.goto(`${base}/${route}`);
            await page.locator('h1').waitFor();
            assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`Auth overflow ${route} ${width}`);
            if ([390,1440].includes(width)) await page.screenshot({path:`test-results/redesign-${route}-${width}.png`,fullPage:true});
        }
    }
    await page.setViewportSize({width:1440,height:1000});
    await page.goto(`${base}/register`);
    const country = page.getByLabel('Pays de résidence');
    const currency = page.getByLabel('Devise du portefeuille');
    await country.waitFor();
    assert.equal(await country.locator('option').count(), 13, '12 documented countries plus placeholder');
    assert.equal(await currency.isDisabled(), true);
    await page.getByLabel('Nom complet').fill('Compte RDC');
    await page.getByLabel('Adresse e-mail').fill('cdf@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('e2e-password-123');
    await page.getByLabel('Confirmer le mot de passe').fill('e2e-password-123');
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    assert.equal(await country.evaluate(el => el.validity.valueMissing && document.activeElement === el), true);
    await country.selectOption('COD');
    assert.deepEqual(await currency.locator('option').evaluateAll(options => options.map(option => option.value)), ['', 'CDF']);
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    assert.equal(await currency.evaluate(el => el.validity.valueMissing && document.activeElement === el), true);
    await currency.selectOption('CDF');
    await country.selectOption('CMR');
    assert.equal(await currency.inputValue(), '', 'Country changes must reset currency');
    assert.deepEqual(await currency.locator('option').evaluateAll(options => options.map(option => option.value)), ['', 'XAF']);
    await country.selectOption('COD');
    await currency.selectOption('CDF');
    for (const width of [320,390,768,1440]) {
        await page.setViewportSize({width,height:900});
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Selected RDC overflow at ${width}`);
        if ([390,1440].includes(width)) await page.screenshot({path:`test-results/register-region-${width}.png`,fullPage:true});
    }
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.waitForURL('**/dashboard');
    await page.locator('h1').waitFor();
    await page.locator('.workspace-splash').waitFor({state:'detached'});
    await page.locator('.p-fx-notice').filter({hasText:'CDF'}).waitFor();
    await page.goto(`${base}/profile`);
    await page.locator('h1').waitFor();
    await page.locator('.workspace-splash').waitFor({state:'detached'});
    assert.match(await page.locator('.p-confirm-summary').innerText(), /République démocratique du Congo/);
    assert.match(await page.locator('.p-confirm-summary').innerText(), /CDF/);
    await page.goto(`${base}/wallet`);
    await page.locator('h1').waitFor();
    await page.locator('.workspace-splash').waitFor({state:'detached'});
    assert.match(await page.locator('.p-balance-card h2').innerText(), /0,00\s*CDF/);
    await page.getByText('Les dépôts sont temporairement indisponibles.', {exact:true}).waitFor();
    assert.equal(await page.getByRole('button', {name:'Continuer le dépôt'}).count(), 0);
    await page.getByRole('button', {name:'Se déconnecter',exact:true}).click();
    await page.goto(`${base}/register`);
    await page.getByLabel('Pays de résidence').selectOption('GAB');
    await page.getByLabel('Devise du portefeuille').selectOption('XAF');
    await page.getByLabel('Nom complet').fill('Trader E2E');
    await page.getByLabel('Adresse e-mail').fill('trader@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('e2e-password-123');
    await page.getByLabel('Confirmer le mot de passe').fill('e2e-password-123');
    await page.getByRole('button', { name: 'Créer mon compte' }).click();
    await page.waitForURL(/\/(dashboard|markets|admin)$/);
    await page.getByRole('link', { name: 'Gérer mon portefeuille' }).click();
    php(['tests/browser/fund.php', 'trader@example.test']);
    await page.reload();
    assert.match(await page.locator('.p-balance-card h2').innerText(), /1\s?000/);
    await page.getByRole('link', { name: 'Créer un marché', exact: false }).click();
    await page.getByLabel('Question du marché').fill('Le scénario E2E sera-t-il validé ?');
    await page.getByLabel('Règles de résolution et source').fill('Oui si le test se termine correctement. Source : résultat E2E.');
    await page.getByLabel('Clôture (UTC)').fill(new Date(Date.now() + 86400000).toISOString().slice(0, 16));
    await page.getByRole('button', { name: 'Soumettre à la modération' }).click();
    await page.getByRole('button', { name: 'Valider définitivement' }).click();
    await page.waitForURL(/\/markets\/\d+$/);
    const marketUrl = page.url();
    assert.match(await page.locator('.p-detail').innerText(), /À modérer/);
    await page.getByRole('button', { name: 'Se déconnecter' }).click();
    await page.locator('#btn-enter-bourse').click();
    await page.waitForURL('**/login');
    await page.getByLabel('Adresse e-mail').fill('admin@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('e2e-password-123');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL(/\/(dashboard|markets|admin)$/);
    await page.goto(marketUrl);
    await page.getByRole('link', { name: 'Gérer ce marché dans l’administration' }).click();
    await page.getByRole('button', { name: 'Enregistrer la décision' }).click();
    await page.getByRole('button', { name: 'Valider définitivement' }).click();
    await page.getByRole('status').filter({ hasText: 'Modération enregistrée' }).waitFor();
    await page.goto(`${base}/admin/users`);
    const cdfAccount = page.getByRole('row').filter({hasText:'cdf@example.test'});
    await cdfAccount.waitFor();
    assert.match(await cdfAccount.innerText(), /0\s*CDF/);
    const cdfContext = await browser.newContext({reducedMotion:'reduce'});
    await cdfContext.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, route => route.abort());
    const cdfPage = await cdfContext.newPage();
    cdfPage.on('pageerror', error => errors.push(error.message));
    await cdfPage.goto(`${base}/login`);
    await cdfPage.getByLabel('Adresse e-mail').fill('cdf@example.test');
    await cdfPage.getByLabel('Mot de passe', {exact:true}).fill('e2e-password-123');
    await cdfPage.getByRole('button', {name:'Se connecter',exact:true}).click();
    await cdfPage.waitForURL('**/dashboard');
    await cdfPage.goto(marketUrl);
    await cdfPage.locator('h1').waitFor();
    await cdfPage.locator('.workspace-splash').waitFor({state:'detached'});
    assert.match(await cdfPage.locator('.p-detail').innerText(), /La participation sera disponible dès qu’un taux de change récent pourra être confirmé/);
    assert.equal(await cdfPage.getByRole('button', {name:'Confirmer ma position'}).isDisabled(), true);
    await cdfContext.close();
    await mkdir('test-results', { recursive: true });
    await page.goto(`${base}/admin`);
    await page.screenshot({ path: 'test-results/redesign-admin-desktop.png', fullPage: true });
    await page.getByRole('button', { name: 'Se déconnecter' }).click();
    await page.locator('#btn-enter-bourse').click();
    await page.waitForURL('**/login');
    await page.getByLabel('Adresse e-mail').fill('trader@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('e2e-password-123');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL(/\/(dashboard|markets|admin)$/);
    await page.goto(marketUrl);
    await page.getByLabel('Mise en XAF').fill('100');
    await page.getByRole('button', { name: 'Confirmer ma position' }).click();
    await page.getByRole('button', { name: 'Valider définitivement' }).click();
    await page.waitForURL('**/positions');
    assert.match(await page.locator('tbody').innerText(), /Le scénario E2E/);
    await page.reload();
    assert.match(await page.locator('.p-sidebar-balance').innerText(), /900/);
    await page.getByRole('link', { name: 'Support', exact: true }).click();
    await page.getByLabel('Sujet').fill('Question sur les crédits');
    await page.getByLabel('Votre message').fill('Comment consulter mon historique de transactions ?');
    await page.getByRole('button', { name: 'Créer un ticket' }).click();
    await page.waitForURL(/\/support\/\d+$/);
    await page.getByLabel('Votre réponse').fill('Je viens de trouver la page, merci.');
    await page.getByLabel('État après envoi').selectOption('closed');
    await page.getByRole('button', { name: 'Envoyer la réponse' }).click();
    await page.getByRole('status').filter({ hasText: 'Réponse enregistrée' }).waitFor();
    assert.match(await page.locator('.p-conversation').innerText(), /Je viens de trouver/);
    for (const route of ['wallet', 'transactions', 'notifications', 'profile', 'predictions']) {
        await page.goto(`${base}/${route}`);
        await page.locator('.p-page-heading h1').waitFor();
    }
    await page.goto(`${base}/markets`);
    await page.locator('.predict-card').first().waitFor();
    await mkdir('test-results', { recursive: true });
    await page.screenshot({ path: 'test-results/platform-desktop.png', fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Menu de navigation' }).click();
    await page.getByRole('link', { name: 'Mes positions' }).click();
    await page.waitForURL('**/positions');
    await page.locator('.ds-table tbody tr').first().waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Mobile overflow');
    await page.screenshot({ path: 'test-results/platform-mobile.png', fullPage: true });
    await page.goto(base);
    await page.locator('.landing-hero').waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Landing mobile overflow');
    await page.screenshot({ path: 'test-results/platform-landing-mobile.png', fullPage: true });
    for (const width of [320,375,390,430,768,1024,1280,1366,1440,1920]) {
        await page.setViewportSize({width,height:900});
        for (const route of ['', 'dashboard','markets','positions','wallet','transactions','creator','support','profile','help']) {
            await page.goto(`${base}/${route}`);
            await page.locator('h1').first().waitFor();
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Overflow ${route} at ${width}`);
            assert.equal(await page.locator('body').innerText().then(t=>t.length>80), true, `Empty ${route} at ${width}`);
        }
        if ([390,1440].includes(width)) {
            for (const route of ['', 'dashboard','markets','wallet']) {
                await page.goto(`${base}/${route}`);
                await page.locator('h1').first().waitFor();
                await page.screenshot({path:`test-results/redesign-${route||'home'}-${width}.png`,fullPage:true});
            }
        }
    }
    await page.setViewportSize({width:390,height:844});
    await page.goto(marketUrl);
    await page.getByRole('button',{name:'Prendre position'}).click();
    await page.getByRole('dialog',{name:'Votre position',exact:true}).waitFor();
    await page.getByRole('dialog',{name:'Votre position',exact:true}).getByLabel('Mise en XAF').fill('100');
    await page.getByRole('dialog',{name:'Votre position',exact:true}).getByRole('button',{name:'Confirmer ma position'}).click();
    await page.getByRole('dialog',{name:'Vérifier avant de confirmer'}).waitFor();
    await page.screenshot({path:'test-results/redesign-order-mobile.png',fullPage:true});
    await page.getByRole('button',{name:'Revenir',exact:true}).click();
    await page.getByRole('dialog',{name:'Votre position',exact:true}).getByRole('button',{name:'Fermer',exact:true}).click();
    const removed = await context.request.get(`${base}/demo`);
    assert.equal(removed.status(), 404);
    await page.goto(base);
    assert.equal(await page.locator('[href="/demo"]').count(), 0);
    assert.equal(await page.locator('#phone-dialer-view').count(), 0);
    assert.doesNotMatch(await page.locator('body').innerText(), /démonstration|crédits fictifs|12M\+/i);
    assert.deepEqual(errors, []);
    console.log('PASS: required country/currency, dependent options, RDC/CDF wallet persistence and payment isolation, registration responsive, login, deposit, market creation, moderation, purchase, persistence, support, pages, mobile, official landing, no synthetic funding endpoint, no JS errors.');
} catch (error) {
    await mkdir('test-results', { recursive: true });
    if (page) await page.screenshot({ path: 'test-results/platform-failure.png', fullPage: true });
    console.error(errors, output.slice(-3000));
    throw error;
} finally {
    if (browser) await browser.close();
    server.kill('SIGTERM');
    await new Promise(resolve => server.once('exit', resolve));
    await rm(temp, { recursive: true, force: true });
}
