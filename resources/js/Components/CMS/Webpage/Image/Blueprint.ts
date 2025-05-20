import { trans } from "laravel-vue-i18n"
export default {
	blueprint: [
		{
			name: "Images",
			key: ["value"],
			replaceForm: [
				{
					key: ["layout_type"],
					label: "Layout",
					type: "layout_type",
				},
				{
					key: ["layout", "properties", "dimension"],
					label: "Dimension",
					type: "dimension",
					information: trans("Setup all dimension in all image"),
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					label: "Images",
					key: ["images"],
					type: "images-property",
				},
			],
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadow"],
					label: "Shadow",
					type: "shadow",
					useIn : ["desktop", "tablet", "mobile"],
				},
				{
					key: ["shadowColor"],
					label: "Shadow Color",
					type: "color",
					useIn : ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Mobile",
			key: ["mobile"],
			replaceForm: [
				{
					key: ["type"],
					label: "Type",
					type: "select",
					props_data: {
						placeholder: "Select type",
						by: "value",
						required: true,
						options: [
							{
								label: "Default",
								value: "default",
							},
							{
								label: "Carousel",
								value: "carousel",
							},
						],
					},
				},
			],
		},
	],
}
