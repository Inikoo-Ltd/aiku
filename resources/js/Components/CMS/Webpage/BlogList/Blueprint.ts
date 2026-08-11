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

const sourceField = {
	label: "Source",
	key: ["source"],
	type: "select",
	props_data: {
		mode: "single",
		options: [
			{ label: "Latest posts", value: "latest" },
			{ label: "Hand-picked posts", value: "manual" },
		],
	},
	information: "Latest pulls posts automatically, hand-picked lets you choose them yourself.",
}

const categoriesField = {
	label: "Categories",
	key: ["categories"],
	type: "select",
	props_data: {
		mode: "multiple",
		placeholder: "All blog categories",
		options: [
			{ label: "David's Travel Blog", value: "david_aw_news" },
			{ label: "Tips", value: "tips" },
			{ label: "Blog", value: "blog" },
		],
	},
	information: "Only posts in the selected categories are listed. Leave empty to show them all.",
}

const numberOfPostsField = {
	label: "Number of posts",
	key: ["number_of_posts"],
	type: "number",
	props_data: {},
	information: "How many posts the block shows, between 1 and 12.",
}

const pickedPostsField = {
	label: "Posts",
	key: ["picked_posts"],
	type: "selectquery",
	props_data: {
		mode: "multiple",
		valueProp: "id",
		labelProp: "title",
		placeholder: "Search a blog post",
		fetchRoute: {
			name: "grp.json.shop.website.blog_webpages",
			parameters: {
				shop: typeof route === "function" ? route().params["shop"] : null,
				website: typeof route === "function" ? route().params["website"] : null,
			},
		},
	},
	information: "Listed in the order you pick them, capped by the number of posts.",
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

export const blueprint = (data?: any, id?: number) => {
	const block = data?.layout?.web_blocks?.find((item: any) => item.id == id) ?? null
	const fieldValue = block?.web_block?.layout?.data?.fieldValue ?? null
	const isManual = fieldValue?.source === "manual"

	return {
		blueprint: [
			idField,
			titleField,
			sourceField,
			...(isManual ? [pickedPostsField] : [categoriesField]),
			numberOfPostsField,
			...presentationFields,
			cardGroup,
			layoutGroup,
		],
	}
}
