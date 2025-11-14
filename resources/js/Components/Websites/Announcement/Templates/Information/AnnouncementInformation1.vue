<script setup lang='ts'>
import Moveable from "vue3-moveable"
// import { propertiesToHTMLStyle, onDrag, styleToString } from '@/Composables/usePropertyWorkshop'
import { getStyles } from "@/Composables/styles"
// import type { BlockProperties } from '@/Composables/usePropertyWorkshop'
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, inject, onMounted, ref } from "vue"
import { closeIcon } from '@/Composables/useAnnouncement'
library.add(faTimes)

const props = defineProps<{
    announcementData?: {
        fields: {
            text_1: {
                text: string
                block_properties: BlockProperties
            }
            text_2: {
                text: string
                block_properties: BlockProperties
            }
        }
        container_properties: BlockProperties
    }
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: 'templateClicked', value: typeof componentDefaultData): void
}>()

// Side editor: workshop
const fieldSideEditor = [
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
                        'heading', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
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
                        'heading', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
                        'alignLeft', 'alignRight', "link",
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]
                }
            }
        ]
    },
    // {
    //     name: "Button",
    //     icon: {
    //         icon: "fal fa-hand-pointer",
    //         tooltip: "Main title"
    //     },
    //     replaceForm: [
    //         {
    //             key: ['fields', 'button_1'],
    //             type: "button"
    //         }
    //     ]
    // },
]

// Data: Container (default on pick template)
const defaultContainerData = {
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
    // "additional_style": {
    //     "display": "flex",
    //     "align-items": "center",
    //     "justify-content": "space-between"
    // }
}

// Data: Fields (default on pick template)
const defaultFieldsData = {
    "text_1": {
        "text": "<span style=\"color: #fff700\"><strong>Christmas Sale</strong></span> • Enjoy big sales between Dec 1-25 2024&nbsp;<span aria-hidden=\"true\">&rarr;</span>",
        // "block_properties": {
        //     "position": {
        //         "x": "50%",
        //         "y": "50%",
        //         "type": "absolute"
        //     }
        // }
    },
    "text_2": {
        "text": "<p><span style=\"font-size: 20px; color: #ffffff\"><strong>Hot Deals Ever🔥!</strong></span></p>",
        // "block_properties": {
        //     "position": {
        //         "x": "20%",
        //         "y": "30%",
        //         "type": "absolute"
        //     }
        // }
    },
    // "button_1": {
    //     "url": "https://example.com",
    //     "label": "Click me",
    //     "width": "full",
    //     "border": {
    //         "top": {
    //             "value": 0
    //         },
    //         "left": {
    //             "value": 0
    //         },
    //         "unit": "px",
    //         "color": "rgba(20, 20, 20, 1)",
    //         "right": {
    //             "value": 0
    //         },
    //         "bottom": {
    //             "value": 0
    //         },
    //         "rounded": {
    //             "unit": "px",
    //             "topleft": {
    //                 "value": 0
    //             },
    //             "topright": {
    //                 "value": 0
    //             },
    //             "bottomleft": {
    //                 "value": 0
    //             },
    //             "bottomright": {
    //                 "value": 0
    //             }
    //         }
    //     },
    //     "target": "_blank",
    //     "background": {
    //         "type": "color",
    //         "color": "rgba(250, 250, 250, 1)",
    //         "image": {
    //             "original": null
    //         }
    //     },
    //     "text_color": "rgba(255, 255, 255, 1)",
    //     "block_properties": {
    //         "position": {
    //             "x": "20%",
    //             "y": "30%",
    //             "type": "absolute"
    //         }
    //     }
    // },
    // "close_button": {
    //     "size": "0.5",
    //     "block_properties": {
    //         "text": {
    //             "color": "rgba(0, 0, 0, 0.5)",
    //         },
    //         "position": {
    //             "x": "97%",
    //             "y": "50%",
    //             "type": "absolute"
    //         }
    //     }
    // }
}

// To select on select templates
const componentDefaultData = {
    code: 'announcement-information-1',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}

// Data: to publish in website

const onClickClose = () => {
    window.parent.postMessage('close_button_click', '*');
}

const openFieldWorkshop = inject('openFieldWorkshop')
const onClickOpenFieldWorkshop = (index?: number) => {
    if(openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}


defineExpose({
    // compiled_layout,
    fieldSideEditor
})

</script>

<template>
    <div
        v-if="!isToSelectOnly"
        class="relative isolate flex flex-wrap justify-center md:justify-between items-center gap-x-6 px-6 sm:px-3.5 transition-all"
        :style="getStyles(announcementData?.container_properties)"
    >
        <!-- <template> -->
            <div
                ref="_text_2"
                @click="() => onClickOpenFieldWorkshop(2)"
                class="announcement-component-editable"
                v-html="announcementData?.fields.text_2.text"
                :style="getStyles(announcementData?.fields.text_2.block_properties)"
            >
            </div>
            
            <div
                ref="_text_1"
                @click="() => onClickOpenFieldWorkshop(1)"
                class="announcement-component-editable"
                v-html="announcementData?.fields.text_1.text"
                :style="getStyles(announcementData?.fields.text_1.block_properties)"
            >
            
            </div>
    </div>

    <div
        v-else @click="() => emits('templateClicked', componentDefaultData)"
        class="inset-0 absolute"
    >
    </div>
</template>