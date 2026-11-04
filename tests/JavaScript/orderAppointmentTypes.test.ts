import assert from "node:assert/strict"
import test from "node:test"
import { orderAppointmentTypes } from "../../resources/js/Components/CMS/Webpage/Appointment/orderAppointmentTypes.ts"

const videoCall = { id: 2, name: "Showroom Video Call" }
const visit = { id: 1, name: "Showroom Visit" }
const tour = { id: 3, name: "Factory Tour" }

const names = (appointmentTypes: { name: string }[]) => appointmentTypes.map(appointmentType => appointmentType.name)

test("without a saved order the types stay in alphabetical order, as the live page showed them", () => {
	assert.deepEqual(names(orderAppointmentTypes([visit, videoCall])), ["Showroom Video Call", "Showroom Visit"])
	assert.deepEqual(names(orderAppointmentTypes([visit, videoCall], null)), ["Showroom Video Call", "Showroom Visit"])
})

test("the order set in the workshop puts the showroom visit first", () => {
	assert.deepEqual(names(orderAppointmentTypes([videoCall, visit], [1, 2])), ["Showroom Visit", "Showroom Video Call"])
})

test("a type added after the order was saved goes after the ordered ones, alphabetically", () => {
	assert.deepEqual(
		names(orderAppointmentTypes([videoCall, tour, visit, { id: 4, name: "Also new" }], [1, 2])),
		["Showroom Visit", "Showroom Video Call", "Also new", "Factory Tour"]
	)
})

test("ids of types that were deleted or switched off are ignored", () => {
	assert.deepEqual(names(orderAppointmentTypes([videoCall, visit], [99, 1, 2])), ["Showroom Visit", "Showroom Video Call"])
})

test("the list it was given is left untouched", () => {
	const appointmentTypes = [videoCall, visit]
	orderAppointmentTypes(appointmentTypes, [1, 2])

	assert.deepEqual(names(appointmentTypes), ["Showroom Video Call", "Showroom Visit"])
})
