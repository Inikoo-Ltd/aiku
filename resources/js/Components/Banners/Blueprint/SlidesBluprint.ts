import { trans } from "laravel-vue-i18n"

export default {
    data:[
        {
            title: "Background & Link",
            icon: ["fal", "fa-image"],
            fields: [
                {
                    name: "image",
                    type: "slideBackground",
                    label: trans("Image"),
                    value: ["image"],
                },
                {
                    name: ["layout", "link"],
                    type: "text",
                    label: trans("Link"),
                    value: ["layout", "link"],
                    placeholder: "https://www.example.com",
                },
            ],
        },
        {
            title: "corners",
            icon: ["fal", "fa-expand-arrows"],
            fields: [
                {
                    name: ["layout", "corners"],
                    type: "corners",
                    label: null,
                    value: null,
                    optionType: ["cornerText", "linkButton", "ribbon", 'clear'],
                },
            ],
        },
        {
            title: "central stage",
            icon: ["fal", "fa-align-center"],
            fields: [
                {
                    name: ["layout", "centralStage", "title"],
                    type: "text",
                    label: trans("Title"),
                    value: ["layout", "centralStage", "title"],
                    placeholder: "Holiday Sales!"
                },
                {
                    name: ["layout", "centralStage", "subtitle"],
                    type: "text",
                    label: trans("subtitle"),
                    defaultValue : '',
                    value: ["layout", "centralStage", "subtitle"],
                    placeholder: "Holiday sales up to 80% all items."
                },
                {
                    name: ["layout", "centralStage", "linkOfText"],
                    type: "text",
                    label: trans("Hyperlink"),
                    defaultValue : '',
                    value: ["layout", "centralStage", "linkOfText"],
                    placeholder: "https://www.example.com"
                },
                {
                    name: ["layout", "centralStage", "style", "fontFamily"],
                    type: "selectFont",
                    label: trans("Font Family"),
                    value: ["layout", "centralStage", "style", "fontFamily"],
                    options: [
                        { "label": "Arial", "value": "Arial, sans-serif", "slug": "arial" },
                        { "label": "Avenir", "value": "Avenir, sans-serif", "slug": "avenir" },
                        { "label": "Bunya", "value": "Bunya, sans-serif", "slug": "bunya" },
                        { "label": "Cardinal", "value": "Cardinal, sans-serif", "slug": "cardinal" },
                        { "label": "Comfortaa", "value": "Comfortaa, sans-serif", "slug": "comfortaa" },
                        { "label": "Lobster", "value": "Lobster, cursive", "slug": "lobster" },
                        { "label": "Laila", "value": "Laila, sans-serif", "slug": "laila" },
                        { "label": "Port Lligat Slab", "value": "Port Lligat Slab, serif", "slug": "port-lligat-slab" },
                        { "label": "Playfair", "value": "Playfair, serif", "slug": "playfair" },
                        { "label": "Raleway", "value": "Raleway, sans-serif", "slug": "raleway" },
                        { "label": "Roman Melikhov", "value": "Roman Melikhov, serif", "slug": "roman-melikhov" },
                        { "label": "Shoemaker", "value": "Shoemaker, serif", "slug": "shoemaker" },
                        { "label": "Source Sans Pro", "value": "Source Sans Pro, sans-serif", "slug": "source-sans-pro" },
                        { "label": "Quicksand", "value": "Quicksand, sans-serif", "slug": "quicksand" },
                        { "label": "Times New Roman", "value": "Times New Roman, serif", "slug": "times-new-roman" },
                        { "label": "Yatra One", "value": "Yatra One, serif", "slug": "yatra-one" }
                    ],
                },
                {
                    name: ["layout", "centralStage", "textAlign"],
                    type: "textAlign",
                    label: trans("Text Align"),
                    value: ["layout", "centralStage", "textAlign"],
                    defaultValue : "center",
                    options: [
                        {
                            label: "Align left",
                            value: "left",
                            icon: 'fal fa-align-left'
                        },
                        {
                            label: "Align center",
                            value: "center",
                            icon: 'fal fa-align-center'
                        },
                        {
                            label: "Align right",
                            value: "right",
                            icon: 'fal fa-align-right'
                        },
                    ],
                },
                {
                    name: ["layout", "centralStage", "style", "fontSize"],
                    type: "radio",
                    label: trans("Font Size"),
                    value: ["layout", "centralStage", "style", "fontSize"],
                    defaultValue: { fontTitle: "text-[25px] md:text-[32px] lg:text-[44px]", fontSubtitle: "text-[12px] md:text-[15px] lg:text-[20px]" },
                    options: [
                        { 
                            label: "Extra Small",
                            value: {
                                fontTitle: "text-[13px] md:text-[17px] lg:text-[21px]",
                                fontSubtitle: "text-[8px] md:text-[10px] lg:text-[12px]"
                            }
                        },
                        {
                            label: "Small",
                            value: {
                                fontTitle: "text-[18px] md:text-[24px] lg:text-[32px]",
                                fontSubtitle: "text-[10px] md:text-[12px] lg:text-[15px]"
                            }
                        },
                        {
                            label: "Normal",
                            value: {
                                fontTitle: "text-[25px] md:text-[32px] lg:text-[44px]",
                                fontSubtitle: "text-[12px] md:text-[15px] lg:text-[20px]"
                            }
                        },
                        {
                            label: "Large",
                            value: {
                                fontTitle: "text-[30px] md:text-[43px] lg:text-[60px]",
                                fontSubtitle: "text-[15px] md:text-[19px] lg:text-[25px]"
                            }
                        },
                        {
                            label: "Extra Large",
                            value: {
                                fontTitle: "text-[40px] md:text-[52px] lg:text-[70px]",
                                fontSubtitle: "text-[20px] md:text-[24px] lg:text-[30px]"
                            },
                        },
                    ],
                },
                {
                    name: ["layout", "centralStage", "style", "color"],
                    type: "colorpicker",
                    label: trans("Text Color"),
                    icon: 'far fa-text',
                    value: ["layout", "centralStage", "style", "color"],
                },
                {
                    name: ["layout", "centralStage", "style", "textShadow"],
                    type: "toogle",
                    label: trans("Text Shadow"),
                    value: ["layout", "centralStage", "style", "TextShadow"],
                },
            ],
        },
    ]
}