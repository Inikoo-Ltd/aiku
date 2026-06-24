export const blueprint = [
    {
        name: "Container",
        icon: {
            icon: "fal fa-rectangle-wide",
            tooltip: "Container"
        },
        key: ["container_properties"],
        replaceForm: [
            {
                key: ["background"],
                label: "Background",
                type: "background",
            },
            {
                key: ["text"],
                type: "textProperty",
            },
            {
                key: ["margin"],
                label: "Margin",
                type: "margin",
                useIn: ["desktop", "tablet", "mobile"],
            },
            {
                key: ["padding"],
                label: "Padding",
                type: "padding",
                useIn: ["desktop", "tablet", "mobile"],
            },
            {
                key: ["border"],
                label: "Border",
                type: "border",
                useIn: ["desktop", "tablet", "mobile"],
            },
            {
                key: ["dimension"],
                label: "Dimension",
                type: "dimension",
                useIn: ["desktop", "tablet", "mobile"],
            },
        ]
    },
    {
        name: "Main title",
        icon: {
            icon: "fal fa-text",
            tooltip: "Main title"
        },
        key: ['fields', 'text_1'],
        accordion_key: 1,
        replaceForm: [
            {
                key: ['text'],
                type: "editorhtml",
                props_data: {
                    toogle: [
                        'heading', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
                        'alignLeft', 'alignRight', "link",
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]
                }
            }
        ]
    },
    {
        name: "Countdown",
        icon: {
            icon: "fal fa-stopwatch-20",
            tooltip: "Time countdown"
        },
        key: ['fields'],
        accordion_key: 2,
        replaceForm: [
            {
                key: ['countdown'],
                type: "countdown",
                props_data: {
                    noToday: true,
                    toogle: [
                        'heading', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
                        'alignLeft', 'alignRight', "link",
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]
                }
            }
        ]
    },
    {
        name: "Button",
        icon: {
            icon: "fal fa-hand-pointer",
            tooltip: "Main title"
        },
        key: ['fields'],
        accordion_key: 3,
        replaceForm: [
            {
                key: ['button_1'],
                type: "button"
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
            "value": 8
        },
        "left": {
            "value": 20
        },
        "unit": "px",
        "right": {
            "value": 20
        },
        "bottom": {
            "value": 8
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
        "type": "gradient",
        "color": "#f1e1c2",
        "gradient": {
            "value": "linear-gradient(90deg, #f1e1c2 0%, #fcbc98 35%, #f9cdc3 57%, #facefb 83%)",
            "colors": [
                "#f1e1c2",
                "#fcbc98",
                "#f9cdc3",
                "#facefb",
            ],
            "angle": "to right",
            "type": "linear"
        },
        "image": {
            "original": null
        }
    },
    "text": {
        "color": "rgba(10,10,10,1)",
        "fontFamily": "'Raleway', sans-serif"
    },
    "isCenterHorizontal": false,
}

export const defaultFieldsData = {
    "text_1": {
        "text": "<p><span style=\"font-family: 'Quicksand', sans-serif; font-size: 20px; color: #a810a8\"><strong>Special christmas sale✨!</strong></span></p>",
    },
    "countdown": {
        "date": new Date(Date.now() + 2 * 24 * 60 * 60 * 1000),
        "expired_text": "<p><em>Countdown expired</em></p>"
    },
    "button_1": {
        "link": {
            "type": "external",
            "href": "#",
            "target": "_blank",
        },
        "text": 'Claim Now!',
        "container": {
            "properties": {
                "text": {
                    "color": "rgba(255,255,255,1)",
                    "fontFamily": "Arial, sans-serif"
                },
                "background": {
                    "type": "color",
                    "color": "rgba(147, 51, 234, 1)",
                    "image": {
                        "original": null
                    }
                },
                "padding": {
                    "unit": "px",
                    "top": {
                        "value": 5
                    },
                    "left": {
                        "value": 15
                    },
                    "right": {
                        "value": 15
                    },
                    "bottom": {
                        "value": 5
                    }
                },
                "margin": {
                    "unit": "px",
                    "top": {
                        "value": 0
                    },
                    "left": {
                        "value": 0
                    },
                    "right": {
                        "value": 0
                    },
                    "bottom": {
                        "value": 0
                    }
                },
                "border": {
                    "color": "#000000",
                    "unit": "px",
                    "rounded": {
                        "unit": "px",
                        "topright": {
                            "value": 7
                        },
                        "topleft": {
                            "value": 7
                        },
                        "bottomright": {
                            "value": 7
                        },
                        "bottomleft": {
                            "value": 7
                        }
                    },
                    "top": {
                        "value": 0
                    },
                    "left": {
                        "value": 0
                    },
                    "right": {
                        "value": 0
                    },
                    "bottom": {
                        "value": 0
                    }
                }
            }
        }
    },
}

export const defaultData = {
    code: 'announcement-promo-2-countdown',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}
