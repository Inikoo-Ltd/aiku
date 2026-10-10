import assert from "node:assert/strict"
import { readFileSync, readdirSync } from "node:fs"
import { join } from "node:path"
import test from "node:test"

const root = join(import.meta.dirname, "..", "..")
const langDirectory = join(root, "lang")
const dimensionKey = "This will overwrite :count products dimensions, are you sure want to continue ?"

const filesUnder = (directory: string): string[] =>
	readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const path = join(directory, entry.name)
		if (entry.isDirectory()) {
			return filesUnder(path)
		}
		return /\.(vue|ts|js)$/.test(entry.name) ? [path] : []
	})

const languageFiles = readdirSync(langDirectory).filter((name) => name.endsWith(".json"))

test("no live ctrans call builds its key from an interpolated template literal", () => {
	const offenders = filesUnder(join(root, "resources", "js")).flatMap((path) =>
		readFileSync(path, "utf8")
			.split("\n")
			.filter((line) => !line.trim().startsWith("//") && /ctrans\(`[^`]*\$\{/.test(line))
			.map((line) => `${path}: ${line.trim()}`)
	)

	assert.deepEqual(offenders, [])
})

test("the update-dimensions confirmation is looked up by a :count key in every language that translates it", () => {
	const translated = languageFiles.filter((name) => {
		const strings = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))
		assert.ok(
			!Object.keys(strings).some((key) => key.includes("${totalProductsForDimensionUpdate}")),
			`${name} still carries the interpolated key`
		)
		return dimensionKey in strings
	})

	assert.ok(translated.includes("pl.json"))
	assert.ok(translated.includes("en.json"))

	for (const name of translated) {
		const value = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))[dimensionKey]
		assert.equal(value.match(/:count\b/g)?.length, 1, `${name} must keep :count exactly once`)
	}
})

test("the update-dimensions confirmation survives t:up through the glossary of every language that translates it", () => {
	const glossary = JSON.parse(readFileSync(join(root, "resources", "translation-glossary.json"), "utf8"))

	for (const name of languageFiles.filter((name) => name !== "en.json")) {
		const locale = name.replace(/\.json$/, "")
		const strings = JSON.parse(readFileSync(join(langDirectory, name), "utf8"))
		if (dimensionKey in strings) {
			assert.equal(glossary[locale]?.[dimensionKey], strings[dimensionKey], `${locale} glossary`)
		}
	}
})

test("the Retina basket recommendations translate through ctrans, not a trans alias", () => {
	const source = readFileSync(
		join(root, "resources", "js", "Components", "Retina", "BasketRecommendationsInternal.vue"),
		"utf8"
	)

	assert.match(source, /import \{ ctrans \} from '@\/Composables\/useTrans'/)
	assert.doesNotMatch(source, /ctrans as trans/)
	assert.doesNotMatch(source, /(?<![\w.])trans\(/)
})
