const TextItemBlueprint = [
	{
		key: ["text"],
		label: "Text",
		type: "text",
	},
]

const layoutProperties = () => [
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
]

const layoutSection = () => ({
	name: "Layout",
	key: ["container", "properties"],
	replaceForm: layoutProperties(),
})

const buttonSection = (name: string, key: string[], withUrl = false) => ({
	name,
	key,
	editGlobalStyle: "button",
	replaceForm: [
		{
			key: ["text"],
			label: "Text",
			type: "text",
		},
		...(withUrl
			? [
					{
						key: ["url"],
						label: "Url",
						type: "link",
					},
				]
			: []),
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
})

export const aboutSection = () => ({
	name: "About",
	key: ["about"],
	replaceForm: [buttonSection("Button", ["button"]), layoutSection()],
})

export const aboutSectionWithoutButton = () => ({
	name: "About",
	key: ["about"],
	replaceForm: [layoutSection()],
})

export const marketingSection = () => ({
	name: "Marketing & Materials",
	key: ["marketing"],
	replaceForm: [buttonSection("Button", ["button"]), layoutSection()],
})

export const faqSection = () => ({
	name: "Faq",
	key: ["faq"],
	replaceForm: [layoutSection()],
})

export const customisationSection = () => ({
	name: "Customisation",
	key: ["customisation"],
	replaceForm: [
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
			name: "Link",
			key: ["link"],
			replaceForm: [
				{
					key: ["text"],
					label: "Text",
					type: "text",
				},
				{
					key: ["url"],
					label: "Url",
					type: "link",
				},
			],
		},
		{
			name: "Table Headers",
			key: ["table"],
			replaceForm: [
				{
					key: ["option"],
					label: "Option",
					type: "text",
				},
				{
					key: ["available"],
					label: "Available",
					type: "text",
				},
				{
					key: ["moq"],
					label: "MOQ",
					type: "text",
				},
				{
					key: ["notes"],
					label: "Notes",
					type: "text",
				},
			],
		},
		{
			name: "Contact",
			key: ["contact"],
			replaceForm: [
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
			],
		},
		buttonSection("Contact Button", ["contact", "button"], true),
		layoutSection(),
	],
})

export const labelingGuideSection = () => ({
	name: "Labeling Guide",
	key: ["labeling_guide"],
	replaceForm: [
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
			name: "Card",
			key: ["card"],
			replaceForm: [
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
			],
		},
		buttonSection("Download Button", ["card", "button"], true),
		{
			name: "Includes",
			key: ["includes"],
			replaceForm: [
				{
					key: ["title"],
					label: "Title",
					type: "text",
				},
				{
					key: ["items"],
					name: "Items",
					type: "array-data",
					props_data: {
						blueprint: TextItemBlueprint,
						order_name: "item",
						can_drag: true,
						can_delete: true,
						can_add: true,
						new_value_data: {
							text: "New Item",
						},
					},
				},
			],
		},
		{
			key: ["note"],
			label: "Note",
			type: "editorhtml",
		},
		layoutSection(),
	],
})

export const storageSection = () => ({
	name: "Storage & Shelf Life",
	key: ["storage"],
	replaceForm: [
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
			name: "Temperature",
			key: ["temperature"],
			replaceForm: [
				{
					key: ["label"],
					label: "Label",
					type: "text",
				},
			],
		},
		{
			name: "Guidelines",
			key: ["guidelines"],
			replaceForm: [
				{
					key: ["title"],
					label: "Title",
					type: "text",
				},
			],
		},
		layoutSection(),
	],
})

export const AROMA_ONLY_SECTIONS = ["customisation", "labeling_guide", "storage"]

const AROMA_ORGANISATION_SLUG = "aroma"

export const showsAromaSections = (data?: any) => {
	const slug = data?.organisation?.slug

	return slug === undefined || slug === AROMA_ORGANISATION_SLUG
}
