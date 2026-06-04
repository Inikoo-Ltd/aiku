<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, ref, onMounted, onUnmounted } from "vue"
import type { BlockProperties, LinkProperties } from "@/types/Announcement"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"

library.add(faTimes)

const props = defineProps<{
    announcementData?: {
        fields: {
            text_1: {
                text: string
                block_properties?: BlockProperties
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
}>()

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

let timer: any = null

const parseTime = (time: number) => ({
    tens: Math.floor(time / 10),
    ones: time % 10,
})

const hasRefreshedOnExpire = ref(false)

const updateCountdown = () => {
    const now = new Date().getTime()
    const timeLeft = compTimeLeft.value - now

    if (timeLeft <= 0) {
        clearInterval(timer)
        days.value = '00'
        hours.value = '00'
        minutes.value = '00'
        seconds.value = '00'

        if (!hasRefreshedOnExpire.value && compTimeLeft.value > 0) {
            hasRefreshedOnExpire.value = true
            router.reload({ only: ['announcements'] })
        }
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
    updateCountdown()
    timer = setInterval(updateCountdown, 1000)
})

onUnmounted(() => {
    clearInterval(timer)
})
</script>

<template>
    <div :style="getStyles(announcementData?.container_properties)">
        <div class="col-span-3 grid grid-cols-1 md:grid-cols-3 justify-center gap-y-2 items-center">
            <div v-if="announcementData?.fields?.text_1?.text" class="text-center md:text-left" v-html="announcementData?.fields.text_1.text" :style="getStyles(announcementData?.fields?.text_1.block_properties)">

            </div>

            <!-- Section: Countdown -->
            <div v-if="compTimeLeft > new Date().getTime()" class="grid grid-cols-4 gap-x-2 font-sans mx-auto">
                <div class="flex flex-col items-center">
                    <div id="countdown-days" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{ days }}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Days") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-hours" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{ hours }}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Hours") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-minutes" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{ minutes }}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Minutes") }}</div>
                </div>
                <div class="flex flex-col items-center">
                    <div id="countdown-seconds" class="text-base w-fit flex justify-center overflow-hidden relative rounded-md tabular-nums">
                        {{ seconds }}
                    </div>
                    <div class="text-xs opacity-60">{{ trans("Seconds") }}</div>
                </div>
            </div>

            <div v-else class="flex justify-center" v-html="announcementData?.fields?.countdown?.expired_text">
            </div>

            <div v-if="announcementData?.fields.button_1.text" class="mt-2 mb-1 md:mt-0 md:mb-0 relative justify-self-center md:justify-self-end">
                <a :href="announcementData?.fields.button_1.link.href || '#'" :target="announcementData?.fields.button_1.link.target" v-html="announcementData?.fields.button_1.text" :style="getStyles(announcementData?.fields.button_1.container.properties)">
                </a>
            </div>
        </div>
    </div>
</template>
