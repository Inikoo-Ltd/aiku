import { ctrans } from "@/Composables/useTrans"
import { imageUploadRecommendations } from "@/Composables/imageUploadRecommendations"

export default {
	blueprint: [
		{
			label: "Image",
			key: ["image"],
			replaceForm: [
				{
					key: ["source"],
					label: "Image",
					type: "image-cropped",
					props_data: {
						recommendation: imageUploadRecommendations["slider-1"],
						stencilProps: {
							aspectRatio: [1, 4 / 3, 16 / 9, null],
							movable: true,
							scalable: true,
							resizable: true,
						},
					},
				},
				{
					key: ["alt"],
					label: "Alternate Text",
					type: "text",
				},
			],
		},
		{
			key: ["link"],
			label: "Link",
			type: "link",
		},
		{
			name: "video",
			type: "slideVideo",
			label: ctrans("Video"),
			useIn: ["desktop", "tablet", "mobile"],
			value: ["video"],
		},

	],
}
