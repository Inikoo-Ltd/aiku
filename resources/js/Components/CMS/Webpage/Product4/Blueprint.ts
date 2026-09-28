import Product3 from "@/Components/CMS/Webpage/Product3/Blueprint"
import BlueprintSideInformation from "@/Components/CMS/Webpage/Product1/BlueprintSideInformation"

const [idField, settingsField, ...product3Fields] = Product3.blueprint

export default {
	blueprint: [
		idField,
		{
			...settingsField,
			replaceForm: [
				...settingsField.replaceForm,
				{
					key: ["information"],
					type: "switch",
					label: "Show information",
					props_data: {},
				},
				{
					key: ["payments_and_policy"],
					type: "switch",
					label: "Show Secure Payments",
					props_data: {},
				},
			],
		},
		{
			key: ["paymentData"],
			name: "Payment",
			type: "payment_templates",
		},
		{
			key: ["information"],
			name: "Information",
			type: "array-data",
			props_data: {
				blueprint: BlueprintSideInformation.blueprint,
				order_name: "information",
				can_drag: true,
				can_delete: true,
				can_add: true,
				new_value_data: {
					text: "Lorem Ipsum",
					title: "Lorem Ipsum",
				},
			},
		},
		{
			name: "Information & Faq Style",
			key: ["information_style"],
			replaceForm: [
				{
					key: ["title", "text"],
					type: "textProperty",
					label: "Title",
					props_data: {},
				},
				{
					key: ["content", "text"],
					type: "textProperty",
					label: "content",
					props_data: {},
				},
			],
		},
		...product3Fields,
	],
}
