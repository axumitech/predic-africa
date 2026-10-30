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
    AI_PIPELINE_ENABLED: 'false', FX_REFRESH_ENABLED: 'false', CURRENCYAPI_KEY: '', NDSGPAY_ENABLED: 'false' };
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
    await mkdir('test-results/mobile-menu', { recursive: true });
    for (const [width, height] of [[320, 568], [390, 844], [768, 600], [932, 430]]) {
        await page.setViewportSize({ width, height });
        const trigger = page.getByRole('button', { name: 'Menu de navigation', exact: true });
        await trigger.click();
        const menu = page.getByRole('dialog', { name: 'Menu de navigation', exact: true });
        await menu.waitFor();
        const layout = await menu.evaluate(el => {
            const rect = el.getBoundingClientRect();
            const body = el.querySelector('.ds-dialog__body');
            const links = [...el.querySelectorAll('.nav-menu__item')];
            return { contained: rect.left >= 0 && rect.top >= 0 && rect.right <= innerWidth + 1 && rect.bottom <= innerHeight + 1,
                noOverflow: body.scrollWidth <= body.clientWidth + 1,
                links: links.every(link => link.getBoundingClientRect().width >= body.clientWidth - 48 && getComputedStyle(link).flexDirection === 'row'),
                scrollable: body.scrollHeight > body.clientHeight };
        });
        assert.equal(layout.contained, true, `Menu bounds at ${width}x${height}`);
        assert.equal(layout.noOverflow, true, `Menu horizontal overflow at ${width}`);
        assert.equal(layout.links, true, `Menu link layout at ${width}`);
        if (height < 600) assert.equal(layout.scrollable, true);
        assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden');
        await menu.getByRole('link', { name: 'Mon profil', exact: true }).scrollIntoViewIfNeeded();
        const footer = await menu.getByRole('button', { name: 'Se déconnecter', exact: true }).boundingBox();
        assert.ok(footer.y >= 0 && footer.y + footer.height <= height + 1);
        await page.screenshot({ path: `test-results/mobile-menu/menu-${width}x${height}.png` });
        await page.keyboard.press('Escape');
        assert.equal(await menu.count(), 0);
        assert.equal(await trigger.getAttribute('aria-expanded'), 'false');
        assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('aria-label')), 'Menu de navigation');
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.getByRole('button', { name: 'Menu de navigation', exact: true }).click();
    await page.getByRole('dialog', { name: 'Menu de navigation', exact: true }).getByRole('link', { name: 'Portefeuille', exact: true }).click();
    await page.waitForURL(/\/wallet$/);
    assert.equal(await page.getByRole('dialog', { name: 'Menu de navigation', exact: true }).count(), 0);
    await page.setViewportSize({ width: 1440, height: 1000 });

    await page.locator('.workspace-splash').waitFor({ state: 'detached' });
    await mkdir('test-results/wallet-transfers', { recursive: true });
    assert.match(await page.getByRole('group', { name: 'Portefeuille XAF', exact: true }).innerText(), /Compte principal/);
    await page.screenshot({ path: 'test-results/wallet-transfers/carousel-desktop.png', fullPage: true });
    await page.getByRole('button', { name: 'Portefeuille suivant', exact: true }).click();
    await page.getByRole('heading', { name: 'Ajouter un portefeuille', exact: true }).waitFor();
    await page.getByLabel('Devise du nouveau portefeuille').selectOption('CDF');
    await page.getByRole('button', { name: 'Créer un portefeuille' }).click();
    await page.getByText('Portefeuille créé et sélectionné.', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Portefeuille précédent', exact: true }).click();
    await page.getByText('Portefeuille sélectionné.', { exact: true }).waitFor();
    assert.equal(await page.locator('.p-wallet-summary .p-balance-card').count(), 0);
    const actions = page.getByRole('group', { name: 'Actions du portefeuille XAF' });
    await actions.getByRole('button', { name: 'Retirer' }).click();
    await page.getByText('Les retraits Mobile Money ne sont pas disponibles actuellement.', { exact: true }).waitFor();
    await actions.getByRole('button', { name: 'Déposer', exact: true }).click();
    await page.getByRole('heading', { name: 'Alimenter mon portefeuille', exact: true }).waitFor();
    await actions.getByRole('button', { name: 'Transférer', exact: true }).click();
    await page.getByLabel('Montant en XAF', { exact: true }).fill('100');
    await page.getByRole('button', { name: 'Voir le taux et le montant reçu' }).click();
    const dialog = page.getByRole('dialog', { name: 'Confirmer le transfert' });
    await dialog.waitFor();
    assert.match(await dialog.innerText(), /1 XAF ≈ 4 CDF/);
    assert.match(await dialog.innerText(), /400,00 CDF/);
    await mkdir('test-results/wallet-transfers', { recursive: true });
    await page.screenshot({ path: 'test-results/wallet-transfers/confirmation-desktop.png', fullPage: true });
    await dialog.getByRole('button', { name: 'Transférer', exact: true }).click();
    await page.getByText('Transfert entre portefeuilles effectué.', { exact: true }).waitFor();
    assert.match(await page.locator('.p-wallet-slide[aria-hidden="false"] .p-wallet-card-amount').innerText(), /4\s?900\s*XAF/);
    await page.getByRole('button', { name: 'Portefeuille suivant', exact: true }).click();
    await page.getByText('Portefeuille sélectionné.', { exact: true }).waitFor();
    assert.match(await page.locator('.p-wallet-slide[aria-hidden="false"] .p-wallet-card-amount').innerText(), /400,00\s*CDF/);
    await page.getByRole('cell', { name: 'Transfert entrant', exact: true }).waitFor();
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'test-results/wallet-transfers/carousel-mobile.png', fullPage: true });
    const carousel = page.getByRole('region', { name: 'Choix du portefeuille' });
    await carousel.focus();
    await page.keyboard.press('ArrowRight');
    await page.getByRole('heading', { name: 'Ajouter un portefeuille', exact: true }).waitFor();
    await carousel.dispatchEvent('pointerdown', { clientX: 80, pointerType: 'touch' });
    await carousel.dispatchEvent('pointerup', { clientX: 250, pointerType: 'touch' });
    assert.equal(await page.getByRole('button', { name: 'Choisir le portefeuille CDF' }).getAttribute('aria-current'), 'true');
    await page.getByRole('group', { name: 'Actions du portefeuille CDF' }).getByRole('button', { name: 'Transférer', exact: true }).click();
    await page.clock.install();
    await page.getByLabel('Montant en CDF', { exact: true }).fill('4');
    await page.getByRole('button', { name: 'Voir le taux et le montant reçu' }).click();
    await dialog.waitFor();
    assert.match(await dialog.innerText(), /1 XAF/);
    await page.screenshot({ path: 'test-results/wallet-transfers/confirmation-mobile.png', fullPage: true });
    await page.clock.fastForward(61000);
    await dialog.getByText('Le devis a expiré. Actualisez le taux avant de confirmer.', { exact: true }).waitFor();
    assert.equal(await dialog.getByRole('button', { name: 'Transférer', exact: true }).isDisabled(), true);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await page.keyboard.press('Escape');
    await page.goto(base + '/markets');
    await page.getByText('Marché de test du portefeuille actif', { exact: true }).waitFor();
    assert.match(await page.locator('.p-market-minimum').innerText(), /400,00 CDF/);
    assert.match(await page.locator('.p-market-facts').innerText(), /CDF/);
    assert.equal(await page.locator('.app-header__actions a[href="/notifications"], .app-header__actions a[href="/profile"]').count(), 0);
    await page.getByLabel('Changer de portefeuille', { exact: true }).selectOption('XAF');
    await page.getByText('Portefeuille sélectionné.', { exact: true }).waitFor();
    assert.match(await page.locator('.p-market-minimum').innerText(), /100 XAF/);
    assert.match(await page.locator('.p-header-wallet-balance').innerText(), /4\s?900.*XAF/s);
    await page.getByLabel('Changer de portefeuille', { exact: true }).selectOption('CDF');
    await page.getByText('Portefeuille sélectionné.', { exact: true }).waitFor();
    await page.locator('.p-header-wallet-balance').click();
    await page.waitForURL(/\/wallet$/);
    for (const width of [320, 390, 768, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, `Header overflow ${width}`);
        const header = await page.locator('.p-topbar').evaluate(el => ({ width: el.clientWidth, scroll: el.scrollWidth }));
        assert.ok(header.scroll <= header.width + 1, `Header content overflow ${width}`);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: 'test-results/wallet-transfers/header-wallet-mobile.png', fullPage: true });
    const manifest = await (await context.request.get(base + '/manifest.webmanifest')).json();
    assert.equal(manifest.name, 'PredicAfrica');
    assert.equal(manifest.display, 'standalone');
    for (const icon of manifest.icons) assert.equal((await context.request.get(base + icon.src)).status(), 200);
    assert.equal(await page.locator('link[rel="apple-touch-icon"]').getAttribute('href'), '/icons/apple-touch-icon.png');
    await page.evaluate(async () => { await navigator.serviceWorker.ready; });
    await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    const cdp = await context.newCDPSession(page);
    const manifestCheck = await cdp.send('Page.getAppManifest');
    assert.deepEqual(manifestCheck.errors, []);
    // Exercise the install button without actually installing software on the host.
    await page.evaluate(() => {
        window.pwaTestPromptCount = 0;
        const event = new Event('beforeinstallprompt', { cancelable: true });
        event.prompt = async () => { window.pwaTestPromptCount++; };
        event.userChoice = Promise.resolve({ outcome: 'dismissed' });
        window.dispatchEvent(event);
    });
    await page.getByRole('button', { name: 'Installer l’application', exact: true }).click();
    assert.equal(await page.evaluate(() => window.pwaTestPromptCount), 1);
    await page.getByRole('button', { name: 'Installer l’application', exact: true }).click();
    await page.getByRole('dialog', { name: 'Installer PredicAfrica', exact: true }).waitFor();
    await page.screenshot({ path: 'test-results/wallet-transfers/pwa-install-mobile.png' });
    await page.keyboard.press('Escape');
    await page.evaluate(() => window.dispatchEvent(new Event('appinstalled')));
    await page.getByRole('button', { name: 'Installer l’application', exact: true }).waitFor({ state: 'detached' });
    const cachedPaths = await page.evaluate(async () => {
        const keys = await caches.keys();
        const paths = [];
        for (const key of keys.filter(key => key.startsWith('predicafrica-pwa-'))) {
            const cache = await caches.open(key);
            paths.push(...(await cache.keys()).map(request => new URL(request.url).pathname));
        }
        return paths;
    });
    assert.ok(cachedPaths.includes('/offline.html'));
    assert.ok(cachedPaths.every(path => path === '/offline.html' || path.startsWith('/icons/') || path === '/PA_Favicon_512x512.svg'));
    await context.setOffline(true);
    await page.goto(base + '/wallet');
    await page.getByRole('heading', { name: 'Vous êtes hors connexion', exact: true }).waitFor();
    assert.equal(await page.locator('.p-header-wallet-balance, .p-wallet-card-amount').count(), 0);
    await page.screenshot({ path: 'test-results/wallet-transfers/pwa-offline-mobile.png' });
    await context.setOffline(false);
    await page.getByRole('link', { name: 'Réessayer', exact: true }).click();
    await page.waitForURL(/\/dashboard$/);
    await page.getByRole('heading', { name: /Bonjour/ }).waitFor();
    await page.goto(base + '/profile');
    await page.getByText('+24177000000', { exact: true }).waitFor();
    await page.getByLabel('Changer de portefeuille', { exact: true }).selectOption('XAF');
    await page.getByText('Portefeuille sélectionné.', { exact: true }).waitFor();
    await page.getByLabel(/^Pays de résidence/).selectOption('GAB');
    await page.getByLabel('Portefeuille par défaut', { exact: true }).selectOption('CDF');
    await page.getByLabel('Mot de passe actuel', { exact: true }).fill('transfer-password-123');
    await page.getByRole('button', { name: 'Enregistrer mon profil', exact: true }).click();
    await page.getByText('Profil enregistré.', { exact: true }).waitFor();
    assert.equal(await page.getByLabel(/^Pays de résidence/).count(), 0);
    await page.getByText('Gabon', { exact: true }).waitFor();
    assert.equal(await page.getByLabel('Portefeuille par défaut', { exact: true }).inputValue(), 'CDF');
    assert.equal(await page.getByLabel('Changer de portefeuille', { exact: true }).inputValue(), 'XAF');
    await page.screenshot({ path: 'test-results/wallet-transfers/profile-preferences-mobile.png', fullPage: true });
    await page.getByRole('button', { name: 'Menu de navigation', exact: true }).click();
    await page.getByRole('dialog', { name: 'Menu de navigation', exact: true }).getByRole('button', { name: 'Se déconnecter', exact: true }).click();
    await page.waitForURL(base + '/');
    await page.goto(base + '/login');
    await page.getByLabel('Adresse e-mail').fill('transfers@example.test');
    await page.getByLabel('Mot de passe', { exact: true }).fill('transfer-password-123');
    await page.getByRole('button', { name: 'Se connecter', exact: true }).click();
    await page.waitForURL(/\/dashboard$/);
    assert.equal(await page.getByLabel('Changer de portefeuille', { exact: true }).inputValue(), 'CDF');
    await page.goto(base + '/notifications');
    const preference = page.getByRole('switch', { name: 'Recevoir les notifications', exact: true });
    assert.equal(await preference.getAttribute('aria-checked'), 'true');
    await preference.click();
    await page.getByText('Notifications désactivées.', { exact: true }).waitFor();
    assert.equal(await preference.getAttribute('aria-checked'), 'false');
    await page.reload();
    const skipIntro = page.getByRole('button', { name: /Passer l.animation/ });
    if (await skipIntro.count()) { await skipIntro.click(); await page.clock.runFor(500); }
    await page.locator('.workspace-splash').waitFor({ state: 'detached' });
    assert.equal(await preference.getAttribute('aria-checked'), 'false');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
    await page.screenshot({ path: 'test-results/wallet-transfers/notifications-preference-mobile.png', fullPage: true });
    await preference.click();
    await page.getByText('Notifications activées.', { exact: true }).waitFor();
    assert.equal(await preference.getAttribute('aria-checked'), 'true');
    await mkdir('test-results/pwa-popup', { recursive: true });
    const devices = [
        ['iphone', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1', 'Sur iPhone ou iPad', 'Partager'],
        ['android', 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0.0.0 Mobile Safari/537.36', 'Sur Android', 'Chrome'],
        ['mac', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15', 'Sur Mac avec Safari', 'Ajouter au Dock'],
    ];
    for (const [device, userAgent, label, instruction] of devices) {
        const deviceContext = await browser.newContext({ userAgent, viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
        await deviceContext.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, r => r.abort());
        const devicePage = await deviceContext.newPage();
        devicePage.setDefaultTimeout(10000);
        await devicePage.clock.install();
        await devicePage.goto(base + '/help');
        await devicePage.getByRole('button', { name: 'Installer l’application', exact: true }).waitFor();
        await devicePage.clock.runFor(7000);
        const popup = devicePage.getByRole('dialog', { name: 'Installer PredicAfrica', exact: true });
        await popup.waitFor();
        assert.match(await popup.innerText(), new RegExp(label));
        assert.match(await popup.innerText(), new RegExp(instruction));
        assert.equal(await devicePage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true);
        await devicePage.screenshot({ path: `test-results/pwa-popup/${device}.png` });
        await popup.getByRole('button', { name: 'Fermer', exact: true }).click();
        await devicePage.reload();
        await devicePage.clock.runFor(7000);
        assert.equal(await popup.count(), 0, `Dismissal remembered for ${device}`);
        await deviceContext.close();
    }
    const standaloneContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
    await standaloneContext.addInitScript(() => { Object.defineProperty(navigator, 'standalone', { value: true }); });
    const standalonePage = await standaloneContext.newPage();
    await standalonePage.clock.install();
    await standalonePage.goto(base + '/help');
    await standalonePage.getByRole('heading', { level: 1 }).waitFor();
    await standalonePage.clock.runFor(8000);
    assert.equal(await standalonePage.getByRole('dialog', { name: 'Installer PredicAfrica', exact: true }).count(), 0);
    assert.equal(await standalonePage.getByRole('button', { name: 'Installer l’application', exact: true }).count(), 0);
    await standaloneContext.close();
    assert.deepEqual(errors, []);
    php(['artisan', 'ledger:reconcile']); php(['artisan', 'payments:audit']);
    console.log('Wallet transfer desktop/mobile, quote expiry, balances and ledger checks passed.');
} catch (e) { console.error(output.slice(-5000)); throw e; }
finally { await browser?.close(); server.kill(); }
