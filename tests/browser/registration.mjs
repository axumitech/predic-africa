import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';

// Own temporary DB; no account or provider call on the deployed application.
const temp = await mkdtemp(join(tmpdir(), 'predic-e2e-registration-'));
const database = join(temp, 'database.sqlite');
await writeFile(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_CONFIG_CACHE: join(temp, 'unused-config.php'), APP_DEBUG: 'false', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', DATABASE_URL: '', CACHE_STORE: 'array', SESSION_DRIVER: 'database', NDSGPAY_ENABLED: 'false', FX_REFRESH_ENABLED: 'false' };
const migration = spawnSync('php', ['artisan', 'migrate', '--force'], {env, encoding:'utf8'});
assert.equal(migration.status, 0, migration.stdout + migration.stderr);
const socket = createServer();
await new Promise((resolve, reject) => { socket.once('error', reject); socket.listen(0, '127.0.0.1', resolve); });
const port = socket.address().port;
await new Promise(resolve => socket.close(resolve));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-S', `127.0.0.1:${port}`, '../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'], {env, cwd:join(process.cwd(),'public'), stdio:'ignore'});
let browser;
try {
    let ready = false;
    for (let i=0; i<60; i++) {
        try { if ((await fetch(`${base}/up`)).ok) {ready=true; break;} } catch {}
        await new Promise(resolve=>setTimeout(resolve,100));
    }
    assert.ok(ready);
    browser = await chromium.launch({channel:'chrome',headless:true});
    const page = await browser.newPage({viewport:{width:390,height:844}});
    const errors = [];
    page.on('pageerror', error=>errors.push(error.message));
    await page.goto(`${base}/register`);
    const country = page.getByLabel('Pays de résidence', {exact:true});
    const phone = page.getByLabel('Numéro de téléphone', {exact:true});
    await country.waitFor({state:'visible'});
    assert.equal(await country.locator('option').count(),13); // placeholder plus 12 countries
    assert.ok(await phone.isDisabled());
    await country.selectOption('GAB');
    assert.equal(await page.locator('.pa-register-phone-prefix').innerText(),'+241');
    await phone.fill('77000000');
    await country.selectOption('COD');
    assert.equal(await phone.inputValue(),'');
    assert.equal(await page.locator('.pa-register-phone-prefix').innerText(),'+243');
    await page.getByLabel('Devise du portefeuille',{exact:true}).selectOption('CDF');
    await page.getByLabel('Nom complet',{exact:true}).fill('Compte téléphone de test');
    await page.getByLabel('Adresse e-mail',{exact:true}).fill('phone@example.invalid');
    await page.getByLabel('Mot de passe',{exact:true}).fill('registration-password-123');
    await page.getByLabel('Confirmer le mot de passe',{exact:true}).fill('registration-password-123');
    await page.getByRole('button',{name:'Créer mon compte'}).click();
    assert.equal(await phone.evaluate(node=>node.validity.valueMissing),true);
    await phone.fill('+24177000000');
    await page.getByRole('button',{name:'Créer mon compte'}).click();
    await page.locator('#pa-register-phone-error').waitFor();
    assert.match(await page.locator('#pa-register-phone-error').innerText(),/pays sélectionné/);
    await phone.fill('081 000 0000');
    for(const width of [390,1440]) {
        await page.setViewportSize({width,height:900});
        assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
        await page.screenshot({path:`/private/tmp/predicafrica-registration-phone-${width}.png`,fullPage:true});
    }
    await page.getByRole('button',{name:'Créer mon compte'}).click();
    await page.waitForURL('**/dashboard');
    assert.deepEqual(errors,[]);
    console.log('PASS registration: 12 countries, prefix, reset, required phone, server validation, signup CDF, mobile and desktop. Isolated DB only.');
} finally {
    if(browser) await browser.close();
    server.kill('SIGTERM');
    await new Promise(resolve=>server.once('exit',resolve));
    await rm(temp,{recursive:true,force:true});
}
