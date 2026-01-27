export default {
	blueprint: [
		{
			name: "Settings",
			key: ["setting"],
			replaceForm: [
				{
					key: ["product_specs"],
					type: "switch",
					label: "Show Product Specs",
					props_data: {},
				},
				{
					key: ["payments_and_policy"],
					type: "switch",
					label: "Payments & Policy Section",
					props_data: {},
				},
				{
					key: ["appointment"],
					type: "switch",
					label: "Appointment",
					props_data: {},
				},
			],
		},
		{
			key: ["appointment_data"],
			name: "Appointment",
			replaceForm: [
				{
					key: ["text"],
					type: "editorhtml",
					label: "text",
				},
				{
					key: ["link"],
					type: "link",
					label: "Link",
				},
			],
		},

		{
			key: ["delivery_info"],
			name: "Delivery Info",
			replaceForm: [
				{
					key: ["text"],
					type: "editorhtml",
					label: "text",
				},
			],
		},

		{
			key: ["paymentData"],
			name: "Payment",
			type: "payment_templates",
		},
		{
			name: "Button login",
			key: ["buttonLogin", "properties"],
			replaceForm: [
				{
					key: ["background"],
					label: "Background",
					type: "background",
				},
				{
					key: ["text"],
					type: "textProperty",
				},
				{
					key: ["border"],
					label: "Border",
					type: "border",
				},
			],
		},
	],
}
