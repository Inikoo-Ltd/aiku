import { trans } from "laravel-vue-i18n"

export  const blueprint = [
        {
            name: "Button",
            key: ["button"],
            replaceForm: [
                {
                    key: ["background"],
                    label : "Background",
                    type: "background",
                },
                {
                    key: ["text"],
                    type: "textProperty",
                },
                {
                    key: ["margin"],
                    label : "Margin",
                    type: "margin",
                },
                {
                    key: ["padding"],
                    label : "Padding",
                    type: "padding",
                },
                {
                    key: ["border"],
                    label : "Border",
                    type: "border",
                },
            ],
        },
    ]

