<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref, onMounted, onUnmounted, inject, watch } from "vue"
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { trans } from "laravel-vue-i18n"
import { uniqueId } from "lodash"
import { get } from "lodash-es"
library.add(faTimes)


const props = defineProps<{
    announcementData?: {
        fields: {
            text_1: { text: string; block_properties: BlockProperties }
            button_1: { link: LinkProperties; text: string; container: { properties: BlockProperties } }
            countdown: { date: string; expired_text?: string }
            text_transition_data: {
                duration: number
                transition: any
                multi_text: Array<{ id: string; text: string }>
            }
        }
        container_properties: BlockProperties
    }
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: "templateClicked", value: typeof componentDefaultData): void
}>()


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

const fieldSideEditor = [
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
                key: ['countdown_style',"container",'properties','background'],
                label: "Background",
				type: "background",
            },
            {
                key: ['countdown_style',"container",'properties','text'],
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
                    props_data : {
                        suffix : 'ms'
                    }
			},
        ]
    },
]



const defaultContainerData = {
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

const defaultFieldsData = {
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

const componentDefaultData = {
    code: "announcement-promo-3",
    fields: defaultFieldsData,
    container_properties: defaultContainerData
}



const openFieldWorkshop = inject("openFieldWorkshop", ref<number | null>(null))
const onClickOpenFieldWorkshop = (index?: number) => {
    if (openFieldWorkshop && index) openFieldWorkshop.value = index
}


const targetTime = computed(() =>
    props.announcementData?.fields?.countdown?.date
        ? new Date(props.announcementData.fields.countdown.date).getTime()
        : 0
)

const days = ref("00")
const hours = ref("00")
const minutes = ref("00")
const seconds = ref("00")

let timer: any = null

const parseTime = (value: number) => ({
    tens: Math.floor(value / 10),
    ones: value % 10
})

const updateCountdown = () => {
    const now = Date.now()
    const diff = targetTime.value - now

    if (diff <= 0) {
        days.value = hours.value = minutes.value = seconds.value = "00"
        clearInterval(timer)
        return
    }

    const d = Math.floor(diff / 86400000)
    const h = Math.floor((diff % 86400000) / 3600000)
    const m = Math.floor((diff % 3600000) / 60000)
    const s = Math.floor((diff % 60000) / 1000)

    const format = (val: number) => {
        const p = parseTime(val)
        return `${p.tens}${p.ones}`
    }

    days.value = format(d)
    hours.value = format(h)
    minutes.value = format(m)
    seconds.value = format(s)
}


const activeMultiIndex = ref(0)
const isAnimating = ref(false)
let multiTimer: any = null

const playMultiText = () => {
    const list = props.announcementData?.fields?.text_transition_data?.multi_text
    if (!list?.length) return

    isAnimating.value = true

    setTimeout(() => {
        activeMultiIndex.value = (activeMultiIndex.value + 1) % list.length
        isAnimating.value = false
    }, get(props.announcementData,["fields", "text_transition_data",'duration',1000]))
}

const mobileIndex = ref(0)

let mobileTimer: any = null
const isMobile = ref(false)

const checkMobile = () => {
    isMobile.value = window.innerWidth <= 768
}

watch(props.announcementData?.fields?.countdown, (newValue) => {
    updateCountdown()
})


const textCountdown = ref(getStyles(props.announcementData?.fields?.countdown_style?.container?.properties,'desktop',false))

watch(
  () => props.announcementData?.fields?.countdown_style,
  () => {
    textCountdown.value = getStyles(props.announcementData?.fields?.countdown_style?.container?.properties,'desktop',false)
  },
  { deep: true }
)

onMounted(() => {
    updateCountdown()
    timer = setInterval(updateCountdown, 1000)

    const duration = props.announcementData?.fields?.text_transition_data?.duration ?? 5000
    multiTimer = setInterval(playMultiText, duration)


    checkMobile()
    window.addEventListener("resize", checkMobile)

    mobileTimer = setInterval(() => {
        if (isMobile.value) {
            mobileIndex.value = (mobileIndex.value + 1) % 3
        }
    }, 5000)
})

onUnmounted(() => {
    clearInterval(timer)
    clearInterval(multiTimer)
    window.removeEventListener("resize", checkMobile)
    clearInterval(mobileTimer)
})



defineExpose({ fieldSideEditor })

</script>


<template>
    <div v-if="isMobile && !isToSelectOnly" class="w-full px-4 py-2 h-16 flex items-center justify-center"
        :style="getStyles(announcementData?.container_properties)">

        <!-- SLIDE 1: TEXT_2 -->
        <div v-show="mobileIndex === 0" class="w-full h-full flex items-center justify-between w-full">

            <!-- TEXT_2 -->
            <div v-html="announcementData?.fields.text_2.text"
                :style="getStyles(announcementData?.fields?.text_2.block_properties)"
                class="announcement-component-editable text-center" />

            <!-- COUNTDOWN KECIL -->
            <div class="w-fit h-full flex items-center justify-center mt-1">
                <div class="flex items-center justify-center gap-2 font-sans">

                    <div class="flex flex-col items-center" >
                        <div
                            class="px-1 py-[2px] bg-purple-700 text-white rounded text-[10px] tabular-nums leading-none"  :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                            {{ days }}
                        </div>
                        <span class="text-[8px]   leading-none mt-[1px]  text-gray-100  text-countdown">DAY</span>
                    </div>

                    <div class="flex flex-col items-center">
                        <div
                            class="px-1 py-[2px] bg-purple-700 text-white rounded text-[10px] tabular-nums leading-none" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                            {{ hours }}
                        </div>
                        <span class="text-[8px]   leading-none mt-[1px]  text-gray-100  text-countdown">HRS</span>
                    </div>

                    <div class="flex flex-col items-center">
                        <div
                            class="px-1 py-[2px] bg-purple-700 text-white rounded text-[10px] tabular-nums leading-none" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                            {{ minutes }}
                        </div>
                        <span class="text-[8px]   leading-none mt-[1px]  text-gray-100  text-countdown">MINS</span>
                    </div>

                    <div class="flex flex-col items-center">
                        <div
                            class="px-1 py-[2px] bg-purple-700 text-white rounded text-[10px] tabular-nums leading-none" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)"> 
                            {{ seconds }}
                        </div>
                        <span class="text-[8px]   leading-none mt-[1px]  text-gray-100  text-countdown">SECS</span>
                    </div>

                </div>
            </div>
        </div>


        <!-- SLIDE 2: TEXT_1 -->
        <div v-show="mobileIndex === 1" class="w-full h-full flex items-center justify-center">
            <div v-html="announcementData?.fields.text_1.text"
                :style="getStyles(announcementData?.fields?.text_1.block_properties)"
                class="announcement-component-editable text-center text-sm" />
        </div>

        <!-- SLIDE 3: MULTI TEXT -->
        <div v-show="mobileIndex === 2" class="w-full h-full flex items-center justify-center overflow-hidden">
            <p id="aiku_sentence_multi_text" :class="isAnimating ? 'aiku-multitext-leave' : 'aiku-multitext-enter'"
                v-html="announcementData?.fields?.text_transition_data?.multi_text?.[activeMultiIndex]?.text"
                class="whitespace-nowrap overflow-hidden block w-full text-center" />
        </div>
    </div>



    <div v-else-if="!isToSelectOnly" class="w-full flex items-center justify-between px-4 py-2"
        :style="getStyles(announcementData?.container_properties)">

        <!-- LEFT -->
        <div class="flex items-center gap-4 text-purple-800 font-semibold text-xs 
                flex-[0_0_33%] min-w-0">

            <div v-if="announcementData?.fields?.text_2?.text" @click="() => (onClickOpenFieldWorkshop(1))"
                class="announcement-component-editable text-center md:text-left min-w-0"
                v-html="announcementData?.fields.text_2.text"
                :style="getStyles(announcementData?.fields?.text_2.block_properties)" />

            <!-- COUNTDOWN -->
            <div v-if="announcementData?.fields?.countdown" class="flex items-center gap-1 font-sans shrink-0">
                <div class="flex flex-col items-center">
                    <div class="px-2 py-1 bg-purple-700 text-white rounded-md text-xs tabular-nums" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                        {{ days }}
                    </div>
                    <span class="text-[9px] text-gray-100  text-countdown">DAY</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="px-2 py-1 bg-purple-700 text-white rounded-md text-xs tabular-nums" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                        {{ hours }}
                    </div>
                    <span class="text-[9px]  text-gray-100  text-countdown">HRS</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="px-2 py-1 bg-purple-700 text-white rounded-md text-xs tabular-nums" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                        {{ minutes }}
                    </div>
                    <span class="text-[9px]   text-gray-100  text-countdown">MINS</span>
                </div>
                <div class="flex flex-col items-center">
                    <div class="px-2 py-1 bg-purple-700 text-white rounded-md text-xs tabular-nums" :style="getStyles(announcementData?.fields?.countdown_style?.container?.properties)">
                        {{ seconds }}
                    </div>
                    <span class="text-[9px]  text-gray-100  text-countdown">SECS</span>
                </div>
            </div>
        </div>

        <!-- CENTER -->
        <div class="flex items-center gap-4 text-purple-900 text-sm 
                flex-[0_0_20%] justify-center min-w-0">
            <div class="flex items-center gap-1 min-w-0">
                <div v-if="announcementData?.fields?.text_1?.text" @click="() => (onClickOpenFieldWorkshop(1))"
                    class="announcement-component-editable text-center md:text-left min-w-0 truncate"
                    v-html="announcementData?.fields.text_1.text"
                    :style="getStyles(announcementData?.fields?.text_1.block_properties)" />
            </div>
        </div>

        <!-- RIGHT -->
        <div class="flex items-center gap-4 text-purple-900 text-sm 
                flex-[0_0_33%] justify-end min-w-0">

            <div ref="__multitext_container" @click="() => onClickOpenFieldWorkshop(1)"
                class="announcement-component-editable -my-4 min-w-0 overflow-hidden">

                <div class="flex w-full text-center px-10 scale-75 md:scale-100 
                        overflow-hidden">

                    <p id="aiku_sentence_multi_text"
                        :class="isAnimating ? 'aiku-multitext-leave' : 'aiku-multitext-enter'"
                        v-html="announcementData?.fields?.text_transition_data?.multi_text?.[activeMultiIndex]?.text"
                        class="whitespace-nowrap overflow-hidden text-ellipsis block w-full">
                    </p>

                </div>
            </div>
        </div>
    </div>

    <div v-else @click="() => emits('templateClicked', componentDefaultData)" class="inset-0 absolute">
    </div>
</template>

<style scoped>
.aiku-multitext-leave {
    animation: key-multitext-enter 0.3s forwards;
}

.aiku-multitext-enter {
    animation: key-multitext-leave 0.3s forwards;
}


.text-countdown { 
        color: v-bind('textCountdown?.color || null') !important;
        font-family: v-bind('textCountdown?.fontFamily || null') !important;
        font-size: v-bind('textCountdown?.fontSize || null') !important;
        font-style: v-bind('textCountdown?.fontStyle || null') !important;;
}
</style>