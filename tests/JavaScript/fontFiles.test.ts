import assert from "node:assert/strict"
import { existsSync, readFileSync } from "node:fs"
import test from "node:test"

const fontsCss = readFileSync("resources/css/fonts.css", "utf8")
const fontUrls = [...fontsCss.matchAll(/url\('([^']+)'\)/g)].map(match => match[1])

test("every font file named in fonts.css exists in public", () => {
	assert.ok(fontUrls.length > 0)
	assert.deepEqual(fontUrls.filter(url => !existsSync(`public${url}`)), [])
})

test("Bunya loads the licensed webfont in every weight, never the personal-use files", () => {
	const bunyaUrls = fontUrls.filter(url => url.includes("/Bunya/"))

	assert.deepEqual(bunyaUrls.filter(url => url.includes("_PERSONAL")), [])
	for (const weight of ["Thin", "Light", "Regular", "Bold", "Black"]) {
		assert.ok(bunyaUrls.includes(`/assets/Fonts/Bunya/Bunya-${weight}.woff2`), weight)
		assert.ok(bunyaUrls.includes(`/assets/Fonts/Bunya/Bunya-${weight}.woff`), weight)
	}
})
