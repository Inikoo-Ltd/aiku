<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronUp, faChevronDown } from "@fal"
library.add(faChevronUp, faChevronDown)

defineProps<{
    maxHeightClass: string
}>()

const scroller = ref<HTMLElement | null>(null)
const content = ref<HTMLElement | null>(null)
const canScrollUp = ref(false)
const canScrollDown = ref(false)

const update = () => {
    const element = scroller.value
    canScrollUp.value = !!element && element.scrollTop > 1
    canScrollDown.value = !!element && element.scrollTop + element.clientHeight < element.scrollHeight - 1
}

const scrollBy = (direction: 1 | -1) => scroller.value?.scrollBy({ top: direction * 80, behavior: "smooth" })

const scrollToBottom = () => {
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight
    update()
}

let resizeObserver: ResizeObserver | null = null

onMounted(() => {
    update()
    if (typeof ResizeObserver === "undefined") return
    resizeObserver = new ResizeObserver(update)
    if (scroller.value) resizeObserver.observe(scroller.value)
    if (content.value) resizeObserver.observe(content.value)
})

onBeforeUnmount(() => resizeObserver?.disconnect())

defineExpose({ scrollToBottom })
</script>

<template>
    <div class="relative">
        <div ref="scroller" class="overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" :class="maxHeightClass" @scroll.passive="update">
            <div ref="content">
                <slot />
            </div>
        </div>
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
            <button
                v-if="canScrollUp"
                type="button"
                :aria-label="ctrans('Scroll up')"
                class="absolute inset-x-0 top-0 z-10 flex h-6 items-start justify-center bg-gradient-to-b from-white via-white/90 to-transparent text-xs text-gray-500 hover:text-gray-800"
                @click="scrollBy(-1)">
                <FontAwesomeIcon icon="fal fa-chevron-up" fixed-width />
            </button>
        </Transition>
        <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-200" leave-to-class="opacity-0">
            <button
                v-if="canScrollDown"
                type="button"
                :aria-label="ctrans('Scroll down')"
                class="absolute inset-x-0 bottom-0 z-10 flex h-6 items-end justify-center bg-gradient-to-t from-white via-white/90 to-transparent text-xs text-gray-500 hover:text-gray-800"
                @click="scrollBy(1)">
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width />
            </button>
        </Transition>
    </div>
</template>
