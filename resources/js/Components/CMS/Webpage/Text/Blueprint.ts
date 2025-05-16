export default {
	blueprint: [
		{
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Background",
					type: "background",
				},
				{
					key: ["padding"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Padding",
					type: "padding",
				},
				{
					key: ["margin"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Margin",
					type: "margin",
				},
				{
					key: ["border"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Border",
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
		/* {
			label : 'text',
			key: ["text"],
			type: "link",
		}, */
		/* {
			name: "test2",
			key: ["container", "test2"],
			replaceForm: [
				{
					key: ["test3"],
					replaceForm: [
						{
							name : 'test2',
							key: ["margin"],
							type: "padding",
						},
					]
				},
			],
		}, */
	],
}