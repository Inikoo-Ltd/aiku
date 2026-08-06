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
	information: "Heading shown above the list. Leave empty to hide it.",
}

const categoriesField = {
	label: "Categories",
	key: ["categories"],
	type: "select",
	props_data: {
		mode: "multiple",
		placeholder: "All blog categories",
		options: [
			{ label: "David's Travel Blog", value: "davids_travel_blog" },
			{ label: "Tips", value: "tips" },
			{ label: "Blog", value: "blog" },
		],
	},
	information: "Only posts in the selected categories are listed. Leave empty to show them all.",
}

const presentationFields = [
	{
		label: "Show publication date",
		key: ["show_published_date"],
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
		categoriesField,
		{
			label: "Number of posts",
			key: ["number_of_posts"],
			type: "number",
			props_data: {},
			information: "Between 1 and 12, the newest posts are shown first.",
		},
		...presentationFields,
		{
			label: "Show view all button",
			key: ["show_view_all"],
			type: "switch",
			props_data: {},
		},
		{
			label: "View all label",
			key: ["view_all_label"],
			type: "text",
		},
		cardGroup,
		layoutGroup,
	],
}
