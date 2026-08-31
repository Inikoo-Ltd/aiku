import assert from "node:assert/strict"
import test from "node:test"
import {
	authQuoteForRandomValue,
	translatedAuthQuotes,
} from "../../resources/js/Composables/useAuthQuote.ts"

test("uses exactly half of the random range for English quotes", () => {
	assert.equal(authQuoteForRandomValue(0).languageCode, "en")
	assert.equal(authQuoteForRandomValue(0.499999).languageCode, "en")
	assert.notEqual(authQuoteForRandomValue(0.5).languageCode, "en")
	assert.notEqual(authQuoteForRandomValue(0.999999).languageCode, "en")
})

test("provides an English translation and language metadata for every translated quote", () => {
	for (const quote of translatedAuthQuotes) {
		assert.ok(quote.text.length > 0)
		assert.ok(quote.englishTranslation.length > 0)
		assert.ok(quote.language.length > 0)
		assert.notEqual(quote.languageCode, "en")
		assert.ok(["ltr", "rtl"].includes(quote.direction))
	}
})

test("includes the requested scripts in the translated quote collection", () => {
	const languageCodes = translatedAuthQuotes.map((quote) => quote.languageCode)

	assert.ok(languageCodes.includes("ko"))
	assert.ok(languageCodes.includes("ja"))
	assert.ok(languageCodes.includes("uk"))
	assert.ok(languageCodes.includes("en-Brai"))
})
