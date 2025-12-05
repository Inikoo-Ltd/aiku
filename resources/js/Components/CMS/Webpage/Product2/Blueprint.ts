import BlueprintSideInformation from "./BlueprintSideInformation"

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
			],
		},
		{
			key: ["paymentData"],
			name: "Payment",
			type: "payment_templates",
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
		{
            name: "Button Add to basket / portofolio",
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
        },
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
