export default {
	blueprint: [
		{
			name: "Layout",
			key: ["value", "layout_type"],
			type: "layout_type",
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
					key: ["gap"],
					label: "Gap",
					useIn : ["desktop", "tablet", "mobile"],
					type: "numberCss",
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
					useIn : ["desktop", "tablet", "mobile"],
					type: "shadow",
				},
				{
					key: ["shadowColor"],
					label: "Shadow Color",
					useIn : ["desktop", "tablet", "mobile"],
					type: "color",
				},
			],
		},
	],
}
