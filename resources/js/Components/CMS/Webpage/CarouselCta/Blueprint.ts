import CardBlueprint from "./CardBlueprint"
export default {
	blueprint: [
		{
			name: "Carousel Settings",
			key: ["carousel_data"],
			replaceForm: [
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
			key: ['carousel_data',"cards"],
			name: "Cards  Data",
			type: "array-data",
			props_data: {
				blueprint: CardBlueprint.blueprint,
				order_name: "card",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					button: {
						link: {
							type: "internal",
							href: "/",
							id: null,
							workshop_route: "",
						},
						text: "Explore Here",
						container: {
							properties: null,
						},
					},
					text: "<h3>Lorem Ipsum</h3><p>description from the product</p>",
					image: {
						alt: "Image 1",
						source: null,
					},
				},
			},
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
