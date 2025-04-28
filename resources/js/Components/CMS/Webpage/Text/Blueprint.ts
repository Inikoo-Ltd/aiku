export default {
	blueprint: [
		{
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label : "Background",
					type: "background",
				},
				{
					key: ["padding"],
					label : "Padding",
					type: "padding",
				},
				{
					key: ["margin"],
					label : "Margin",
					type: "margin",
				},
				{
					key: ["border"],
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