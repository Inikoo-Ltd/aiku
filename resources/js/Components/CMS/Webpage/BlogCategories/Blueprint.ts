const idField = {
	label: "# Id ",
	key: ["id"],
	type: "text",
	information: "id selector is used to select one unique element!",
}

const titleField = {
	label: "Title",
	key: ["title"],
	type: "text",
	information: "Heading shown above the cards. Leave empty to hide it.",
}

const subtitleField = {
	label: "Subtitle",
	key: ["subtitle"],
	type: "text",
	information: "Short line under the heading. Leave empty to hide it.",
}

const columnsField = {
	label: "Columns",
	key: ["columns"],
	type: "number",
	useIn: ["desktop", "tablet", "mobile"],
	props_data: {
		minValue: 1,
		maxValue: 6,
	},
	information: "How many cards sit side by side on each screen. Defaults to 3 on desktop, 2 on tablet and 1 on mobile.",
}

const presentationFields = [
	{
		label: "Show icon",
		key: ["show_icon"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Show category position",
		key: ["show_position"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Show description",
		key: ["show_description"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Show call to action",
		key: ["show_cta"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Call to action label",
		key: ["cta_label"],
		type: "text",
	},
	{
		label: "Show url badge",
		key: ["show_url"],
		type: "switch",
		props_data: {},
	},
]

const cardGroup = {
	name: "Card",
	key: ["card", "container", "properties"],
	replaceForm: [
		{
			key: ["text"],
			label: "Text",
			type: "textProperty",
		},
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
			key: ["border"],
			label: "Border",
			type: "border",
			useIn: ["desktop", "tablet", "mobile"],
		},
		{
			key: ["shadow"],
			label: "Shadow",
			type: "shadow",
			useIn: ["desktop", "tablet", "mobile"],
		},
	],
}

const layoutGroup = {
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
}

export default {
	blueprint: [
		idField,
		titleField,
		subtitleField,
		columnsField,
		...presentationFields,
		cardGroup,
		layoutGroup,
	],
}
