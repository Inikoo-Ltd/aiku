export default {
    blueprint : [
        {
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
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
    ]
}