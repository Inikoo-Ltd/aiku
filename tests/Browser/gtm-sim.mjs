/*
 * Reproduces how a tag manager injects the storefront widget, to tell a broken embed from a
 * broken widget. Run with:
 *   WIDGET_ORIGIN=https://example.com WIDGET_KEY=<key> node tests/Browser/gtm-sim.mjs
 */

import { chromium } from "playwright"
import { createServer } from "node:http"

const TUNNEL = process.env.WIDGET_ORIGIN ?? "http://localhost"
const KEY = process.env.WIDGET_KEY ?? ""

const variants = {
  "A: GTM async inject WITH data-key": `
    var s=document.createElement('script');
    s.src='${TUNNEL}/chat-widget/v1.js';s.async=true;
    s.setAttribute('data-key','${KEY}');
    document.body.appendChild(s);`,
  "B: GTM async inject WITHOUT data-key": `
    var s=document.createElement('script');
    s.src='${TUNNEL}/chat-widget/v1.js';s.async=true;
    document.body.appendChild(s);`,
  "C: key in query string ?k=": `
    var s=document.createElement('script');
    s.src='${TUNNEL}/chat-widget/v1.js?k=${KEY}';s.async=true;
    document.body.appendChild(s);`,
}

const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH })

for (const [name, code] of Object.entries(variants)) {
  const server = createServer((req, res) => {
    res.writeHead(200, { "content-type": "text/html; charset=utf-8" })
    res.end(`<!doctype html><html><head><meta charset="utf-8"></head><body><h1>store</h1>
      <script>${code}<\/script></body></html>`)
  })
  await new Promise((r) => server.listen(4601, r))

  const page = await browser.newPage()
  const logs = []
  page.on("console", (m) => logs.push(`${m.type()}: ${m.text()}`))
  page.on("pageerror", (e) => logs.push(`pageerror: ${e.message}`))

  await page.goto("http://localhost:4601/", { waitUntil: "networkidle" })
  await page.waitForTimeout(2500)

  const mounted = await page.evaluate(
    () => !!document.getElementById("aiku-chat-widget")?.shadowRoot?.querySelector("button")
  )
  console.log(`${name}\n   bubble: ${mounted ? "YES" : "NO"}   logs: ${JSON.stringify(logs)}\n`)

  await page.close()
  server.close()
  await new Promise((r) => setTimeout(r, 200))
}
await browser.close()
