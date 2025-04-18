import { trans } from "laravel-vue-i18n"

export default {
    blueprint: [
        {
            key: ["settings", "container", "properties", "background"],
            name: "Background",
            type: "background",
        },
        {
            key: ["settings", "container", "properties", "padding"],
            name: "Padding",
            type: "padding",
        },
        {
            key: ["settings", "container", "properties", "margin"],
            name: "Margin",
            type: "margin",
        },
        {
            key: ["settings", "container", "properties", "border"],
            name: "Border",
            type: "border",
        },
        {
            key: ["settings", "container", "properties"],
            name: "Shadow",
            replaceForm : [
                {
                    key: ["shadow"],
                    label: "Shadow",
                    type: "shadow",
                },
                {
                    key: ["shadowColor"],
                    label: "Shadow Color",
                    type: "color",
                },
            ]
           
        },
       
    ],
}
