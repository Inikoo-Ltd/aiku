import { trans } from "laravel-vue-i18n"

export default {
    blueprint: [
        {
            name: "Layout",
            key: ["container", "properties"],
            replaceForm: [
                {
                    key: ["background"],
                    label :"Background",
                    type: "background",
                     
                },
                {
                    key: ["margin"],
                    label : "Margin",
                    type: "margin",
                    useIn : ["desktop", "tablet", "mobile"],
                },
                {
                    key: ["border"],
                    label : "Border",
                    type: "border",
                    useIn : ["desktop", "tablet", "mobile"],
                    
                },
            ],
        },
    ],
}
