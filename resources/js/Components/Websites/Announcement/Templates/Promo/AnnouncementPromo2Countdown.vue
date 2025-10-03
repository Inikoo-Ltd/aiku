<script setup lang='ts'>
import Moveable from "vue3-moveable"
import { propertiesToHTMLStyle, onDrag, styleToString } from '@/Composables/usePropertyWorkshop'
// import type { BlockProperties, LinkProperties } from '@/Composables/usePropertyWorkshop'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, ref, onMounted, onBeforeUnmount, onUnmounted } from "vue"
import { closeIcon } from '@/Composables/getAnnouncement'
import type { BlockProperties, LinkProperties } from "@/types/Website/Announcement"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
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
    isEditable?: boolean  // true if in workshop
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
        name: "Main title",
        icon: {
            icon: "fal fa-text",
            tooltip: "Main title"
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
        name: "Countdown",
        icon: {
            icon: "fal fa-stopwatch-20",
            tooltip: "Time countdown"
        },
        replaceForm: [
            {
                key: ['fields', 'countdown'],
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
        replaceForm: [
            {
                key: ['fields', 'button_1'],
                type: "button"
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
        "type": "color",
        "color": "linear-gradient(90deg, #f1e1c2 0%, #fcbc98 35%, #f9cdc3 57%, #facefb 83%)",
        "image": {
            "original": null
        }
    },
    "text": {
        "color": "rgba(10,10,10,1)",
        "fontFamily": "'Raleway', sans-serif"
    },
    "isCenterHorizontal": false,
    // "additional_style": {
    //     display: "grid",
    //     "grid-template-columns": "repeat(3, 1fr)",
    //     "align-items": "center",
    //     "column-gap": "0.5rem"
    // }
}

// Data: Text, Button, Close Button
const defaultFieldsData = {
    "text_1": {
        "text": "<p><span style=\"font-family: 'Quicksand', sans-serif; font-size: 20px; color: #a810a8\"><strong>Special christmas sale✨!</strong></span></p>",
        // "block_properties": {
        //     "position": {
        //         "x": "50%",
        //         "y": "50%",
        //         "type": "absolute"
        //     }
        // }
    },
    // "text_2": {
    //     "text": "<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed laoreet nisi at elit venenatis fringilla. Cras ut semper quam, sit.</p>",
    //     "block_properties": {
    //         "position": {
    //             "x": "20%",
    //             "y": "30%",
    //             "type": "absolute"
    //         }
    //     }
    // },
    "button_1": {
        "link": {
            "type": "external",
            "url": "#",
            "id": 9,
            "workshop_route": ""
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
    code: 'announcement-promo-2-countdown',
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}

// const onClickClose = () => {
//     window.parent.postMessage('close_button_click', '*')
// }


// const compiled_layout = computed(() => {
//     const script = "<script> const initialTime = 1000 * 60 * 60 * 24 * 5; const endTime = new Date('" + props.announcementData?.fields?.countdown?.date + "').getTime() + initialTime; let timer = null; const parseTime = (time) => ({ tens: Math.floor(time / 10), ones: time % 10, }); const updateCountdown = () => { const now = new Date().getTime(); const timeLeft = endTime - now; if (timeLeft <= 0) { clearInterval(timer); document.getElementById('countdown-days').textContent = '00'; document.getElementById('countdown-hours').textContent = '00'; document.getElementById('countdown-minutes').textContent = '00'; document.getElementById('countdown-seconds').textContent = '00'; return; } const days = parseTime(Math.floor(timeLeft / (1000 * 60 * 60 * 24))); const hours = parseTime(Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))); const minutes = parseTime(Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60))); const seconds = parseTime(Math.floor((timeLeft % (1000 * 60)) / 1000)); document.getElementById('countdown-days').textContent = `${days.tens}${days.ones}`; document.getElementById('countdown-hours').textContent = `${hours.tens}${hours.ones}`; document.getElementById('countdown-minutes').textContent = `${minutes.tens}${minutes.ones}`; document.getElementById('countdown-seconds').textContent = `${seconds.tens}${seconds.ones}`; }; timer = setInterval(updateCountdown, 1000); updateCountdown(); <\/script>"


//     const button_element = props.announcementData?.fields?.button_1?.text ? `
//         <div class="tw-justify-self-end">
//             <a href="${props.announcementData?.fields.button_1.link.href || '#'}" target="${props.announcementData?.fields.button_1.link.target}" style="${styleToString(propertiesToHTMLStyle(props.announcementData.fields.button_1.container.properties))}">
//                 ${props.announcementData.fields.button_1.text}
//             </a>
//         </div>` : ''


//     return `
//     <div id="wowsbar_announcement" style="${styleToString(propertiesToHTMLStyle(props.announcementData?.container_properties))}">
//         <div class="" style="${styleToString(propertiesToHTMLStyle(props.announcementData?.fields.text_1.block_properties))}">
//             ${props.announcementData?.fields.text_1.text}
//         </div>

//         <div class="tw-grid tw-grid-cols-4  tw-gap-x-2 tw-font-sans tw-mx-auto tw-mt-1">
//             <div class="tw-flex tw-flex-col tw-items-center">
//                 <div id="countdown-days" class="tw-text-base tw-bg-white tw-w-fit tw-border tw-border-gray-200 tw-flex tw-justify-center tw-overflow-hidden tw-relative tw-rounded-md tw-py-1 tw-px-2 tw-tabular-nums">
//                     00
//                 </div>
//                 <div class="tw-text-xs tw-opacity-60">Days</div>
//             </div>
//             <div class="tw-flex tw-flex-col tw-items-center">
//                 <div id="countdown-hours" class="tw-text-base tw-bg-white tw-w-fit tw-border tw-border-gray-200 tw-flex tw-justify-center tw-overflow-hidden tw-relative tw-rounded-md tw-py-1 tw-px-2 tw-tabular-nums">
//                     00
//                 </div>
//                 <div class="tw-text-xs tw-opacity-60">Hours</div>
//             </div>
//             <div class="tw-flex tw-flex-col tw-items-center">
//                 <div id="countdown-minutes" class="tw-text-base tw-bg-white tw-w-fit tw-border tw-border-gray-200 tw-flex tw-justify-center tw-overflow-hidden tw-relative tw-rounded-md tw-py-1 tw-px-2 tw-tabular-nums">
//                     00
//                 </div>
//                 <div class="tw-text-xs tw-opacity-60">Minutes</div>
//             </div>
//             <div class="tw-flex tw-flex-col tw-items-center">
//                 <div id="countdown-seconds" class="tw-text-base tw-bg-white tw-w-fit tw-border tw-border-gray-200 tw-flex tw-justify-center tw-overflow-hidden tw-relative tw-rounded-md tw-py-1 tw-px-2 tw-tabular-nums">
//                     00
//                 </div>
//                 <div class="tw-text-xs tw-opacity-60">Seconds</div>
//             </div>
//         </div>

//         ${button_element}
//     </div>
//     ${script}
//     `
// })

const openFieldWorkshop = inject('openFieldWorkshop', null)
const onClickOpenFieldWorkshop = (index?: number) => {
    if(openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}

const initialTime = 1000 * 60 * 60 * 24 * 3 // 3 days
// const endTime = ref(new Date(props.announcementData?.fields?.countdown?.date).getTime() + initialTime)
const compTimeLeft = computed(() => {
    if (props.announcementData?.fields?.countdown?.date) {
        return new Date(props.announcementData?.fields?.countdown?.date).getTime()
    } else {
        return 0
    }
})

const days = ref('00')
const hours = ref('00')
const minutes = ref('00')
const seconds = ref('00')

let timer = null

const parseTime = (time) => ({
    tens: Math.floor(time / 10),
    ones: time % 10,
})

const updateCountdown = () => {
    const now = new Date().getTime()
    const timeLeft = compTimeLeft.value - now

    if (timeLeft <= 0) {
        clearInterval(timer)
        days.value = '00'
        hours.value = '00'
        minutes.value = '00'
        seconds.value = '00'
        return
    }

    const parsedDays = parseTime(Math.floor(timeLeft / (1000 * 60 * 60 * 24)))
    const parsedHours = parseTime(
        Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
    )
    const parsedMinutes = parseTime(
        Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60))
    )
    const parsedSeconds = parseTime(Math.floor((timeLeft % (1000 * 60)) / 1000))

    days.value = `${parsedDays.tens}${parsedDays.ones}`
    hours.value = `${parsedHours.tens}${parsedHours.ones}`
    minutes.value = `${parsedMinutes.tens}${parsedMinutes.ones}`
    seconds.value = `${parsedSeconds.tens}${parsedSeconds.ones}`
}

onMounted(() => {
    updateCountdown() // Update immediately
    timer = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
    clearInterval(timer) // Clean up on component unmount
});

defineExpose({
    // compiled_layout,
    fieldSideEditor
})
// <button
//     v-if="announcementData?.fields?.button_1?.text"
//     @click="() => onClickClose()"
//     class="inline-flex items-center"
//     style="${propertiesToHTMLStyle(props.announcementData?.fields.button_1?.container?.properties)}"
// >
//     ${props.announcementData?.fields?.button_1?.text}
// </button>
</script>

<template>
    <div
        v-if="!isToSelectOnly"
        :style="propertiesToHTMLStyle(announcementData?.container_properties)"
    >
        <div class="col-span-3 grid grid-cols-1 md:grid-cols-3 justify-center gap-y-2 items-center">
            <div v-if="announcementData?.fields.text_1.text" @click="() => (onClickOpenFieldWorkshop(1))" class="announcement-component-editable text-center md:text-left" v-html="announcementData?.fields.text_1.text" :style="propertiesToHTMLStyle(announcementData?.fields?.text_1.block_properties)">
            
            </div>
            
            <div v-if="compTimeLeft > new Date().getTime()" @click="() => (onClickOpenFieldWorkshop(2))" class="announcement-component-editable grid grid-cols-4 gap-x-2 font-sans mx-auto">
                <div class="flex flex-col items-center">
                    <div id="countdown-days" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{days}}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Days") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-hours" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{hours}}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Hours") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-minutes" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{minutes}}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Minutes") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-seconds" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{seconds}}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Seconds") }}</div>
                </div>
            </div>

            <div v-else @click="() => (onClickOpenFieldWorkshop(2))" class="announcement-component-editable flex justify-center" v-html="announcementData?.fields?.countdown?.expired_text">
            </div>
            
            <div v-if="announcementData?.fields.button_1.text" class="relative justify-self-center md:justify-self-end">
                <div v-if="isEditable"  @click="() => (onClickOpenFieldWorkshop(3))" class="absolute inset-0 announcement-component-editable " />
                <a :href="announcementData?.fields.button_1.link.href || '#'" :target="announcementData?.fields.button_1.link.target" v-html="announcementData?.fields.button_1.text" :style="propertiesToHTMLStyle(announcementData?.fields.button_1.container.properties)">
                </a>
            </div>
        </div>
        
    </div>

    <div v-else @click="() => emits('templateClicked', componentDefaultData)" class="inset-0 absolute">
    </div>

</template>