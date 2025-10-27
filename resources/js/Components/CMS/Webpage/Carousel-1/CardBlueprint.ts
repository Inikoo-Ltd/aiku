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
