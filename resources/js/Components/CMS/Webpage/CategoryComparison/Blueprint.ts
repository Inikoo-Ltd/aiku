export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			label: "Title",
			key: ["title"],
			type: "text",
			information: "Custom heading text. Leave empty to use the default 'Range Comparison'",
		},
		{
			name: "Colors",
			key: ["settings"],
			replaceForm: [
				{
					key: ["highlight_color"],
					label: "Highlighted column",
					type: "color",
				},
				{
					key: ["label_color"],
					label: "Row label",
					type: "color",
				},
				{
					key: ["link_color"],
					label: "Family link",
					type: "color",
				},
				{
					key: ["value_color"],
					label: "Value",
					type: "color",
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
				{
					key: ["border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
