export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
        {
			name: "Side Menu",
			key: ["sidebar","properties"],
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
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
        {
			name: "Description",
			key: ["description","properties"],
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
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
        {
			name: "CTA",
			key: ["cta","properties"],
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
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
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
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
