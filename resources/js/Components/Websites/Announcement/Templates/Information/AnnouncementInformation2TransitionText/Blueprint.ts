import { trans } from "laravel-vue-i18n"

export const blueprint = [
    {
        name: "Container",
        icon: {
            icon: "fal fa-rectangle-wide",
            tooltip: "Container"
        },
        replaceForm: [
            {
                key: ['container_properties'],
                type: "properties"
            },
        ]
    },
    {
        name: "Multi text",
        icon: {
            icon: "fal fa-text",
            tooltip: "Main title"
        },
        replaceForm: [
            {
                key: ['fields', 'text_transition_1'],
                type: "multi_editorhtml",
                props_data: {
                    toogle: [
                        'heading1', 'heading2', 'heading3', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
                        'alignLeft', 'alignRight', "link",
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]
                }
            }
        ]
    },
]

export const defaultContainerData = {
    "link": {
        "href": "#",
        "target": "_blank"
    },
    "border": {
        "top": {
            "value": 0
        },
        "left": {
            "value": 0
        },
        "unit": "px",
        "color": "rgba(243, 243, 243, 1)",
        "right": {
            "value": 0
        },
        "bottom": {
            "value": 0
        },
        "rounded": {
            "unit": "px",
            "topleft": {
                "value": 0
            },
            "topright": {
                "value": 0
            },
            "bottomleft": {
                "value": 0
            },
            "bottomright": {
                "value": 0
            }
        }
    },
    "margin": {
        "top": {
            "value": 0
        },
        "left": {
            "value": 0
        },
        "unit": "px",
        "right": {
            "value": 0
        },
        "bottom": {
            "value": 0
        }
    },
    "padding": {
        "top": {
            "value": 20
        },
        "left": {
            "value": 20
        },
        "unit": "px",
        "right": {
            "value": 20
        },
        "bottom": {
            "value": 20
        }
    },
    "position": {
        "x": "0%",
        "y": "0px",
        "type": "relative"
    },
    "dimension": {
        "width": {
            "unit": "%",
            "value": 100
        },
        "height": {
            "unit": "px",
            "value": 0
        }
    },
    "background": {
        "type": "color",
        "color": "rgba(50,50,50,1)",
        "image": {
            "original": null
        }
    },
    "text": {
        "color": "rgba(255,255,255,1)",
        "fontFamily": "'Raleway', sans-serif"
    },
    "isCenterHorizontal": false,
}

export const defaultFieldsData = {
    "text_transition_1": {
        "transition": {
            label: trans('Slide down'),
            icon: 'fal fa-arrow-down',
            value: 'animate__slide_down',
            keyframes: `@keyframes key-multitext-enter { 0% { transform: translateY(0); opacity: 1; } 100% { transform: translateY(100%); opacity: 0; } } @keyframes key-multitext-leave { 0% { transform: translateY(-100%); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }`
        },
        "duration": 5000,
        "multi_text": [
            "<p><span style=\"font-size: 20px; color: rgb(217, 255, 0)\">Special christmas sale</span><span style=\"font-size: 20px; color: rgb(255, 221, 0)\">✨!</span></p>",
            "<p><span style=\"font-family: Inter, sans-serif; font-size: 20px\">Offer up to </span><span style=\"font-size: 20px; color: #00ffb3\">20%!</span></p>",
            "<p><span style=\"color: #ffffff\">Subscribe to newletter to get announced new promo</span></p>",
        ],
    },
}

export const defaultData = {
    code: 'announcement-information-2-transition-text',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}
