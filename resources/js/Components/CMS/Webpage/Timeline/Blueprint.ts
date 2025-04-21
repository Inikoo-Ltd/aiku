export default {
	blueprint: [
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
					key: ["dimension"],
					label : "Dimension", 
					type: "dimension",
					props_data: {},
				},
				{
					key: ["padding"],
					label : "Padding", 
					type: "padding",
					props_data: {},
				},
				{
					key: ["margin"],
					label : "Margin", 
					type: "margin",
					props_data: {},
				},
				{
					key: ["border"],
					label : "Border", 
					type: "border",
					props_data: {},
				},
			],
		},
       /*  {
			name: "Timeline Property",
			key: ["timeline"],
			type: "timeline"
		}, */
	],
}
