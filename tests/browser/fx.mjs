import assert from 'node:assert/strict';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-fx-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', LOG_CHANNEL: 'stderr', APP_CONFIG_CACHE: join(temp, 'unused-config.php'),
    DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', QUEUE_CONNECTION: 'sync',
    AI_PIPELINE_ENABLED: 'false', CURRENCYAPI_KEY: '', NDSGPAY_ENABLED: 'true', NDSGPAY_SECRET_KEY: 'test-secret',
    NDSGPAY_WEBHOOK_SECRET: 'test-hook', NDSGPAY_MERCHANT_ID: 'test-merchant', NDSGPAY_BASE_URL: 'https://ndsgpay-backend.vercel.app/api',
    NDSGPAY_CAPABILITIES: JSON.stringify([{country:'COD',currency:'CDF',correspondent:'AIRTEL_COD',calling_code:'243',national_lengths:[9]}]) };
const php = args => { const result = spawnSync('php', args, {env, encoding:'utf8'}); assert.equal(result.status,0,result.stdout+result.stderr); return result.stdout; };
php(['artisan','migrate','--force']);
const fixture = JSON.parse(php(['tests/browser/fx-fixtures.php']));
const socket = createServer();
await new Promise((resolve,reject) => { socket.once('error',reject); socket.listen(0,'127.0.0.1',resolve); });
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php',['-S',`127.0.0.1:${port}`,'../tests/browser/fx-router.php'],{cwd:join(process.cwd(),'public'),env,stdio:'pipe'});
let output=''; server.stdout.on('data',c=>output+=c); server.stderr.on('data',c=>output+=c);
let browser, page;
const errors=[];
await mkdir('test-results/fx',{recursive:true});
try {
    for (let i=0;i<60;i++) { try { if ((await fetch(base)).ok) break; } catch {} await new Promise(r=>setTimeout(r,150)); }
    browser = await chromium.launch({channel:'chrome',headless:true});
    const context = await browser.newContext({viewport:{width:1440,height:1000},reducedMotion:'reduce'});
    await context.route(/https:\/\/(fonts\.googleapis|fonts\.gstatic|cdnjs\.cloudflare)\.com\/.*/,r=>r.abort());
    page=await context.newPage(); page.setDefaultTimeout(12000); page.on('pageerror',e=>errors.push(e.message));
    const navigate=async path=>{await page.goto(base+path); await page.locator('h1').waitFor(); await page.locator('.workspace-splash').waitFor({state:'detached'});};
    const login=async email=>{await page.goto(base+'/login'); await page.getByLabel('Adresse e-mail').fill(email); await page.getByLabel('Mot de passe',{exact:true}).fill('fx-password-123'); await page.getByRole('button',{name:'Se connecter',exact:true}).click(); await page.waitForURL(/\/(dashboard|admin)$/); await page.locator('h1').waitFor(); await page.locator('.workspace-splash').waitFor({state:'detached'});};
    await login('fx-cdf@example.test');
    await navigate('/wallet');
    await page.getByLabel('Montant en CDF').fill('1234.56');
    await page.getByLabel('Numéro Mobile Money').fill('243810000000');
    await page.getByRole('button',{name:'Continuer le dépôt'}).click();
    await page.getByRole('dialog').filter({hasText:'Vérifier avant de confirmer'}).waitFor();
    await page.getByRole('button',{name:'Valider définitivement'}).click();
    await page.getByRole('button',{name:'Actualiser le statut'}).waitFor();
    assert.match(await page.locator('.p-wallet-slide[aria-hidden="false"] .p-wallet-card-amount').innerText(),/0,00\s*CDF/);
    await page.getByRole('button',{name:'Actualiser le statut'}).click();
    await page.getByText('Succès confirmé',{exact:true}).waitFor();
    assert.match(await page.locator('.p-wallet-slide[aria-hidden="false"] .p-wallet-card-amount').innerText(),/1\s?234,56\s*CDF/);
    await page.screenshot({path:'test-results/fx/wallet-cdf.png',fullPage:true});
    await navigate(`/markets/${fixture.market}`);
    await page.evaluate(() => { document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'; });
    await page.clock.install();
    await page.getByLabel('Mise en CDF').fill('400.01');
    await page.getByRole('button',{name:'Confirmer ma position'}).click();
    const confirm=page.getByRole('dialog',{name:'Vérifier avant de confirmer'});
    await confirm.waitFor();
    assert.match(await confirm.innerText(),/400,01 CDF/);
    assert.match(await confirm.innerText(),/940,02 CDF/);
    assert.match(await confirm.innerText(),/100 XAF/);
    await page.screenshot({path:'test-results/fx/quote-cdf-desktop.png',fullPage:true});
    await page.clock.fastForward(61000);
    assert.equal(await confirm.getByRole('button',{name:'Valider définitivement'}).isDisabled(),true);
    await confirm.getByRole('button',{name:'Actualiser la confirmation'}).click();
    await page.waitForFunction(()=>document.querySelector('.p-quote-expiry')?.textContent?.includes('valable'));
    await confirm.getByRole('button',{name:'Valider définitivement'}).click();
    await page.waitForURL('**/positions');
    await page.getByRole('region',{name:'Positions personnelles'}).waitFor();
    assert.match(await page.getByRole('region',{name:'Positions personnelles'}).innerText(),/400,01 CDF/);
    assert.match(await page.getByRole('region',{name:'Positions personnelles'}).innerText(),/940,02 CDF/);
    for(const width of [360,390,768,1440]) {
        await page.setViewportSize({width,height:900});
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,`CDF positions overflow at ${width}`);
    }
    await page.setViewportSize({width:390,height:844});
    await navigate(`/markets/${fixture.market}`);
    await page.getByRole('button',{name:'Prendre position →'}).click();
    const order=page.getByRole('dialog',{name:'Votre position',exact:true});
    await order.getByLabel('Mise en CDF').fill('400.01');
    await order.getByRole('button',{name:'Confirmer ma position'}).click();
    await confirm.waitFor();
    const mobileConfirmation = await confirm.boundingBox();
    assert.ok(mobileConfirmation && mobileConfirmation.y >= 0 && mobileConfirmation.y + mobileConfirmation.height <= 845, 'Native quote confirmation must fit the mobile viewport');
    await page.screenshot({path:'test-results/fx/quote-cdf-mobile.png',fullPage:true});
    await confirm.getByRole('button',{name:'Revenir',exact:true}).click();
    await order.getByRole('button',{name:'Fermer',exact:true}).click();
    php(['tests/browser/fx-fixtures.php','stale']);
    await navigate(`/markets/${fixture.market}`);
    assert.match(await page.locator('.p-fx-notice').innerText(),/trop ancien/);
    await page.getByRole('button',{name:'Prendre position →'}).click();
    assert.equal(await order.getByRole('button',{name:'Confirmer ma position'}).isDisabled(),true);
    await order.getByRole('button',{name:'Fermer',exact:true}).click();
    await page.setViewportSize({width:1440,height:1000});
    await page.getByRole('button',{name:'Se déconnecter',exact:true}).click();
    php(['tests/browser/fx-fixtures.php','close']);
    await login('fx-admin@example.test');
    await navigate(`/admin/markets/${fixture.market}`);
    await page.getByLabel('Résultat officiel').selectOption('yes');
    await page.getByLabel('Source du résultat / justification').fill('Résultat officiel du scénario de test FX isolé.');
    await page.getByRole('button',{name:'Résoudre et mettre à jour les soldes'}).click();
    await page.getByRole('button',{name:'Valider définitivement'}).click();
    await page.getByRole('status').filter({hasText:'Résolution enregistrée'}).waitFor();
    php(['tests/browser/fx-fixtures.php','assert']);
    php(['artisan','ledger:reconcile']);
    await navigate('/admin');
    assert.match(await page.locator('.p-currency-balances').innerText(),/1\s?774,57 CDF/);
    assert.deepEqual(errors,[]);
    console.log('PASS FX: native CDF deposit verification, exact cents, live-rate display, quote expiration and refresh, native purchase, mobile confirmation, stale-rate blocking, locked native settlement, per-currency accounting. Providers fully simulated; temporary DB only.');
} catch(error) {
    if(page) await page.screenshot({path:'test-results/fx/failure.png',fullPage:true});
    console.error(errors, output.split('\n').filter(line=>line.includes('testing.ERROR')).slice(-2).join('\n'), output.slice(-2000)); throw error;
} finally {
    if(browser) await browser.close();
    server.kill('SIGTERM'); await new Promise(resolve=>server.once('exit',resolve));
    await rm(temp,{recursive:true,force:true});
}
