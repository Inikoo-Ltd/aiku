import assert from "node:assert/strict"
import test from "node:test"
import { getSchema } from "@tiptap/core"
import Document from "@tiptap/extension-document"
import Paragraph from "@tiptap/extension-paragraph"
import Text from "@tiptap/extension-text"
import { CtaButton } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/CtaButton/CtaButtonExtension.ts"
import {
	CTA_BUTTON_THEME_BACKGROUND,
	CTA_BUTTON_THEME_TEXT,
	ctaButtonStyle,
	ctaButtonWrapperStyle,
	defaultCtaButtonAttributes,
	normaliseCtaButtonAttributes,
	safeCtaButtonColor,
	safeCtaButtonHref,
} from "../../resources/js/Components/Forms/Fields/BubleTextEditor/CtaButton/ctaButtonLayout.ts"
import { BLOG_EDITOR_TOOLS, DEFAULT_EDITOR_TOOLS } from "../../resources/js/Components/Forms/Fields/BubleTextEditor/editorTools.ts"

const schema = getSchema([Document, Paragraph, Text, CtaButton])
const renderButton = (attributes: Record<string, unknown>) =>
	schema.nodes.ctaButton.spec.toDOM!(schema.nodes.ctaButton.create(attributes)) as [string, Record<string, string>, [string, Record<string, string>, string]]

test("only the blog editor offers the button", () => {
	assert.ok(BLOG_EDITOR_TOOLS.includes("ctaButton"))
	assert.ok(!DEFAULT_EDITOR_TOOLS.includes("ctaButton"))
	assert.deepEqual(BLOG_EDITOR_TOOLS.filter((tool) => tool !== "ctaButton"), DEFAULT_EDITOR_TOOLS)
})

test("page paths, web, email and phone links are kept", () => {
	assert.equal(safeCtaButtonHref(" /christmas "), "/christmas")
	assert.equal(safeCtaButtonHref("https://www.awgifts.eu/christmas"), "https://www.awgifts.eu/christmas")
	assert.equal(safeCtaButtonHref("mailto:sales@aiku.test"), "mailto:sales@aiku.test")
	assert.equal(safeCtaButtonHref("tel:+442012345678"), "tel:+442012345678")
	assert.equal(safeCtaButtonHref("#product-3"), "#product-3")
})

test("script and data links and protocol-relative hosts are dropped", () => {
	assert.equal(safeCtaButtonHref("javascript:alert(1)"), "")
	assert.equal(safeCtaButtonHref(" JavaScript:alert(1)"), "")
	assert.equal(safeCtaButtonHref("java\tscript:alert(1)"), "")
	assert.equal(safeCtaButtonHref("data:text/html,<script>alert(1)</script>"), "")
	assert.equal(safeCtaButtonHref("//evil.test/path"), "")
	assert.equal(safeCtaButtonHref(null), "")
})

test("only plain colours reach the style attribute", () => {
	assert.equal(safeCtaButtonColor("#E11D48"), "#E11D48")
	assert.equal(safeCtaButtonColor("rgb(225, 29, 72)"), "rgb(225, 29, 72)")
	assert.equal(safeCtaButtonColor("red; background-image: url(https://evil.test)"), null)
	assert.equal(safeCtaButtonColor("#12"), null)
	assert.equal(safeCtaButtonColor(""), null)
})

test("a button without its own colours uses the website theme", () => {
	const style = ctaButtonStyle(defaultCtaButtonAttributes())

	assert.ok(style.includes(`background-color: ${CTA_BUTTON_THEME_BACKGROUND};`))
	assert.ok(style.includes(`color: ${CTA_BUTTON_THEME_TEXT};`))
	assert.ok(style.includes("text-decoration: none;"))
	assert.ok(style.includes("border-radius: 0.375rem;"))
})

test("chosen colours, size and shape are written into the style", () => {
	const style = ctaButtonStyle(normaliseCtaButtonAttributes({ size: "large", shape: "pill", backgroundColor: "#e11d48", textColor: "#ffffff" }))

	assert.ok(style.includes("background-color: #e11d48;"))
	assert.ok(style.includes("color: #ffffff;"))
	assert.ok(style.includes("padding: 1rem 2.5rem;"))
	assert.ok(style.includes("border-radius: 9999px;"))
	assert.equal(ctaButtonWrapperStyle("right"), "text-align: right; margin: 1.25rem 0;")
})

test("unknown values fall back to the defaults and an empty label reads Buy now", () => {
	const attributes = normaliseCtaButtonAttributes({ label: "  ", alignment: "middle", size: "huge", shape: "star", target: "_top" })

	assert.deepEqual(
		{ label: attributes.label, alignment: attributes.alignment, size: attributes.size, shape: attributes.shape, target: attributes.target },
		{ label: "Buy now", alignment: "center", size: "medium", shape: "rounded", target: "_self" },
	)
})

test("the saved html is a real link the public blog can show and the editor can read back", () => {
	const [wrapperTag, wrapper, [anchorTag, anchor, label]] = renderButton({
		label: "Buy now",
		href: "/christmas",
		target: "_blank",
		alignment: "left",
		backgroundColor: "#e11d48",
	})

	assert.equal(wrapperTag, "div")
	assert.equal(wrapper["data-cta-button"], "")
	assert.equal(wrapper["data-align"], "left")
	assert.equal(anchorTag, "a")
	assert.equal(anchor.class, "cta-button")
	assert.equal(anchor.href, "/christmas")
	assert.equal(anchor.target, "_blank")
	assert.equal(anchor.rel, "noopener")
	assert.equal(anchor["data-background-color"], "#e11d48")
	assert.equal(anchor["data-text-color"], undefined)
	assert.equal(label, "Buy now")
})

test("a button whose link is unsafe is saved without the link", () => {
	const [, , [, anchor]] = renderButton({ label: "Click", href: "javascript:alert(1)" })

	assert.equal(anchor.href, undefined)
	assert.equal(anchor.rel, undefined)
})

test("the link type follows the address, and a page of the website keeps its webpage", () => {
	assert.equal(normaliseCtaButtonAttributes({ href: "mailto:sales@aiku.test", linkType: "web" }).linkType, "email")
	assert.equal(normaliseCtaButtonAttributes({ href: "tel:+442012345678" }).linkType, "phone")
	assert.equal(normaliseCtaButtonAttributes({ href: "sms:+442012345678" }).linkType, "sms")
	assert.equal(normaliseCtaButtonAttributes({ href: "https://www.awgifts.eu" }).linkType, "web")

	const internal = normaliseCtaButtonAttributes({ href: "/christmas", linkType: "internal", webpageId: 42 })
	assert.equal(internal.linkType, "internal")
	assert.equal(internal.webpageId, "42")

	const withoutWebpage = normaliseCtaButtonAttributes({ href: "/christmas", linkType: "internal", webpageId: null })
	assert.equal(withoutWebpage.linkType, "web")
	assert.equal(withoutWebpage.webpageId, null)
})

test("the seo rel is written next to noopener and read back", () => {
	const [, , [, anchor]] = renderButton({ label: "Buy now", href: "/christmas", linkType: "internal", webpageId: "7", target: "_blank", seoRel: "nofollow" })

	assert.equal(anchor.rel, "nofollow noopener")
	assert.equal(anchor["data-link-type"], "internal")
	assert.equal(anchor["data-webpage-id"], "7")
	assert.equal(normaliseCtaButtonAttributes({ seoRel: "sponsored" }).seoRel, null)

	const anchorAttributes: Record<string, string> = { href: "/christmas", target: "_blank", rel: "nofollow noopener" }
	const savedAnchor = {
		textContent: "Buy now",
		dataset: { linkType: "internal", webpageId: "7" },
		getAttribute: (name: string) => anchorAttributes[name] ?? null,
	}
	const wrapper = { dataset: { align: "center" }, querySelector: () => savedAnchor, getAttribute: () => null } as unknown as HTMLElement
	const parsed = schema.nodes.ctaButton.spec.parseDOM![0].getAttrs!(wrapper) as Record<string, unknown>

	assert.equal(parsed.seoRel, "nofollow")
	assert.equal(parsed.linkType, "internal")
	assert.equal(parsed.webpageId, "7")
})
