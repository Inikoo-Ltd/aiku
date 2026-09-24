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
<style>
  button { background: red !important; font-size: 40px; }
  /* Storefront themes really do ship these, and both killed the widget in the wild. */
  div:empty { display: none; }
  body > div { transform: translateY(2rem); }
</style>
</head>
<body><h1>Hats</h1>
<script src="/chat-widget/v1.js" data-key="testkey"></script>
</body></html>`

const requests = []
let offlineBody = null

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

        /* Offline by default: a shop with no agent on duty draws the offline form, which pulls in
           more of the UI than the online path. Set CHAT_ONLINE=1 for the other branch. */
        return res.end(
            JSON.stringify({ chat_config: { is_online: process.env.CHAT_ONLINE === "1" } })
        )
    }

    if (url.pathname === "/app/api/chats/offline-message") {
        let body = ""
        req.on("data", (c) => (body += c))
        req.on("end", () => {
            offlineBody = body
            res.writeHead(200, { "content-type": "application/json" })
            res.end(JSON.stringify({ data: { ulid: "01JOFFLINE000000000000000A" } }))
        })

        return
    }

    if (url.pathname === "/app/api/chats/sessions") {
        res.writeHead(200, { "content-type": "application/json" })

        return res.end(JSON.stringify({ data: { ulid: "01JTESTULID0000000000000AB" } }))
    }

    if (url.pathname === "/") {
        res.writeHead(200, { "content-type": "text/html; charset=utf-8" })

        return res.end(STOREFRONT_PAGE)
    }

    /* A real message so the bubble list renders: an empty thread skips most of the chat UI. */
    if (url.pathname.includes("/messages")) {
        res.writeHead(200, { "content-type": "application/json" })

        return res.end(
            JSON.stringify({
                data: [
                    {
                        id: 1,
                        chat_session_id: 1,
                        message_type: "text",
                        sender_type: "agent",
                        message_text: "Hello from the shop",
                        is_read: true,
                        created_at: "2026-09-24T10:00:00.000000Z",
                    },
                ],
            })
        )
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

results.hostNotHiddenByTheme = await page.evaluate(() => {
    const host = document.getElementById("aiku-chat-widget")
    const style = getComputedStyle(host)
    const rect = host.shadowRoot.querySelector("button").getBoundingClientRect()

    return {
        display: style.display,
        transform: style.transform,
        buttonVisible: rect.width > 0 && rect.height > 0,
        onScreen: rect.bottom <= window.innerHeight + 1 && rect.right <= window.innerWidth + 1,
    }
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

/* Clicking inside the panel must not shut it: inside a shadow root the event is retargeted to
   the host, which used to read as a click outside. */
results.panelStaysOpenOnInsideClick = await page.evaluate(async () => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot
    const field = shadow?.querySelector("input, textarea")

    if (!field) {
        return "no field rendered"
    }

    field.dispatchEvent(new MouseEvent("mousedown", { bubbles: true, composed: true }))
    await new Promise((r) => setTimeout(r, 300))

    return !!shadow.querySelector("input, textarea")
})

/* The offline form must carry the shop id: it comes from Iris everywhere else. */
results.offlineMessageCarriesShop = await page.evaluate(async () => {
    const shadow = document.getElementById("aiku-chat-widget")?.shadowRoot
    const fields = shadow?.querySelectorAll("input, textarea")

    if (!fields || fields.length < 3) {
        return "offline form not rendered"
    }

    const setValue = (el, value) => {
        const proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement : HTMLInputElement
        Object.getOwnPropertyDescriptor(proto.prototype, "value").set.call(el, value)
        el.dispatchEvent(new Event("input", { bubbles: true, composed: true }))
    }

    setValue(fields[0], "Tester")
    setValue(fields[1], "tester@example.com")
    setValue(fields[2], "hello")

    await new Promise((r) => setTimeout(r, 100))
    ;[...shadow.querySelectorAll("button")]
        .find((b) => /offline/i.test(b.textContent || ""))
        ?.click()

    await new Promise((r) => setTimeout(r, 800))

    return true
})

results.sessionRequested = requests.some((r) => r.startsWith("/app/api/chats/sessions"))
results.offlineBody = offlineBody
results.requests = requests
results.consoleErrors = consoleErrors

console.log(JSON.stringify(results, null, 2))

await browser.close()
server.close()
