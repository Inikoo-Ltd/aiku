import { ctrans } from "@/Composables/useTrans"

export default {
    data: [
        {
            title: "Controls",
            icon: "fal fa-tools",
            fields: [
                {
                    name: "delay",
                    type: "range",
                    label: ctrans("Duration"),
                    value: null,
                    timeRange: {
                        min: "2.5",
                        max: "15",
                        step: "0.5",
                        range: ["2.5", "5", "7.5", "10", "12.5", "15"],
                    },
                },
              /*   {
                    name: ["common", "height"],
                    type: "rangeSlider",
                    label: ctrans("Banner Height"),
                    useIn: ["desktop", "mobile"],
                    defaultValue: {
                        desktop: 400,
                        mobile: 300
                    },
                    range: {
                        min: 200,
                        max: 800,
                        step: 10
                    }
                }, */
                {
                    name: "navigation",
                    type: "bannerNavigation",
                    label: ctrans("Navigation"),
                    value: null,
                    options: [
                        { label: 'Navigation (arrows)', name: 'sideNav' },
                        { label: 'Pagination (bullets or buttons)', name: 'bottomNav' }
                    ]
                },
                {
                    name: ["common", "spaceBetween"],
                    type: "radio",
                    label: ctrans("space Between"),
                    value: null,
                    placeholder: "Enter space between",
                    options: [
                        {
                            label: "zero",
                            value: 0,
                        },
                        {
                            label: "Medium",
                            value: 5,
                        },
                        {
                            label: "Large",
                            value: 10,
                        },
                        {
                            label: "Extra Large",
                            value: 20,
                        },
                    ],
                },
                {
                    name: ["common", "spaceColor"],
                    type: "colorpicker",
                    label: ctrans("Border color"),
                    value: null,
                    placeholder: "Enter space between"
                },
            ],
        },
        /*  {
             title: "corners",
             icon: ["fal", "fa-expand-arrows"],
             fields: [
                 {
                     name: ["common", "corners"],
                     type: "corners",
                     label: null,
                     value: null,
                     optionType: ["cornerText", "linkButton", "ribbon", 'clear'],
                 },
             ],
         }, */
        {
            title: "central stage",
            icon: ["fal", "fa-align-center"],
            fields: [
                {
                    name: ["common", "centralStage", "title"],
                    type: "text",
                    label: ctrans("Title"),
                    value: ["common", "centralStage", "title"],
                    placeholder: "Enter title of the slide"
                },
                {
                    name: ["common", "centralStage", "subtitle"],
                    type: "text",
                    label: ctrans("subtitle"),
                    value: ["common", "centralStage", "subtitle"],
                    placeholder: "Enter subtitle of the slide"
                },
                {
                    name: ["common", "centralStage", "linkOfText"],
                    type: "text",
                    label: ctrans("Hyperlink"),
                    defaultValue: '',
                    value: ["common", "centralStage", "linkOfText"],
                    placeholder: "https://www.example.com"
                },
                {
                    name: ["common", "centralStage", "style", "fontFamily"],
                    type: "selectFont",
                    label: ctrans("Font Family"),
                    value: ["common", "centralStage", "style", "fontFamily"],
                },
                {
                    name: ["common", "centralStage", "textAlign"],
                    type: "textAlign",
                    label: ctrans("Text Align"),
                    defaultValue: "center",
                    value: ["common", "centralStage", "textAlign"],
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
                    name: ["common", "centralStage", "style", "fontSize"],
                    type: "radio",
                    label: ctrans("Font Size"),
                    value: ["common", "centralStage", "style", "fontSize"],
                    defaultValue: {
                        fontTitle: "text-[25px] md:text-[32px] lg:text-[44px]",
                        fontSubtitle: "text-[12px] md:text-[15px] lg:text-[20px]"
                    },
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
                    name: ["common", "centralStage", "style", "color"],
                    type: "colorpicker",
                    label: ctrans("Text Color"),
                    value: ["common", "centralStage", "style", "color"],
                    icon: 'far fa-text'
                },
                {
                    name: ["common", "centralStage", "style", "textShadow"],
                    type: "toggle",
                    label: ctrans("Text Shadow"),
                    value: ["common", "centralStage", "style", "TextShadow"],
                },
            ],
        },
    ]
}