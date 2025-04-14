export default {
	blueprint: [
		{
			label: "Link",
			key: ["link"],
			type: "text",
			props_data: {
				placeholder: "https://",
			},
		},
		{
			name: "Properties",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["dimension"],
					label:"Dimension",
					type: "dimension",
				},
				{
					key: ["padding"],
					label:"Padding",
					type: "padding",
				},
				{
					key: [ "margin"],
					label:"Margin",
					type: "margin",
				},
				{
					key: ["border"],
					label:"Border",
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
