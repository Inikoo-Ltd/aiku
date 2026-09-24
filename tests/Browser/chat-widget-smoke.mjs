/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 *
 * Loads the built storefront widget onto a page that is not ours, with the chat API stubbed, and
 * checks that it boots, draws inside its shadow root and opens a session. Run with:
 *   node tests/Browser/chat-widget-smoke.mjs
 */

import { chromium } from "playwright"
import { readFileSync } from "node:fs"
import { createServer } from "node:http"

const widgetJs = readFileSync("public/chat-widget/v1.js", "utf8")
const widgetCss = readFileSync("public/chat-widget/v1.css", "utf8")

const STOREFRONT_PAGE = `<!doctype html>
<html><head><meta charset="utf-8"><title>HatiNest</title>
<style>button { background: red !important; font-size: 40px; }</style>
</head>
<body><h1>Hats</h1>
<script src="/chat-widget/v1.js" data-key="testkey"></script>
</body></html>`

const requests = []

const server = createServer((req, res) => {
    const url = new URL(req.url, "http://localhost")
    requests.push(url.pathname + url.search)

    if (url.pathname === "/chat-widget/v1.js") {
        res.writeHead(200, { "content-type": "application/javascript; charset=utf-8" })

        return res.end(widgetJs)
    }

    if (url.pathname === "/chat-widget/v1.css") {
        res.writeHead(200, { "content-type": "text/css; charset=utf-8" })

        return res.end(widgetCss)
    }

    if (url.pathname === "/app/api/chats/widget-config") {
        res.writeHead(200, { "content-type": "application/json" })

        return res.end(
            JSON.stringify({
                data: { shop_id: 51, name: "HatiNest", theme: null },
            })
        )
    }

    if (url.pathname === "/app/api/chats/status") {
        res.writeHead(200, { "content-type": "application/json" })

        return res.end(JSON.stringify({ chat_config: { is_online: true } }))
    }

    if (url.pathname === "/app/api/chats/sessions") {
        res.writeHead(200, { "content-type": "application/json" })

        return res.end(JSON.stringify({ data: { ulid: "01JTESTULID0000000000000AB" } }))
    }

    if (url.pathname === "/") {
        res.writeHead(200, { "content-type": "text/html; charset=utf-8" })

        return res.end(STOREFRONT_PAGE)
    }

    res.writeHead(200, { "content-type": "application/json" })
    res.end(JSON.stringify({ data: [] }))
})

await new Promise((resolve) => server.listen(4599, resolve))

const browser = await chromium.launch(
    process.env.CHROME_PATH ? { executablePath: process.env.CHROME_PATH } : {}
)
const page = await browser.newPage()

const consoleErrors = []
page.on("console", (message) => {
    if (message.type() === "error") {
        consoleErrors.push(message.text())
    }
})
page.on("pageerror", (error) => consoleErrors.push(`pageerror: ${error.message}`))

await page.goto("http://localhost:4599/", { waitUntil: "networkidle" })

const results = {}

results.containerExists = await page.locator("#aiku-chat-widget").count()

results.shadowRootAttached = await page.evaluate(
    () => !!document.getElementById("aiku-chat-widget")?.shadowRoot
)

results.buttonRendered = await page.evaluate(() => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot

    return !!shadow?.querySelector("button")
})

results.hostCssDidNotLeak = await page.evaluate(() => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot
    const button = shadow?.querySelector("button")

    if (!button) {
        return null
    }

    const style = getComputedStyle(button)

    return { fontSize: style.fontSize, background: style.backgroundColor }
})

results.themeVarApplied = await page.evaluate(() => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot
    const root = shadow?.querySelector("div")

    return root ? getComputedStyle(root).getPropertyValue("--theme-color-4").trim() : null
})

/* Clicking the bubble is what opens a session, so it is the real end to end check. */
await page.evaluate(() => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot
    shadow?.querySelector("button")?.click()
})
await page.waitForTimeout(1500)

results.sessionRequested = requests.some((r) => r.startsWith("/app/api/chats/sessions"))
results.requests = requests
results.consoleErrors = consoleErrors

console.log(JSON.stringify(results, null, 2))

await browser.close()
server.close()
