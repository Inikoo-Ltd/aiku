import { trans } from "laravel-vue-i18n"
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
					label: "Autoplay",
					key: ["carousel_setting", "autoplay"],
					type: "switch",
				},
				{
					label: "Loop",
					key: ["carousel_setting", "loop"],
					type: "switch",
				},
				{
					label: "use button",
					key: ["carousel_setting", "button"],
					type: "switch",
				},
				{
					name: "Button next and previous",
					key: ["buttonStyle"],
					replaceForm: [
						{
							key: ["text"],
							label: "",
							type: "textProperty",
						},
					],
				},
			],
		},
		{
			name: "Cards",
			key: ["carousel_data"],
			information: trans("This settings will apply for all cards"),
			replaceForm: [
				{
					key: ["card_container", "properties", "background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["card_container", "properties", "dimension"],
					label: "Dimension",
					type: "dimension",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["card_container", "properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["card_container", "properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["card_container", "properties", "border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Button Default Style",
			key: ["button"],
			editGlobalStyle: "button",
			replaceForm: [
				{
					key: ["container", "properties", "text"],
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container_button", "properties", "justifyContent"],
					label: "Justify Content",
					type: "justify-content",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "margin"],
					label: "Margin",
					type: "margin",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "padding"],
					label: "Padding",
					type: "padding",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["container", "properties", "border"],
					label: "Border",
					type: "border",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Cards Data",
			key: ["carousel_data", "cards"],
			type: "array-data-drawer",
			props_data: {
				blueprint: CardBlueprint.blueprint,
				order_name: "card",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					text: "Lorem Ipsum",
					button: {
						text: "Explore more",
					},
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
