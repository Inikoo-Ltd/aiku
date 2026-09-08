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
	type: "editorhtml",
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

const blogListFields = [
	{
		label: "Show blog list",
		key: ["show_list"],
		type: "switch",
		props_data: {},
		information: "Lists the latest blogs of every category under the cards.",
	},
	{
		label: "Blog list title",
		key: ["list_title"],
		type: "text",
	},
	{
		label: "Number of posts",
		key: ["number_of_posts"],
		type: "number",
		props_data: {
			minValue: 1,
			maxValue: 12,
		},
	},
	{
		label: "Show publication date",
		key: ["list_show_published_date"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Show post call to action",
		key: ["list_show_cta"],
		type: "switch",
		props_data: {},
	},
	{
		label: "Post call to action label",
		key: ["list_cta_label"],
		type: "text",
	},
	{
		label: "Load more label",
		key: ["list_load_more_label"],
		type: "text",
	},
]

const exploreGroup = {
	name: "Explore panel",
	key: ["explore"],
	replaceForm: [
		{
			key: ["show"],
			label: "Show explore panel",
			type: "switch",
			props_data: {},
		},
		{
			key: ["eyebrow"],
			label: "Eyebrow",
			type: "text",
		},
		{
			key: ["title"],
			label: "Title",
			type: "text",
		},
		{
			key: ["description"],
			label: "Description",
			type: "editorhtml",
		},
		{
			key: ["label"],
			label: "Button label",
			type: "text",
		},
	],
}

const newsletterGroup = {
	name: "Newsletter",
	key: ["newsletter"],
	information: "Subscribe form, the email is sent to the newsletter webhook of the website.",
	replaceForm: [
		{
			key: ["show"],
			label: "Show newsletter",
			type: "switch",
			props_data: {},
		},
		{
			key: ["eyebrow"],
			label: "Eyebrow",
			type: "text",
		},
		{
			key: ["title"],
			label: "Title",
			type: "text",
		},
		{
			key: ["description"],
			label: "Description",
			type: "editorhtml",
		},
		{
			key: ["label"],
			label: "Button label",
			type: "text",
		},
	],
}

const blogSubTypes = [
	{ value: "newsletters", label: "Newsletters" },
	{ value: "product_guides", label: "Product Guides" },
	{ value: "business_tips", label: "Business Tips" },
]

const categoryContentGroup = {
	name: "Category content",
	key: ["category_content"],
	information: "Label, description and image of each blog category, saved with this page so every shop keeps its own. Leave a field empty to use the value taken from the blogs of that category.",
	replaceForm: blogSubTypes.map(subType => ({
		name: subType.label,
		key: [subType.value],
		replaceForm: [
			{
				key: ["label"],
				label: "Label",
				type: "text",
			},
			{
				key: ["description"],
				label: "Description",
				type: "editorhtml",
			},
			{
				key: ["image"],
				label: "Image",
				type: "image-cropped",
				props_data: {
					stencilProps: {
						aspectRatio: [16 / 9],
						movable: true,
						scalable: true,
						resizable: true,
					},
				},
			},
			{
				key: ["image_alt"],
				label: "Alternate Text",
				type: "text",
			},
		],
	})),
}

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
		categoryContentGroup,
		...blogListFields,
		exploreGroup,
		newsletterGroup,
		cardGroup,
		layoutGroup,
	],
}
