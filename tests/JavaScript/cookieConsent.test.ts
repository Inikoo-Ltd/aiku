import assert from "node:assert/strict"
import test from "node:test"
import { consentModeFromChoice, parseConsent, parseCookieScriptConsent, serializeConsent } from "../../resources/js/Composables/useCookieConsent.ts"

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
