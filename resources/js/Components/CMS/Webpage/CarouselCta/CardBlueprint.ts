export default {
	blueprint: [
		{
			name: "image",
			label: "Image",
			key: ["image"],
			replaceForm: [
				{
					key: ["source"],
					label: "Image",
					type: "image-cropped",
					props_data: {
						stencilProps: {
							aspectRatio: [16 / 9, null],
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
				{
					key: ['container',"properties",'dimension'],
					label: "Dimension",
					type: "dimension",
					useIn: ["desktop", "tablet", "mobile"],
				}	
			],
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
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["padding"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Padding",
					type: "padding",
				},
				{
					key: ["margin"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Margin",
					type: "margin",
				},
				{
					key: ["border"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Border",
					type: "border",
				},
				{
					key: ["shadow"],
					label: "Shadow",
					type: "shadow",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadowColor"],
					label: "Shadow Color",
					type: "color",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
