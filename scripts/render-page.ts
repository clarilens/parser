import {chromium} from 'playwright';
import {parseRenderRequest, RENDER_LIMITS, type RenderRequest} from './browser/models.js';
import {hostResolverRules, pinAllowedHosts} from './browser/network-policy.js';

async function readRequest(): Promise<RenderRequest> {
    const chunks: Buffer[] = [];
    for await (const chunk of process.stdin) chunks.push(Buffer.from(chunk));
    return parseRenderRequest(JSON.parse(Buffer.concat(chunks).toString('utf8')));
}

async function render(request: RenderRequest): Promise<string> {
    const pinned = await pinAllowedHosts(request.allowedHosts);
    const browser = await chromium.launch({
        headless: true,
        chromiumSandbox: true,
        args: [`--host-resolver-rules=${hostResolverRules(pinned)}`],
    });
    try {
        const context = await browser.newContext({serviceWorkers: 'block', acceptDownloads: false});
        await context.routeWebSocket('**/*', socket => socket.close());
        const page = await context.newPage();
        page.on('download', download => {
            void download.cancel();
        });

        let transferredBytes = 0;
        const cdp = await context.newCDPSession(page);
        await cdp.send('Network.enable');
        cdp.on('Network.dataReceived', event => {
            transferredBytes += event.dataLength;
            if (transferredBytes > RENDER_LIMITS.maxTransferBytes) void page.close().catch(() => {
            });
        });

        let requests = 0;
        await context.route('**/*', async route => {
            if (++requests > RENDER_LIMITS.maxRequests) return route.abort();
            const url = new URL(route.request().url());
            if (url.protocol === 'file:') {
                return request.allowLocalFile && url.href === request.url ? route.continue() : route.abort();
            }
            if (['data:', 'blob:', 'about:'].includes(url.protocol)) return route.continue();
            if (!['http:', 'https:'].includes(url.protocol) || !pinned.has(url.hostname)) return route.abort();
            return route.continue();
        });

        page.setDefaultTimeout(request.timeoutMs);
        await page.goto(request.url, {waitUntil: 'domcontentloaded', timeout: request.timeoutMs});
        for (const action of request.actions) await page.locator(action.selector).click();
        if (request.ready.selector) {
            await page.locator(request.ready.selector).waitFor({state: 'visible', timeout: request.timeoutMs});
        }
        const html = await page.content();
        if (Buffer.byteLength(html) > RENDER_LIMITS.maxHtmlBytes) throw new Error('Rendered HTML is too large');
        if (!request.repeat) return html;
        const frames = [html];
        let sequenceBytes = Buffer.byteLength(html);
        for (let click = 0; click < request.repeat.maxClicks; click++) {
            if (await page.locator(request.repeat.click).count() === 0) break;
            const previous = await page.locator(request.repeat.items).count();
            await page.locator(request.repeat.click).click();
            try {
                await page.waitForFunction(({selector, count}) => document.querySelectorAll(selector).length > count,
                    {selector: request.repeat.items, count: previous}, {timeout: request.timeoutMs});
            } catch {
                // Preserve the stalled DOM; the crawl runner reports no_progress.
            }
            const next = await page.content();
            if (Buffer.byteLength(next) > RENDER_LIMITS.maxHtmlBytes) throw new Error('Rendered HTML is too large');
            sequenceBytes += Buffer.byteLength(next);
            if (sequenceBytes > 20_000_000) throw new Error('Browser sequence is too large');
            frames.push(next);
            if (await page.locator(request.repeat.items).count() <= previous) break;
        }
        return JSON.stringify({frames});
    } finally {
        await browser.close();
    }
}

const deadline = setTimeout(() => process.exit(124), RENDER_LIMITS.deadlineMs);
try {
    process.stdout.write(await render(await readRequest()));
} catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    process.stderr.write(message.slice(0, 2000));
    process.exitCode = 1;
} finally {
    clearTimeout(deadline);
}
