import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"

const page = readFileSync(new URL("../../resources/js/Pages/Grp/Procurement/PurchaseOrder.vue", import.meta.url), "utf8")

test("the purchase order page shows its errors where the user acted, never in a toast", () => {
	assert.doesNotMatch(page, /notify\(/)
	assert.doesNotMatch(page, /vue3-notification/)

	for (const error of ["actionError", "estimatedDeliveryDateError", "createDeliveryError", "cancelError"]) {
		assert.match(page, new RegExp(`v-if="${error}"`), `${error} is rendered`)
	}
	assert.match(page, /submitBlockedBy === 'critical_notes'/)
	assert.match(page, /submitBlockedBy === 'agreed_terms'/)
})

test("cancelling a purchase order accepts yes in the user's language as well as in English", () => {
	assert.match(page, /cancelConfirmationWord = computed\(\(\) => ctrans\("yes"\)\.toLowerCase\(\)\)/)
	assert.match(page, /\["yes", cancelConfirmationWord\.value\]\.includes\(/)
	assert.match(page, /Type :word to cancel this order\.", \{ word: cancelConfirmationWord \}/)

	const german = JSON.parse(readFileSync(new URL("../../lang/de.json", import.meta.url), "utf8"))
	assert.match(german["Have you already informed them? Type :word to cancel this order."], /:word/)
})
