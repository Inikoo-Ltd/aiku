<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref } from "vue"
// import { closeIcon } from '@/Composables/useAnnouncement'
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { uniqueId } from "lodash-es"

import { inject } from "vue";
library.add(faTimes)

const props = defineProps<{
    announcementData?: {
        fields: {
            text_1: {
                text: string
                block_properties: BlockProperties
            }
            button_1: {
                link: LinkProperties
                text: string
                container: {
                    properties: BlockProperties
                }
            }
            countdown: {
                date: string
                expired_text?: string
            }
        }
        container_properties: BlockProperties
    }
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: 'templateClicked',  template: typeof componentDefaultData): void
}>()

const _text_1 = ref(null)
// const _buttonClose = ref(null)

const fieldSideEditor = [
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
                label : "Background",
                type: "background",
            },
            {
                key: ["text"],
                type: "textProperty",
            },
            {
                key: ["margin"],
                label : "Margin",
                type: "margin",
                useIn : ["desktop", "tablet", "mobile"],
            },
            {
                key: ["padding"],
                label : "Padding",
                type: "padding",
                useIn : ["desktop", "tablet", "mobile"],
            },
            {
                key: ["border"],
                label : "Border",
                type: "border",
                useIn : ["desktop", "tablet", "mobile"],
            },
            {
                key: ["dimension"],
                label:"Dimension",
                type: "dimension",
                useIn : ["desktop", "tablet", "mobile"],
            },
        ]
    },
    {
        name: "Text Transitions",
        icon: { icon: "fal fa-text", tooltip: "Text Transitions" },
        key: ["fields", "text_transition_data"],
        replaceForm: [
            {
                name: "Multi text",
                icon: { icon: "fal fa-text", tooltip: "Multi text" },
                key: ["multi_text"],
                type: "array-data",
                props_data: {
                    blueprint: [
                        {
                            label: "Icon",
                            key: ["icon"],
                            type: "icon-picker",
                        },
                        {
                            label: "Text",
                            key: ["text"],
                            type: "editorhtml",
                            props_data: {
                                toogle: [
                                    "heading", "fontSize", "bold", "italic", "underline", "fontFamily",
                                    "alignLeft", "alignRight", "link",
                                    "alignCenter", "undo", "redo", "highlight", "color", "clear"
                                ]
                            }
                        },
                    ],
                    order_name: "Text",
                    can_drag: true,
                    can_delete: true,
                    can_add: true,
                    new_value_data: {
                        text: "<p>New text</p>",
                        id: uniqueId()
                    }
                }
            },
            {
                label: "Gap between texts",
                key: ["gap"],
                type: "number",
                props_data : {
                    suffix : 'px'
                }
			},
        ]
    },
]

// Data: Container
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
            "value": 10
        },
        "left": {
            "value": 20
        },
        "unit": "px",
        "right": {
            "value": 20
        },
        "bottom": {
            "value": 10
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
        "color": "rgba(240,248,255,1)", // AliceBlue
        "image": {
            "original": null
        },
        "gradient": {
            "type": "linear",
            "angle": "to right",
            "value": "linear-gradient(to right, rgba(245, 245, 245, 1), rgba(250, 250, 250, 1), rgba(245, 245, 245, 1))",
            "colors": [
                "rgba(245, 245, 245, 1)",
                "rgba(250, 250, 250, 1)",
                "rgba(245, 245, 245, 1)"
            ]
        },
    },
    "text": {
        "color": "rgba(10,10,10,1)",
        "fontFamily": "Inter, sans-serif"
    },
    "isCenterHorizontal": false
}

// Data: Text, Button, Close Button
const defaultFieldsData = {
    text_transition_data: {
        multi_text: [
            {
                text: '<p>Text 1</p>',
            },
            {
                text: '<p>Text 2</p>',
            },
        ],
        gap: 40,
    },
}

// To select on select templates
const componentDefaultData = {
    code: 'announcement-informational-1',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}

const openFieldWorkshop = inject('openFieldWorkshop', ref<number | null>(null))
const onClickOpenFieldWorkshop = (index?: number) => {
    if(openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}

defineExpose({
    fieldSideEditor
})
</script>

<template>
    <div
        v-if="!isToSelectOnly"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div @click="() => onClickOpenFieldWorkshop(1)" class="flex justify-center announcement-component-editable">
            <div v-for="(abc, idx) in announcementData?.fields?.text_transition_data?.multi_text"
                :key="abc.title"
                class="flex gap-x-2 items-center transition-all"
                :class="idx + 1 < announcementData?.fields?.text_transition_data?.multi_text?.length ? 'border-r border-black/20' : ''"
                :style="{
                    paddingLeft: announcementData?.fields?.text_transition_data?.gap + 'px',
                    paddingRight: announcementData?.fields?.text_transition_data?.gap + 'px',
                }"
            >
                <FontAwesomeIcon v-if="abc.icon" :icon="abc.icon" class="opacity-50" />
                <span class="" v-html="abc.text"></span>
            </div>
        </div>
    </div>

    <div
        v-else @click="() => emits('templateClicked', componentDefaultData)"
        class="inset-0 absolute"
    >
    </div>

</template>