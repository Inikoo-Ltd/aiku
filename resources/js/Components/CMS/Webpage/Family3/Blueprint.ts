export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information : 'id selector is used to select one unique element!'
		},
		{
			label: "Responsive Visibility",
			key: ["container", "properties", "visibility"],
			type: "visibility",
			useIn: ["desktop", "tablet", "mobile"],
		},
		{
			label: "Column position",
			key: ["column_position"],
			type: "select-button",
			useIn: ["desktop", "tablet", "mobile"],
			defaultValue : 'Image-left',
			props_data:{
				options : [ "Image-left", "Image-right" ],
			}
		},
		{
			name: "Button",
			key: ["button"],
			editGlobalStyle: "button",
			replaceForm: [
			/* 	{
					key: ["link"],
					label: "Link",
					type: "link",
				}, */
				{
					key: ["text"],
					label: "Text",
					type: "text",
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
}

