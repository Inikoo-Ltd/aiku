export const blueprint = [
    {
        name: "Container",
        icon: {
            icon: "fal fa-rectangle-wide",
            tooltip: "Container"
        },
        key: ['container_properties'],
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
        name: "Button",
        icon: {
            icon: "fal fa-hand-pointer",
            tooltip: "Main title"
        },
        key: ['fields'],
        accordion_key: 2,
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
        "type": "gradient",
        "color": "rgba(221,245,254,1)",
        "image": {
            "original": null
        },
        "gradient": {
            "value": "linear-gradient(90deg, rgba(221,245,254,1) 0%, rgba(252,247,255,1) 16%, rgba(255,252,246,1) 35%, rgba(248,240,255,1) 57%, rgba(255,250,246,1) 83%)",
            "colors": [
                "rgba(221,245,254,1)",
                "rgba(252,247,255,1)",
                "rgba(255,252,246,1)",
                "rgba(248,240,255,1)",
                "rgba(255,250,246,1)",
            ],
            "angle": "to right",
            "type": "linear"
        },
    },
    "text": {
        "color": "rgba(10,10,10,1)",
        "fontFamily": "'Raleway', sans-serif"
    },
    "isCenterHorizontal": false
}

export const defaultFieldsData = {
    "text_1": {
        "text": "<p>Pure Ingredients, Pure Health: <strong>20% Off</strong> on Organic Goods!</p>",
        "block_properties": {
            "position": {
                "x": "50%",
                "y": "50%",
                "type": "absolute"
            }
        }
    },
    "text_2": {
        "text": "<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed laoreet nisi at elit venenatis fringilla. Cras ut semper quam, sit.</p>",
        "block_properties": {
            "position": {
                "x": "20%",
                "y": "30%",
                "type": "absolute"
            }
        }
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
                    "color": "rgba(255, 255, 255, 1)",
                    "fontFamily": "Arial, sans-serif"
                },
                "background": {
                    "type": "color",
                    "color": "rgba(20,20,20,1)",
                    "image": {
                        "original": null
                    }
                },
                "padding": {
                    "unit": "px",
                    "top": {
                        "value": 3
                    },
                    "left": {
                        "value": 15
                    },
                    "right": {
                        "value": 15
                    },
                    "bottom": {
                        "value": 3
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
                            "value": 4
                        },
                        "topleft": {
                            "value": 4
                        },
                        "bottomright": {
                            "value": 4
                        },
                        "bottomleft": {
                            "value": 4
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
    "close_button": {
        "size": "0.5",
        "block_properties": {
            "text": {
                "color": "rgba(0, 0, 0, 0.5)",
            },
            "position": {
                "x": "97%",
                "y": "50%",
                "type": "absolute"
            }
        }
    }
}

export const defaultData = {
    code: 'announcement-promo-1',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}
