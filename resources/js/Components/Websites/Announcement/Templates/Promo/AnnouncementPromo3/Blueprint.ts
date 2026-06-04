import { trans } from "laravel-vue-i18n"
import { uniqueId } from "lodash-es"

const textBlueprint = [
    {
        label: "Multi text",
        key: ["text"],
        type: "editorhtml",
        props_data: {
            toogle: [
                "heading", "fontSize", "bold", "italic", "underline", "fontFamily",
                "alignLeft", "alignRight", "link",
                "alignCenter", "undo", "redo", "highlight", "color", "clear"
            ]
        }
    }
]

export const blueprint = [
    {
        name: "Container",
        icon: { icon: "fal fa-rectangle-wide", tooltip: "Container" },
        key: ["container_properties"],
        replaceForm: [
            { key: ["background"], label: "Background", type: "background" },
            { key: ["text"], type: "textProperty" },
            { key: ["margin"], label: "Margin", type: "margin", useIn: ["desktop", "tablet", "mobile"] },
            { key: ["padding"], label: "Padding", type: "padding", useIn: ["desktop", "tablet", "mobile"] },
            { key: ["border"], label: "Border", type: "border", useIn: ["desktop", "tablet", "mobile"] },
            { key: ["dimension"], label: "Dimension", type: "dimension", useIn: ["desktop", "tablet", "mobile"] }
        ]
    },
    {
        name: "Left Text",
        icon: { icon: "fal fa-text", tooltip: "Left title" },
        key: ["fields", "text_1"],
        accordion_key: 1,
        replaceForm: [
            {
                key: ["text"],
                type: "editorhtml",
                props_data: textBlueprint[0].props_data
            }
        ]
    },
    {
        name: "Main title",
        icon: { icon: "fal fa-text", tooltip: "Main title" },
        key: ["fields", "text_2"],
        accordion_key: 2,
        replaceForm: [
            {
                key: ["text"],
                type: "editorhtml",
                props_data: textBlueprint[0].props_data
            }
        ]
    },
    {
        name: "Countdown",
        icon: { icon: "fal fa-stopwatch-20", tooltip: "Time countdown" },
        key: ["fields"],
        accordion_key: 2,
        replaceForm: [
            {
                key: ["countdown"],
                type: "countdown",
                props_data: {
                    noToday: true,
                    toogle: textBlueprint[0].props_data.toogle
                }
            },
            {
                key: ['countdown_style', "container", 'properties', 'background'],
                label: "Background",
                type: "background",
            },
            {
                key: ['countdown_style', "container", 'properties', 'text'],
                label: "text",
                type: "textProperty",
            }
        ]
    },
    {
        name: "Multi text",
        icon: { icon: "fal fa-text", tooltip: "Multi text" },
        key: ["fields", "text_transition_data"],
        replaceForm: [
            {
                name: "Multi text",
                icon: { icon: "fal fa-text", tooltip: "Multi text" },
                key: ["multi_text"],
                type: "array-data",
                props_data: {
                    blueprint: textBlueprint,
                    order_name: "Text",
                    can_drag: true,
                    can_delete: true,
                    can_add: true,
                    new_value_data: {
                        text: "<h3>Lorem Ipsum</h3><p>description from the product</p>",
                        id: uniqueId()
                    }
                }
            },
            {
                label: "Time text changed",
                key: ["duration"],
                type: "number",
                props_data: {
                    suffix: 'ms'
                }
            },
        ]
    },
]

export const defaultContainerData = {
    link: { href: "#", target: "_blank" },
    border: {
        top: { value: 0 },
        left: { value: 0 },
        right: { value: 0 },
        bottom: { value: 0 },
        unit: "px",
        color: "rgba(243, 243, 243, 1)",
        rounded: {
            unit: "px",
            topleft: { value: 0 },
            topright: { value: 0 },
            bottomleft: { value: 0 },
            bottomright: { value: 0 }
        }
    },
    margin: {
        top: { value: 0 },
        left: { value: 0 },
        right: { value: 0 },
        bottom: { value: 0 },
        unit: "px"
    },
    padding: {
        top: { value: 8 },
        left: { value: 20 },
        right: { value: 20 },
        bottom: { value: 8 },
        unit: "px"
    },
    position: {
        type: "relative",
        x: "0%",
        y: "0px"
    },
    dimension: {
        width: { value: null, unit: "%" },
        height: { value: null, unit: "%" }
    },
    background: {
        type: "color",
        color: "#F2F2F2",
        image: { original: null }
    },
    text: {
        color: "rgba(10,10,10,1)",
        fontFamily: "'Raleway', sans-serif"
    },
    isCenterHorizontal: false
}

export const defaultFieldsData = {
    text_1: { text: `<p>Trustpilot</p>` },
    text_2: {
        text: `
            <p style="text-align: center;"><span style="font-size: 0.875rem;"><strong>For same day dispatch</strong></span></p>
            <p style="text-align: center;"><span style="font-size: 0.75rem;">order within the next </span></p>
        `
    },
    text_transition_data: {
        transition: {
            label: trans("Slide down"),
            icon: "fal fa-arrow-down",
            value: "animate__slide_down",
            keyframes: `
            @keyframes key-multitext-enter {
                0% { transform: translateX(0); opacity: 1; }
                100% { transform: translateX(100%); opacity: 0; }
            }
            @keyframes key-multitext-leave {
                0% { transform: translateX(-100%); opacity: 0; }
                100% { transform: translateX(0); opacity: 1; }
            }
        `
        },
        duration: 1000,
        multi_text: [
            { id: uniqueId(), text: "worldwide delivery" },
            { id: uniqueId(), text: "No minimum order" },
            { id: uniqueId(), text: "Over 10000 products" }
        ]
    },
    countdown: {
        date: new Date(Date.now() + 2 * 86400000),
        expired_text: `<p><em>Countdown expired</em></p>`

    }
}

export const defaultData = {
    code: 'announcement-promo-3',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}
