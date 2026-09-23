<script setup lang="ts">
import { watch, onBeforeUnmount } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBold, faItalic, faUnderline, faStrikethrough, faListUl, faListOl } from "@fortawesome/free-solid-svg-icons"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    textarea?: HTMLTextAreaElement | null
    allowUnderline?: boolean
}>()

/**
 * execCommand keeps the edit on the browser's undo stack and fires the input event that
 * v-model listens to. Where it is refused the text is still changed, only not undoable.
 */
const replaceRange = (el: HTMLTextAreaElement, start: number, end: number, text: string) => {
    el.focus()
    el.setSelectionRange(start, end)

    if (!document.execCommand("insertText", false, text)) {
        el.setRangeText(text, start, end, "end")
        el.dispatchEvent(new Event("input", { bubbles: true }))
    }
}

/**
 * A marker only takes effect when it touches text, so the whitespace a double click picks
 * up is left outside it. Clicking again on already marked text takes the marker off.
 */
const toggleMarker = (marker: string) => {
    const el = props.textarea
    if (!el) return

    let start = el.selectionStart
    let end = el.selectionEnd

    while (start < end && /\s/.test(el.value[start])) start++
    while (end > start && /\s/.test(el.value[end - 1])) end--

    const selected = el.value.slice(start, end)
    const length = marker.length

    if (el.value.slice(start - length, start) === marker && el.value.slice(end, end + length) === marker) {
        replaceRange(el, start - length, end + length, selected)
        el.setSelectionRange(start - length, end - length)
        return
    }

    replaceRange(el, start, end, marker + selected + marker)
    el.setSelectionRange(start + length, end + length)
}

const toggleList = (numbered: boolean) => {
    const el = props.textarea
    if (!el) return

    const start = el.value.lastIndexOf("\n", el.selectionStart - 1) + 1
    const lineEnd = el.value.indexOf("\n", el.selectionEnd)
    const end = lineEnd === -1 ? el.value.length : lineEnd

    const lines = el.value.slice(start, end).split("\n")
    const prefix = numbered ? /^\d+\. / : /^• /
    const isList = lines.every((line) => !line.trim() || prefix.test(line))

    let number = 0
    const block = lines
        .map((line) => {
            if (isList) return line.replace(prefix, "")
            if (!line.trim()) return line
            number++
            return (numbered ? `${number}. ` : "• ") + line.replace(/^(• |\d+\. )/, "")
        })
        .join("\n")

    replaceRange(el, start, end, block)
}

const shortcuts: Record<string, string> = { b: "*", i: "_", u: "__" }

const onKeydown = (event: KeyboardEvent) => {
    if (!(event.metaKey || event.ctrlKey) || event.shiftKey || event.altKey) return

    const marker = shortcuts[event.key.toLowerCase()]
    if (!marker || (marker === "__" && !props.allowUnderline)) return

    event.preventDefault()
    toggleMarker(marker)
}

watch(
    () => props.textarea,
    (el, previous) => {
        previous?.removeEventListener("keydown", onKeydown)
        el?.addEventListener("keydown", onKeydown)
    },
    { immediate: true }
)

onBeforeUnmount(() => props.textarea?.removeEventListener("keydown", onKeydown))
</script>

<template>
    <div class="flex items-center gap-0.5">
        <button type="button" @mousedown.prevent @click="toggleMarker('*')"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Bold') + ' (Ctrl+B)'" :aria-label="ctrans('Bold')">
            <FontAwesomeIcon :icon="faBold" class="text-xs" fixed-width />
        </button>
        <button type="button" @mousedown.prevent @click="toggleMarker('_')"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Italic') + ' (Ctrl+I)'" :aria-label="ctrans('Italic')">
            <FontAwesomeIcon :icon="faItalic" class="text-xs" fixed-width />
        </button>
        <button v-if="allowUnderline" type="button" @mousedown.prevent @click="toggleMarker('__')"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Underline') + ' (Ctrl+U)'" :aria-label="ctrans('Underline')">
            <FontAwesomeIcon :icon="faUnderline" class="text-xs" fixed-width />
        </button>
        <button type="button" @mousedown.prevent @click="toggleMarker('~')"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Strikethrough')" :aria-label="ctrans('Strikethrough')">
            <FontAwesomeIcon :icon="faStrikethrough" class="text-xs" fixed-width />
        </button>
        <button type="button" @mousedown.prevent @click="toggleList(false)"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Bullet list')" :aria-label="ctrans('Bullet list')">
            <FontAwesomeIcon :icon="faListUl" class="text-xs" fixed-width />
        </button>
        <button type="button" @mousedown.prevent @click="toggleList(true)"
            class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500"
            v-tooltip="ctrans('Numbered list')" :aria-label="ctrans('Numbered list')">
            <FontAwesomeIcon :icon="faListOl" class="text-xs" fixed-width />
        </button>
    </div>
</template>
