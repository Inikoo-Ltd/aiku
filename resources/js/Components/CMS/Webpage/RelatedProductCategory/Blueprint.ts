export const blueprint = (data?: {}) => {
    // console.log('SeeAlso1 Blueprint data:', data)
    return {
        blueprint: [
        {
            label: "# Id ",
            key: ["id"],
            type: "text",
            information: "id selector is used to select one unique element!",
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
                    label: "Show Each Row",
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
    ],
}
}

export default blueprint