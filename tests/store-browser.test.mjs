import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdtempSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { renderInChrome } from './chrome.mjs';

const directory = mkdtempSync(join(tmpdir(), 'warung-store-browser-'));
execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/StoreOperationTest.php', '--filter=store controls render'], { env: { ...process.env, STORE_BROWSER_FIXTURES: directory } });
for (const width of [1440, 1024, 390]) {
    for (const role of ['admin', 'cashier']) {
        test(`store status, confirmation and sidebar ${role} ${width}`, async () => {
            const source = readFileSync(join(directory, `${role}.html`), 'utf8');
            const setup = `<script>
                window.browserErrors=[];window.addEventListener('error',e=>{if(e.message)window.browserErrors.push(e.message)});
                window.statusRequests=0;window.actualStatus='open';window.failStatus=false;
                window.fetch=async(url,options={})=>{
                    if(options.method==='PUT'){
                        window.statusRequests++;
                        await new Promise(r=>setTimeout(r,50));
                        if(window.failStatus)return {ok:false,json:async()=>({message:'Simulasi gagal menyimpan'})};
                        window.actualStatus=String(url).endsWith('/close')?'closed':'open';
                    }
                    return {ok:true,json:async()=>({status:window.actualStatus})};
                };
            </script>`;
            const checks = `<pre id="browser-results" hidden></pre><script>
                const delay=ms=>new Promise(r=>setTimeout(r,ms));
                const check=(value,message)=>{if(!value)throw new Error(message)};
                window.addEventListener('load',async()=>{try{
                    await delay(200);
                    const sidebar=document.getElementById('app-sidebar');
                    const shell=document.getElementById('app-shell');
                    const toggle=document.getElementById('app-sidebar-toggle');
                    if(innerWidth>=1024){
                        check(Math.round(sidebar.getBoundingClientRect().width)===72,'Sidebar must start as icons');
                        sidebar.dispatchEvent(new MouseEvent('mouseenter'));
                        await delay(300);
                        check(shell.classList.contains('sidebar-expanded'),'Hover must expand sidebar');
                        check(Math.round(sidebar.getBoundingClientRect().width)===272,'Expanded sidebar width');
                        const user=sidebar.querySelector('[data-sidebar-user]');
                        check(getComputedStyle(user).display!=='none','User info must be visible when expanded');
                        sidebar.dispatchEvent(new MouseEvent('mouseleave'));
                        await delay(300);
                        check(!shell.classList.contains('sidebar-expanded'),'Mouse leave must collapse sidebar');
                        toggle.click();check(shell.classList.contains('sidebar-expanded'),'Click must pin sidebar');
                        toggle.click();
                    }else{
                        toggle.click();check(!sidebar.classList.contains('-translate-x-full'),'Touch menu must open');
                        document.getElementById('app-backdrop').click();check(sidebar.classList.contains('-translate-x-full'),'Backdrop must close menu');
                    }
                    const badge=document.querySelector('.store-status-badge');
                    check(getComputedStyle(badge).color==='rgb(4, 120, 87)','Open status must be green');
                    if('${role}'==='admin'){
                        const action=document.querySelector('[data-store-action]');
                        const dialog=document.getElementById('store-status-dialog');
                        document.querySelector('[data-store-reveal]').click();
                        action.click();check(dialog.open,'Confirmation must open');
                        check(window.statusRequests===0,'Opening dialog must not change status');
                        dialog.querySelector('[data-store-cancel]').click();check(!dialog.open&&window.actualStatus==='open','Cancel must preserve status');
                        action.click();window.failStatus=true;
                        dialog.querySelector('[data-store-confirm]').click();
                        check(dialog.querySelector('[data-store-confirm]').disabled,'Processing must prevent duplicates');
                        await delay(100);
                        check(dialog.open&&window.storeIsOpen,'Failure must preserve actual open status');
                        check(!dialog.querySelector('[data-store-dialog-error]').hidden,'Failure must be actionable');
                        window.failStatus=false;dialog.querySelector('[data-store-confirm]').click();
                        await delay(100);
                        check(!dialog.open&&!window.storeIsOpen,'Success must set closed status');
                        check(getComputedStyle(badge).color==='rgb(185, 28, 28)','Closed status must be red');
                        check(document.getElementById('pos-checkout').disabled,'Closed checkout must be disabled');
                        action.click();dialog.querySelector('[data-store-confirm]').click();await delay(100);
                        check(window.storeIsOpen,'Reopening must restore open status');
                    }else{
                        check(!document.querySelector('[data-store-action]'),'Cashier must have no status action');
                        window.actualStatus='closed';window.dispatchEvent(new Event('focus'));await delay(100);
                        check(!window.storeIsOpen&&document.getElementById('pos-checkout').disabled,'Other user closing must update cashier');
                    }
                    check(document.documentElement.scrollWidth<=innerWidth+1,'Page must not overflow');
                    check(window.browserErrors.length===0,window.browserErrors.join(';'));
                    document.getElementById('browser-results').textContent='PASS';
                }catch(error){document.getElementById('browser-results').textContent=error.stack}});
            </script>`;
            const file = join(directory, `${role}-${width}.html`);
            writeFileSync(file, source.replace('<head>', `<head>${setup}`).replace(/https?:\/\/[^" ]+\/build\/assets\/([^" ]+)/g, (_, filename) => `file://${resolve('public/build/assets', filename)}`).replace('</body>', `${checks}</body>`));
            const profile = join(directory, `chrome-${role}-${width}`);
            mkdirSync(profile, { recursive: true });
            const output = await renderInChrome(file, width, profile);
            assert.equal(output.match(/<pre id="browser-results" hidden="">([\s\S]*?)<\/pre>/)?.[1], 'PASS');
        });
    }
}
