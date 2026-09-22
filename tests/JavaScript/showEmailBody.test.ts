import assert from "node:assert/strict"
import test from "node:test"
import { showEmailBody } from "../../resources/js/Composables/showEmailBody.ts"

test("shows the markup of a received email", () => {
	assert.equal(showEmailBody({ html_body: "<div>Photos attached</div>" }), true)
})

test("keeps showing it while a translation is on screen", () => {
	assert.equal(showEmailBody({ html_body: "<div>Photos attached</div>", edited_at: null }), true)
})

test("falls back to text when the message is not what arrived", () => {
	assert.equal(showEmailBody({ html_body: "<div>hi</div>", is_retracted: true }), false)
	assert.equal(showEmailBody({ html_body: "<div>hi</div>", edited_at: "2026-09-21 09:00:00" }), false)
})

test("has nothing to show for an ordinary chat message", () => {
	assert.equal(showEmailBody({}), false)
	assert.equal(showEmailBody({ html_body: null }), false)
})
