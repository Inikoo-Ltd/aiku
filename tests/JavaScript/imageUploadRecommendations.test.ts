import assert from "node:assert/strict"
import test from "node:test"
import {
	generalImageUploadRecommendation,
	imageUploadRecommendations,
	recommendedImageSize,
} from "../../resources/js/Composables/imageUploadRecommendations.ts"

test("recommends a wide 3:1 image for the CTA image background banner", () => {
	assert.equal(recommendedImageSize(imageUploadRecommendations["cta-image-background"], 3), "2400 × 800 px")
	assert.equal(recommendedImageSize(imageUploadRecommendations["cta-image-background"], 16 / 9), "2400 × 1350 px")
	assert.equal(recommendedImageSize(imageUploadRecommendations.cta3, 3), "2400 × 800 px")
})

test("recommends a full-width background in free crop and at 3:1", () => {
	assert.equal(recommendedImageSize(imageUploadRecommendations.background, null), "2400 px wide")
	assert.equal(recommendedImageSize(imageUploadRecommendations.background, 3), "2400 × 800 px")
})

test("gives only a width when the crop is free", () => {
	assert.equal(recommendedImageSize(imageUploadRecommendations["slider-1"], null), "1920 px wide")
	assert.equal(recommendedImageSize(generalImageUploadRecommendation), "1600 px wide")
})

test("never recommends the old 400 px fallback for any block", () => {
	for (const recommendation of Object.values(imageUploadRecommendations)) {
		assert.ok(recommendation.width >= 1200)
		assert.ok(recommendation.focus)
	}
})

test("covers every workshop block that opens the image upload dialog", () => {
	for (const blockCode of [
		"cta-image-background",
		"cta1",
		"cta3",
		"cta4",
		"overview_2",
		"carousel-cta",
		"carousel-1",
		"carousel-image-background",
		"slider-1",
	]) {
		assert.ok(blockCode in imageUploadRecommendations, blockCode)
	}
})
