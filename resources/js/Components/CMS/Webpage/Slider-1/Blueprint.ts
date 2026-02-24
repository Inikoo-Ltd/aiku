import SliderBlueprint from "./SliderBlueprint"
export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			name: "slider Settings",
			key: ["slider_data"],
			replaceForm: [
				{
					label: "Slide per View",
					key: ["slider_setting", "slidesPerView"],
					useIn: ["desktop", "tablet", "mobile"],
					type: "number",
				},
				{
					label: "Space Between",
					key: ["slider_setting", "spaceBetween"],
					type: "number",
				},
			],
		},
		{
			name: "Images",
			key: ["image",'properties'],
			replaceForm: [
				{
					label: "Slide per View",
					key: ["dimension"],
					useIn: ["desktop", "tablet", "mobile"],
					type: "dimension",
				},
			],
		},

		{
			key: ["slider_data", "cards"],
			name: "Sliders  Data",
			type: "array-data-drawer",
			props_data: {
				blueprint: SliderBlueprint.blueprint,
				order_name: "slider",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					image: {
						source: null,
						alt: "Image",
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
