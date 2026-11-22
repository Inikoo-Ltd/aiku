import assert from "node:assert/strict"
import test from "node:test"
import { consentModeFromChoice, parseConsent, serializeConsent } from "../../resources/js/Composables/useCookieConsent.ts"

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
