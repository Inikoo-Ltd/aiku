import { trans } from "laravel-vue-i18n"

export  const blueprint = [
        {
            name: "Button",
            key: ["button"],
            replaceForm: [
                {
                    key: ["container",'properties',"background"],
                    label : "Background",
                    type: "background",
                },
                {
                    key: ["container",'properties',"text"],
                    type: "textProperty",
                },
                {
                    key: ["container",'properties',"margin"],
                    label : "Margin",
                    type: "margin",
                },
                {
                    key: ["container",'properties',"padding"],
                    label : "Padding",
                    type: "padding",
                },
                {
                    key: ["container",'properties',"border"],
                    label : "Border",
                    type: "border",
                },
            ],
        },
    ]

