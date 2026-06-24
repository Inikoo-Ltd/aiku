<script setup lang='ts'>
import { getStyles } from "@/Composables/styles"
import { faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, onMounted, watch, inject } from "vue"
import type { BlockProperties } from "@/types/Announcement"
import { blueprint, defaultData } from "@/Components/Websites/Announcement/Templates/Information/AnnouncementInformation2TransitionText/Blueprint"

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
    _parentComponent?: Element
    isEditable?: boolean
    isToSelectOnly?: boolean
}>()

const emits = defineEmits<{
    (e: 'templateClicked', value: typeof defaultData): void
}>()

const openFieldWorkshop = inject('openFieldWorkshop', ref<number | null>(null))
const onClickOpenFieldWorkshop = (index?: number) => {
    if (openFieldWorkshop && index) {
        openFieldWorkshop.value = index
    }
}

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

onMounted(() => {
    const sentenceElem = document.getElementById("wowsbar_sentence_multi_text")

    if (sentenceElem && props.announcementData?.fields?.text_transition_1?.multi_text?.length) {
        updateKeyframes(props.announcementData?.fields?.text_transition_1?.transition?.keyframes || '')
        applyInterval(props.announcementData?.fields?.text_transition_1?.duration, sentenceElem)
    }
})

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

watch(
    () => props.announcementData?.fields?.text_transition_1?.duration,
    (newDuration, oldDuration) => {
        if (newDuration !== oldDuration) {
            const sentenceElem = document.getElementById("wowsbar_sentence_multi_text")
            if (sentenceElem) {
                applyInterval(newDuration as number, sentenceElem)
            }
        }
    }
)

watch(
    () => props.announcementData?.fields?.text_transition_1?.transition,
    (newTransition, oldTransition) => {
        if (newTransition?.value !== oldTransition?.value) {
            updateKeyframes(newTransition?.keyframes)
        }
    },
    { immediate: true }
)

defineExpose({
    fieldSideEditor: blueprint
})
</script>

<template>
    <div
        v-if="!isToSelectOnly"
        class="flex justify-center items-center overflow-hidden"
        :style="getStyles(announcementData?.container_properties)"
    >
        <div ref="__multitext_container" @click="() => onClickOpenFieldWorkshop(1)" class="announcement-component-editable -my-4">
            <div class="flex w-full text-center px-10 scale-75 md:scale-100">
                <p id="wowsbar_sentence_multi_text" v-html="announcementData?.fields?.text_transition_1?.multi_text?.[0] || ''" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></p>
            </div>
        </div>
    </div>

    <div v-else @click="() => emits('templateClicked', defaultData)" class="inset-0 absolute">
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
