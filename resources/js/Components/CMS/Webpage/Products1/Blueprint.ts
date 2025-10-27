import { trans } from "laravel-vue-i18n"

export default {
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
                },
                {
                    key: ["is_hide_filter"],
                    label: "Hide Filter?",
                    type: "switch",
                    information: trans('Hide filter on products page i.e filter price, tags, brands'),
                    // useIn : ["desktop", "tablet", "mobile"],
                },
            ],
        },
        {
            name: "Card Product",
            key: ["card_product","properties"],
            replaceForm: [
                {
					key: ["background"],
					label: "Background",
					type: "background",
				},
                {
                    key: ["border"],
                    label: "Border",
                    type: "border",
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
				},
                {
                    key: ["padding"],
                    label: "Padding",
                    type: "padding",
                    useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["margin"],
                    label: "Margin",
                    type: "margin",
                    useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["border"],
                    label: "Border",
                    type: "border",
                    useIn : ["desktop", "tablet", "mobile"],
                },
            ],
        },
    ],
}
