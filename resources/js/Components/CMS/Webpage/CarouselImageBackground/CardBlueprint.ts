export default {
	blueprint: [
		{
			name:"Image",
			label: "Image",
			key: ["image"],
			replaceForm: [
				{
					key: ["source"],
					label: "Image",
					type: "image-cropped",
					props_data: {
						stencilProps: {
							aspectRatio: 1 / 1,
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
			name : 'Text',
			key: ["text"],
			label: "Text",
			type: "editorhtml",
		},
		{
			name : 'Link',
			key: ["link"],
			label: "Link",
			type: "link",
		},
	],
}
