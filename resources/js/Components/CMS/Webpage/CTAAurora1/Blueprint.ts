export const blueprint = [
	{
		name: "Properties",
		key: ["container", "properties"],
		replaceForm: [
			{
				key: ["background"],
				label: "Background",
				type: "background",
			},
			{
				key: ["padding"],
				label: "Padding",
				type: "padding",
			},
			{
				key: ["margin"],
				label: "Margin",
				type: "margin",
			},
			{
				key: ["border"],
				label: "Border",
				type: "border",
			},
			{
				key: ["shadow"],
				label : "Shadow",
				type: "shadow",
			},
			{
				key: ["shadowColor"],
				label : "Shadow Color",
				type: "color",
			},
		],
	},
	{
		name: "Button",
		key: ["button"],
		editGlobalStyle : "button",
		replaceForm: [
			{
				key: ["link"],
				label: "Link",
				type: "link",
			},
			{
				key: ["text"],
				label: "Text",
				type: "text",
			},
			{
				key: ["container", "properties", "text"],
				type: "textProperty",
			},
			{
				key: ["container", "properties", "background"],
				label: "Background",
				type: "background",
			},
			{
				key: ["container", "properties", "margin"],
				label: "Margin",
				type: "margin",
			},
			{
				key: ["container", "properties", "padding"],
				label: "Padding",
				type: "padding",
			},
			{
				key: ["container", "properties", "border"],
				label: "Border",
				type: "border",
			},
		],
	},
]

export default blueprint
