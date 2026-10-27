export interface ImageUploadRecommendation {
	width: number
	focus?: string
	note?: string
}

export const generalImageUploadRecommendation: ImageUploadRecommendation = {
	width: 1600,
	focus: "Keep the main subject in the centre so it survives cropping on smaller screens.",
	note: "Use JPG for photos and PNG for logos or graphics with transparency.",
}

export const imageUploadRecommendations = {
	background: {
		width: 2400,
		focus: "Keep the subject in the centre and avoid text inside the image.",
		note: "Backgrounds are cut to fill the area, so the edges change with the screen size.",
	},
	"cta-image-background": {
		width: 2400,
		focus: "Keep the subject in the centre-right: the text covers the left side.",
		note: "The image fills the whole banner, so the top and bottom are cut on wide screens and the sides on mobile.",
	},
	cta3: {
		width: 2400,
		focus: "Keep the subject in the centre-right: the text box covers the left side.",
		note: "The image fills the whole block, so its edges are cut to fit the screen.",
	},
	cta1: {
		width: 1600,
		focus: "Keep the subject in the centre.",
		note: "The image is stretched to fill half of the block, so a 16:9 image keeps its proportions best.",
	},
	cta4: {
		width: 1600,
		focus: "Keep the subject in the centre.",
		note: "The image fills half of the block, so its edges are cut to fit.",
	},
	overview_2: {
		width: 1600,
		focus: "Keep the subject in the centre.",
	},
	"carousel-cta": {
		width: 1600,
		focus: "Keep the subject in the centre.",
		note: "The image fills half of the slide, so its edges are cut to fit.",
	},
	"carousel-1": {
		width: 1200,
		focus: "Keep the product in the centre with some space around it.",
		note: "Use the same ratio for every card so the carousel lines up.",
	},
	"carousel-image-background": {
		width: 1200,
		focus: "Keep the subject in the centre: the card text sits over the image.",
		note: "Use the same ratio for every card so the carousel lines up.",
	},
	"slider-1": {
		width: 1920,
		focus: "Keep the subject in the centre.",
		note: "Use the same ratio for every slide so the slider does not jump in height.",
	},
} satisfies Record<string, ImageUploadRecommendation>

export const recommendedImageSize = (
	recommendation: ImageUploadRecommendation,
	aspectRatio?: number | null
): string => {
	if (!aspectRatio) {
		return `${recommendation.width} px wide`
	}

	return `${recommendation.width} × ${Math.round(recommendation.width / aspectRatio)} px`
}
