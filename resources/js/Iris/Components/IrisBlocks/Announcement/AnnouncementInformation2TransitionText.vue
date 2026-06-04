<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, onMounted, onBeforeUnmount } from "vue"
import type { BlockProperties } from "@/types/Announcement"

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
            countdown?: {
                date: string
                expired_text?: string
            }
        }
        container_properties: BlockProperties
    }
}>()

const indexTextActive = ref(0)
const interval = ref<any>(null)
const applyInterval = (duration: number, element: Element) => {
    clearInterval(interval.value)

    interval.value = setInterval(() => {
        if (element) {
            element.className = 'wowsbar-multitext-leave'
            element.addEventListener('animationend', () => {
                indexTextActive.value = (indexTextActive.value + 1) % (props.announcementData?.fields?.text_transition_1?.multi_text.length || 4)
                element.innerHTML = props.announcementData?.fields?.text_transition_1?.multi_text[indexTextActive.value] || ''
                element.className = 'wowsbar-multitext-enter'
            }, { once: true })
        }
    }, duration || 5000)
}

const __multitext_container = ref<Element | null>(null)
const updateKeyframes = (keyframes?: string) => {
    if (__multitext_container?.value && keyframes) {

        const existingStyle = __multitext_container.value.querySelector('style')
        if (existingStyle) {
            __multitext_container.value.removeChild(existingStyle)
        }

        const styleElement = document.createElement('style')
        styleElement.textContent = keyframes
        __multitext_container.value.appendChild(styleElement)
    }
}

onMounted(() => {
    const sentenceElem = document.getElementById("wowsbar_sentence_multi_text")

    if (sentenceElem && props.announcementData?.fields?.text_transition_1?.multi_text?.length) {
        updateKeyframes(props.announcementData?.fields?.text_transition_1?.transition?.keyframes || '')
        applyInterval(props.announcementData?.fields?.text_transition_1?.duration, sentenceElem)
    }
})

onBeforeUnmount(() => {
    clearInterval(interval.value)
})
</script>

<template>
    <div
        class="flex justify-center items-center overflow-hidden"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div ref="__multitext_container" class="-my-4">
            <div class="flex w-full text-center px-10 scale-75 md:scale-100">
                <p id="wowsbar_sentence_multi_text" v-html="announcementData?.fields?.text_transition_1?.multi_text?.[0] || ''" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
            </div>
        </div>
    </div>
</template>

<style scoped>
  .wowsbar-multitext-leave {
    animation: key-multitext-enter 0.3s forwards;
  }
  .wowsbar-multitext-enter {
    animation: key-multitext-leave 0.3s forwards;
  }
</style>
