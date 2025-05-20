export default {
	blueprint: [
		{
			name: "Texts",
			key: ["texts","values"],
			type: "overview-property",
			props_data: {
				type : 'text'
			},
		},
		{
			name: "Images",
			key: ["images"],
			type: "overview-property",
			props_data: {
				type : 'image'
			},
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["dimension"],
					type: "dimension",
					label : "Dimension",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["padding"],
					type: "padding",
					label : "Padding",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["margin"],
					type: "margin",
					label : "Margin",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["border"],
					type: "border",
					label : "Border",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
                    key: ["shadow"],
                    label : "Shadow",
					useIn : ["desktop", "tablet", "mobile"],
                    type: "shadow",
                },
                {
                    key: ["shadowColor"],
                    label : "Shadow Color",
					useIn : ["desktop", "tablet", "mobile"],
                    type: "color",
                },
			],
		},
		/* {
			name: "Setting",
			key: ["texts"],
			replaceForm: [
				{
					key: ["texts"],
					type: "overview_form",
					name: "Text",
					props_data: {
						type: "text",
					},
				},
				{
					key: ["images"],
					type: "overview_form",
					name: "Images",
					props_data: {
						type: "images",
					},
				},
			],
		}, */
	],
}
