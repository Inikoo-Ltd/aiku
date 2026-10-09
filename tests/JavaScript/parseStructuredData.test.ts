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

const { parseStructuredData } = await import("../../resources/js/Iris/Composables/useStructuredData.ts")

test("webpages with no structured data write no JSON-LD at all", () => {
	for (const raw of [[], [""], "[]", '[""]', {}, "", null, "not json"]) {
		assert.equal(parseStructuredData(raw), null)
	}
})

test("every structured data node carries a schema context", () => {
	assert.deepEqual(parseStructuredData('{"@type":"Organization"}'), { "@context": "https://schema.org", "@type": "Organization" })
	assert.deepEqual(parseStructuredData(["", { "@context": "https://schema.org", "@type": "Service" }]), [{ "@context": "https://schema.org", "@type": "Service" }])
})
