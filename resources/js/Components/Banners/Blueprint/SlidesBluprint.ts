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
                   /*  uploadRoute: props.imagesUploadRoute, */
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
                        "Arial",
                        "Avenir",
                        "Bunya",
                        "Cardinal",
                        "Comfortaa",
                        "Lobster",
                        "Laila",
                        "Port Lligat Slab",
                        "Playfair",
                        "Raleway",
                        "Roman Melikhov",
                        "Shoemaker",
                        "Source Sans Pro",
                        "Quicksand",
                        "Times New Roman",
                        "Yatra One"
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