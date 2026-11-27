import assert from "node:assert/strict"
import test from "node:test"
import { consentModeFromChoice, parseConsent, parseCookieScriptConsent, serializeConsent, useCookieConsent } from "../../resources/js/Composables/useCookieConsent.ts"

test("a choice round-trips through the cookie value", () => {
    const choice = { analytics: true, marketing: false, preferences: true }
    assert.deepEqual(parseConsent(serializeConsent(choice)), choice)
})

test("a rejection is everything denied and a missing cookie is not chosen", () => {
    assert.equal(serializeConsent({ analytics: false, marketing: false, preferences: false }), "none")
    assert.deepEqual(parseConsent("none"), { analytics: false, marketing: false, preferences: false })
    assert.equal(parseConsent(null), null)
})

test("marketing grants every ad signal", () => {
    const mode = consentModeFromChoice({ analytics: false, marketing: true, preferences: false })
    assert.equal(mode.ad_storage, "granted")
    assert.equal(mode.ad_user_data, "granted")
    assert.equal(mode.ad_personalization, "granted")
    assert.equal(mode.analytics_storage, "denied")
})

const none = { analytics: false, marketing: false, preferences: false }
const cookieScript = (stored: object) => JSON.stringify(stored).replace(/;/g, "%3B")

test("a CookieScript accept keeps exactly the categories the visitor ticked", () => {
    assert.deepEqual(
        parseCookieScriptConsent(cookieScript({ action: "accept", categories: JSON.stringify(["performance", "targeting", "functionality"]), key: "abc" })),
        { analytics: true, marketing: true, preferences: true },
    )
    assert.deepEqual(
        parseCookieScriptConsent(cookieScript({ action: "accept", categories: JSON.stringify(["performance"]) })),
        { analytics: true, marketing: false, preferences: false },
    )
    assert.deepEqual(parseCookieScriptConsent(cookieScript({ action: "accept", categories: "[]" })), none)
})

test("a CookieScript reject stays a reject whatever its categories say", () => {
    assert.deepEqual(parseCookieScriptConsent(cookieScript({ action: "reject", categories: "[]" })), none)
    assert.deepEqual(parseCookieScriptConsent(cookieScript({ action: "reject", categories: [] })), none)
    assert.deepEqual(parseCookieScriptConsent(cookieScript({ action: "reject", categories: JSON.stringify(["performance", "targeting"]) })), none)
})

test("a CookieScript cookie that cannot be read shows the banner instead of guessing", () => {
    assert.equal(parseCookieScriptConsent(null), null)
    assert.equal(parseCookieScriptConsent("not json"), null)
    assert.equal(parseCookieScriptConsent(cookieScript({ categories: JSON.stringify(["performance"]) })), null)
    assert.equal(parseCookieScriptConsent(cookieScript({ action: "accept", categories: "[broken" })), null)
    assert.equal(parseCookieScriptConsent(cookieScript({ action: "accept" })), null)
})

test("a CookieScript cookie written percent-encoded is still read", () => {
    const encoded = encodeURIComponent(JSON.stringify({ action: "accept", categories: JSON.stringify(["targeting"]) }))
    assert.deepEqual(parseCookieScriptConsent(encoded), { analytics: false, marketing: true, preferences: false })
})

/* A minimal browser: a cookie jar, sessionStorage, and fetch calls kept for checking (INI-572). */
const fakeBrowser = (cookies: Record<string, string> = {}) => {
    const jar = { ...cookies }
    const session = new Map<string, string>()
    const sent: any[] = []
    const g = globalThis as any
    g.document = {
        get cookie() { return Object.entries(jar).map(([name, value]) => `${name}=${value}`).join("; ") },
        set cookie(entry: string) { const [pair] = entry.split(";"); const at = pair.indexOf("="); jar[pair.slice(0, at)] = pair.slice(at + 1) },
    }
    g.sessionStorage = { getItem: (key: string) => session.get(key) ?? null, setItem: (key: string, value: string) => session.set(key, value) }
    g.location = { protocol: "https:" }
    g.window = g
    g.fetch = (url: string, options: any) => { sent.push({ url, ...JSON.parse(options.body) }); return Promise.resolve() }
    useCookieConsent().consent.value = null
    return sent
}

test("the banner is counted as shown once per session, and only the first choice is counted", () => {
    const sent = fakeBrowser()
    const { load, acceptAll, save } = useCookieConsent()

    load()
    load()
    assert.deepEqual(sent, [{ url: "/analytics/cookie-consent", event: "shown" }])

    acceptAll()
    save({ analytics: true, marketing: false, preferences: false })
    assert.deepEqual(sent.slice(1), [{ url: "/analytics/cookie-consent", event: "choice", analytics: true, marketing: true, preferences: true }])
})

test("a choice already saved, or carried over from CookieScript, is not counted", () => {
    const saved = fakeBrowser({ iris_cookie_consent: "none" })
    useCookieConsent().load()
    assert.deepEqual(saved, [])

    const migrated = fakeBrowser({ CookieScriptConsent: cookieScript({ action: "reject", categories: "[]" }) })
    useCookieConsent().load()
    assert.deepEqual(migrated, [])
    assert.deepEqual(useCookieConsent().consent.value, none)
})
