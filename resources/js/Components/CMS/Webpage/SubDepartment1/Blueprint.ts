import { trans } from "laravel-vue-i18n";

export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			name: "Settings",
			key: ["settings"],
			replaceForm: [
				{
					key: ["per_row"],
					label: "Show Each Row",
					type: "number",
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
		{
			name: "Title",
			key: ["text"],
			replaceForm: [
                {
					label: "Show Text",
					key: ["visible"],
					type: "switch",
				},
				{
					label: "Responsive Text",
					key: ["value"],
					type: "text-responsive",
					defaultValue : `<h2 class="text-2xl font-bold mb-6" aria-label="Browse Sub-departments Section">${ trans("Browse By Sub-department") }</h2>`
				},
			],
		},
		{
			name: "Card",
			key: ["card", "container", "properties"],
			replaceForm: [
				{
					key: ["text"],
					label: "text",
					type: "textProperty",
				},
				{
					key: ["background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
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
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					label: "Padding",
					type: "padding",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["margin"],
					label: "Margin",
					type: "margin",
					props_data: {},
					useIn: ["desktop", "tablet", "mobile"],
				},
			],
		},
	],
}
