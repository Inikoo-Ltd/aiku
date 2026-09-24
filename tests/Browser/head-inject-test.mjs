import { chromium } from "playwright"
import { createServer } from "node:http"

const TUNNEL = process.env.WIDGET_ORIGIN
const KEY = process.env.WIDGET_KEY

const cases = {
  "body.appendChild, run from <head> (= gtm.init timing)": {
    head: `var a=document.createElement('script');a.src='${TUNNEL}/chat-widget/v1.js?k=${KEY}';a.async=!0;document.body.appendChild(a);`,
  },
  "head.appendChild, run from <head>": {
    head: `var a=document.createElement('script');a.src='${TUNNEL}/chat-widget/v1.js?k=${KEY}';a.async=!0;(document.head||document.documentElement).appendChild(a);`,
  },
}

const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH })

for (const [name, cfg] of Object.entries(cases)) {
  const server = createServer((req, res) => {
    res.writeHead(200, { "content-type": "text/html; charset=utf-8" })
    res.end(`<!doctype html><html><head><meta charset="utf-8">
      <script>${cfg.head}<\/script></head><body><h1>store</h1></body></html>`)
  })
  await new Promise((r) => server.listen(4602, r))

  const page = await browser.newPage()
  const logs = []
  page.on("pageerror", (e) => logs.push(`pageerror: ${e.message}`))
  page.on("console", (m) => m.type() === "error" && logs.push(m.text()))

  await page.goto("http://localhost:4602/", { waitUntil: "networkidle" })
  await page.waitForTimeout(2500)
  const ok = await page.evaluate(
    () => !!document.getElementById("aiku-chat-widget")?.shadowRoot?.querySelector("button")
  )
  console.log(`${name}\n   bubble: ${ok ? "YES" : "NO"}   ${JSON.stringify(logs)}\n`)

  await page.close()
  server.close()
  await new Promise((r) => setTimeout(r, 200))
}
await browser.close()
