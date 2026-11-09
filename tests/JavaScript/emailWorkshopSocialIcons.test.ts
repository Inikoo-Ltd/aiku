import assert from "node:assert/strict"
import test from "node:test"
import {
	applySocialIconSet,
	createSocialIcon,
	setSocialIconSources,
	socialIconSetOf,
	socialIconSrc,
} from "../../resources/js/Components/CMS/Website/Outboxes/EmailWorkshop/emailWorkshopBlocks.ts"

const ownIcons = {
	"circle-color/tiktok": "https://img.aiku.test/circle-color-tiktok.png",
	"circle-black/facebook": "https://img.aiku.test/circle-black-facebook.png",
	"circle-black/instagram": "https://img.aiku.test/circle-black-instagram.png",
}

test("creates a social icon for a new network from our own hosted icons", () => {
	setSocialIconSources(ownIcons)
	const icon = createSocialIcon("tiktok", "circle-color")

	assert.equal(icon.name, "tiktok")
	assert.equal(icon.iconSet, "circle-color")
	assert.equal(icon.image.alt, "TikTok")
	assert.equal(icon.image.href, "https://www.tiktok.com/")
	assert.equal(icon.image.src, "https://img.aiku.test/circle-color-tiktok.png")
})

test("knows the icon style of new icons and of icons saved with the old beefree urls", () => {
	setSocialIconSources(ownIcons)

	assert.equal(socialIconSetOf(createSocialIcon("x", "t-only-logo-white")), "t-only-logo-white")
	assert.equal(socialIconSetOf({ image: { src: "https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/circle-blue/x@2x.png" } }), "circle-blue")
	assert.equal(socialIconSetOf({ name: "x", iconSet: null, image: { src: "https://example.com/my-icon.png" } }), null)
})

test("changing the icon style moves old beefree icons to our own icons and keeps custom uploads", () => {
	setSocialIconSources(ownIcons)
	const legacyFacebook = { name: "facebook", image: { src: "https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/t-outline-circle-dark-black/facebook@2x.png" } }
	const custom = { ...createSocialIcon("instagram"), iconSet: null }
	custom.image.src = "https://example.com/my-instagram.png"

	applySocialIconSet([legacyFacebook, custom], "circle-black")

	assert.equal(legacyFacebook.image.src, socialIconSrc("facebook", "circle-black"))
	assert.equal(legacyFacebook.image.src, "https://img.aiku.test/circle-black-facebook.png")
	assert.equal(custom.image.src, "https://example.com/my-instagram.png")
})

test("an icon missing from our own set is left empty instead of loading it from beefree", () => {
	setSocialIconSources(ownIcons)

	assert.equal(socialIconSrc("discord", "circle-blue"), "")
})

test("changing the icon style turns old beefree twitter icons into our own x icons", () => {
	setSocialIconSources({ "circle-black/x": "https://img.aiku.test/circle-black-x.png" })
	const legacyTwitter = { name: "twitter", image: { src: "https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/circle-color/twitter@2x.png" } }
	const unnamedTwitter = { image: { src: "https://app-rsrc.getbee.io/public/resources/social-networks-icon-sets/circle-color/twitter@2x.png" } }

	applySocialIconSet([legacyTwitter, unnamedTwitter], "circle-black")

	assert.equal(legacyTwitter.image.src, "https://img.aiku.test/circle-black-x.png")
	assert.equal(unnamedTwitter.image.src, "https://img.aiku.test/circle-black-x.png")
})

test("resetting an old beefree twitter icon uses our own x icon", () => {
	setSocialIconSources({ "circle-black/x": "https://img.aiku.test/circle-black-x.png" })

	assert.equal(socialIconSrc("twitter", "circle-black"), "https://img.aiku.test/circle-black-x.png")
})
