import assert from "node:assert/strict"
import test from "node:test"
import { sentryIgnoreErrors } from "../../resources/js/Composables/sentryNoise.ts"

const ignored = (message: string) => sentryIgnoreErrors.some((pattern) => (typeof pattern === "string" ? message.includes(pattern) : pattern.test(message)))

test("third party and deploy noise is dropped", () => {
	for (const message of [
		"ReferenceError: fbq is not defined",
		"ReferenceError: Can't find variable: pintrk",
		"Error: Error invoking postMessage: Java object is gone",
		"TypeError: Failed to fetch dynamically imported module: https://www.ancientwisdom.biz/iris/assets/ProductSoundButton-DP8H_2We.js",
		"Network Error",
		"HTTP Client Error with status code: 503",
		"Request failed with status code 503",
	]) {
		assert.ok(ignored(message), message)
	}
})

test("our own errors still reach sentry", () => {
	for (const message of [
		"TypeError: Cannot read properties of null (reading 'text')",
		"ReferenceError: product is not defined",
		"Request failed with status code 500",
		"ReferenceError: richSnippetReviewsWidgets is not defined",
	]) {
		assert.ok(!ignored(message), message)
	}
})
