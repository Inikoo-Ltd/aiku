export default {
	blueprint: [
		{
			label: "Image",
			key: ["image"],
			replaceForm: [
				{
					key: ["source"],
					label: "Image",
					type: "upload_image",
				},
				{
					key: ["alt"],
					label: "Alternate Text",
					type: "text",
				},	
			],
		},
		{
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		},
		{
			key: ["link"],
			label: "Link",
			type: "link",
		},
	],
}
