export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			label: "Responsive Visibility",
			key: ["container", "properties", "visibility"],
			type: "visibility",
			useIn: ["desktop", "tablet", "mobile"],
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
					key: ["text"],
					label: "Text",
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
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
			],
		},
		{
			name: "Form box",
			key: ["register", "card", "container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Terms and conditions",
			key: ["register", "terms"],
			replaceForm: [
				{
					key: ["text"],
					label: "Text",
					type: "text",
				},
				{
					key: ["url"],
					label: "Link",
					information: "The page the terms and conditions text links to.",
					type: "text",
				},
			],
		},
		{
			name: "Register button",
			key: ["register", "button"],
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
