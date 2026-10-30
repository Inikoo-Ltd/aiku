import assert from "node:assert/strict"
import test from "node:test"
import { ALERT_EVENT_WAYS, alertWayAllowed, type AlertEvent, type AlertWay } from "../../resources/js/Composables/alertEvents.ts"

const everyChoice = (Object.entries(ALERT_EVENT_WAYS) as [AlertEvent, AlertWay[]][])
	.flatMap(([event, ways]) => ways.map((way) => [event, way] as const))

test("an agent who never chose keeps every alert they had", () => {
	for (const [event, way] of everyChoice) {
		assert.equal(alertWayAllowed(null, null, event, way), true)
		assert.equal(alertWayAllowed(null, undefined, event, way), true)
		assert.equal(alertWayAllowed(null, {}, event, way), true)
	}
})

test("each way of each event switches off on its own", () => {
	const choices = { waiting: { sound: false }, colleague: { popup: false, desktop: true } }

	assert.equal(alertWayAllowed(null, choices, "waiting", "sound"), false)
	assert.equal(alertWayAllowed(null, choices, "waiting", "desktop"), true)
	assert.equal(alertWayAllowed(null, choices, "colleague", "popup"), false)
	assert.equal(alertWayAllowed(null, choices, "colleague", "desktop"), true)
	assert.equal(alertWayAllowed(null, choices, "colleague", "sound"), true)
	assert.equal(alertWayAllowed(null, choices, "customer_message", "sound"), true)
})

test("a choice saved in another tab wins over what this tab loaded with", () => {
	const loaded = { assigned: { sound: true } }
	const savedElsewhere = { assigned: { sound: false } }

	assert.equal(alertWayAllowed(savedElsewhere, loaded, "assigned", "sound"), false)
	assert.equal(alertWayAllowed({ assigned: { sound: true } }, { assigned: { sound: false } }, "assigned", "sound"), true)
})

test("the escalation for a customer still waiting has no pop-up to switch", () => {
	assert.deepEqual(ALERT_EVENT_WAYS.waiting, ["desktop", "sound"])
})
