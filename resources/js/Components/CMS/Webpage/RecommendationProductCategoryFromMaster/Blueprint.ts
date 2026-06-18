import { trans } from "laravel-vue-i18n"

export const blueprint = (data?: { website_id?: number }) => {
    // console.log('SeeAlso1 Blueprint data:', data)
    return {
        blueprint: [
        {
            label: "# Id ",
            key: ["id"],
            type: "text",
            information: trans("id selector is used to select one unique element!"),
        },
        {
            label: "Responsive Visibility",
            key: ["container", "properties", "visibility"],
            type: "visibility",
            useIn: ["desktop", "tablet", "mobile"],
        },
        {
            name: "Settings",
            key: ["settings"],
            replaceForm: [
                {
                    key: ["per_row"],
                    label: trans("Show Each Row"),
                    type: "number",
                    useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["product_category"],
                    label: "Show Product Category",
                    type: "select_product_category",
                    props_data : {}
                },
            ],
        },
        {
            name: trans("Related Products Category Web Block"),
            key: ["recommendation_settings"],
            type: "related-products-block-settings",
            props_data: {
                updateRoute: {
                    name: "grp.models.website.update",
                    parameters: [data?.website_id],
                    method: "patch",
                },
                payloadKeys: {
                    title: "title_product_category_recommender",
                    min_amt_shown: "min_amt_shown_recommender_product_category",
                    max_amt_shown: "max_amt_shown_recommender_product_category",
                },
            },
        },
    ],
}
}

export default blueprint