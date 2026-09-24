/*
 * Loads a real storefront and reports whether the Aiku chat widget booted.
 *   STOREFRONT=https://example.myshopify.com node tests/Browser/storefront-check.mjs
 */
import { chromium } from "playwright"

const url = process.env.STOREFRONT
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH })
const page = await browser.newPage()

const logs = []
const net = []
page.on("console", (m) => logs.push(`${m.type()}: ${m.text()}`))
page.on("pageerror", (e) => logs.push(`pageerror: ${e.message}`))
page.on("requestfailed", (r) =>
    net.push(`FAILED ${r.url().slice(0, 90)} :: ${r.failure()?.errorText}`)
)
page.on("response", (r) => {
    if (/chat-widget|widget-config|googletagmanager/.test(r.url())) {
        net.push(`${r.status()} ${r.url().slice(0, 110)}`)
    }
})

await page.goto(url, { waitUntil: "networkidle", timeout: 60000 })
await page.waitForTimeout(4000)

const widget = await page.evaluate(() => {
    const el = document.getElementById("aiku-chat-widget")
    return {
        containerPresent: !!el,
        shadow: !!el?.shadowRoot,
        button: !!el?.shadowRoot?.querySelector("button"),
    }
})

console.log("final url:", page.url())
console.log("title:", await page.title())
console.log("gtm in html:", (await page.content()).includes("googletagmanager.com/gtm.js"))
console.log("widget:", JSON.stringify(widget))
console.log("network:"); net.forEach((n) => console.log("   ", n))
console.log("console:"); logs.slice(0, 15).forEach((l) => console.log("   ", l.slice(0, 200)))

await browser.close()
