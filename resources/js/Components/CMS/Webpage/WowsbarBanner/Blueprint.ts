export default {
    blueprint : [
        {
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
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
    ]
}