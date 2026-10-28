import assert from "node:assert/strict"
import test from "node:test"
import { myShopIds } from "../../resources/js/Composables/chatMyShops.ts"

test("my shops are the alert shops the agent can open, or every shop they can open before they pick", () => {
	assert.deepEqual(myShopIds([1, 2, 3], [3, 1, 9]), [1, 3])
	assert.deepEqual(myShopIds([1, 2, 3], undefined), [1, 2, 3])
	assert.deepEqual(myShopIds([1, 2, 3], [9]), [1, 2, 3])
	assert.deepEqual(myShopIds([], [1]), [])
})
