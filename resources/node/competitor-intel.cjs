/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 05 Nov 2026 18:45:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * Reads a competitor's public homepage and its policy pages for the dossier: a screenshot, the
 * company data the page publishes (JSON-LD, meta tags, contact links, social links), the platform
 * it runs on, its announcement bars and the text of up to six pages. Never logs in, and when a
 * site asks to prove we are human it says so and stops.
 */

const puppeteer = require('puppeteer')

const BLOCKED_WORDS = /captcha|verify you are (a )?human|unusual traffic|access denied|are you a robot|slide to verify|drag the slider/i
const POLICY_WORDS = /shipping|delivery|envio|envío|livraison|versand|doprava|return|devoluci|terms|conditions|faq|payment/i
const SOCIALS = {
    facebook: /facebook\.com/i,
    instagram: /instagram\.com/i,
    tiktok: /tiktok\.com/i,
    youtube: /youtube\.com|youtu\.be/i,
    linkedin: /linkedin\.com/i,
    pinterest: /pinterest\./i,
    x: /(twitter\.com|(^|\/\/)x\.com)/i,
}
const PRIVATE_HOST = /^(localhost|.+\.localhost|.+\.local|.+\.internal|0\.0\.0\.0|127\.\d+\.\d+\.\d+|10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+|169\.254\.\d+\.\d+|\[.*\])$/i
const MAX_PAGES = 6
const PAGE_TEXT = 12000

const readStdin = async () => {
    let input = ''
    for await (const chunk of process.stdin) {
        input += chunk
    }
    return JSON.parse(input)
}

const extract = (socials, pageText) => {
    const text = (element) => (element?.innerText || '').replace(/\s+/g, ' ').trim()
    const meta = (name) => document.querySelector(`meta[name="${name}"], meta[property="${name}"]`)?.content || null
    const jsonLd = [...document.querySelectorAll('script[type="application/ld+json"]')].flatMap((script) => {
        try {
            const parsed = JSON.parse(script.textContent)
            return Array.isArray(parsed) ? parsed : (parsed['@graph'] || [parsed])
        } catch {
            return []
        }
    })
    const html = document.documentElement.outerHTML
    const platform = window.Shopify || /cdn\.shopify\.com|shopify-section/.test(html) ? 'shopify'
        : /woocommerce/i.test(html) ? 'woocommerce'
        : /prestashop/i.test(html) ? 'prestashop'
        : /Magento|mage\/|magento/i.test(html) ? 'magento'
        : /wix\.com|wixstatic/i.test(html) ? 'wix'
        : /squarespace/i.test(html) ? 'squarespace'
        : /bigcommerce/i.test(html) ? 'bigcommerce'
        : 'other'
    const links = [...document.querySelectorAll('a[href]')].map((a) => ({href: a.href, text: text(a).slice(0, 120)}))
    const logo = document.querySelector('img[class*="logo" i], img[alt*="logo" i], img[src*="logo" i], header img, .header img')?.src
        || meta('og:image')
        || document.querySelector('link[rel*="icon"]')?.href
        || null
    const banners = [...document.querySelectorAll('[class*="announcement" i], [id*="announcement" i], [class*="promo-bar" i], [class*="promobar" i], [class*="top-bar" i], [class*="topbar" i], [class*="marquee" i], [class*="ticker" i], [class*="usp" i]')]
        .map(text)
        .filter((line) => line.length > 3 && line.length < 400)
    const bodyText = document.body.innerText.replace(/\n{2,}/g, '\n')
    const coupons = [...bodyText.matchAll(/\b(?:[Cc]ode|CODE|[Cc]oupon|COUPON|[Vv]oucher|VOUCHER)\b[:\s]+["'“]?([A-Z0-9]{4,20})\b/g)].map((match) => match[1]).filter((code) => /[A-Z]/.test(code))

    return {
        title: document.title,
        description: meta('description') || meta('og:description'),
        og_site_name: meta('og:site_name'),
        generator: meta('generator'),
        platform,
        logo,
        json_ld: jsonLd.filter((item) => /Organization|LocalBusiness|Store|WebSite|Corporation/i.test(JSON.stringify(item['@type'] || ''))).slice(0, 5),
        phones: [...new Set(links.filter((link) => link.href.startsWith('tel:')).map((link) => decodeURIComponent(link.href.slice(4)).trim()))],
        emails: [...new Set(links.filter((link) => link.href.startsWith('mailto:')).map((link) => link.href.slice(7).split('?')[0].trim().toLowerCase()))],
        socials: Object.fromEntries(Object.entries(socials).map(([name, pattern]) => [name, links.find((link) => new RegExp(pattern, 'i').test(link.href))?.href || null]).filter(([, href]) => href)),
        banners: [...new Set(banners)].slice(0, 10),
        coupons: [...new Set(coupons)].slice(0, 10),
        links: [...new Map(links.filter((link) => link.href.startsWith('http')).map((link) => [link.href, link.text])).entries()].slice(0, 400).map(([href, text]) => ({href, text})),
        text: bodyText.slice(0, pageText),
    }
}

const policyUrls = (homepage, website) => {
    const origin = new URL(homepage.final_url || website).origin
    const shopifyPolicies = homepage.platform === 'shopify'
        ? ['/policies/shipping-policy', '/policies/refund-policy', '/policies/terms-of-service'].map((path) => origin + path)
        : []
    const footerLinks = homepage.links
        .filter((link) => POLICY_WORDS.test(link.text) || POLICY_WORDS.test(new URL(link.href).pathname))
        .filter((link) => new URL(link.href).origin === origin && !/\.(pdf|jpg|png)$/i.test(link.href))
        .map((link) => link.href.split('#')[0])

    return [...new Set([...shopifyPolicies, ...footerLinks])]
        .filter((href) => href !== origin && href !== origin + '/')
        .slice(0, MAX_PAGES)
}

const settle = (page) => page.waitForNetworkIdle({idleTime: 800, timeout: 15000}).catch(() => null)

const open = async (page, url, timeout) => {
    const response = await page.goto(url, {waitUntil: 'domcontentloaded', timeout})
    await settle(page)
    return response
}

const readPage = async (page, url) => {
    const response = await open(page, url, 45000)
    const content = await page.evaluate(() => ({
        title: document.title,
        text: (document.querySelector('main, [role="main"], #MainContent, .main-content, article') || document.body).innerText.replace(/\n{2,}/g, '\n').slice(0, 12000),
    }))

    return {
        url,
        final_url: page.url(),
        status: response ? response.status() : null,
        blocked: BLOCKED_WORDS.test(content.text.slice(0, 3000)),
        ...content,
    }
}

const main = async () => {
    const input = await readStdin()
    const browser = await puppeteer.launch({headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage']})
    const result = {homepage: null, screenshot: null, pages: [], error: null}

    try {
        const page = await browser.newPage()
        // ponytail: literal private addresses only; a public name that resolves to a private address still gets through, a resolver check would close that
        await page.setRequestInterception(true)
        page.on('request', (request) => {
            const url = new URL(request.url())
            const isWeb = ['http:', 'https:'].includes(url.protocol)
            if ((isWeb && PRIVATE_HOST.test(url.hostname)) || (!isWeb && request.isNavigationRequest())) {
                return request.abort('blockedbyclient')
            }
            return request.continue()
        })
        await page.setUserAgent(input.user_agent)
        await page.setViewport({width: 1366, height: 900})

        const response = await open(page, input.website, 60000)
        await page.evaluate(() => window.scrollTo(0, 300)).catch(() => null)
        await new Promise((resolve) => setTimeout(resolve, 1500))
        await page.evaluate(() => window.scrollTo(0, 0)).catch(() => null)

        const homepage = await page.evaluate(extract, Object.fromEntries(Object.entries(SOCIALS).map(([name, pattern]) => [name, pattern.source])), PAGE_TEXT)
        result.homepage = {
            url: input.website,
            final_url: page.url(),
            status: response ? response.status() : null,
            blocked: BLOCKED_WORDS.test(homepage.text.slice(0, 3000)),
            ...homepage,
        }
        result.screenshot = await page.screenshot({type: 'jpeg', quality: 70, encoding: 'base64'})

        if (!result.homepage.blocked) {
            for (const url of policyUrls(result.homepage, input.website)) {
                try {
                    const pageRead = await readPage(page, url)
                    if (pageRead.status && pageRead.status < 400 && !pageRead.blocked) {
                        result.pages.push(pageRead)
                    }
                } catch (error) {
                    result.pages.push({url, error: error.message})
                }
            }
        }
    } catch (error) {
        result.error = error.message
    } finally {
        await browser.close()
    }

    delete result.homepage?.links
    process.stdout.write(JSON.stringify(result))
}

main().catch(error => {
    process.stdout.write(JSON.stringify({error: error.message}))
    process.exit(1)
})
