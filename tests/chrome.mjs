import { spawn } from 'node:child_process';
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

export async function renderInChrome(file, width, profile, screenshot) {
    const browser = spawn(process.env.CHROME_BIN ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', ['--headless', '--no-first-run', '--no-default-browser-check', '--allow-file-access-from-files', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
    let socket;
    try {
        const portFile = join(profile, 'DevToolsActivePort');
        for (let attempt = 0; !existsSync(portFile) && attempt < 100; attempt++) await delay(100);
        const port = readFileSync(portFile, 'utf8').split('\n')[0];
        const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        let nextId = 0;
        const pending = new Map();
        socket.onmessage = ({ data }) => {
            const message = JSON.parse(data);
            if (pending.has(message.id)) {
                const { resolve, reject, timer } = pending.get(message.id);
                clearTimeout(timer);
                pending.delete(message.id);
                if (message.error) reject(new Error(message.error.message)); else resolve(message.result);
            }
        };
        const send = (method, params = {}) => new Promise((resolve, reject) => {
            const id = ++nextId;
            const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Chrome timed out: ${method}`)); }, 10000);
            pending.set(id, { resolve, reject, timer });
            socket.send(JSON.stringify({ id, method, params }));
        });
        await send('Page.enable');
        await send('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width < 640 });
        await send('Page.navigate', { url: `file://${file}` });
        for (let attempt = 0; attempt < 100; attempt++) {
            const result = await send('Runtime.evaluate', { expression: "document.getElementById('browser-results')?.textContent", returnByValue: true });
            if (result.result.value) break;
            await delay(100);
        }
        if (screenshot) {
            const result = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
            writeFileSync(screenshot, Buffer.from(result.data, 'base64'));
        }
        const result = await send('Runtime.evaluate', { expression: 'document.documentElement.outerHTML', returnByValue: true });
        return result.result.value;
    } finally {
        socket?.close();
        browser.kill('SIGTERM');
        await Promise.race([new Promise((resolve) => browser.once('exit', resolve)), delay(2000)]);
    }
}
