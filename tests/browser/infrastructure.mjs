import assert from 'node:assert/strict';
import { mkdtemp, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { chromium } from 'playwright';
const temp=await mkdtemp(join(tmpdir(),'predic-e2e-infrastructure-'));
const database=join(temp,'database.sqlite');await writeFile(database,'');
const env={...process.env,APP_ENV:'testing',APP_CONFIG_CACHE:join(temp,'unused-config.php'),APP_DEBUG:'false',DB_CONNECTION:'sqlite',DB_DATABASE:database,DB_URL:'',DATABASE_URL:'',CACHE_STORE:'array',SESSION_DRIVER:'database',QUEUE_CONNECTION:'database',NDSGPAY_ENABLED:'true',NDSGPAY_SECRET_KEY:'infra-test-secret',NDSGPAY_WEBHOOK_SECRET:'infra-test-hook',NDSGPAY_CAPABILITIES:JSON.stringify([{country:'GAB',currency:'XAF',correspondent:'AIRTEL_GAB',calling_code:'241',national_lengths:[8]}]),FX_REFRESH_ENABLED:'false'};
for(const args of [['artisan','migrate','--force'],['tests/browser/infrastructure-fixtures.php']]){const r=spawnSync('php',args,{env,encoding:'utf8'});assert.equal(r.status,0,r.stdout+r.stderr);}
const socket=createServer();await new Promise((resolve,reject)=>{socket.once('error',reject);socket.listen(0,'127.0.0.1',resolve);});const port=socket.address().port;await new Promise(resolve=>socket.close(resolve));
const base=`http://127.0.0.1:${port}`;
const server=spawn('php',['-S',`127.0.0.1:${port}`,'../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],{env,cwd:join(process.cwd(),'public'),stdio:'ignore'});
let browser;
try{
 for(let i=0;i<60;i++){try{if((await fetch(`${base}/up`)).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({channel:'chrome',headless:true});
 const context=await browser.newContext({viewport:{width:1440,height:1100},reducedMotion:'reduce'});
 const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(12000);
 await page.goto(`${base}/login`);await page.getByLabel('Adresse e-mail').fill('superadmin@example.invalid');await page.getByLabel('Mot de passe',{exact:true}).fill('infra-password-123');await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/superadmin');await page.locator('.workspace-splash').waitFor({state:'detached'});
 assert.equal(await page.locator('.infra-metric').count(),4);
 await page.screenshot({path:'/private/tmp/predicafrica-infrastructure-1440.png',fullPage:true});
 const save=async(reason,revision)=>{await page.getByLabel('Justification',{exact:true}).fill(reason);await page.getByLabel('Mot de passe actuel',{exact:true}).fill('infra-password-123');await page.getByRole('button',{name:'Enregistrer les modifications',exact:true}).click();await page.locator('.infra-save').getByText(`Révision ${revision}`,{exact:false}).waitFor();};
 await page.getByLabel('Accepter les dépôts',{exact:false}).uncheck();await save('Pause des dépôts pour vérification',1);assert.match(await page.locator('.infra-live').innerText(),/Dépôts suspendus/);
 await page.getByLabel('Minimum',{exact:true}).fill('200');await save('Modification du seuil de dépôt',2); // second write proves revision refresh
 await page.getByRole('button',{name:/^Opérateurs/}).click();assert.equal(await page.locator('.infra-operator').count(),24);await page.locator('.infra-operator').filter({hasText:'MOOV_GAB'}).getByRole('checkbox').check();await save('Activation Moov Gabon de test',3);
 await page.setViewportSize({width:390,height:844});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:'/private/tmp/predicafrica-infrastructure-390.png',fullPage:true});
 await page.getByRole('button',{name:'Commandes',exact:true}).click();await page.getByLabel('Maintenance de la plateforme',{exact:false}).check();await save('Maintenance générale de test',4);
 const guest=await browser.newContext();assert.equal((await guest.request.get(`${base}/register`)).status(),503);assert.equal((await guest.request.get(`${base}/login`)).status(),200);
 await page.getByLabel('Maintenance de la plateforme',{exact:false}).uncheck();await page.getByLabel('Accepter les dépôts',{exact:false}).check();await save('Réouverture après contrôle',5);assert.equal((await guest.request.get(`${base}/register`)).status(),200);
 await page.getByRole('button',{name:'Journal',exact:true}).click();assert.equal(await page.locator('.infra-event').count(),5);assert.ok(!(await page.content()).includes('infra-test-secret'));
 await guest.close();assert.deepEqual(errors,[]);console.log('PASS superadmin UI: login, immediate deposit pause, repeated revision-guarded updates, 24 operators, maintenance/reopening, audit and mobile/desktop. Isolated DB only.');
}finally{if(browser)await browser.close();server.kill('SIGTERM');await new Promise(r=>server.once('exit',r));await rm(temp,{recursive:true,force:true});}
