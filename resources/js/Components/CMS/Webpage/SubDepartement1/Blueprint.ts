export default {
    blueprint: [
        {
            name: "Layout",
            key: ["container", "properties"],
            replaceForm: [
                {
                    key: ["background"],
                    label: "Background",
                    type: "background",
                    useIn : ["desktop", "tablet", "mobile"],
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
                {
                    key: ["border"],
                    label: "Border",
                    type: "border",
                    props_data: {},
                    useIn : ["desktop", "tablet", "mobile"],
                },
            ],
        },
    ],
}
