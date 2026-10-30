import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-ux-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: join(temp, 'unused-config.php'), APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', AI_PIPELINE_ENABLED: 'false', NDSGPAY_ENABLED: 'false' };
const php = args => {
    const result = spawnSync('php', args, { env, encoding: 'utf8' });
    assert.equal(result.status, 0, result.stdout + result.stderr);
    return result.stdout;
};
php(['artisan', 'migrate', '--force']);
const fixture = JSON.parse(php(['tests/browser/ux-fixtures.php']));
const socket = createServer();
await new Promise((resolve, reject) => { socket.once('error', reject); socket.listen(0, '127.0.0.1', resolve); });
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], { env, cwd: join(process.cwd(), 'public'), stdio: 'pipe' });
let output = '';
server.stdout.on('data', c => { output += c; });
server.stderr.on('data', c => { output += c; });
const directory = 'test-results/ux';
await mkdir(directory, { recursive: true });
let browser;
const errors = [];
const checks = [];
let page;
async function login(email) {
    await page.goto(`${base}/login`);
    await page.getByLabel('Adresse e-mail').fill(email);
    await page.getByLabel('Mot de passe', { exact: true }).fill('ux-password-123');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.waitForURL(/\/(dashboard|admin)$/);
}
async function audit(role, routes) {
    if (process.argv.includes('--interactions')) return;
    for (const width of [360, 390, 430, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        for (const route of routes) {
            const response = await page.goto(base + route);
            await page.locator('h1').first().waitFor();
            if (await page.locator('.workspace-splash').count()) {
                await page.getByRole('button', { name: 'Passer l’animation' }).click();
                await page.locator('.workspace-splash').waitFor({ state: 'detached' });
            }
            await page.evaluate(() => document.fonts.ready);
            const info = await page.evaluate(() => ({
                overflow: document.documentElement.scrollWidth > innerWidth + 1,
                width: innerWidth, scroll: document.documentElement.scrollWidth,
                bodyOverflow: getComputedStyle(document.body).overflowX,
                headings: [...document.querySelectorAll('h1')].map(el => el.textContent),
            }));
            assert.equal(info.overflow, false, `${role} ${route} ${width}: ${JSON.stringify(info)}`);
            assert.equal(info.bodyOverflow === 'hidden' || info.bodyOverflow === 'clip', false, 'Page overflow must not be masked');
            assert.equal(info.headings.length, 1, `${role} ${route}: single page heading`);
            const name = `${role}-${route.replaceAll('/', '_').replaceAll('?', '_') || 'home'}-${width}`;
            await page.screenshot({ path: `${directory}/${name}.png`, fullPage: true });
            checks.push({ role, route, width, status: response.status(), screenshot: `${name}.png`, ...info });
        }
    }
}
async function focusInsideDialog() {
    assert.equal(await page.evaluate(() => document.querySelector('dialog[open]:last-of-type')?.contains(document.activeElement)), true);
    for (let i = 0; i < 12; i++) {
        await page.keyboard.press(i < 6 ? 'Tab' : 'Shift+Tab');
        assert.equal(await page.evaluate(() => [...document.querySelectorAll('dialog[open]')].at(-1)?.contains(document.activeElement)), true, 'Dialog focus escaped');
    }
}
try {
    for (let i = 0; i < 60; i++) {
        try { if ((await fetch(base)).ok) break; } catch {}
        await new Promise(resolve => setTimeout(resolve, 150));
    }
    browser = await chromium.launch({ channel: 'chrome', headless: true });
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    page = await context.newPage();
    page.setDefaultTimeout(12000);
    page.on('pageerror', e => errors.push(e.message));
    await context.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, r => r.abort());
    await audit('public', ['/', '/login', '/register', '/terms', '/privacy', '/data', '/help', '/page-inconnue-ux']);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base);
    await page.locator('.hero-title').waitFor();
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement?.textContent), 'Aller au contenu');
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(() => document.activeElement?.id), 'main-content');
    await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
    await page.getByRole('dialog', { name: 'Navigation', exact: true }).waitFor();
    await focusInsideDialog();
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog[open]').count(), 0);
    assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    await page.getByRole('button', { name: 'English', exact: true }).click();
    assert.match(await page.locator('h1').innerText(), /prediction marketplace/);
    await page.getByRole('button', { name: 'Français', exact: true }).click();
    const faq = page.locator('#faq summary').first();
    await faq.focus();
    await page.keyboard.press('Enter');
    assert.equal(await faq.evaluate(el => el.parentElement.open), true);
    await page.goto(`${base}/help`);
    const toc = page.getByRole('navigation', { name: 'Sommaire' }).getByRole('link').first();
    await toc.focus();
    await page.keyboard.press('Enter');
    await page.waitForURL('**/help#section-0');
    assert.ok((await page.locator('#section-0').boundingBox()).y >= 70, 'Document heading is hidden under its header');
    await page.goto(`${base}/login`);
    await page.getByLabel('Adresse e-mail').fill('unknown@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('wrong-password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await page.getByRole('alert').first().waitFor();
    await page.screenshot({ path: `${directory}/login-validation-390.png`, fullPage: true });
    await login('ux-trader@example.test');
    await audit('trader', ['/dashboard', '/markets', `/markets/${fixture.market}`, `/markets/${fixture.longMarket}`, `/markets/${fixture.resolvedMarket}`, '/positions', '/transactions', '/wallet', '/creator', '/support', `/support/${fixture.ticket}`, '/notifications', '/profile', '/predictions', '/admin']);
    // Between-breakpoint and landscape checks cover layout transitions and short sidebars.
    for (const [width, height] of [[639, 720], [767, 600], [1023, 768], [844, 390], [1024, 600], [1280, 800], [1920, 1080]]) {
        await page.setViewportSize({ width, height });
        for (const route of ['/dashboard', '/wallet', '/profile', `/support/${fixture.ticket}`]) {
            await page.goto(base + route);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `Between-width overflow ${route} ${width}`);
        }
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${base}/markets`);
    await page.getByRole('button', { name: 'Menu de navigation', exact: true }).click();
    await focusInsideDialog();
    await page.keyboard.press('Escape');
    assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Menu de navigation');
    await page.getByRole('button', { name: 'Filtres', exact: true }).click();
    await page.getByRole('dialog', { name: 'Filtrer les marchés' }).getByLabel('Catégorie').selectOption('Sport');
    await page.getByRole('dialog').getByRole('button', { name: 'Appliquer' }).click();
    await page.waitForURL(/category=Sport/);
    await page.getByRole('button', { name: 'Liste', exact: true }).click();
    assert.equal(await page.locator('.ds-table tbody tr').first().evaluate(el => getComputedStyle(el).display), 'block');
    await page.goto(`${base}/transactions`);
    const reference = page.locator('.p-link-button').first();
    await reference.click();
    await page.getByRole('dialog', { name: 'Détail de l’opération' }).waitFor();
    await focusInsideDialog();
    await page.keyboard.press('Escape');
    assert.equal(await reference.evaluate(el => el === document.activeElement), true);
    await page.goto(`${base}/markets/${fixture.market}`);
    await page.getByRole('button', { name: 'Prendre position', exact: false }).click();
    await page.getByRole('dialog', { name: 'Votre position', exact: true }).getByRole('button', { name: 'Confirmer ma position' }).click();
    await page.getByRole('dialog', { name: 'Vérifier avant de confirmer' }).waitFor();
    await page.screenshot({ path: `${directory}/nested-confirmation-390.png`, fullPage: true });
    const bottom = await page.locator('dialog[open]').last().locator('.ds-dialog__actions').boundingBox();
    assert.ok(bottom.y + bottom.height <= 845, 'Confirmation actions are outside viewport');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('dialog[open]').count(), 1);
    assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
    await page.keyboard.press('Escape');
    assert.equal(await page.evaluate(() => document.body.style.overflow), '');
    await page.goto(`${base}/profile`);
    await page.getByRole('button', { name: 'Afficher : Mot de passe actuel' }).click();
    assert.equal(await page.getByLabel('Mot de passe actuel', { exact: true }).getAttribute('type'), 'text');
    await page.getByLabel('Mot de passe actuel', { exact: true }).fill('incorrect-password');
    let finishRequest;
    const requestGate = new Promise(resolve => { finishRequest = resolve; });
    await page.route('**/profile', async route => {
        if (route.request().method() === 'POST') await requestGate;
        await route.continue();
    });
    const saveProfile = page.getByRole('button', { name: 'Enregistrer mon profil' });
    await saveProfile.click();
    await page.locator('.ds-spinner').waitFor();
    assert.equal(await page.getByRole('button', { name: 'Enregistrement…', exact: true }).isDisabled(), true);
    await page.getByRole('status').filter({ hasText: 'La réponse prend plus de temps' }).waitFor();
    await page.screenshot({ path: `${directory}/loading-slow-request-390.png`, fullPage: true });
    finishRequest();
    await page.getByRole('alert').first().waitFor();
    await page.unroute('**/profile');
    await page.screenshot({ path: `${directory}/profile-validation-390.png`, fullPage: true });
    await context.setOffline(true);
    await page.getByRole('status').filter({ hasText: 'Connexion interrompue' }).waitFor();
    await page.screenshot({ path: `${directory}/offline-390.png`, fullPage: true });
    await context.setOffline(false);
    await page.setViewportSize({ width: 1024, height: 600 });
    await page.goto(`${base}/dashboard`);
    const logout = await page.getByRole('button', { name: 'Se déconnecter', exact: true }).boundingBox();
    assert.ok(logout && logout.y + logout.height <= 600, 'Sidebar logout hidden on short screen');
    await page.getByRole('button', { name: 'Réduire la navigation' }).click();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await page.getByRole('button', { name: 'Déployer la navigation' }).click();
    await page.setViewportSize({ width: 768, height: 900 });
    await page.goto(`${base}/transactions`);
    await page.locator('h1').waitFor();
    await page.locator('.workspace-splash').waitFor({ state: 'detached' });
    await page.waitForFunction(() => document.activeElement?.id === 'workspace-content');
    const tableRegion = page.getByRole('region', { name: 'Opérations comptabilisées en XAF' });
    await tableRegion.focus();
    await page.keyboard.press('ArrowRight');
    await page.waitForFunction(() => document.querySelector('.ds-table-wrap').scrollLeft > 0);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await page.setViewportSize({ width: 1024, height: 600 });
    await page.goto(`${base}/dashboard`);
    await page.getByRole('button', { name: 'Se déconnecter', exact: true }).click();
    await login('ux-admin@example.test');
    await audit('admin', ['/admin', '/admin/markets', `/admin/markets/${fixture.pendingMarket}`, `/admin/markets/${fixture.market}`, '/admin/users', '/admin/pipeline', '/support', `/support/${fixture.ticket}`]);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${base}/admin/users`);
    const suspend = page.getByRole('button', { name: 'Suspendre', exact: true }).first();
    await suspend.click();
    await page.getByRole('dialog', { name: 'Suspendre cet accès ?' }).waitFor();
    await page.screenshot({ path: `${directory}/admin-confirmation-1440.png`, fullPage: true });
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Se déconnecter', exact: true }).click();
    await login('ux-empty@example.test');
    await audit('empty', ['/dashboard', '/positions', '/transactions', '/wallet', '/support', '/notifications']);
    assert.equal(await page.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches), true);
    assert.deepEqual(errors, []);
    await writeFile(`${directory}/${checks.length ? 'report' : 'interactions-report'}.json`, JSON.stringify({ browser: 'Chrome headless', physicalDevices: false, viewports: [360, 390, 430, 768, 1024, 1440], checks, errors, fixture }, null, 2));
    console.log(`PASS UX: ${checks.length} route/viewport checks; long and empty data; navigation, keyboard, focus, nested dialogs, filters, validation, offline, desktop collapse and landscape.`);
} catch (e) {
    if (page) await page.screenshot({ path: `${directory}/failure.png`, fullPage: true });
    console.error(errors, output.slice(-1500));
    throw e;
} finally {
    if (browser) await browser.close();
    server.kill('SIGTERM');
    await new Promise(resolve => server.once('exit', resolve));
    await rm(temp, { recursive: true, force: true });
}
