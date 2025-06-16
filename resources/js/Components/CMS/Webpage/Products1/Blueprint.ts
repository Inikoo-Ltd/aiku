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
