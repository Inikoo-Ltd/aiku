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
