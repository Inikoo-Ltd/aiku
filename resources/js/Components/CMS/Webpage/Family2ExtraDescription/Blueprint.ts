const TextItemBlueprint = [
	{
		key: ["text"],
		label: "Text",
		type: "text",
	},
]

const HighlightBlueprint = [
	{
		key: ["icon"],
		label: "Icon",
		type: "icon-picker",
	},
	{
		key: ["label"],
		label: "Label",
		type: "text",
	},
]

const CustomisationOptionBlueprint = [
	{
		key: ["option"],
		label: "Option",
		type: "text",
	},
	{
		key: ["available"],
		label: "Available",
		type: "switch",
	},
	{
		key: ["moq"],
		label: "MOQ",
		type: "text",
	},
	{
		key: ["notes"],
		label: "Notes",
		type: "editorhtml",
	},
]

export default {
	blueprint: [
		/* {
			label: "Responsive Visibility",
			key: ["container", "properties", "visibility"],
			type: "visibility",
			useIn: ["desktop", "tablet", "mobile"],
		}, */
		{
			name: "About",
			key: ["about"],
			replaceForm: [
				{
					name: "Button",
					key: ["button"],
					editGlobalStyle: "button",
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
					],
				},
			],
		},
		{
			name: "Marketing & Materials",
			key: ["marketing"],
			replaceForm: [
				{
					name: "Button",
					key: ["button"],
					editGlobalStyle: "button",
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
					],
				},
			],
		},
		{
			name: "Faq",
			key: ["faq"],
			replaceForm: [
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
					],
				},
			],
		},
		{
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
					key: ["image"],
					label: "Image",
					type: "upload_image",
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
							type: "text",
						},
					],
				},
				{
					key: ["highlights"],
					name: "Highlights",
					type: "array-data",
					props_data: {
						blueprint: HighlightBlueprint,
						order_name: "highlight",
						can_drag: true,
						can_delete: true,
						can_add: true,
						new_value_data: {
							icon: ["fal", "box"],
							label: "New Highlight",
						},
					},
				},
				{
					name: "Highlight Style",
					key: ["highlight", "container", "properties"],
					replaceForm: [
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
					key: ["options"],
					name: "Options",
					type: "array-data",
					props_data: {
						blueprint: CustomisationOptionBlueprint,
						order_name: "option",
						can_drag: true,
						can_delete: true,
						can_add: true,
						new_value_data: {
							option: "New Option",
							available: true,
							moq: "",
							notes: "",
						},
					},
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
				{
					name: "Contact Button",
					key: ["contact", "button"],
					editGlobalStyle: "button",
					replaceForm: [
					{
						key: ["text"],
						label: "Text",
						type: "text",
					},
					{
						key: ["url"],
						label: "Url",
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
				],
			},
			],
		},
		{
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
				{
					name: "Download Button",
					key: ["card", "button"],
					editGlobalStyle: "button",
					replaceForm: [
					{
						key: ["text"],
						label: "Text",
						type: "text",
					},
					{
						key: ["url"],
						label: "Url",
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
				},
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
				],
			},
			],
		},
		{
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
					name: "Table Headers",
					key: ["table"],
					replaceForm: [
						{
							key: ["storage"],
							label: "Storage",
							type: "text",
						},
						{
							key: ["shelf_life"],
							label: "Shelf Life",
							type: "text",
						},
						{
							key: ["after_opening"],
							label: "After Opening",
							type: "text",
						},
					],
				},
				{
					name: "Conditions",
					key: ["conditions"],
					replaceForm: [
						{
							key: ["storage"],
							label: "Storage",
							type: "text",
						},
						{
							key: ["shelf_life"],
							label: "Shelf Life",
							type: "text",
						},
						{
							key: ["after_opening"],
							label: "After Opening",
							type: "text",
						},
					],
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
						{
							key: ["value"],
							label: "Value",
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
						{
							key: ["items"],
							name: "Items",
							type: "array-data",
							props_data: {
								blueprint: TextItemBlueprint,
								order_name: "guideline",
								can_drag: true,
								can_delete: true,
								can_add: true,
								new_value_data: {
									text: "New Guideline",
								},
							},
						},
					],
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
				],
			},
			],
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
	],
}
