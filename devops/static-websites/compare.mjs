import { createRequire } from 'module'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const here = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = path.resolve(here, '../..')
const config = JSON.parse(await fs.readFile(path.join(here, 'sites.json'), 'utf8'))

const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }
const BROWSER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'

const usage = `Usage: node devops/static-websites/compare.mjs <domain> [--bundle=<dir>] [--only=/path]
Opens every frozen page from the bundle with all traffic to the real domain intercepted, fails on any request to a
forbidden host, screenshots it, measures the pixel difference against the original and checks the menu and accordions.`

const args = process.argv.slice(2)
const domain = args.find(arg => !arg.startsWith('--'))
const option = name => args.find(arg => arg.startsWith(`--${name}=`))?.split('=').slice(1).join('=')

if (!domain || args.includes('--help')) {
    console.log(usage)
    process.exit(domain ? 1 : 0)
}

const bundleDir = path.resolve(option('bundle') ?? path.join(repoRoot, 'storage/app/static-websites', domain))
const onlyPath = option('only')
const manifest = JSON.parse(await fs.readFile(path.join(bundleDir, 'pages.json'), 'utf8'))
const frozenShotsDir = path.join(bundleDir, 'screenshots/frozen')
await fs.mkdir(frozenShotsDir, { recursive: true })

const forbiddenPattern = new RegExp(config.forbiddenHosts.map(host => host.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|'), 'i')
const siteHosts = new Set([domain, `www.${domain}`])
const pagesByPath = new Map(manifest.pages.map(page => [page.path, page]))

const contentTypes = {
    html: 'text/html; charset=utf-8', css: 'text/css', js: 'text/javascript', jpg: 'image/jpeg', png: 'image/png',
    webp: 'image/webp', avif: 'image/avif', gif: 'image/gif', svg: 'image/svg+xml', ico: 'image/x-icon',
    woff2: 'font/woff2', woff: 'font/woff', ttf: 'font/ttf', otf: 'font/otf', eot: 'application/vnd.ms-fontobject',
    pdf: 'application/pdf', mp4: 'video/mp4', webm: 'video/webm',
}

const slugOf = pagePath => pagePath === '/' ? 'home' : pagePath.replace(/^\//, '').replace(/\/$/, '').replace(/\//g, '__')

const runtimeDir = path.join(repoRoot, 'public/static-websites')
const withRuntime = html => html
    .replace(/<\/head>/i, '<link rel="stylesheet" href="/static-websites/static-site.css"></head>')
    .replace(/<\/body>(?![\s\S]*<\/body>)/i, '<script src="/static-websites/static-site.js" defer></script></body>')

const serveBundle = async (context, violations) => {
    await context.route('**/*', async route => {
        const url = new URL(route.request().url())
        if (siteHosts.has(url.host)) {
            if (url.pathname.startsWith('/static-websites/')) {
                const name = path.basename(url.pathname)
                return route.fulfill({ status: 200, body: await fs.readFile(path.join(runtimeDir, name)), contentType: contentTypes[path.extname(name).slice(1)] })
            }
            if (url.pathname.startsWith('/_assets/')) {
                const name = path.basename(url.pathname)
                const body = await fs.readFile(path.join(bundleDir, 'assets', name)).catch(() => null)
                if (!body) {
                    violations.push({ type: 'missing_asset', url: url.href })
                    return route.fulfill({ status: 404, body: '' })
                }
                return route.fulfill({ status: 200, body, contentType: contentTypes[path.extname(name).slice(1)] ?? 'application/octet-stream' })
            }
            const page = pagesByPath.get(url.pathname.replace(/\/$/, '') || '/')
            if (page) {
                return route.fulfill({ status: 200, body: withRuntime(await fs.readFile(path.join(bundleDir, page.file), 'utf8')), contentType: contentTypes.html })
            }
            violations.push({ type: 'unknown_page', url: url.href })
            return route.fulfill({ status: 404, body: 'not frozen' })
        }
        if (forbiddenPattern.test(url.href)) {
            violations.push({ type: 'forbidden_request', url: url.href })
            return route.abort()
        }
        return route.continue()
    })
}

const settle = async page => {
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {})
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += Math.round(window.innerHeight * 0.6)) {
            window.scrollTo(0, y)
            await new Promise(resolve => setTimeout(resolve, 120))
        }
        window.scrollTo(0, 0)
    })
    await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {})
    await page.waitForTimeout(600)
}

const browser = await chromium.launch()
const diffPage = await browser.newPage()

const pixelDifference = async (originalFile, frozenFile) => {
    const [original, frozen] = await Promise.all([originalFile, frozenFile].map(file => fs.readFile(file).then(buffer => 'data:image/png;base64,' + buffer.toString('base64')).catch(() => null)))
    if (!original || !frozen) {
        return null
    }
    return diffPage.evaluate(async ({ original, frozen }) => {
        const load = source => new Promise((resolve, reject) => {
            const image = new Image()
            image.onload = () => resolve(image)
            image.onerror = reject
            image.src = source
        })
        const [a, b] = await Promise.all([load(original), load(frozen)])
        const width = Math.min(a.width, b.width)
        const height = Math.min(a.height, b.height)
        const scale = Math.min(1, 4000 / height)
        const w = Math.max(1, Math.round(width * scale))
        const h = Math.max(1, Math.round(height * scale))
        const pixels = image => {
            const canvas = new OffscreenCanvas(w, h)
            const context = canvas.getContext('2d')
            context.drawImage(image, 0, 0, width, height, 0, 0, w, h)
            return context.getImageData(0, 0, w, h).data
        }
        const pa = pixels(a)
        const pb = pixels(b)
        let different = 0
        for (let i = 0; i < pa.length; i += 4) {
            if (Math.abs(pa[i] - pb[i]) + Math.abs(pa[i + 1] - pb[i + 1]) + Math.abs(pa[i + 2] - pb[i + 2]) > 60) {
                different++
            }
        }
        return { percent: Math.round(different / (w * h) * 1000) / 10, originalHeight: a.height, frozenHeight: b.height }
    }, { original, frozen })
}

const results = []

for (const page of manifest.pages.filter(page => !onlyPath || page.path === onlyPath)) {
    const slug = slugOf(page.path)
    const result = { path: page.path, violations: [], console_errors: [], checks: {} }

    for (const [viewportName, viewport] of [['desktop', DESKTOP], ['mobile', MOBILE]]) {
        const context = await browser.newContext({ viewport, userAgent: BROWSER_USER_AGENT, ...(viewportName === 'mobile' ? { isMobile: true, hasTouch: true } : {}) })
        await serveBundle(context, result.violations)
        const tab = await context.newPage()
        tab.on('console', message => {
            if (message.type() === 'error') {
                result.console_errors.push(`${viewportName}: ${message.text().slice(0, 160)}`)
            }
        })
        tab.on('pageerror', error => result.console_errors.push(`${viewportName}: ${String(error.message).slice(0, 160)}`))
        await tab.goto(`https://${domain}${page.path}`, { waitUntil: 'networkidle', timeout: 60000 })
        await settle(tab)
        const shot = path.join(frozenShotsDir, `${slug}.${viewportName}.png`)
        await tab.screenshot({ path: shot, fullPage: true })
        result[viewportName] = await pixelDifference(path.join(bundleDir, 'screenshots/original', `${slug}.${viewportName}.png`), shot)

        result.checks[`${viewportName}_broken_images`] = await tab.evaluate(() => [...document.images].filter(image => image.complete && image.naturalWidth === 0 && image.getAttribute('src')).map(image => image.getAttribute('src')).slice(0, 5))

        if (viewportName === 'mobile') {
            const burger = tab.locator('.header-burger-btn').filter({ visible: true }).first()
            if (await burger.count()) {
                await burger.click()
                await tab.waitForTimeout(500)
                result.checks.mobile_menu_opens = await tab.evaluate(() => document.body.classList.contains('header--menu-open') && getComputedStyle(document.querySelector('.header-menu')).visibility === 'visible')
                const folderLink = tab.locator('.header-menu [data-folder-id]').filter({ visible: true }).first()
                if (await folderLink.count()) {
                    await folderLink.click()
                    await tab.waitForTimeout(500)
                    result.checks.mobile_folder_opens = await tab.evaluate(() => document.querySelectorAll('.header-menu-nav-folder--active').length > 1)
                }
            }
        } else {
            const videoOverlay = tab.locator('.sqs-video-wrapper[data-html] .sqs-video-overlay').first()
            if (await videoOverlay.count()) {
                await videoOverlay.scrollIntoViewIfNeeded()
                await videoOverlay.click()
                await tab.waitForTimeout(500)
                result.checks.video_thumbnail_plays = await tab.evaluate(() => [...document.querySelectorAll('.sqs-video-wrapper iframe')].some(frame => /youtube\.com|vimeo\.com/.test(frame.src)))
            }
            const accordion = tab.locator('.accordion-item__click-target').first()
            if (await accordion.count()) {
                await accordion.scrollIntoViewIfNeeded()
                await accordion.click()
                await tab.waitForTimeout(500)
                result.checks.accordion_opens = await tab.evaluate(() => {
                    const dropdown = document.getElementById(document.querySelector('.accordion-item__click-target').getAttribute('aria-controls'))
                    return !!dropdown && dropdown.getBoundingClientRect().height > 0
                })
            }
        }
        await context.close()
    }

    results.push(result)
    const flags = [
        result.violations.length ? `${result.violations.length} violations` : null,
        result.console_errors.length ? `${result.console_errors.length} console errors` : null,
        ...Object.entries(result.checks).filter(([, value]) => value === false || (Array.isArray(value) && value.length)).map(([key]) => key),
    ].filter(Boolean)
    console.log(`  ${page.path.padEnd(48)} desktop ${String(result.desktop?.percent ?? '-').padStart(5)}%  mobile ${String(result.mobile?.percent ?? '-').padStart(5)}%  ${flags.join(', ') || 'ok'}`)
}

await browser.close()

const reportFile = path.join(bundleDir, onlyPath ? 'compare.partial.json' : 'compare.json')
await fs.writeFile(reportFile, JSON.stringify(results, null, 2))

const row = result => {
    const slug = slugOf(result.path)
    const cell = (viewport) => `<figure><figcaption>${viewport} · ${result[viewport]?.percent ?? '–'}% different · height ${result[viewport]?.originalHeight ?? '–'} → ${result[viewport]?.frozenHeight ?? '–'}</figcaption><div class="pair"><img loading="lazy" src="screenshots/original/${slug}.${viewport}.png"><img loading="lazy" src="screenshots/frozen/${slug}.${viewport}.png"></div></figure>`
    const problems = [...result.violations.map(v => `${v.type}: ${v.url}`), ...result.console_errors, ...Object.entries(result.checks).filter(([, v]) => v === false || (Array.isArray(v) && v.length)).map(([k, v]) => `${k}: ${JSON.stringify(v)}`)]
    return `<section><h2>${result.path}</h2>${problems.length ? `<ul>${problems.map(p => `<li>${p.replace(/</g, '&lt;')}</li>`).join('')}</ul>` : '<p class="ok">no problems found</p>'}${cell('desktop')}${cell('mobile')}</section>`
}
await fs.writeFile(path.join(bundleDir, 'review.html'), `<!doctype html><meta charset="utf-8"><title>${domain} frozen review</title>
<style>body{font:14px system-ui;margin:16px;background:#f6f6f6}section{background:#fff;padding:12px;margin:0 0 16px;border-radius:8px}h2{margin:0 0 6px}.pair{display:grid;grid-template-columns:1fr 1fr;gap:8px;align-items:start}.pair img{width:100%;border:1px solid #ddd}figure{margin:8px 0}figcaption{color:#555;margin-bottom:4px}.ok{color:#2e7d32}li{color:#c62828}</style>
<h1>${domain}: original (left) vs frozen (right)</h1>${results.map(row).join('')}`)

const violations = results.reduce((sum, result) => sum + result.violations.length, 0)
console.log(`\n${domain}: ${results.length} pages compared, ${violations} violations. Review: ${path.join(bundleDir, 'review.html')}`)
process.exit(violations ? 2 : 0)
