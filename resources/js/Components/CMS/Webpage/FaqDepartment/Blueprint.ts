import { trans } from "laravel-vue-i18n"

export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},

		{
			name: "Layout",
			key: ["faq", "container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["padding"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Padding",
					type: "padding",
				},
				{
					key: ["margin"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Margin",
					type: "margin",
				},
				{
					key: ["border"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Border",
					type: "border",
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
