import { trans } from "laravel-vue-i18n"

export default (data?: { website_id?: number }) => {
    return {
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
                        useIn: ["desktop", "tablet", "mobile"],
                        information: trans("Can use decimal e.g. 4.5 to show half of next slide"),
                    },
                ],
            },
            {
                name: trans("Related Products Web Block"),
                key: ["recommendation_settings"],
                type: "related-products-block-settings",
                props_data: {
                    updateRoute: {
                        name: "grp.models.website.update",
                        parameters: [data?.website_id],
                        method: "patch",
                    },
                    payloadKeys: {
                        title: "title_recommender",
                        min_amt_shown: "min_amt_shown_recommender",
                        max_amt_shown: "max_amt_shown_recommender",
                    },
                },
            },
        ],
    }
}
