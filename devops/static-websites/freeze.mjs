import { createRequire } from 'module'
import { createHash } from 'crypto'
import fs from 'fs/promises'
import path from 'path'
import { fileURLToPath } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const here = path.dirname(fileURLToPath(import.meta.url))
const repoRoot = path.resolve(here, '../..')
const config = JSON.parse(await fs.readFile(path.join(here, 'sites.json'), 'utf8'))

const ASSET_PREFIX = '/_assets/'
const BROWSER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36'
const DESKTOP = { width: 1440, height: 900 }
const MOBILE = { width: 390, height: 844 }

const usage = `Usage: node devops/static-websites/freeze.mjs <domain> [--out=<dir>] [--only=/path] [--no-screenshots]
Freezes a Squarespace site listed in sites.json into a bundle of pages and self-hosted assets.`

const args = process.argv.slice(2)
const domain = args.find(arg => !arg.startsWith('--'))
const option = name => args.find(arg => arg.startsWith(`--${name}=`))?.split('=').slice(1).join('=')
const flag = name => args.includes(`--${name}`)

if (!domain || !config[domain]?.source || flag('help')) {
    console.log(usage)
    process.exit(domain ? 1 : 0)
}

const site = config[domain]
const outDir = path.resolve(option('out') ?? path.join(repoRoot, 'storage/app/static-websites', domain))
const onlyPath = option('only')
const takeScreenshots = !flag('no-screenshots')

const assetsDir = path.join(outDir, 'assets')
const pagesDir = path.join(outDir, 'pages')
const originalShotsDir = path.join(outDir, 'screenshots/original')
await fs.mkdir(assetsDir, { recursive: true })
await fs.mkdir(pagesDir, { recursive: true })
await fs.mkdir(originalShotsDir, { recursive: true })

const sourceUrl = new URL(site.source)
const siteHosts = new Set([sourceUrl.host, sourceUrl.host.replace(/^www\./, ''), domain, `www.${domain}`])
const forbiddenPattern = new RegExp(config.forbiddenHosts.map(host => host.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|'), 'i')

const extensionByType = {
    'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp', 'image/avif': 'avif', 'image/gif': 'gif',
    'image/svg+xml': 'svg', 'image/x-icon': 'ico', 'image/vnd.microsoft.icon': 'ico',
    'font/woff2': 'woff2', 'font/woff': 'woff', 'application/font-woff': 'woff', 'application/x-font-woff': 'woff',
    'font/ttf': 'ttf', 'application/x-font-ttf': 'ttf', 'font/otf': 'otf', 'application/vnd.ms-fontobject': 'eot',
    'text/css': 'css', 'application/javascript': 'js', 'text/javascript': 'js',
    'application/pdf': 'pdf', 'video/mp4': 'mp4', 'video/webm': 'webm', 'audio/mpeg': 'mp3',
}

const slugOf = pagePath => pagePath === '/' ? 'home' : pagePath.replace(/^\//, '').replace(/\/$/, '').replace(/\//g, '__')
const hashOf = value => createHash('sha1').update(value).digest('hex').slice(0, 16)

const report = { domain, source: site.source, frozen_at: new Date().toISOString(), pages: {}, assets_failed: [], removed_links: [] }

const fetchWithRetry = async (url, init = {}, attempts = 6) => {
    for (let attempt = 1; ; attempt++) {
        try {
            const response = await fetch(url, { ...init, headers: { 'user-agent': BROWSER_USER_AGENT, ...(init.headers ?? {}) }, redirect: 'follow' })
            if (response.status >= 500 && attempt < attempts) {
                throw new Error(`HTTP ${response.status}`)
            }
            return response
        } catch (error) {
            if (attempt >= attempts) {
                throw error
            }
            await new Promise(resolve => setTimeout(resolve, 2000 * attempt * attempt))
        }
    }
}

const assetMap = new Map()
const pendingAssets = new Map()

const saveAsset = async (bytes, url, contentType) => {
    const type = (contentType ?? '').split(';')[0].trim().toLowerCase()
    const urlExtension = path.extname(new URL(url).pathname).slice(1).toLowerCase()
    const extension = extensionByType[type] ?? (urlExtension.match(/^[a-z0-9]{2,5}$/) ? urlExtension : 'bin')
    const name = `${hashOf(url)}.${extension}`
    await fs.writeFile(path.join(assetsDir, name), bytes)
    return name
}

const downloadAsset = (url, kind = 'file') => {
    const key = url.split('#')[0]
    if (assetMap.has(key)) {
        return Promise.resolve(assetMap.get(key))
    }
    if (!pendingAssets.has(key)) {
        pendingAssets.set(key, (async () => {
            try {
                const response = await fetchWithRetry(key, { headers: kind === 'image' ? { accept: 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8' } : {} })
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`)
                }
                const contentType = response.headers.get('content-type')
                let name
                if ((contentType ?? '').includes('text/css') || kind === 'css') {
                    const css = await processCss(await response.text(), key)
                    name = await saveAsset(Buffer.from(css), key, 'text/css')
                } else {
                    name = await saveAsset(Buffer.from(await response.arrayBuffer()), key, contentType)
                }
                const assetPath = ASSET_PREFIX + name
                assetMap.set(key, assetPath)
                return assetPath
            } catch (error) {
                report.assets_failed.push({ url: key, error: String(error.message ?? error) })
                assetMap.set(key, null)
                return null
            }
        })())
    }
    return pendingAssets.get(key)
}

const withLimit = async (items, limit, worker) => {
    const queue = [...items]
    await Promise.all(Array.from({ length: Math.min(limit, queue.length) }, async () => {
        while (queue.length) {
            await worker(queue.shift())
        }
    }))
}

const resolveUrl = (value, base) => {
    try {
        const resolved = new URL(value.trim().replace(/^['"]|['"]$/g, ''), base)
        return /^https?:$/.test(resolved.protocol) ? resolved.href : null
    } catch {
        return null
    }
}

const isDroppedFont = block => config.droppedFontHosts.some(host => block.includes(host))

async function processCss (css, baseUrl) {
    let output = css.replace(/@font-face\s*{[^}]*}/g, block => isDroppedFont(block) ? '' : block)

    const imports = [...output.matchAll(/@import\s+(?:url\()?\s*(['"]?)([^'")\s;]+)\1\s*\)?([^;]*);/g)]
    for (const [statement, , target, media] of imports) {
        const absolute = resolveUrl(target, baseUrl)
        const local = absolute ? await downloadAsset(absolute, 'css') : null
        output = output.replace(statement, local ? `@import url(${local})${media};` : '')
    }

    const references = [...new Set([...output.matchAll(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g)].map(match => match[2]))]
        .filter(reference => !reference.startsWith('data:') && !reference.startsWith('#') && !reference.toLowerCase().startsWith('%23') && !reference.startsWith(ASSET_PREFIX))
    const replacements = new Map()
    await withLimit(references, 6, async reference => {
        const absolute = resolveUrl(reference, baseUrl)
        if (absolute) {
            const local = await downloadAsset(absolute)
            const fragment = reference.includes('#') ? '#' + reference.split('#').slice(1).join('#') : ''
            replacements.set(reference, local ? local + fragment : null)
        }
    })

    return output.replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (match, quote, reference) => {
        if (!replacements.has(reference)) {
            return match
        }
        const local = replacements.get(reference)
        return local ? `url(${quote}${local}${quote})` : 'url("")'
    })
}

const readSitemapPaths = async () => {
    const response = await fetchWithRetry(new URL('/sitemap.xml', site.source).href)
    const xml = await response.text()
    return [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(match => new URL(match[1]).pathname.replace(/\/$/, '') || '/')
}

const findHomepagePath = async paths => {
    for (const pagePath of paths) {
        const response = await fetchWithRetry(new URL(pagePath + '?format=json', site.source).href).catch(() => null)
        const json = response?.ok ? await response.json().catch(() => null) : null
        if (json?.collection?.homepage && !json?.item) {
            return pagePath
        }
    }
    return null
}

const linkStatusCache = new Map()
const linkStatus = async pagePath => {
    if (!linkStatusCache.has(pagePath)) {
        linkStatusCache.set(pagePath, fetchWithRetry(new URL(pagePath, site.source).href, { method: 'GET' }).then(response => response.status).catch(() => 0))
    }
    return linkStatusCache.get(pagePath)
}

const settle = async page => {
    await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {})
    await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += Math.round(window.innerHeight * 0.6)) {
            window.scrollTo(0, y)
            await new Promise(resolve => setTimeout(resolve, 200))
        }
        window.scrollTo(0, document.body.scrollHeight)
        await new Promise(resolve => setTimeout(resolve, 400))
        window.scrollTo(0, 0)
    })
    await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {})
    await page.waitForTimeout(800)
}

const replacementFontCss = async typekitFamilies => {
    const blocks = []
    for (const [familyToken, variants] of Object.entries(typekitFamilies)) {
        const typekitName = Object.keys(config.fontReplacements).find(name => name.replace(/-/g, '') === familyToken)
        if (!typekitName) {
            throw new Error(`No replacement configured in sites.json fontReplacements for the Typekit font "${familyToken}"`)
        }
        const { family: googleFamily, sizeAdjust } = config.fontReplacements[typekitName]
        const weights = [...new Set(variants.map(variant => Number(variant.slice(1)) * 100))].sort()
        const hasItalic = variants.some(variant => variant.startsWith('i'))
        const query = (withItalic) => withItalic
            ? `${googleFamily.replace(/ /g, '+')}:ital,wght@${[...weights.map(w => `0,${w}`), ...weights.map(w => `1,${w}`)].join(';')}`
            : `${googleFamily.replace(/ /g, '+')}:wght@${weights.join(';')}`
        let response = await fetchWithRetry(`https://fonts.googleapis.com/css2?family=${query(hasItalic)}&display=swap`)
        if (!response.ok && hasItalic) {
            response = await fetchWithRetry(`https://fonts.googleapis.com/css2?family=${query(false)}&display=swap`)
        }
        if (!response.ok) {
            throw new Error(`Google Fonts has no ${googleFamily} in weights ${weights.join(', ')}`)
        }
        const css = (await response.text())
            .replace(new RegExp(`font-family:\\s*'${googleFamily}'`, 'g'), `font-family: '${typekitName}'`)
            .replace(/@font-face\s*{/g, sizeAdjust ? `@font-face {\n  size-adjust: ${sizeAdjust};` : '@font-face {')
        blocks.push(await processCss(css, 'https://fonts.googleapis.com/'))
    }
    return blocks.join('\n')
}

const cleanupInPage = (settings) => {
    const issues = []
    const keptHosts = new Set(settings.keptScriptHosts)
    const resolve = value => {
        try {
            const resolved = new URL(value, location.href)
            return /^https?:$/.test(resolved.protocol) ? resolved.href : null
        } catch {
            return null
        }
    }

    document.querySelectorAll('script').forEach(script => {
        if (script.type === 'application/ld+json') {
            return
        }
        const src = script.getAttribute('src')
        if (src) {
            const host = new URL(src, location.href).host
            if (keptHosts.has(host)) {
                return
            }
            script.remove()
            return
        }
        if (settings.keptInlineScriptMarkers.some(marker => script.textContent.includes(marker))) {
            return
        }
        script.remove()
    })

    const headerHeight = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-height'))
    if (headerHeight) {
        document.querySelectorAll('[style*="padding-top"]').forEach(element => {
            if (Math.abs(parseFloat(element.style.paddingTop) - headerHeight) < 0.5) {
                element.setAttribute('data-static-header-offset', '')
            }
        })
    }

    document.querySelectorAll('noscript').forEach(element => element.remove())
    document.querySelectorAll('link[rel~="preload"], link[rel~="modulepreload"], link[rel~="preconnect"], link[rel~="dns-prefetch"], link[rel="alternate"]').forEach(element => element.remove())
    document.querySelectorAll('meta[name="generator"]').forEach(element => element.remove())

    const translate = document.getElementById('google_translate_element')
    if (translate) {
        translate.innerHTML = ''
    }
    document.querySelectorAll('body > .skiptranslate, #goog-gt-tt, .goog-te-spinner-pos, .goog-te-banner-frame, iframe.skiptranslate, [id^="goog-gt-"], .VIpgJd-ZVi9od-aZ2wEe-wOHMyf, .VIpgJd-yAWNEb-L7lbkb').forEach(element => element.remove())
    document.querySelectorAll('link[rel="stylesheet"][href*="translate_http"]').forEach(element => element.remove())
    document.querySelectorAll('style').forEach(style => {
        if (/goog-te|VIpgJd/.test(style.textContent)) {
            style.remove()
        }
    })

    document.querySelectorAll('.grecaptcha-badge').forEach(element => (element.parentElement?.children.length === 1 ? element.parentElement : element).remove())
    document.querySelectorAll('iframe').forEach(frame => {
        const src = frame.getAttribute('src') ?? ''
        if (!src || src.startsWith('about:') || /recaptcha|translate/.test(src)) {
            frame.remove()
        }
    })

    document.querySelectorAll('a[href="/cart"], a[href$="/cart"], .header-actions-action--cart, .header-menu-cart').forEach(element => {
        (element.closest('.header-actions-action, .header-menu-actions-action, .header-menu-cart, .header-nav-item, .header-menu-nav-item, .header-actions-action--cart') ?? element).remove()
    })

    document.querySelectorAll('img').forEach(image => {
        const src = image.getAttribute('src') ?? ''
        if ((!src || src.startsWith('data:')) && image.dataset.src) {
            image.setAttribute('src', image.dataset.src + (image.dataset.src.includes('?') ? '' : '?format=1500w'))
        }
    })

    document.querySelectorAll('video').forEach(video => {
        const src = video.currentSrc || video.getAttribute('src') || ''
        if (src.startsWith('blob:') || video.querySelector('source[src*="squarespace"]')) {
            let source = null
            for (let node = video; node && !source; node = node.parentElement) {
                for (const attribute of node.attributes ?? []) {
                    const match = attribute.value.match(/https:\/\/video\.squarespace-cdn\.com\/content\/v1\/[^"'&\s{}]+\/[0-9a-f-]{36}/)
                    if (match) {
                        source = match[0]
                        break
                    }
                }
            }
            if (source && !video.getAttribute('poster')) {
                video.setAttribute('poster', source + '/thumbnail')
            }
            issues.push({ type: 'squarespace_video', where: video.closest('section')?.className.split(' ').filter(Boolean).slice(0, 3).join(' ') ?? 'page', source })
            video.removeAttribute('src')
            video.setAttribute('data-static-video-pending', '')
            video.querySelectorAll('source').forEach(element => element.remove())
        }
    })

    document.querySelectorAll('form').forEach(form => {
        const block = form.closest('.sqs-block')
        issues.push({ type: 'form', block: block?.className.match(/sqs-block-(?!website-component)([a-z-]+)/)?.[1] ?? 'form', text: (block?.innerText ?? '').replace(/\s+/g, ' ').slice(0, 80) })
    })
    document.querySelectorAll('.sqs-block-donation, [data-definition-name*="donation"]').forEach(block => {
        issues.push({ type: 'donation', text: block.innerText.replace(/\s+/g, ' ').slice(0, 80) })
    })

    const assets = new Set()
    const images = new Set()
    const add = (value, bucket = assets) => {
        const absolute = value ? resolve(value) : null
        if (absolute) {
            bucket.add(absolute.split('#')[0])
        }
    }
    const srcsetUrls = value => (value ?? '').split(',').map(part => part.trim().split(/\s+/)[0]).filter(Boolean)

    document.querySelectorAll('img').forEach(image => {
        add(image.getAttribute('src'), images)
        srcsetUrls(image.getAttribute('srcset')).forEach(url => add(url, images))
    })
    document.querySelectorAll('source').forEach(source => {
        add(source.getAttribute('src'))
        srcsetUrls(source.getAttribute('srcset')).forEach(url => add(url, images))
    })
    document.querySelectorAll('video[poster]').forEach(video => add(video.getAttribute('poster'), images))
    document.querySelectorAll('link[rel~="icon"], link[rel="apple-touch-icon"], link[rel="mask-icon"]').forEach(link => add(link.getAttribute('href'), images))
    document.querySelectorAll('meta[property$="image"], meta[name$="image"], meta[itemprop="image"], meta[property="og:image:secure_url"]').forEach(meta => add(meta.getAttribute('content'), images))
    document.querySelectorAll('image').forEach(image => add(image.getAttribute('href') ?? image.getAttribute('xlink:href'), images))
    document.querySelectorAll('[style*="url("]').forEach(element => {
        for (const match of element.getAttribute('style').matchAll(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g)) {
            if (!match[2].startsWith('data:') && !match[2].startsWith('#')) {
                add(match[2], images)
            }
        }
    })
    document.querySelectorAll('script[type="application/ld+json"]').forEach(script => {
        for (const match of script.textContent.matchAll(/(?:https?:)?\\?\/\\?\/[^"\s]+/g)) {
            const url = match[0].replace(/\\\//g, '/')
            if (new RegExp(settings.forbiddenPattern, 'i').test(url)) {
                add(url, images)
            }
        }
    })

    const internalLinks = new Set()
    document.querySelectorAll('a[href]').forEach(anchor => {
        const absolute = resolve(anchor.getAttribute('href'))
        if (!absolute) {
            return
        }
        const url = new URL(absolute)
        if (settings.siteHosts.includes(url.host)) {
            if (url.pathname.startsWith('/s/')) {
                add(absolute)
            } else if (!anchor.hasAttribute('data-folder-id')) {
                internalLinks.add(url.pathname.replace(/\/$/, '') || '/')
            }
        } else if (/(^|\.)(static1\.squarespace\.com|images\.squarespace-cdn\.com|file\.squarespace-cdn\.com)$/.test(url.host)) {
            add(absolute)
        }
    })

    const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"][href]')].map(link => resolve(link.getAttribute('href'))).filter(Boolean)
    const inlineStyles = [...document.querySelectorAll('style')].map(style => style.textContent)

    const typekitFamilies = {}
    for (const className of document.documentElement.classList) {
        const match = className.match(/^wf-([a-z0-9]+)-([ni]\d)-active$/)
        if (match) {
            (typekitFamilies[match[1]] ??= []).push(match[2])
        }
    }

    return {
        issues,
        assets: [...assets],
        images: [...images],
        stylesheets,
        inlineStyles,
        internalLinks: [...internalLinks],
        typekitFamilies,
        title: document.title,
        description: document.querySelector('meta[name="description"]')?.getAttribute('content') ?? null,
    }
}

const rewriteInPage = (settings) => {
    const map = new Map(settings.map)
    const resolve = value => {
        try {
            const resolved = new URL(value, location.href)
            return /^https?:$/.test(resolved.protocol) ? resolved.href.split('#')[0] : null
        } catch {
            return null
        }
    }
    const local = value => {
        const absolute = value ? resolve(value) : null
        return absolute && map.has(absolute) ? map.get(absolute) : null
    }
    const absoluteLocal = value => {
        const path = local(value)
        return path ? settings.publicOrigin + path : null
    }
    const forbidden = new RegExp(settings.forbiddenPattern, 'i')
    const removedLinks = []

    const rewriteSrcset = value => (value ?? '').split(',').map(part => {
        const [url, ...descriptor] = part.trim().split(/\s+/)
        const replacement = local(url)
        return replacement ? [replacement, ...descriptor].join(' ') : null
    }).filter(Boolean).join(', ')

    document.querySelectorAll('img').forEach(image => {
        const src = local(image.getAttribute('src'))
        if (src) {
            image.setAttribute('src', src)
            for (const attribute of ['data-src', 'data-image']) {
                if (image.hasAttribute(attribute)) {
                    image.setAttribute(attribute, src)
                }
            }
        }
        if (image.hasAttribute('srcset')) {
            const srcset = rewriteSrcset(image.getAttribute('srcset'))
            srcset ? image.setAttribute('srcset', srcset) : image.removeAttribute('srcset')
        }
    })
    document.querySelectorAll('source').forEach(source => {
        const src = local(source.getAttribute('src'))
        if (src) {
            source.setAttribute('src', src)
        }
        if (source.hasAttribute('srcset')) {
            const srcset = rewriteSrcset(source.getAttribute('srcset'))
            srcset ? source.setAttribute('srcset', srcset) : source.removeAttribute('srcset')
        }
    })
    document.querySelectorAll('video[poster]').forEach(video => {
        const poster = local(video.getAttribute('poster'))
        poster ? video.setAttribute('poster', poster) : video.removeAttribute('poster')
    })
    document.querySelectorAll('link[href]').forEach(link => {
        const href = local(link.getAttribute('href'))
        if (href) {
            link.setAttribute('href', href)
        }
    })
    document.querySelectorAll('meta[content]').forEach(meta => {
        const content = absoluteLocal(meta.getAttribute('content'))
        if (content) {
            meta.setAttribute('content', content)
        }
    })
    document.querySelectorAll('image').forEach(image => {
        for (const attribute of ['href', 'xlink:href']) {
            const href = local(image.getAttribute(attribute))
            if (href) {
                image.setAttribute(attribute, href)
            }
        }
    })
    document.querySelectorAll('[style*="url("]').forEach(element => {
        element.setAttribute('style', element.getAttribute('style').replace(/url\(\s*(['"]?)([^'")]+)\1\s*\)/g, (match, quote, value) => {
            const replacement = value.startsWith('#') ? null : local(value)
            return replacement ? `url(${quote}${replacement}${quote})` : match
        }))
    })
    document.querySelectorAll('style').forEach((style, index) => {
        if (settings.inlineStyles[index] !== undefined) {
            style.textContent = settings.inlineStyles[index]
        }
    })
    document.querySelectorAll('script[type="application/ld+json"]').forEach(script => {
        script.textContent = script.textContent.replace(/(?:https?:)?\\?\/\\?\/[^"\s]+/g, match => absoluteLocal(match.replace(/\\\//g, '/')) ?? match)
    })

    const pagePaths = new Set(settings.pagePaths)
    const removable = new Set(settings.removableLinks)
    document.querySelectorAll('a[href]').forEach(anchor => {
        const href = anchor.getAttribute('href')
        const absolute = resolve(href)
        if (!absolute) {
            return
        }
        const url = new URL(absolute)
        const hash = new URL(href, location.href).hash
        if (settings.siteHosts.includes(url.host)) {
            if (url.pathname.startsWith('/s/')) {
                const file = local(absolute)
                if (file) {
                    anchor.setAttribute('href', file)
                }
                return
            }
            let pagePath = url.pathname.replace(/\/$/, '') || '/'
            if (pagePath === settings.homepagePath) {
                pagePath = '/'
            }
            if (removable.has(pagePath) && !anchor.hasAttribute('data-folder-id')) {
                removedLinks.push({ path: pagePath, text: anchor.innerText.trim().slice(0, 60) })
                const navItem = anchor.closest('.header-nav-item, .header-menu-nav-item, .header-nav-folder-item')
                if (navItem) {
                    navItem.remove()
                } else {
                    anchor.replaceWith(...anchor.childNodes)
                }
                return
            }
            if (pagePaths.has(pagePath) || anchor.hasAttribute('data-folder-id')) {
                anchor.setAttribute('href', pagePath + url.search + hash)
            }
            return
        }
        const file = local(absolute)
        if (file) {
            anchor.setAttribute('href', file)
        }
    })
    document.querySelectorAll('[data-href]').forEach(element => {
        const pagePath = element.getAttribute('data-href')
        if (pagePath === settings.homepagePath) {
            element.setAttribute('data-href', '/')
        }
    })

    document.querySelectorAll('*').forEach(element => {
        for (const attribute of [...element.attributes]) {
            if (forbidden.test(attribute.value)) {
                if (attribute.name.startsWith('data-') || attribute.name === 'srcset') {
                    element.removeAttribute(attribute.name)
                }
            }
        }
    })

    const head = document.head
    for (const href of settings.extraStylesheets) {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = href
        head.appendChild(link)
    }

    const html = '<!DOCTYPE html>\n' + document.documentElement.outerHTML
    const leftovers = [...html.matchAll(new RegExp(`.{0,80}(${settings.forbiddenPattern}).{0,80}`, 'gi'))].map(match => match[0])

    return { html, removedLinks, leftovers }
}

const archivedVideos = new Set()

const archiveVideo = async source => {
    const id = source.split('/').pop()
    if (archivedVideos.has(id)) {
        return
    }
    archivedVideos.add(id)
    const videoDir = path.join(outDir, 'videos', id)
    await fs.mkdir(videoDir, { recursive: true })
    try {
        const master = await (await fetchWithRetry(`${source}/playlist.m3u8`)).text()
        const streams = [...master.matchAll(/#EXT-X-STREAM-INF:[^\n]*BANDWIDTH=(\d+)[^\n]*\n(\S+)/g)].map(match => ({ bandwidth: Number(match[1]), url: match[2] }))
        const best = streams.sort((a, b) => b.bandwidth - a.bandwidth)[0]
        const audio = master.match(/#EXT-X-MEDIA:TYPE=AUDIO[^\n]*URI="([^"]+)"/)?.[1]
        const tracks = { video: best?.url, audio }
        for (const [track, playlistUrl] of Object.entries(tracks)) {
            if (!playlistUrl) {
                continue
            }
            const playlist = await (await fetchWithRetry(playlistUrl)).text()
            const segments = playlist.split('\n').filter(line => line && !line.startsWith('#'))
            await fs.mkdir(path.join(videoDir, track), { recursive: true })
            let index = 0
            const localNames = new Map()
            for (const segment of segments) {
                localNames.set(segment, `${track}/${String(index++).padStart(4, '0')}${path.extname(new URL(segment, playlistUrl).pathname) || '.ts'}`)
            }
            await withLimit(segments, 4, async segment => {
                const response = await fetchWithRetry(new URL(segment, playlistUrl).href)
                await fs.writeFile(path.join(videoDir, localNames.get(segment)), Buffer.from(await response.arrayBuffer()))
            })
            await fs.writeFile(path.join(videoDir, `${track}.m3u8`), playlist.split('\n').map(line => localNames.get(line) ?? line).join('\n'))
        }
        const thumbnail = await fetchWithRetry(`${source}/thumbnail`)
        await fs.writeFile(path.join(videoDir, 'thumbnail.jpg'), Buffer.from(await thumbnail.arrayBuffer()))
        report.videos_archived.push({ id, source, folder: `videos/${id}`, resolution: master.match(new RegExp(`RESOLUTION=(\\d+x\\d+)[^\\n]*\\n${(best?.url ?? '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`))?.[1] ?? null, to_mp4: `ffmpeg -i video.m3u8 -i audio.m3u8 -c copy ${id}.mp4` })
    } catch (error) {
        report.videos_archived.push({ id, source, error: String(error.message ?? error) })
    }
}

report.videos_archived = []

const allPaths = await readSitemapPaths()
const homepagePath = await findHomepagePath(allPaths)
const excluded = new Set(site.exclude ?? [])
const pagePaths = allPaths.map(pagePath => pagePath === homepagePath ? '/' : pagePath).filter(pagePath => !excluded.has(pagePath))
const selectedPaths = onlyPath ? pagePaths.filter(pagePath => pagePath === onlyPath) : pagePaths

console.log(`${domain}: ${pagePaths.length} pages (homepage ${homepagePath} served at /), excluded: ${[...excluded].join(', ') || 'none'}`)

const browser = await chromium.launch()
const desktopContext = await browser.newContext({ viewport: DESKTOP, userAgent: BROWSER_USER_AGENT })
const mobileContext = await browser.newContext({ viewport: MOBILE, userAgent: BROWSER_USER_AGENT, isMobile: true, hasTouch: true, deviceScaleFactor: 1 })

let fontCssPath = null
const manifestPages = []

for (const pagePath of selectedPaths) {
    const slug = slugOf(pagePath)
    const pageUrl = new URL(pagePath === '/' ? '/' : pagePath, site.source).href
    process.stdout.write(`  ${pagePath} ... `)

    if (takeScreenshots) {
        const mobile = await mobileContext.newPage()
        await mobile.goto(pageUrl, { waitUntil: 'networkidle', timeout: 120000 })
        await settle(mobile)
        await mobile.screenshot({ path: path.join(originalShotsDir, `${slug}.mobile.png`), fullPage: true })
        await mobile.close()
    }

    const page = await desktopContext.newPage()
    const videoStreams = new Set()
    page.on('response', response => {
        if (/\.m3u8(\?|$)/.test(response.url())) {
            videoStreams.add(response.url())
        }
    })
    await page.goto(pageUrl, { waitUntil: 'networkidle', timeout: 120000 })
    await settle(page)
    if (takeScreenshots) {
        await page.screenshot({ path: path.join(originalShotsDir, `${slug}.desktop.png`), fullPage: true })
    }

    const collected = await page.evaluate(cleanupInPage, {
        keptScriptHosts: config.keptScriptHosts,
        keptInlineScriptMarkers: config.keptInlineScriptMarkers,
        forbiddenPattern: forbiddenPattern.source,
        siteHosts: [...siteHosts],
    })

    if (fontCssPath === null && Object.keys(collected.typekitFamilies).length) {
        const fontCss = await replacementFontCss(collected.typekitFamilies)
        const fontCssName = `fonts-${hashOf(fontCss)}.css`
        await fs.writeFile(path.join(assetsDir, fontCssName), fontCss)
        fontCssPath = ASSET_PREFIX + fontCssName
        report.typekit_replaced = Object.fromEntries(Object.keys(collected.typekitFamilies).map(token => {
            const name = Object.keys(config.fontReplacements).find(candidate => candidate.replace(/-/g, '') === token)
            return [name, { replacement: config.fontReplacements[name].family, size_adjust: config.fontReplacements[name].sizeAdjust ?? null, variants: collected.typekitFamilies[token] }]
        }))
    }

    for (const issue of collected.issues.filter(issue => issue.type === 'squarespace_video' && issue.source)) {
        await archiveVideo(issue.source)
    }

    await withLimit(collected.images, 6, url => downloadAsset(url, 'image'))
    await withLimit(collected.assets, 6, url => downloadAsset(url))
    await withLimit(collected.stylesheets, 4, url => downloadAsset(url, 'css'))
    const inlineStyles = []
    for (const css of collected.inlineStyles) {
        inlineStyles.push(await processCss(css, pageUrl))
    }

    const removableLinks = []
    for (const linkPath of collected.internalLinks) {
        const normalised = linkPath === homepagePath ? '/' : linkPath
        if (pagePaths.includes(normalised) || normalised === '/cart') {
            continue
        }
        if (excluded.has(normalised) || (await linkStatus(normalised)) === 404) {
            removableLinks.push(normalised)
        }
    }

    const rewritten = await page.evaluate(rewriteInPage, {
        map: [...assetMap.entries()].filter(([, value]) => value),
        inlineStyles,
        pagePaths,
        removableLinks,
        homepagePath,
        siteHosts: [...siteHosts],
        publicOrigin: `https://${domain}`,
        forbiddenPattern: forbiddenPattern.source,
        extraStylesheets: [fontCssPath].filter(Boolean),
    })
    await page.close()

    await fs.writeFile(path.join(pagesDir, `${slug}.html`), rewritten.html)
    manifestPages.push({ path: pagePath, source_url: pageUrl, title: collected.title, description: collected.description, file: `pages/${slug}.html` })
    report.pages[pagePath] = {
        issues: [...collected.issues, ...[...videoStreams].map(url => ({ type: 'squarespace_video_stream', url }))],
        removed_links: rewritten.removedLinks,
        squarespace_leftovers: rewritten.leftovers,
    }
    report.removed_links.push(...rewritten.removedLinks.map(link => ({ page: pagePath, ...link })))
    console.log(`ok (${collected.images.length} images, ${collected.stylesheets.length} css, ${rewritten.leftovers.length} leftovers)`)
}

await browser.close()

const existingManifest = onlyPath ? JSON.parse(await fs.readFile(path.join(outDir, 'pages.json'), 'utf8').catch(() => '{"pages":[]}')) : { pages: [] }
const mergedPages = [...existingManifest.pages.filter(existing => !manifestPages.some(page => page.path === existing.path)), ...manifestPages]
    .sort((a, b) => pagePaths.indexOf(a.path) - pagePaths.indexOf(b.path))

await fs.writeFile(path.join(outDir, 'pages.json'), JSON.stringify({ domain, source: site.source, frozen_at: report.frozen_at, homepage_source_path: homepagePath, pages: mergedPages }, null, 2))
await fs.writeFile(path.join(outDir, onlyPath ? 'report.partial.json' : 'report.json'), JSON.stringify(report, null, 2))

const assetFiles = await fs.readdir(assetsDir)
const assetBytes = (await Promise.all(assetFiles.map(name => fs.stat(path.join(assetsDir, name)).then(stat => stat.size)))).reduce((sum, size) => sum + size, 0)
const leftovers = Object.values(report.pages).reduce((sum, page) => sum + page.squarespace_leftovers.length, 0)
console.log(`\n${domain}: ${mergedPages.length} pages, ${assetFiles.length} assets (${(assetBytes / 1048576).toFixed(1)} MB), ${report.assets_failed.length} failed downloads, ${leftovers} Squarespace leftovers`)
console.log(`Bundle: ${outDir}`)
