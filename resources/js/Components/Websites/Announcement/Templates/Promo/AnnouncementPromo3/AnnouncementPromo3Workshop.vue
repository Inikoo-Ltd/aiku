<script setup lang="ts">
import { getStyles } from "@/Composables/styles"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { computed, ref, onMounted, onUnmounted, inject, watch } from "vue"
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { get } from "lodash-es"
import { blueprint, defaultData } from "@/Components/Websites/Announcement/Templates/Promo/AnnouncementPromo3/Blueprint"

library.add(faTimes)

const props = defineProps<{
    announcementData?: {
        fields: {
            text_1: { text: string; block_properties?: BlockProperties }
            text_2: { text: string; block_properties?: BlockProperties }
            button_1?: { link: LinkProperties; text: string; container: { properties: BlockProperties } }
            countdown: { date: string; expired_text?: string }
            countdown_style?: { container?: { properties?: BlockProperties } }
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
    (e: "templateClicked", value: typeof defaultData): void
}>()

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
    }, get(props.announcementData, ["fields", "text_transition_data", 'duration', 1000]))
}

const mobileIndex = ref(0)

let mobileTimer: any = null
const isMobile = ref(false)

const checkMobile = () => {
    isMobile.value = window.innerWidth <= 768
}

watch(props.announcementData?.fields?.countdown, () => {
    updateCountdown()
})

const textCountdown = ref(getStyles(props.announcementData?.fields?.countdown_style?.container?.properties, 'desktop', false))

watch(
    () => props.announcementData?.fields?.countdown_style,
    () => {
        textCountdown.value = getStyles(props.announcementData?.fields?.countdown_style?.container?.properties, 'desktop', false)
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

defineExpose({ fieldSideEditor: blueprint })
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

    <div v-else @click="() => emits('templateClicked', defaultData)" class="inset-0 absolute">
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
