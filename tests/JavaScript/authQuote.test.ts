import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import {
	authQuoteArticleUrl,
	authQuoteForRandomValue,
	linkedAuthQuoteArticles,
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

test("builds linked quote URLs on the public local and production domains", () => {
	const slug = "stop-pretending-you-are-forecasting"

	assert.equal(
		authQuoteArticleUrl("https://aiku.test", slug),
		"https://aiku.test/blog/stop-pretending-you-are-forecasting"
	)
	assert.equal(
		authQuoteArticleUrl("https://aiku.io", slug),
		"https://aiku.io/blog/stop-pretending-you-are-forecasting"
	)
})

test("keeps every linked login quote verbatim and italic in its article", () => {
	assert.equal(Object.keys(linkedAuthQuoteArticles).length, 5)

	for (const [quote, articleSlug] of Object.entries(linkedAuthQuoteArticles)) {
		const article = readFileSync(
			new URL(`../../resources/markdown/aiku-public/blog/${articleSlug}.md`, import.meta.url),
			"utf8"
		)

		assert.ok(article.includes(`*${quote}*`))
	}
})
