export default {
    blueprint: [
         {
            name: "Chip",
            key: ['chip',"container", "properties"],
            replaceForm: [
                {
                    key: ["text"],
                    label: "text",
                    type: "textProperty",
                },
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
					useIn: ["desktop", "tablet", "mobile"],
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
            ],
        },
    ],
}
