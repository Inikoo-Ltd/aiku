export default {
	blueprint: [
		{
			name: "Settings",
			key: ["setting"],
			replaceForm: [
				{
					key: ["faqs"],
					type: "switch",
					label: "Show Faqs",
					props_data: {},
				},
				{
					key: ["product_specs"],
					type: "switch",
					label: "Show Product Specs",
					props_data: {},
				},
				{
					key: ["customer_review"],
					type: "switch",
					label: "Show Customer Review",
					props_data: {},
				},
				{
					key: ["payments_and_policy"],
					type: "switch",
					label: "Show Payments and Policy",
					props_data: {},
				},
			],
		},
		{
			name: "Layout",
			key: ["container", "properties"],
			replaceForm: [
				{
					key: ["padding"],
					type: "padding",
					label: "Padding",
					useIn: ["desktop", "tablet", "mobile"],
					props_data: {},
				},
				{
					key: ["margin"],
					type: "margin",
					label: "Margin",
					useIn: ["desktop", "tablet", "mobile"],
					props_data: {},
				},
			],
		},
	],
}
