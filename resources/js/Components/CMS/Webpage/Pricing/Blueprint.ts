export default {
	blueprint: [
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn : ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["dimension"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Dimension", 
					type: "dimension",
					props_data: {},
				},
				{
					key: ["padding"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Padding", 
					type: "padding",
					props_data: {},
				},
				{
					key: ["margin"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Margin", 
					type: "margin",
					props_data: {},
				},
				{
					key: ["border"],
					useIn : ["desktop", "tablet", "mobile"],
					label : "Border", 
					type: "border",
					props_data: {},
				},
			],
		},
		{
			name: "Pricing Property",
			key: ["tiers"],
			type: "cards-property"
		},
	],
}
