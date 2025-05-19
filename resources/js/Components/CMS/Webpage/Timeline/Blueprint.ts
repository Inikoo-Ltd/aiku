export default {
	blueprint: [
		{
			name: "Layout",
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
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["padding"],
					label : "Padding", 
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["margin"],
					label : "Margin", 
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["border"],
					label : "Border", 
					useIn : ["desktop", "tablet", "mobile"],
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
