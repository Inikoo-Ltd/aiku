export default {
    blueprint: [
        {
            name: "Settings",
            key: ["container", "properties"],
            replaceForm: [
                {
                    key: ["background"],
                    label: "Background",
                    type: "background",
                },
                {
                    key: ["text","fontFamily"],
                    label: "fontFamily",
                    type: "fontFamily",
                },
                {
                    key: ["padding"],
                    label: "Padding",
                    type: "padding",
                    props_data: {},
                    useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["margin"],
                    label: "Margin",
                    type: "margin",
                    props_data: {},
                    useIn : ["desktop", "tablet", "mobile"],
                },
            ],
        },
    ],
}
