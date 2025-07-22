<script setup lang='ts'>
import Moveable from "vue3-moveable"
import { propertiesToHTMLStyle, onDrag, styleToString } from '@/Composables/usePropertyWorkshop'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, ref, onMounted, onBeforeUnmount, watch } from "vue"
import { closeIcon } from '@/Composables/getAnnouncement'
import type { AnnouncementData, BlockProperties } from "@/types/Announcement"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n";
library.add(faTimes)

const props = defineProps<{
    announcementData?: {
        fields: {
            text_transition_1: {
                transition: {
                    label: string
                    icon?: string
                    value: string
                    keyframes: string
                }
                duration: number
                multi_text: string[]
            }
            // button_1: {
            //     text: string
            //     container: {
            //         properties: BlockProperties
            //     }
            // }
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
    (e: 'templateClicked', value: typeof componentDefaultData): void
}>()

const _text_1 = ref(null)
const _buttonClose = ref(null)

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
                        'heading', 'fontSize', 'bold', 'italic', 'underline', "fontFamily",
                        'alignLeft', 'alignRight', "link",
                        'alignCenter', 'undo', 'redo', 'highlight', 'color', 'clear'
                    ]
                }
            }
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
    // "additional_style": {
    //     display: "flex",
    //     "justify-content": "center",
    //     "align-items": "center",
    //     "overflow": "hidden"
    // }
}

// Data: Text, Button, Close Button
const defaultFieldsData = {
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

// To select on select templates
const componentDefaultData = {
    code: 'announcement-information-2-transition-text',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}

// const onClickClose = () => {
//     window.parent.postMessage('close_button_click', '*')
// }


// const compiled_layout = computed(() => {
//     const script = `<script> const information_2_sentence = ${JSON.stringify(props.announcementData?.fields?.text_transition_1?.multi_text || [])}; let index = 0; const sentenceElem = document.getElementById("wowsbar_sentence_multi_text"); setInterval(() => { if(sentenceElem) { sentenceElem.className = 'fade-out'; sentenceElem.addEventListener('animationend', () => { index = (index + 1) % information_2_sentence.length; sentenceElem.textContent = information_2_sentence[index]; sentenceElem.className = 'fade-in'; }, { once: true }); }}, 5000) <\/script>`

//     return `
//     <div id="wowsbar_announcement" style="${styleToString(propertiesToHTMLStyle(props.announcementData?.container_properties))}">
//         <div class="-tw-my-4">
//             <div class="tw-flex tw-w-full tw-text-center tw-px-10">
//                 <p id="wowsbar_sentence_multi_text" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
//                     ${props.announcementData?.fields?.text_transition_1?.multi_text?.[0]}
//                 </p>
//             </div>
//         </div>
//     </div>
//     ${script}
//     `
// })

const openFieldWorkshop = inject('openFieldWorkshop')
const onClickOpenFieldWorkshop = (index?: number) => {
    if(openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}


const indexTextActive = ref(0)
const interval = ref<Interval | null>(null)
const applyInterval = (duration: number, element: Element) => {
    clearInterval(interval.value)
    
    interval.value = setInterval(() => {
            if (element) {
                element.className = 'wowsbar-multitext-leave';  // Text exit animation
                element.addEventListener('animationend', () => {
                    indexTextActive.value = (indexTextActive.value + 1) % (props.announcementData?.fields?.text_transition_1?.multi_text.length || 4);
                    element.innerHTML = props.announcementData?.fields?.text_transition_1?.multi_text[indexTextActive.value] || '';
                    element.className = 'wowsbar-multitext-enter'; // Text enter animation
                }, { once: true });
            }
        }, duration || 5000);
}
onMounted(() => {
    const sentenceElem = document.getElementById("wowsbar_sentence_multi_text");
    
    if (sentenceElem && props.announcementData?.fields?.text_transition_1?.multi_text?.length) {
        updateKeyframes(props.announcementData?.fields?.text_transition_1?.transition?.keyframes || '');
        applyInterval(props.announcementData?.fields?.text_transition_1?.duration, sentenceElem);
    }
})


const __multitext_container = ref<Element | null>(null)
const updateKeyframes = (keyframes?: string) => {
    if (__multitext_container?.value && keyframes) {

        // Check if there's already a style tag and remove it
        const existingStyle = __multitext_container.value.querySelector('style');
        if (existingStyle) {
            __multitext_container.value.removeChild(existingStyle);
        }

        // Create and append the new style element
        const styleElement = document.createElement('style');
        styleElement.textContent = keyframes;
        __multitext_container.value.appendChild(styleElement);
    }
}

// Watch duration changes and apply to editor view
watch(
    () => props.announcementData?.fields?.text_transition_1?.duration,
    (newDuration, oldDuration) => {
        if (newDuration !== oldDuration) {  // Check if the duration actually changed
            const sentenceElem = document.getElementById("wowsbar_sentence_multi_text");
            if (sentenceElem) {
                applyInterval(newDuration, sentenceElem);
            }
        }
    }
)

// Watch transition changes and apply to editor view
watch(
    () => props.announcementData?.fields?.text_transition_1?.transition,
    (newTransition, oldTransition) => {
        if (newTransition?.value !== oldTransition?.value) {  // Check if the transition actually changed
            updateKeyframes(newTransition?.keyframes);
        }
    },
    { immediate: true }  // Run the watch handler immediately with the current value
);

defineExpose({
    // compiled_layout,
    fieldSideEditor
})
</script>

<template>
    <div
        v-if="!isToSelectOnly"
        class="flex justify-center items-center overflow-hidden"
        :style="propertiesToHTMLStyle(announcementData?.container_properties)"
    >
        <div ref="__multitext_container" @click="() => onClickOpenFieldWorkshop(1)" class="announcement-component-editable -my-4">
            <div class="flex w-full text-center px-10 scale-75 md:scale-100">
                <p id="wowsbar_sentence_multi_text" v-html="announcementData?.fields?.text_transition_1?.multi_text?.[0] || ''" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
            </div>
        </div>
    </div>

    <div v-else @click="() => emits('templateClicked', componentDefaultData)" class="inset-0 absolute">
    </div>

</template>

<style scoped>
  .wowsbar-multitext-leave {
    animation: key-multitext-enter 0.3s forwards; /* Animate out */
  }
  .wowsbar-multitext-enter {
    animation: key-multitext-leave 0.3s forwards; /* Animate in */
  }
</style>

