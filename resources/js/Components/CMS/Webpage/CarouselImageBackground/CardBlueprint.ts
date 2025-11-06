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
			props_data: {
				class : "bg-gray-300 rounded-lg"
			},
		},
		{
			name : 'Link',
			key: ["link"],
			label: "Link",
			type: "link",
		},
		{
			name: "Button",
			key: ["button"],
			editGlobalStyle: "button",
			replaceForm: [
				{
					key: ["link"],
					label: "Link",
					type: "link",
				},
				{
					key: ["text"],
					label: "Text",
					type: "text",
				},
				{
					key: ["container", "properties", "text"],
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
