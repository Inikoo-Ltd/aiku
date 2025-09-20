import { trans } from "laravel-vue-i18n"

export const blueprint = (data?: {}) => {
	return {
		blueprint: [
        {
            name: "Settings",
            key: ["settings"],
            replaceForm: [
                {
                    key: ["per_row"],
                    label: "Show Each Row",
                    type: "number",
                    useIn : ["desktop", "tablet", "mobile"],
                    information: trans("Can use decimal e.g. 4.5 to show half of next slide"),
                },
                // {
                //     key: ["products_data"],
                //     label: "Show Products",
                //     type: "select_product",
                //     props_data : {
                //         productCategory : data?.product_category_id,
                //         shop: data?.shop,
                //         family: data?.family,
                //     }
                // },
            ],
        },
        // {
        //     name: "Layout",
        //     key: ["container", "properties"],
        //     replaceForm: [
        //         {
		// 			key: ["background"],
		// 			label: "Background",
		// 			type: "background",
		// 		},
        //         {
        //             key: ["padding"],
        //             label: "Padding",
        //             type: "padding",
        //             useIn : ["desktop", "tablet", "mobile"],
        //         },
        //         {
        //             key: ["margin"],
        //             label: "Margin",
        //             type: "margin",
        //             useIn : ["desktop", "tablet", "mobile"],
        //         },
        //         {
        //             key: ["border"],
        //             label: "Border",
        //             type: "border",
        //             useIn : ["desktop", "tablet", "mobile"],
        //         },
        //     ],
        // },
    ],
}
}

export default blueprint