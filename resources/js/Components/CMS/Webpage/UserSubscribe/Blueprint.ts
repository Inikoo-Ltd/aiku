export default {
	blueprint: [
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				// {
				// 	key: ["dimension"],
				// 	useIn : ["desktop", "tablet", "mobile"],
				// 	label : "Dimension", 
				// 	type: "dimension",
				// 	props_data: {},
				// },
				// {
				// 	key: ["padding"],
				// 	useIn : ["desktop", "tablet", "mobile"],
				// 	label : "Padding", 
				// 	type: "padding",
				// 	props_data: {},
				// },
				// {
				// 	key: ["margin"],
				// 	useIn : ["desktop", "tablet", "mobile"],
				// 	label : "Margin", 
				// 	type: "margin",
				// 	props_data: {},
				// },
				// {
				// 	key: ["border"],
				// 	useIn : ["desktop", "tablet", "mobile"],
				// 	label : "Border", 
				// 	type: "border",
				// 	props_data: {},
				// },
			],
		},
		{
			name: "Input Box",
			key: ["value", "input"],
			// editGlobalStyle: "button",
			replaceForm: [
				{
					key: ["placeholder"],
					label: "Placeholder",
					props_data: {
						placeholder: "Enter your email",
					},
					information: "The text that will be displayed when the input box empty.",
					type: "text",
				},
			],
		},
		{
			name: "Button",
			key: ["button"],
			// editGlobalStyle: "button",
			replaceForm: [
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
				// {
				// 	key: ["container", "properties", "margin"],
				// 	label: "Margin",
				// 	type: "margin",
				// 	useIn: ["desktop", "tablet", "mobile"],
				// },
				{
					key: ["container", "properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				// {
				// 	key: ["container", "properties", "border"],
				// 	label: "Border",
				// 	type: "border",
				// 	useIn: ["desktop", "tablet", "mobile"],
				// },
			],
		},
	],
}
