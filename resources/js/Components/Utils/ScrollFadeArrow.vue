<!--
    The arrow that tells the user there are more items to scroll to, mostly on a phone where a
    row of boxes, tabs, timeline steps or table columns is wider than the screen. It sits over
    the edge of a horizontally scrolling strip: a solid strip holding the chevron, outlined on
    its inner side so items passing underneath don't show through, then a soft fade into the
    content. It fades in and out as the strip reaches either end, and clicking it scrolls on.

    Use it with useScrollArrows on the scrolling element, inside a `relative` wrapper:
        const { canScrollLeft, canScrollRight, scrollBy } = useScrollArrows(scroller)
        <ScrollFadeArrow direction="left" :visible="canScrollLeft" @click="scrollBy(-1)" />
        <ScrollFadeArrow direction="right" :visible="canScrollRight" @click="scrollBy(1)" />
    tone="gray" for strips on a gray-50 background (the top bar), rounded for rounded boxes,
    wrapperClass for breakpoints (e.g. sm:hidden) and iconClass to keep the chevron in view on
    tall strips (e.g. "sticky top-24 bottom-24" on tables). Use this rather than a new arrow so
    every scrolling strip looks and behaves the same.
-->

<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = withDefaults(defineProps<{
    direction: "left" | "right"
    visible: boolean
    tone?: "white" | "gray"
    rounded?: boolean
    wrapperClass?: string
    iconClass?: string
}>(), {
    tone: "white",
    rounded: false,
    wrapperClass: "",
    iconClass: "",
})

const emit = defineEmits<{
    (e: "click"): void
}>()

const isLeft = computed(() => props.direction === "left")

const solidClass = computed(() => [
    props.tone === "gray" ? "bg-gray-50" : "bg-white",
    isLeft.value ? "border-r" : "border-l",
    props.rounded ? (isLeft.value ? "rounded-l" : "rounded-r") : "",
])

const fadeClass = computed(() => {
    if (props.tone === "gray") {
        return isLeft.value ? "bg-gradient-to-r from-gray-50" : "bg-gradient-to-l from-gray-50"
    }

    return isLeft.value ? "bg-gradient-to-r from-white" : "bg-gradient-to-l from-white"
})
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-300 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-300 ease-in"
        leave-to-class="opacity-0">
        <div
            v-if="visible"
            class="pointer-events-none absolute inset-y-0 z-20 flex"
            :class="[isLeft ? 'left-0' : 'right-0 flex-row-reverse', wrapperClass]">
            <button
                type="button"
                class="pointer-events-auto flex w-7 items-center justify-center border-gray-200 text-gray-500 transition-colors hover:text-gray-800"
                :class="solidClass"
                :aria-label="isLeft ? ctrans('Scroll left') : ctrans('Scroll right')"
                @click="emit('click')">
                <FontAwesomeIcon :icon="isLeft ? faChevronLeft : faChevronRight" :class="iconClass" fixed-width aria-hidden="true" />
            </button>
            <span class="w-5 to-transparent" :class="fadeClass" aria-hidden="true" />
        </div>
    </Transition>
</template>
