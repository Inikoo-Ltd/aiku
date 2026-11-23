<!--
    The arrow that tells the user there are more items to scroll to, mostly on a phone where a
    row of boxes, tabs, timeline steps or table columns is wider than the screen. It sits over
    the edge of a horizontally scrolling strip: a solid strip holding the chevron, outlined on
    its inner side so items passing underneath don't show through and edged on its outer side in
    the background colour, 1px past the strip, so no sliver of content peeks at the edge, then a soft fade into the
    content. It fades in and out as the strip reaches either end, and clicking it scrolls on.

    Use it with useScrollArrows on the scrolling element, inside a `relative isolate` wrapper.
    The arrow is z-20 to sit over the strip's own raised items; isolate keeps that inside the
    strip, otherwise it ties with the mobile left sidebar (also z-20) and paints over the menu:
        const { canScrollLeft, canScrollRight, scrollBy } = useScrollArrows(scroller)
        <ScrollFadeArrow direction="left" :visible="canScrollLeft" @click="scrollBy(-1)" />
        <ScrollFadeArrow direction="right" :visible="canScrollRight" @click="scrollBy(1)" />
    For a tall box that scrolls vertically use direction="up" / "down" with canScrollUp,
    canScrollDown and scrollVerticallyBy from the same composable.
    tone="gray" for strips on a gray-50 background (the top bar), rounded for rounded boxes,
    wrapperClass for breakpoints (e.g. sm:hidden) and iconClass to keep the chevron in view on
    tall strips (e.g. "sticky top-24 bottom-24" on tables). Use this rather than a new arrow so
    every scrolling strip looks and behaves the same.
-->

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch, type CSSProperties } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faChevronLeft, faChevronRight, faChevronUp, faChevronDown } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = withDefaults(defineProps<{
    direction: "left" | "right" | "up" | "down"
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

const root = ref<HTMLElement | null>(null)
const coveredLines = ref<CSSProperties[]>([])

type Edge = "top" | "bottom" | "left" | "right"

const crossEdges = (): Edge[] => (props.direction === "up" || props.direction === "down" ? ["left", "right"] : ["top", "bottom"])

/**
 * The arrow is a solid box over the edge of its strip, so it hides any line it lies on: the
 * underline of a tab row, the border of a top bar. It finds the borders of the strip, its
 * siblings and a few ancestors that run through its own box, and draws each again on top at
 * the same place, so the line carries on under the arrow whatever the strip looks like.
 */
const lineThroughArrow = (element: HTMLElement, edge: Edge, arrow: DOMRect): CSSProperties | null => {
    const style = getComputedStyle(element)
    const side = edge[0].toUpperCase() + edge.slice(1)
    const width = parseFloat(style.getPropertyValue(`border-${edge}-width`))
    const lineStyle = style.getPropertyValue(`border-${edge}-style`)
    const color = style.getPropertyValue(`border-${edge}-color`)

    if (!width || lineStyle === "none" || lineStyle === "hidden" || color === "transparent" || color === "rgba(0, 0, 0, 0)") {
        return null
    }

    const box = element.getBoundingClientRect()
    const isHorizontalLine = edge === "top" || edge === "bottom"
    const lineStart = edge === "top" ? box.top : edge === "bottom" ? box.bottom - width : edge === "left" ? box.left : box.right - width
    const [arrowStart, arrowEnd] = isHorizontalLine ? [arrow.top, arrow.bottom] : [arrow.left, arrow.right]
    const overlapsAlong = isHorizontalLine
        ? box.left < arrow.right && box.right > arrow.left
        : box.top < arrow.bottom && box.bottom > arrow.top

    if (!overlapsAlong || lineStart < arrowStart - 0.5 || lineStart + width > arrowEnd + 0.5) {
        return null
    }

    const offset = `${lineStart - arrowStart}px`
    const line = `${width}px ${lineStyle} ${color}`

    return isHorizontalLine
        ? { top: offset, left: 0, right: 0, [`border${side === "Bottom" ? "Top" : side}`]: line }
        : { left: offset, top: 0, bottom: 0, [`border${side === "Right" ? "Left" : side}`]: line }
}

const findCoveredLines = () => {
    const arrow = root.value
    if (!arrow?.parentElement) {
        coveredLines.value = []
        return
    }

    const arrowBox = arrow.getBoundingClientRect()
    const siblings = Array.from(arrow.parentElement.children).filter(
        (element): element is HTMLElement => element instanceof HTMLElement && element !== arrow && !element.hasAttribute("data-scroll-fade-arrow")
    )
    const ancestors: HTMLElement[] = []
    for (let element: HTMLElement | null = arrow.parentElement; element && ancestors.length < 4; element = element.parentElement) {
        ancestors.push(element)
    }

    coveredLines.value = crossEdges()
        .map((edge) => [...siblings, ...ancestors].map((element) => lineThroughArrow(element, edge, arrowBox)).find(Boolean) ?? null)
        .filter((line): line is CSSProperties => line !== null)
}

const measureWhenShown = () => nextTick(findCoveredLines)

watch(() => [props.visible, props.direction], measureWhenShown)

onMounted(() => {
    measureWhenShown()
    window.addEventListener("resize", findCoveredLines)
})

onBeforeUnmount(() => window.removeEventListener("resize", findCoveredLines))

const isLeft = computed(() => props.direction === "left")

const isVertical = computed(() => props.direction === "up" || props.direction === "down")

const isUp = computed(() => props.direction === "up")

const icon = computed(() => ({ left: faChevronLeft, right: faChevronRight, up: faChevronUp, down: faChevronDown })[props.direction])

const ariaLabel = computed(() => ({
    left: ctrans("Scroll left"),
    right: ctrans("Scroll right"),
    up: ctrans("Scroll up"),
    down: ctrans("Scroll down"),
})[props.direction])

const verticalSolidClass = computed(() => [
    props.tone === "gray" ? "bg-gray-50" : "bg-white",
    "border-y",
    isUp.value
        ? (props.tone === "gray" ? "border-t-gray-50" : "border-t-white")
        : (props.tone === "gray" ? "border-b-gray-50" : "border-b-white"),
    props.rounded ? (isUp.value ? "rounded-t" : "rounded-b") : "",
])

const verticalFadeClass = computed(() => {
    const from = props.tone === "gray" ? "from-gray-50" : "from-white"

    return isUp.value ? `bg-gradient-to-b ${from}` : `bg-gradient-to-t ${from}`
})

const solidClass = computed(() => [
    props.tone === "gray" ? "bg-gray-50" : "bg-white",
    "border-x",
    isLeft.value
        ? (props.tone === "gray" ? "border-l-gray-50" : "border-l-white")
        : (props.tone === "gray" ? "border-r-gray-50" : "border-r-white"),
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
            v-if="visible && isVertical"
            ref="root"
            data-scroll-fade-arrow
            class="pointer-events-none absolute inset-x-0 z-20 flex flex-col"
            :class="[isUp ? '-top-px' : '-bottom-px flex-col-reverse', wrapperClass]">
            <button
                type="button"
                class="pointer-events-auto flex h-7 items-center justify-center border-gray-200 text-gray-500 transition-colors hover:text-gray-800"
                :class="verticalSolidClass"
                :aria-label="ariaLabel"
                @click="emit('click')">
                <FontAwesomeIcon :icon="icon" :class="iconClass" fixed-width aria-hidden="true" />
            </button>
            <span class="h-5 to-transparent" :class="verticalFadeClass" aria-hidden="true" />
            <span v-for="(line, index) in coveredLines" :key="index" class="absolute" :style="line" aria-hidden="true" />
        </div>
        <div
            v-else-if="visible"
            ref="root"
            data-scroll-fade-arrow
            class="pointer-events-none absolute inset-y-0 z-20 flex"
            :class="[isLeft ? '-left-px' : '-right-px flex-row-reverse', wrapperClass]">
            <button
                type="button"
                class="pointer-events-auto flex w-7 items-center justify-center border-gray-200 text-gray-500 transition-colors hover:text-gray-800"
                :class="solidClass"
                :aria-label="isLeft ? ctrans('Scroll left') : ctrans('Scroll right')"
                @click="emit('click')">
                <FontAwesomeIcon :icon="isLeft ? faChevronLeft : faChevronRight" :class="iconClass" fixed-width aria-hidden="true" />
            </button>
            <span class="w-5 to-transparent" :class="fadeClass" aria-hidden="true" />
            <span v-for="(line, index) in coveredLines" :key="index" class="absolute" :style="line" aria-hidden="true" />
        </div>
    </Transition>
</template>
