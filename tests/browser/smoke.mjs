import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { chromium } from 'playwright';

const browser = await chromium.launch({ channel: 'chrome', headless: true });
const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8010';
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
page.setDefaultTimeout(12000);
await page.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/, route => route.abort());
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('dialog', dialog => dialog.accept());
// Deterministic unavailable-pipeline path; HTTP behavior is covered by PHP tests.
await page.route('**/api/predictions', route => route.fulfill({ status: 503, json: { status: 'unavailable', data: [] } }));
await page.route('**/api/markets', route => route.fulfill({ status: 503, json: { status: 'unavailable', data: [] } }));
try {
    await page.goto(base + '/demo', { waitUntil: 'domcontentloaded' });
    await page.locator('#btn-enter-bourse').waitFor();
    await page.locator('#btn-enter-bourse').click();
    await page.locator('#btn-demo-trader').click();
    await page.locator('#view-dashboard.active').waitFor();
    await page.locator('#predictions-catalog .predict-card').first().waitFor();
    assert.equal((await page.locator('#user-balance').innerText()).replace(/\s/g, ''), '15000');

    await page.locator('[data-view="customer-support"]').click();
    await page.locator('#btn-open-new-ticket').click();
    await page.locator('#modal-new-ticket').waitFor({ state: 'visible' });
    await page.locator('#new-ticket-subject').fill('Migration Laravel');
    await page.locator('#new-ticket-desc').fill('Vérifier que la création et la consultation fonctionnent.');
    await page.locator('#form-new-ticket button[type="submit"]').click();
    await page.waitForFunction(() => (JSON.parse(localStorage.getItem('pa_user_tickets')) || []).some(t => t.subject === 'Migration Laravel'));
    await page.locator('#modal-new-ticket').waitFor({ state: 'hidden' });
    assert.match(await page.locator('#user-tickets-tbody').innerText(), /Migration Laravel/);

    await page.locator('[data-view="dashboard"]').click();
    await page.locator('#predictions-catalog .btn--odds').first().click();
    await page.locator('#bet-amount').fill('1000');
    await page.locator('#place-bet-submit-form button[type="submit"]').click();
    await page.waitForFunction(() => Number(localStorage.getItem('pa_balance')) === 14000);
    assert.equal(await page.evaluate(() => JSON.parse(localStorage.getItem('pa_user_bets')).length), 1);

    await page.reload();
    await page.locator('#btn-enter-bourse').click();
    await page.locator('#btn-demo-trader').click();
    await page.waitForFunction(() => document.getElementById('user-balance').textContent.replace(/\s/g, '') === '14000');
    await page.locator('#btn-logout-trigger').click();
    await page.locator('#btn-demo-admin').click();
    await page.locator('#view-backoffice.active').waitFor();
    await mkdir('test-results', { recursive: true });
    await page.screenshot({ path: 'test-results/admin-desktop.png', fullPage: true, animations: 'disabled' });
    await page.locator('#btn-logout-trigger').click();
    await page.locator('#btn-login-back-home').click();
    await page.setViewportSize({ width: 390, height: 844 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), true, 'Mobile layout overflows');
    await page.screenshot({ path: 'test-results/landing-mobile.png', fullPage: true, animations: 'disabled' });
    assert.deepEqual(errors, [], `Browser errors: ${errors.join('\n')}`);
    console.log('PASS: landing, trader, support, purchase, persistence, admin, mobile; no JS errors.');
} catch (error) {
    await mkdir('test-results', { recursive: true });
    await page.screenshot({ path: 'test-results/failure.png', fullPage: true, animations: 'disabled' });
    console.error('Browser errors:', errors);
    throw error;
} finally {
    await browser.close();
}
