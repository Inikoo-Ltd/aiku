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
				/* {
					label: "Space Between",
					key: ["carousel_setting", "spaceBetween"],
					type: "number",
				}, */
				{
					label: "Use Text",
					key: ["carousel_setting", "use_text"],
					type: "switch",
				},
				{
					label: "Autoplay",
					key: ["carousel_setting", "autoplay"],
					type: "switch",
				},
				{
					label: "Loop",
					key: ["carousel_setting", "loop"],
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
					key: ["card_container"],
					replaceForm: [
						{
							key: [ "image_properties","dimension"],
							label: "Dimension",
							type: "dimension",
							useIn: ["desktop", "tablet", "mobile"],
						},
						{
							key: ["container_image", "justifyContent"],
							label: "Justify Content",
							useIn: ["desktop", "tablet", "mobile"],
							type: "select",
							props_data: {
								placeholder: "Object",
								options: [
									{
										label: "Center",
										value: "center",
									},
									{
										label: "End",
										value: "end",
									},
									{
										label: "Start",
										value: "start",
									},
									{
										label: "none",
										value: null,
									},
								],
							},
						},
					],
				},
				{
					key: ["cards"],
					name: "Cards  Data",
					type: "array-data-drawer",
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
