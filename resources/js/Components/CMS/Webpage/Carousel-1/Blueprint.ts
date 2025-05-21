import CardBlueprint from "./CardBlueprint"
export default {
	blueprint: [
		{
			name: "Carousel Settings",
			key: ["carousel_data"],
			replaceForm: [
				{
					label: "Slide per View",
					key: ["carousel_setting", "slidesPerView"],
					useIn: ["desktop", "tablet", "mobile"],
					type: "number",
				},
				{
					label: "Space Between",
					key: ["carousel_setting", "spaceBetween"],
					type: "number",
				},
				{
					label: "Use Text",
					key: ["carousel_setting", "use_text"],
					type: "switch",
				},
			],
		},
		{
			name: "Cards",
			key: ["carousel_data"],
			replaceForm: [
				{
					name: "Cards Layout",
					key: ["card_container", "properties"],
					replaceForm: [
						{
							key: ["background"],
							label: "Background",
							type: "background",
						},
						{
							key: ["dimension"],
							label: "Dimension",
							type: "dimension",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["padding"],
							label: "Padding",
							type: "padding",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["margin"],
							label: "Margin",
							type: "margin",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["border"],
							label: "Border",
							type: "border",
							useIn: ["desktop", "tablet", "mobile"],
						},
					],
				},
				{
					name: "Image layout",
					key: ["card_container", "image_properties"],
					replaceForm: [
						{
							key: ["dimension"],
							label: "Dimension",
							type: "dimension",
							useIn: ["desktop", "tablet", "mobile"],
						},
					],
				},
				{
					key: ["cards"],
					name: "Cards  Data",
					type: "array-data",
					props_data: {
						blueprint: CardBlueprint.blueprint,
						order_name: "card",
						can_drag: true,
						can_delete: true,
						can_add: true,
						new_value_data: {
							text: "Lorem Ipsum",
							image: {
								source: null,
								alt: "Image",
							},
						},
					},
				},
			],
		},
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
					key: ["padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadow"],
					label: "Shadow",
					type: "shadow",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadowColor"],
					label: "Shadow Color",
					type: "color",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
