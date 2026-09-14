import assert from "node:assert/strict"
import { readFileSync } from "node:fs"
import test from "node:test"
import {
	AROMA_ONLY_SECTIONS,
	regulatorySection,
	showsAromaSections,
} from "../../resources/js/Components/CMS/Webpage/Family2ExtraDescription/tabSections.ts"

const blueprintSource = readFileSync(
	"resources/js/Components/CMS/Webpage/Family2ExtraDescription/Blueprint.ts",
	"utf8"
)

test("styles the regulatory tab under the key the panel reads", () => {
	const section = regulatorySection()

	assert.deepEqual(section.key, ["regulatory"])
	assert.deepEqual(section.replaceForm[0].key, ["container", "properties"])
})

test("registers the regulatory section in the family extra description blueprint", () => {
	assert.match(blueprintSource, /regulatorySection,/)
	assert.match(blueprintSource, /regulatorySection\(\),/)
})

test("keeps the regulatory section available to every organisation", () => {
	assert.ok(!AROMA_ONLY_SECTIONS.includes("regulatory"))
	assert.equal(showsAromaSections({ organisation: { slug: "aw" } }), false)
	assert.equal(showsAromaSections({ organisation: { slug: "aroma" } }), true)
})
