import { trans } from "laravel-vue-i18n"

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
                    label: trans("Number slides per row"),
                    type: "number",
                    useIn : ["desktop", "tablet", "mobile"],
                    information: trans("Can use decimal e.g. 4.5 to show half of next slide"),
                },
            ],
        },
    ],
}

