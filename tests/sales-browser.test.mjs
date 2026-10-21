import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, writeFileSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { renderInChrome } from './chrome.mjs';

const directory = mkdtempSync(join(tmpdir(), 'sales-browser-'));
try {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/SalesReportingRegressionTest.php', '--filter=sales pages and compact PDF'], { env: { ...process.env, SALES_BROWSER_FIXTURES: directory }, stdio: 'pipe' });
    const chart = join(directory, 'chart.js');
    execFileSync('curl', ['--fail', '--silent', '--show-error', '--max-time', '20', '--output', chart, 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js'], { stdio: 'pipe' });

    for (const page of ['dashboard', 'reports']) {
        for (const width of [1440, 1024, 768, 390, 320]) {
            test(`${page} has readable cards and no overlapping content at ${width}px`, async () => {
                const capture = `<script>window.browserErrors=[];window.addEventListener('error',(event)=>window.browserErrors.push(event.message || 'Resource load failed: '+event.target?.src),true);window.addEventListener('unhandledrejection',(event)=>window.browserErrors.push(String(event.reason)));const originalConsoleError=console.error;console.error=(...args)=>{window.browserErrors.push(args.join(' '));originalConsoleError(...args)};</script>`;
                const checks = `<pre id="browser-results" hidden></pre><script>window.addEventListener('load',async()=>{await document.fonts.ready;setTimeout(async()=>{try{
                    const check=(condition,message)=>{if(!condition)throw new Error(message)};
                    check(document.documentElement.scrollWidth<=${width}+1,'Document scroll width is '+document.documentElement.scrollWidth+' at innerWidth '+window.innerWidth+'; wide: '+[...document.querySelectorAll('body *')].filter(el=>el.getBoundingClientRect().right>321).slice(0,8).map(el=>el.tagName+'.'+el.className+'='+Math.round(el.getBoundingClientRect().right)).join('|'));
                    check(window.innerWidth===${width},'Unexpected viewport: '+window.innerWidth);
                    check(window.browserErrors.length===0,'Browser errors: '+window.browserErrors.join('; '));
                    const account=document.querySelector('[data-sidebar-user]');
                    check(account.scrollWidth<=account.clientWidth+1,'Sidebar account text is clipped');
                    check(!account.querySelector('.truncate'),'Account email and role must remain readable');
                    check(![...document.querySelectorAll('button,a')].some(element=>element.textContent.trim()==='+'&&getComputedStyle(element).position==='fixed'),'Floating plus button is still present');
                    if(${width}<1024){document.getElementById('app-sidebar-toggle').click();await new Promise(resolve=>setTimeout(resolve,300));check(document.getElementById('app-sidebar').getBoundingClientRect().left>=-1,'Sidebar cannot open');document.getElementById('app-backdrop').click();await new Promise(resolve=>setTimeout(resolve,300));}
                    const grid=document.querySelector('[data-sales-summary]');
                    const columns=getComputedStyle(grid).gridTemplateColumns.split(' ').length;
                    check(columns===(${width}>=1280?3:${width}>=640?2:1),'Unexpected grid column count: '+columns);
                    const cards=[...document.querySelectorAll('[data-stat-card]')];
                    check(cards.length===6,'Six summary cards are required');
                    check(new Set(cards.map(card=>getComputedStyle(card).backgroundColor)).size===6,'Cards need distinct subtle colors');
                    for(const card of cards){
                        const rect=card.getBoundingClientRect();
                        check(rect.left>=0&&rect.right<=innerWidth+1,'Card outside viewport');
                        check(card.scrollWidth<=card.clientWidth+1,'Card content overflows');
                        const [title,value,description]=[...card.children].map(child=>child.getBoundingClientRect());
                        check(title.bottom<=value.top+1&&value.bottom<=description.top+1,'Card text overlaps');
                        check(parseFloat(getComputedStyle(card.querySelector('[data-stat-value]')).fontSize)>=24,'Value hierarchy is missing');
                    }
                    check(document.documentElement.scrollWidth<=innerWidth+1,'Page has horizontal overflow');
                    if('${page}'==='reports'){
                        const buttons=[...document.querySelectorAll('a[href*="/reports/export"]')];
                        check(buttons.length===2,'PDF and CSV links are required');
                        for(const button of buttons){const style=getComputedStyle(button);check(button.getBoundingClientRect().height>=40&&parseFloat(style.paddingLeft)>=12&&parseFloat(style.borderRadius)>=6,'Export action has no visible button container')}
                        const [first,second]=buttons.map(button=>button.getBoundingClientRect());
                        check(first.right<=second.left+1||first.bottom<=second.top+1,'Export buttons overlap');
                    }else{
                        check([...document.querySelectorAll('canvas')].every(canvas=>canvas.getBoundingClientRect().height>0&&canvas.getBoundingClientRect().height<=300),'Chart sizing is invalid');
                    }
                    document.getElementById('browser-results').textContent='PASS';
                }catch(error){document.getElementById('browser-results').textContent=error.stack}},1000)});</script>`;
                let html = readFileSync(join(directory, `${page}.html`), 'utf8')
                    .replace('<head>', `<head>${capture}`)
                    .replace(/https?:\/\/[^" ]+\/build\/assets\/([^" ]+)/g, (_, filename) => `file://${resolve('public/build/assets', filename)}`)
                    .replace('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', `file://${chart}`)
                    .replace('</body>', `${checks}</body>`);
                const file = join(directory, `${page}-${width}.html`);
                writeFileSync(file, html);
                const output = await renderInChrome(file, width, join(directory, `profile-${page}-${width}`), process.env.SALES_SCREENSHOT_DIR ? join(process.env.SALES_SCREENSHOT_DIR, `${page}-${width}.png`) : undefined);
                assert.equal(output.match(/<pre id="browser-results" hidden="">([\s\S]*?)<\/pre>/)?.[1], 'PASS');
            });
        }
    }
    for (const page of ['welcome', 'profile', 'catalog', 'pos', 'receipt']) {
        for (const width of [1440, 768, 390]) {
            test(`${page} stays readable at ${width}px`, async () => {
                const capture = `<script>window.browserErrors=[];window.addEventListener('error',(event)=>window.browserErrors.push(event.message || 'Resource failed'),true);window.addEventListener('unhandledrejection',(event)=>window.browserErrors.push(String(event.reason)));</script>`;
                const checks = `<pre id="browser-results" hidden></pre><script>window.addEventListener('load',()=>setTimeout(()=>{try{
                    const check=(condition,message)=>{if(!condition)throw new Error(message)};
                    check(innerWidth===${width},'Incorrect viewport');
                    check(document.documentElement.scrollWidth<=innerWidth+1,'Page overflows horizontally');
                    check(window.browserErrors.length===0,window.browserErrors.join('; '));
                    if('${page}'==='welcome'){check(document.querySelector('h1')?.textContent.includes('Selamat Datang'),'Welcome heading must be visible');check(document.querySelector('[data-store-start]'),'Welcome kasir action must be present');check(document.querySelectorAll('a').length===1,'Welcome should only have the Mulai Kasir action');if(document.querySelector('[data-store-action]')){document.querySelector('[data-store-reveal]').click();check(document.querySelector('[data-store-action]').getClientRects().length>0,'Admin store action must appear after tapping the status');}}
                    if('${page}'==='profile'){
                        for(const input of document.querySelectorAll('input:not([type="hidden"])'))check(input.getBoundingClientRect().right<=innerWidth+1,'Profile field outside viewport');
                        check(document.querySelectorAll('form[action*="store-profile"]').length===1,'Profile form is malformed');
                        const input=document.getElementById('logo');
                        const transfer=new DataTransfer();transfer.items.add(new File(['logo'],'logo.png',{type:'image/png'}));input.files=transfer.files;input.dispatchEvent(new Event('change'));
                        check(!document.querySelector('[data-store-logo-preview]').hidden,'Selected logo must preview before saving');
                        check(document.querySelector('[data-store-logo-placeholder]').hidden,'Logo placeholder must hide during preview');
                    }
                    if('${page}'==='pos'){
                        if(innerWidth>=1024){const menu=document.querySelector('#pos-menu-list').getBoundingClientRect();const cart=document.querySelector('#pos-cart-panel').getBoundingClientRect();check(cart.left>=menu.right-1,'Desktop POS must place menu and cart side by side');check(cart.height<=innerHeight-100,'Cart panel must fit viewport');check(getComputedStyle(document.querySelector('#pos-cart-lines')).overflowY==='auto','Cart list must scroll independently');}
                        const click=(selector)=>document.querySelector(selector).click();
                        const dialogFits=()=>{const dialog=document.querySelector('dialog[open]');const rect=dialog.getBoundingClientRect();check(rect.left>=0&&rect.right<=innerWidth+1,'Dialog outside viewport');check(dialog.scrollWidth<=dialog.clientWidth+1,'Dialog contents overflow')};
                        click('.js-product');dialogFits();
                        check(document.querySelector('.js-modal-topping-qty').textContent==='0','Topping quantity must start at zero');
                        click('.js-modal-topping-qty-plus');
                        check(document.querySelector('.js-modal-topping-qty').textContent==='1','One plus click must add one topping');
                        click('#pos-modal-add');click('#pos-checkout');dialogFits();click('#pos-step1-next');dialogFits();click('#pos-step2-next');dialogFits();
                        click('.pos-payment-method-btn[data-method="qris"]');click('#pos-step3-qris-next');dialogFits();
                    }
                    document.getElementById('browser-results').textContent='PASS';
                }catch(error){document.getElementById('browser-results').textContent=error.stack}},500));</script>`;
                const file=join(directory,`${page}-${width}.html`);
                writeFileSync(file,readFileSync(join(directory,`${page}.html`),'utf8').replace('<head>',`<head>${capture}`).replace(/https?:\/\/[^" ]+\/build\/assets\/([^" ]+)/g,(_,filename)=>`file://${resolve('public/build/assets',filename)}`).replace('</body>',`${checks}</body>`));
                const output=await renderInChrome(file,width,join(directory,`profile-${page}-${width}`),process.env.SALES_SCREENSHOT_DIR ? join(process.env.SALES_SCREENSHOT_DIR,`${page}-${width}.png`) : undefined);
                assert.equal(output.match(/<pre id="browser-results" hidden="">([\s\S]*?)<\/pre>/)?.[1],'PASS');
            });
        }
    }
} finally {
    process.on('exit', () => rmSync(directory, { recursive: true, force: true }));
}
