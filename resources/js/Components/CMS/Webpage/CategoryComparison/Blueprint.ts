const idField = {
	label: "# Id ",
	key: ["id"],
	type: "text",
	information: "id selector is used to select one unique element!",
}

const titleField = {
	label: "Title",
	key: ["title"],
	type: "text",
	information: "Custom heading text. Leave empty to use the default 'Range Comparison'",
}

const numberOfFamiliesField = {
	label: "Number of families",
	key: ["number_of_families"],
	type: "number",
	useIn: ["desktop", "tablet", "mobile"],
	props_data: {
		minValue: 2,
		maxValue: 8,
	},
	information:
		"How many columns the table shows on each screen, this family included. Defaults to 4 on desktop, 3 on tablet and 2 on mobile.",
}

const colorsField = {
	name: "Colors",
	key: ["settings"],
	replaceForm: [
		{
			key: ["highlight_color"],
			label: "Highlighted column",
			type: "color",
		},
		{
			key: ["label_color"],
			label: "Row label",
			type: "color",
		},
		{
			key: ["link_color"],
			label: "Family link",
			type: "color",
		},
		{
			key: ["value_color"],
			label: "Value",
			type: "color",
		},
	],
}

const layoutField = {
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
		{
			key: ["border"],
			label: "Border",
			type: "border",
			useIn: ["desktop", "tablet", "mobile"],
		},
	],
}

export default {
	blueprint: [idField, titleField, numberOfFamiliesField, colorsField, layoutField],
}
