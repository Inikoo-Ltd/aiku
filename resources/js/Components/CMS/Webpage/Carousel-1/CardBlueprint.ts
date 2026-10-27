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
						recommendation: imageUploadRecommendations["carousel-1"],
						stencilProps: {
							aspectRatio: [1, 4/3, 16/9 ],
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
		/* {
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		}, */
		{
			key: ["link"],
			label: "Link",
			type: "link",
		},
	],
}
