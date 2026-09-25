import assert from "node:assert/strict"
import { register } from "node:module"
import test from "node:test"

register(
	"data:text/javascript," +
		encodeURIComponent(`
			const resourcesJsUrl = ${JSON.stringify(new URL("../../resources/js/", import.meta.url).href)}
			export async function resolve(specifier, context, nextResolve) {
				if (specifier.startsWith("@/")) {
					return nextResolve(resourcesJsUrl + specifier.slice(2) + ".ts", context)
				}
				return nextResolve(specifier, context)
			}
		`)
)

const { generateProductsStructureFromProductsList } = await import(
	"../../resources/js/Iris/Composables/useStructuredData.ts"
)

const productsBlock = (products: Record<string, unknown>[]) => ({
	type: "products-1",
	web_block: { layout: { data: { fieldValue: { products: { data: products } } } } },
})

const product = {
	name: "Mini Aluminium Tin",
	code: "MTIN-09",
	url: "https://www.aw-aromatics.com/tins/mtin-09",
	price: "10.07",
	stock: 5,
}

test("every family product with a price gets an offer, so it stays eligible for a product snippet", () => {
	const [variant] = generateProductsStructureFromProductsList({
		webBlocks: [productsBlock([product])],
		currencyCode: "GBP",
	})

	assert.deepEqual(variant.offers, {
		"@type": "Offer",
		price: "10.07",
		availability: "https://schema.org/InStock",
		url: product.url,
		priceCurrency: "GBP",
	})
})

test("a family product gets an aggregate rating only from real ratings", () => {
	const [rated, unrated] = generateProductsStructureFromProductsList({
		webBlocks: [
			productsBlock([
				{ ...product, rating: 4.5, rating_count: 12 },
				{ ...product, url: `${product.url}-b`, rating: 0, rating_count: 0 },
			]),
		],
	})

	assert.equal(rated.aggregateRating.ratingValue, 4.5)
	assert.equal(rated.aggregateRating.reviewCount, 12)
	assert.equal(unrated.aggregateRating, undefined)
	assert.equal(unrated.review, undefined)
})

test("a family product with neither a price nor ratings is left out", () => {
	const variants = generateProductsStructureFromProductsList({
		webBlocks: [productsBlock([{ ...product, price: null }])],
	})

	assert.deepEqual(variants, [])
})
