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
        name: "Title 1",
        icon: {
            icon: "fal fa-text",
            tooltip: "Title 1"
        },
        replaceForm: [
            {
                key: ['fields', 'text_1'],
                type: "editorhtml",
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
    {
        name: "Title 2",
        icon: {
            icon: "fal fa-text",
            tooltip: "Title 2"
        },
        replaceForm: [
            {
                key: ['fields', 'text_2'],
                type: "editorhtml",
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
        "color": "rgba(147, 51, 234, 1)",
        "image": {
            "original": null
        }
    },
    "text": {
        "color": "rgb(255,255,255)",
        "fontFamily": "Arial, sans-serif"
    },
    "isCenterHorizontal": true,
}

export const defaultFieldsData = {
    "text_1": {
        "text": "<span style=\"color: #fff700\"><strong>Christmas Sale</strong></span> • Enjoy big sales between Dec 1-25 2024&nbsp;<span aria-hidden=\"true\">&rarr;</span>",
    },
    "text_2": {
        "text": "<p><span style=\"font-size: 20px; color: #ffffff\"><strong>Hot Deals Ever🔥!</strong></span></p>",
    },
}

export const defaultData = {
    code: 'announcement-information-1',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}
