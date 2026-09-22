<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, nextTick } from "vue"
import { ctrans } from "@/Composables/useTrans"
import {
    dedupeTargets,
    expandBoxForEditing,
    isInlineEditable,
    isMeasurableBox,
    isResizable,
    labelForEditableKey,
    relativeBox,
    resizeCardFromDrag,
    sortTargetsBySize,
    targetId,
    type EditableTarget,
    type ResizeAxis
} from "@/Composables/useBannerCanvas"

const props = defineProps<{
    stage: HTMLElement | null
    scale?: number
    selectedKey?: string | null
}>()

const emits = defineEmits<{
    (e: "select", payload: { key: string; scope: "slide" | "common"; slideUlid: string | null }): void
    (e: "editText", payload: { key: string; scope: "slide" | "common"; slideUlid: string | null; value: string }): void
    (e: "resize", payload: { modelPath: string; slideUlid: string | null; width: number; height: number }): void
}>()

const targets = ref<EditableTarget[]>([])
const activeSlideUlid = ref<string | null>(null)
const hoveredKey = ref<string | null>(null)
const editingKey = ref<string | null>(null)
const draftText = ref("")
const editingStyle = ref<Record<string, string>>({})
const inputRef = ref<HTMLTextAreaElement | null>(null)

let hiddenNode: HTMLElement | null = null
let resizeObserver: ResizeObserver | null = null
let mutationObserver: MutationObserver | null = null
let measureFrame: number | null = null

const activeSlideElement = (): HTMLElement | null => {
    if (!props.stage) {
        return null
    }

    return props.stage.querySelector(".swiper-slide-active") ?? props.stage.querySelector(".swiper-slide")
}

const restoreHiddenNode = () => {
    if (hiddenNode && hiddenNode.style.visibility === "hidden") {
        hiddenNode.style.visibility = ""
    }

    hiddenNode = null
}

/**
 * The node is hidden rather than removed so the text being typed does not show
 * twice: once in the banner behind and once in the overlay input.
 */
const hideNodeForKey = (key: string) => {
    const node = activeSlideElement()?.querySelector<HTMLElement>(`[data-editable="${key}"]`)

    if (!node || node === hiddenNode) {
        return
    }

    restoreHiddenNode()
    node.style.visibility = "hidden"
    hiddenNode = node
}

const measure = () => {
    const slide = activeSlideElement()

    if (!props.stage || !slide) {
        targets.value = []
        activeSlideUlid.value = null
        return
    }

    activeSlideUlid.value = slide.getAttribute("data-slide-ulid")

    const stageRect = props.stage.getBoundingClientRect()

    const measured = Array.from(slide.querySelectorAll<HTMLElement>("[data-editable]"))
        .map((node) => {
            const key = node.getAttribute("data-editable") as string
            const scope = (node.getAttribute("data-editable-scope") as "slide" | "common") ?? "slide"

            return {
                id: targetId(key, scope),
                key,
                scope,
                label: ctrans(labelForEditableKey(key)),
                isText: isInlineEditable(key),
                isResizable: isResizable(key),
                modelPath: node.getAttribute("data-model-path"),
                text: node.textContent?.trim() ?? "",
                ...relativeBox(node.getBoundingClientRect(), stageRect, props.scale ?? 1)
            }
        })
        .filter((target) => isMeasurableBox(target))

    const next = dedupeTargets(sortTargetsBySize(measured))

    if (JSON.stringify(next) !== JSON.stringify(targets.value)) {
        targets.value = next
    }

    if (editingKey.value) {
        hideNodeForKey(editingKey.value)
    }
}

const scheduleMeasure = () => {
    if (measureFrame !== null) {
        return
    }

    measureFrame = requestAnimationFrame(() => {
        measureFrame = null
        measure()
    })
}

const observeStage = (stage: HTMLElement | null) => {
    resizeObserver?.disconnect()
    mutationObserver?.disconnect()
    resizeObserver = null
    mutationObserver = null

    if (!stage) {
        targets.value = []
        return
    }

    resizeObserver = new ResizeObserver(scheduleMeasure)
    resizeObserver.observe(stage)

    mutationObserver = new MutationObserver(scheduleMeasure)
    mutationObserver.observe(stage, { subtree: true, childList: true, attributes: true, characterData: true })

    scheduleMeasure()
}

watch(() => props.stage, observeStage, { immediate: true, flush: "post" })
watch(() => props.scale, scheduleMeasure)

const selectTarget = (target: EditableTarget) => {
    emits("select", { key: target.key, scope: target.scope, slideUlid: activeSlideUlid.value })
}

/**
 * The editor inherits the look of the text it stands in for, so starting and
 * leaving an edit does not flash a differently styled box over the banner.
 */
const readTextStyle = (node: HTMLElement): Record<string, string> => {
    const style = getComputedStyle(node)

    return {
        fontFamily: style.fontFamily,
        fontSize: style.fontSize,
        fontWeight: style.fontWeight,
        fontStyle: style.fontStyle,
        lineHeight: style.lineHeight,
        letterSpacing: style.letterSpacing,
        textAlign: style.textAlign,
        textTransform: style.textTransform,
        textShadow: style.textShadow,
        color: style.color
    }
}

const startEditing = async (target: EditableTarget) => {
    if (!target.isText) {
        return
    }

    const node = activeSlideElement()?.querySelector<HTMLElement>(`[data-editable="${target.key}"]`)

    if (!node) {
        return
    }

    selectTarget(target)
    editingStyle.value = readTextStyle(node)
    editingKey.value = target.key
    draftText.value = target.text
    hideNodeForKey(target.key)

    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select()
}

const stopEditing = () => {
    editingKey.value = null
    restoreHiddenNode()
    scheduleMeasure()
}

const commitEditing = async () => {
    const target = targets.value.find((item) => item.key === editingKey.value)

    if (!target) {
        return
    }

    if (draftText.value === target.text) {
        stopEditing()
        return
    }

    emits("editText", {
        key: target.key,
        scope: target.scope,
        slideUlid: activeSlideUlid.value,
        value: draftText.value
    })

    // Hold the node hidden until the banner has rendered the new text,
    // otherwise the old text flashes back for a frame on the way out.
    editingKey.value = null
    await nextTick()
    restoreHiddenNode()
    scheduleMeasure()
}

const editingTarget = computed(() => targets.value.find((target) => target.key === editingKey.value) ?? null)

const editingBoxStyle = computed(() => {
    if (!editingTarget.value) {
        return {}
    }

    const stageWidth = props.stage ? props.stage.offsetWidth : 0

    return boxStyle(expandBoxForEditing(editingTarget.value, stageWidth) as EditableTarget)
})

interface ResizeSession {
    modelPath: string
    axis: ResizeAxis
    pointerId: number
    startX: number
    startY: number
    startWidth: number
    startHeight: number
    totalScale: number
    containerWidth: number
}

const resizeSession = ref<ResizeSession | null>(null)
let resizeFrame: number | null = null

const startResize = (target: EditableTarget, axis: ResizeAxis, event: PointerEvent) => {
    const node = activeSlideElement()?.querySelector<HTMLElement>(`[data-editable="${target.key}"]`)
    const container = node?.parentElement

    if (!target.modelPath || !node || !container || !node.offsetWidth || !container.offsetWidth) {
        return
    }

    resizeSession.value = {
        modelPath: target.modelPath,
        axis,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        startWidth: (node.offsetWidth / container.offsetWidth) * 100,
        startHeight: node.offsetHeight,
        // The card carries its own responsive scale on top of the stage scale.
        totalScale: node.getBoundingClientRect().width / node.offsetWidth,
        containerWidth: container.offsetWidth
    }

    ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)
}

const applyResize = (event: PointerEvent) => {
    const session = resizeSession.value

    if (!session || session.pointerId !== event.pointerId) {
        return
    }

    const size = resizeCardFromDrag(
        { width: session.startWidth, height: session.startHeight },
        { x: event.clientX - session.startX, y: event.clientY - session.startY },
        session.axis,
        { totalScale: session.totalScale, containerWidth: session.containerWidth }
    )

    emits("resize", {
        modelPath: session.modelPath,
        slideUlid: activeSlideUlid.value,
        width: size.width,
        height: size.height
    })
}

const onResizeMove = (event: PointerEvent) => {
    if (!resizeSession.value || resizeFrame !== null) {
        return
    }

    resizeFrame = requestAnimationFrame(() => {
        resizeFrame = null
        applyResize(event)
    })
}

const endResize = (event: PointerEvent) => {
    if (resizeFrame !== null) {
        cancelAnimationFrame(resizeFrame)
        resizeFrame = null
    }

    if (resizeSession.value?.pointerId === event.pointerId) {
        applyResize(event)
    }

    resizeSession.value = null
}

const RESIZE_HANDLES: { axis: ResizeAxis; class: string; cursor: string }[] = [
    { axis: "width", class: "top-1/2 -right-1 -translate-y-1/2", cursor: "ew-resize" },
    { axis: "height", class: "left-1/2 -bottom-1 -translate-x-1/2", cursor: "ns-resize" },
    { axis: "both", class: "-right-1 -bottom-1", cursor: "nwse-resize" }
]

const boxStyle = (target: EditableTarget) => ({
    left: `${target.left}px`,
    top: `${target.top}px`,
    width: `${target.width}px`,
    height: `${target.height}px`
})

onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    mutationObserver?.disconnect()

    if (measureFrame !== null) {
        cancelAnimationFrame(measureFrame)
    }

    if (resizeFrame !== null) {
        cancelAnimationFrame(resizeFrame)
    }

    restoreHiddenNode()
})
</script>

<template>
    <div class="absolute inset-0 z-[30] pointer-events-none">
        <div
            v-for="target in targets"
            :key="target.id"
            class="absolute pointer-events-auto cursor-pointer transition-shadow duration-100"
            :class="[
                target.key === selectedKey
                    ? 'ring-2 ring-amber-400'
                    : hoveredKey === target.key
                      ? 'ring-2 ring-amber-300/70'
                      : 'ring-1 ring-transparent'
            ]"
            :style="boxStyle(target)"
            @mouseenter="hoveredKey = target.key"
            @mouseleave="hoveredKey = null"
            @click.stop="selectTarget(target)"
            @dblclick.stop="startEditing(target)"
        >
            <template v-if="target.isResizable && target.modelPath && target.key === selectedKey">
                <div
                    v-for="handle in RESIZE_HANDLES"
                    :key="handle.axis"
                    class="absolute h-2.5 w-2.5 rounded-sm border border-white bg-amber-400 shadow"
                    :class="handle.class"
                    :style="{ cursor: handle.cursor, touchAction: 'none' }"
                    @pointerdown.stop.prevent="startResize(target, handle.axis, $event)"
                    @pointermove="onResizeMove"
                    @pointerup="endResize"
                    @pointercancel="endResize"
                    @click.stop
                    @dblclick.stop
                />
            </template>

            <span
                v-if="target.key === selectedKey || hoveredKey === target.key"
                class="absolute -top-5 left-0 whitespace-nowrap rounded-sm bg-amber-400 px-1 text-[10px] font-medium leading-4 text-gray-800"
            >
                {{ target.label }}
                <span v-if="target.scope === 'common'" class="opacity-70">· {{ ctrans("common") }}</span>
            </span>
        </div>

        <div v-if="editingTarget" class="absolute pointer-events-auto" :style="editingBoxStyle">
            <textarea
                ref="inputRef"
                v-model="draftText"
                rows="1"
                spellcheck="false"
                class="h-full w-full resize-none overflow-hidden bg-transparent p-0 outline-none outline-2 outline-offset-2 outline-dashed outline-amber-400"
                :style="editingStyle"
                @keydown.enter.prevent="commitEditing"
                @keydown.esc.prevent="stopEditing"
                @blur="commitEditing"
            />
        </div>
    </div>
</template>
