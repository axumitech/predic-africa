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
    assert.deepEqual(errors, []);
    php(['artisan', 'ledger:reconcile']); php(['artisan', 'payments:audit']);
    console.log('Wallet transfer desktop/mobile, quote expiry, balances and ledger checks passed.');
} catch (e) { console.error(output.slice(-5000)); throw e; }
finally { await browser?.close(); server.kill(); }
