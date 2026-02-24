import BlueprintSideInformation from "@/Components/CMS/Webpage/Product1/BlueprintSideInformation"
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
			key: ["setting"],
			replaceForm: [
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
					key: ["information"],
					type: "switch",
					label: "Show information",
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
					title : "Lorem Ipsum"
				},
			},
		},
		{
			name: "Information & Faq Style",
			key: ["information_style"],
			replaceForm: [
				{
					key: ["title","text"],
					type: "textProperty",
					label: "Title",
					props_data: {},
				},
				{
					key: ["content","text"],
					type: "textProperty",
					label: "content",
					props_data: {},
				},
			],
		},
		{
			name: "Description",
			key: ["description"],
			replaceForm: [
				{
					key: ["description_title","text"],
					type: "textProperty",
					label: "Description Title",
					props_data: {},
				},
				{
					key: ["description_content","text"],
					type: "textProperty",
					label: "Description Content",
					props_data: {},
				},
				{
					key: ["description_extra","text"],
					type: "textProperty",
					label: "Description Extra",
					props_data: {},
				},
			],
		},
		/* {
            name: "Button Add to basket",
            key: ["button", "properties"],
            replaceForm: [
                    {
                        key: ["background"],
                        label : "Background",
                        type: "background",
                    },
                    {
                        key: ["text"],
                        type: "textProperty",
                    },
                    {
                        key: ["border"],
                        label : "Border",
                        type: "border",
                    },
                ],
        }, */
		{
            name: "Button login",
            key: ["buttonLogin", "properties"],
            replaceForm: [
                    {
                        key: ["background"],
                        label : "Background",
                        type: "background",
                    },
                    {
                        key: ["text"],
                        type: "textProperty",
                    },
                    {
                        key: ["border"],
                        label : "Border",
                        type: "border",
                    },
                ],
        },
	],
}
