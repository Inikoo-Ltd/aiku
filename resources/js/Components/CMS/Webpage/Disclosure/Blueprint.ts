export default {
	blueprint: [
		{
			name: "Settings",
			key: ["value"],
			type: "disclosure",
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label :"Background",
					type: "background",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label : "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
					key: ["margin"],
					label : "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
					key: ["border"],
					label : "Border",
					type: "border",
					useIn : ["desktop", "tablet", "mobile"],
					
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
                    type: "shadow",
					useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
                    type: "color",
					useIn : ["desktop", "tablet", "mobile"],
                },
			],
		},
	],
}
