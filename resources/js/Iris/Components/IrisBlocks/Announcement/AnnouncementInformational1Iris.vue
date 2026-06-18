<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, computed, onMounted, onUnmounted } from "vue"
import type { BlockProperties } from "@/types/Announcement"

library.add(faTimes, faSpinnerThird)

type TransitionText = {
    icon?: string
    text: string
    id?: string
}

const props = defineProps<{
    announcementData?: {
        fields: {
            text_transition_data: {
                multi_text: TransitionText[]
                gap: number
            }
        }
        container_properties: BlockProperties
    }
}>()

const activeIndex = ref(0)
let intervalId: number | null = null
let navigationTimeoutId: number | null = null

const isMobile = computed(() => {
    if (typeof window === 'undefined') {
        return false
    }

    return window.innerWidth < 768
})

const texts = computed(() => {
    return props.announcementData?.fields?.text_transition_data?.multi_text ?? []
})

const navigatingIndex = ref<number | null>(null)

const resetNavigationState = () => {
    if (navigationTimeoutId) {
        clearTimeout(navigationTimeoutId)
        navigationTimeoutId = null
    }

    navigatingIndex.value = null
}

const onClickAnnouncement = (event: MouseEvent, index: number) => {
    const anchor = (event.target as HTMLElement)?.closest?.('a[href]') as HTMLAnchorElement | null

    if (!anchor) {
        return
    }

    navigatingIndex.value = index
    navigationTimeoutId = window.setTimeout(() => {
        navigatingIndex.value = null
        navigationTimeoutId = null
    }, 2500)
}

const startCarousel = () => {
    if (!isMobile.value || texts.value.length <= 1) return

    intervalId = window.setInterval(() => {
        activeIndex.value = (activeIndex.value + 1) % texts.value.length
    }, 5000)
}

const stopCarousel = () => {
    if (intervalId) {
        clearInterval(intervalId)
        intervalId = null
    }
}

onMounted(() => {
    startCarousel()
})

onUnmounted(() => {
    stopCarousel()
    resetNavigationState()
})
</script>

<template>
    <div :style="getStyles(announcementData?.container_properties)">
        <div class="flex justify-center overflow-hidden">
            <!-- MOBILE : Carousel -->
            <div v-if="isMobile" @click.capture="onClickAnnouncement($event, activeIndex)" class="flex items-center transition-all duration-500">
                <FontAwesomeIcon v-if="texts[activeIndex]?.icon && navigatingIndex !== activeIndex" :icon="texts[activeIndex].icon"
                    class="opacity-50 mr-2" />
                <span v-html="texts[activeIndex]?.text"></span>
                <FontAwesomeIcon v-if="navigatingIndex === activeIndex" icon="fad fa-spinner-third" class="opacity-70 ml-2 animate-spin" />
            </div>

            <!-- DESKTOP & TABLET : Normal layout -->
            <template v-else>
                <div v-for="(abc, idx) in texts" :key="idx" @click.capture="onClickAnnouncement($event, idx)" class="flex gap-x-2 items-center transition-all"
                    :class="idx + 1 < texts.length ? 'border-r border-black/20' : ''" :style="{
                        paddingLeft: announcementData?.fields?.text_transition_data?.gap + 'px',
                        paddingRight: announcementData?.fields?.text_transition_data?.gap + 'px',
                    }">
                    <FontAwesomeIcon v-if="abc.icon && navigatingIndex !== idx" :icon="abc.icon" class="opacity-50" />
                    <FontAwesomeIcon v-if="navigatingIndex === idx" icon="fad fa-spinner-third" class="opacity-70 my-auto animate-spin" />
                    <span v-html="abc.text"></span>
                </div>
            </template>
        </div>
    </div>
</template>
