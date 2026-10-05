import assert from "node:assert/strict"
import test from "node:test"
import { isEligibleForProductSnippet } from "../../resources/js/Iris/Composables/productSnippetEligibility.ts"

test("a product with offers, a review or an aggregate rating is eligible for a product snippet", () => {
	assert.equal(isEligibleForProductSnippet({ "@type": "Product", offers: { "@type": "Offer", price: 1.5 } }), true)
	assert.equal(isEligibleForProductSnippet({ "@type": "Product", review: [{ "@type": "Review" }] }), true)
	assert.equal(isEligibleForProductSnippet({ "@type": "Product", aggregateRating: { "@type": "AggregateRating", ratingValue: 4 } }), true)
})

test("a product without offers, review or aggregate rating is not eligible, as when a guest cannot see prices", () => {
	assert.equal(isEligibleForProductSnippet({ "@type": "Product", name: "Mini Aluminium Tin", sku: "MTIN-09" }), false)
	assert.equal(isEligibleForProductSnippet({ "@type": "Product", offers: undefined, aggregateRating: null }), false)
	assert.equal(isEligibleForProductSnippet(null), false)
	assert.equal(isEligibleForProductSnippet(undefined), false)
})
