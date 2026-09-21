<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch, nextTick } from "vue"
import { ctrans } from "@/Composables/useTrans"
import {
    dedupeTargets,
    isInlineEditable,
    isMeasurableBox,
    labelForEditableKey,
    relativeBox,
    sortTargetsBySize,
    targetId,
    type EditableTarget
} from "@/Composables/useBannerCanvas"

const props = defineProps<{
    stage: HTMLElement | null
    scale?: number
    selectedKey?: string | null
}>()

const emits = defineEmits<{
    (e: "select", payload: { key: string; scope: "slide" | "common"; slideUlid: string | null }): void
    (e: "editText", payload: { key: string; scope: "slide" | "common"; slideUlid: string | null; value: string }): void
}>()

const targets = ref<EditableTarget[]>([])
const activeSlideUlid = ref<string | null>(null)
const hoveredKey = ref<string | null>(null)
const editingKey = ref<string | null>(null)
const draftText = ref("")
const inputRef = ref<HTMLInputElement | null>(null)

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

const startEditing = async (target: EditableTarget) => {
    if (!target.isText) {
        return
    }

    selectTarget(target)
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

const commitEditing = () => {
    const target = targets.value.find((item) => item.key === editingKey.value)

    if (!target) {
        return
    }

    if (draftText.value !== target.text) {
        emits("editText", {
            key: target.key,
            scope: target.scope,
            slideUlid: activeSlideUlid.value,
            value: draftText.value
        })
    }

    stopEditing()
}

const editingTarget = computed(() => targets.value.find((target) => target.key === editingKey.value) ?? null)

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

    restoreHiddenNode()
})
</script>

<template>
    <div class="absolute inset-0 z-[30] pointer-events-none">
        <div
            v-for="target in targets"
            :key="target.id"
            class="absolute pointer-events-auto cursor-pointer"
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
            <span
                v-if="target.key === selectedKey || hoveredKey === target.key"
                class="absolute -top-5 left-0 whitespace-nowrap rounded-sm bg-amber-400 px-1 text-[10px] font-medium leading-4 text-gray-800"
            >
                {{ target.label }}
                <span v-if="target.scope === 'common'" class="opacity-70">· {{ ctrans("common") }}</span>
            </span>
        </div>

        <div v-if="editingTarget" class="absolute pointer-events-auto" :style="boxStyle(editingTarget)">
            <input
                ref="inputRef"
                v-model="draftText"
                class="h-full w-full rounded-sm border-2 border-amber-400 bg-gray-900/70 px-1 text-center text-white outline-none"
                :style="{ fontSize: `${Math.min(editingTarget.height * 0.8, 32)}px` }"
                @keydown.enter.prevent="commitEditing"
                @keydown.esc.prevent="stopEditing"
                @blur="commitEditing"
            />
        </div>
    </div>
</template>
