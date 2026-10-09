export default {
	blueprint: [
		{
			label: "# Id ",
			key: ["id"],
			type: "text",
			information: "id selector is used to select one unique element!",
		},
		{
			label: "Responsive Visibility",
			key: ["container", "properties", "visibility"],
			type: "visibility",
			useIn: ["desktop", "tablet", "mobile"],
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["background"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					label: "Text",
					type: "textProperty",
					useIn: ["desktop", "tablet", "mobile"],
				},
				{
					key: ["padding"],
					useIn: ["desktop", "tablet", "mobile"],
					label: "Padding",
					type: "padding",
					props_data: {},
				},
			],
		},
		{
			name: "Booking",
			key: ["settings"],
			replaceForm: [
				{
					key: ["show_marketing_opt_in"],
					label: "Ask to receive news and offers",
					information: "Shows a checkbox under the booking form. Visitors who tick it can receive marketing emails.",
					type: "switch",
				},
			],
		},
	],
}
