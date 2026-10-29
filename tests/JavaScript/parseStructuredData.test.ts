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

const { parseStructuredData, withImageLicense } = await import("../../resources/js/Iris/Composables/useStructuredData.ts")

test("webpages with no structured data write no JSON-LD at all", () => {
	for (const raw of [[], [""], "[]", '[""]', {}, "", null, "not json"]) {
		assert.equal(parseStructuredData(raw), null)
	}
})

test("every structured data node carries a schema context", () => {
	assert.deepEqual(parseStructuredData('{"@type":"Organization"}'), { "@context": "https://schema.org", "@type": "Organization" })
	assert.deepEqual(parseStructuredData(["", { "@context": "https://schema.org", "@type": "Service" }]), [{ "@context": "https://schema.org", "@type": "Service" }])
})

const { toJsonLd } = await import("../../resources/js/Iris/Composables/useStructuredData.ts")
const { buildWebpageStructuredData } = await import("../../resources/js/Iris/Composables/useWebpageStructuredData.ts")
const { buildBreadcrumbListNode } = await import("../../resources/js/Iris/Composables/useBreadcrumbStructuredData.ts")

test("JSON-LD written into the page HTML cannot close its own script tag", () => {
	assert.equal(toJsonLd({ name: "</script><script>alert(1)</script>" }), '{"name":"\\u003c/script>\\u003cscript>alert(1)\\u003c/script>"}')
})

test("relative breadcrumb links resolve against the page URL, so the server writes the same absolute links the browser does", () => {
	const breadcrumbs = [{ type: "simple", simple: { url: "/" } }, { type: "simple", simple: { label: "Aromatherapy", url: "https://www.aw-dropship.com/aromatherapy" } }]

	assert.deepEqual(
		buildBreadcrumbListNode(breadcrumbs, "https://www.aw-dropship.com/aromatherapy/aweo")?.itemListElement.map((item: Record<string, unknown>) => item.item),
		["https://www.aw-dropship.com/", "https://www.aw-dropship.com/aromatherapy"]
	)
})

const disclosureBlock = (isFaq: boolean) => ({
	type: "disclosure",
	web_block: { layout: { data: { fieldValue: { id: "faq", seo: { is_faq: isFaq }, value: [{ label: "Is it vegan?", description: "Yes" }] } } } },
})

const subDepartmentsBlock = (id: string) => ({
	type: "sub-departments-1",
	web_block: { layout: { data: { fieldValue: { id, sub_departments: [{ name: "Oils", url: "https://www.aw-dropship.com/aromatherapy/oils" }] } } } },
})

test("a webpage writes an FAQ only for disclosures marked as FAQ", () => {
	const webpage_data = { canonical_url: "https://www.aw-dropship.com/faq" }

	assert.equal(buildWebpageStructuredData({ webpage_data, web_blocks: [disclosureBlock(false)] }).length, 0)
	assert.equal((buildWebpageStructuredData({ webpage_data, web_blocks: [disclosureBlock(true)] })[0] as Record<string, unknown>)["@type"], "FAQPage")
})

test("a webpage writes one category item list even when it has several category blocks", () => {
	const webpage_data = { canonical_url: "https://www.aw-dropship.com/aromatherapy", model_type: "ProductCategory", sub_type: "department" }

	assert.equal(buildWebpageStructuredData({ webpage_data, web_blocks: [subDepartmentsBlock("a"), subDepartmentsBlock("b")] }).length, 1)
})

test("a webpage whose blocks arrive keyed by position still writes their structured data", () => {
	const webpage_data = { canonical_url: "https://www.aw-dropship.com/faq" }

	assert.equal(buildWebpageStructuredData({ webpage_data, web_blocks: { 0: disclosureBlock(true) } }).length, 1)
})

test("building a webpage's structured data leaves the page props it reads untouched", () => {
	const webpage_data = {
		canonical_url: "https://www.aw-dropship.com/aromatherapy/eo-20",
		seo_data: { structured_data: { "@graph": [{ "@type": "Product", name: "Patchouli" }] } },
	}
	const web_blocks = [{ type: "product-1", web_block: { layout: { data: { fieldValue: { product: { id: 1, name: "Patchouli", code: "EO-20", price: 3.2, stock: 4 } } } } } }]
	const before = JSON.stringify({ webpage_data, web_blocks })

	buildWebpageStructuredData({ webpage_data, web_blocks, currency_code: "GBP" })

	assert.equal(JSON.stringify({ webpage_data, web_blocks }), before)
})

test("every image URL in the structured data carries the website's licence, for the Licensable badge in Google Images", () => {
	const license = { websiteName: "AW Aromatics", termsUrl: "https://www.aw-aromatics.com/terms-and-conditions" }
	const licensedImage = (contentUrl: string) => ({
		"@type": "ImageObject",
		contentUrl,
		license: license.termsUrl,
		acquireLicensePage: license.termsUrl,
		creditText: "AW Aromatics",
		creator: { "@type": "Organization", name: "AW Aromatics" },
		copyrightNotice: "© AW Aromatics",
	})

	const licensed = withImageLicense(
		[
			{ "@type": "Product", image: ["https://media.aiku.io/a.jpg", "https://media.aiku.io/b.jpg"] },
			{ "@type": "ProductGroup", hasVariant: [{ "@type": "Product", image: "https://media.aiku.io/c.jpg" }] },
			{ "@type": "Product", image: { "@type": "ImageObject", contentUrl: "https://media.aiku.io/d.jpg" } },
		],
		license
	)

	assert.deepEqual(licensed[0].image, [licensedImage("https://media.aiku.io/a.jpg"), licensedImage("https://media.aiku.io/b.jpg")])
	assert.deepEqual(licensed[1].hasVariant[0].image, licensedImage("https://media.aiku.io/c.jpg"))
	assert.deepEqual(licensed[2].image, { "@type": "ImageObject", contentUrl: "https://media.aiku.io/d.jpg" })
})

test("image URLs stay plain when the website has no name and no terms page", () => {
	const nodes = [{ "@type": "Product", image: ["https://media.aiku.io/a.jpg"] }]
	assert.deepEqual(withImageLicense(nodes, { websiteName: null, termsUrl: null }), nodes)
})

const collectImageObjects = (value: unknown): Record<string, unknown>[] => {
	if (Array.isArray(value)) return value.flatMap(collectImageObjects)
	if (!value || typeof value !== "object") return []

	const node = value as Record<string, unknown>
	const own = node["@type"] === "ImageObject" ? [node] : []

	return [...own, ...Object.values(node).flatMap(collectImageObjects)]
}

test("department and sub-department pages write every picture they show as a licensed image", () => {
	const source = {
		website_name: "Ancient Wisdom",
		terms_url: "https://www.ancientwisdom.biz/terms-and-conditions",
	}
	const department = buildWebpageStructuredData({
		...source,
		webpage_data: { canonical_url: "https://www.ancientwisdom.biz/homefragrance", model_type: "ProductCategory", sub_type: "department" },
		web_blocks: [
			{ type: "top-families", structure: { families: [{ name: "Soy Pot Candles", url: "https://www.ancientwisdom.biz/candles/soyp", web_images: { main: { original: "https://www.ancientwisdom.biz/i/o/a.jpg", gallery: { original: "https://www.ancientwisdom.biz/i/a.jpg" } } } }] } },
			{
				type: "sub-departments-3",
				structure: {
					sub_department_list: [{ name: "Room Sprays", url: "https://www.ancientwisdom.biz/homefragrance/room-mists-sprays" }],
					families: { data: [{ name: "Melt Eaze Wax Melts", url: "https://www.ancientwisdom.biz/homefragrance/melte", image: { original: "https://www.ancientwisdom.biz/i/b.jpg" } }] },
				},
			},
			{
				type: "see-also-1",
				structure: {
					settings: {
						products_data: {
							type: "trending-now",
							products: [{ name: "Zodiac Starter", url: "https://www.ancientwisdom.biz/zfo-st", web_images: { main: { gallery: { original: "https://www.ancientwisdom.biz/i/hidden.jpg" } } } }],
							top_sellers: [{ name: "Sakura Room Spray", url: "https://www.ancientwisdom.biz/hf-01", web_images: { main: { original: "https://www.ancientwisdom.biz/i/o/c.jpg", gallery: { original: "https://www.ancientwisdom.biz/i/c.jpg" } } } }],
						},
					},
				},
			},
		],
	})
	const subDepartment = buildWebpageStructuredData({
		...source,
		webpage_data: { canonical_url: "https://www.ancientwisdom.biz/homefragrance/room-mists-sprays", model_type: "ProductCategory", sub_type: "sub_department" },
		web_blocks: [
			{ type: "families-3", structure: { families: [{ name: "Oudh Room Sprays", url: "https://www.ancientwisdom.biz/homefragrance/room-mists-sprays/oudhrs", web_images: { main: { gallery: { original: "https://www.ancientwisdom.biz/i/d.jpg" } } } }] } },
			{
				type: "products-1",
				structure: {
					products: { data: [{ name: "Oudh Noir", url: "https://www.ancientwisdom.biz/oudhrs-04", web_images: { main: { gallery: { original: "https://www.ancientwisdom.biz/i/e.jpg" } } } }] },
					products_out_of_stock: { data: [{ name: "Agarwood", url: "https://www.ancientwisdom.biz/oudhrs-01", web_images: { main: { gallery: { original: "https://www.ancientwisdom.biz/i/f.jpg" } } } }] },
				},
			},
		],
	})

	const departmentJson = JSON.stringify(department)
	assert.ok(departmentJson.includes("/homefragrance#top-families-0"))
	assert.ok(departmentJson.includes("/homefragrance#department-list-1"))

	const departmentImages = collectImageObjects(department)
	const subDepartmentImages = collectImageObjects(subDepartment)

	assert.deepEqual(departmentImages.map((image) => image.contentUrl).sort(), ["https://www.ancientwisdom.biz/i/a.jpg", "https://www.ancientwisdom.biz/i/b.jpg", "https://www.ancientwisdom.biz/i/c.jpg"])
	assert.deepEqual(subDepartmentImages.map((image) => image.contentUrl).sort(), ["https://www.ancientwisdom.biz/i/d.jpg", "https://www.ancientwisdom.biz/i/e.jpg", "https://www.ancientwisdom.biz/i/f.jpg"])

	for (const image of [...departmentImages, ...subDepartmentImages]) {
		assert.equal(image.license, source.terms_url)
		assert.equal(image.acquireLicensePage, source.terms_url)
		assert.equal(image.creditText, "Ancient Wisdom")
		assert.equal(image.copyrightNotice, "© Ancient Wisdom")
	}
})
