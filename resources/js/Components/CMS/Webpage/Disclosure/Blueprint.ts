export default {
	blueprint: [
		{
			name: "Settings",
			key: ["value"],
			type: "disclosure",
		},
		{
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn : ["desktop", "tablet", "mobile"],
					label :"Background",
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
	],
}
