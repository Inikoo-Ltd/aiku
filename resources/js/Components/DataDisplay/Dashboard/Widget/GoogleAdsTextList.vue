<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

library.add(faPlus, faTimes)

/**
 * One box per headline, description or keyword, each counted against Google's own limit while it is
 * typed. Enter starts the next one, and a pasted list of lines is spread over as many boxes, so a
 * keyword list copied from elsewhere still goes in at once.
 */
const props = defineProps<{
    id: string
    name: string
    label: string
    addLabel: string
    maxLength: number
    maxItems: number
    errors?: Record<string, string>
}>()

const emit = defineEmits<{ (e: "edited"): void }>()

const rows = defineModel<string[]>({ required: true })

const inputs = ref<HTMLInputElement[]>([])

onMounted(() => {
    if (!rows.value.length) {
        rows.value.push("")
    }
})

const filledCount = computed(() => rows.value.filter((row) => row.trim() !== "").length)
const isFull = computed(() => rows.value.length >= props.maxItems)

const overBy = (value: string) => value.trim().length - props.maxLength

/* The server numbers only the boxes that were sent, and empty ones are not, so its "headlines.2" is
   the third filled box rather than the third box on screen. */
const sentIndex = (index: number) => rows.value.slice(0, index).filter((row) => row.trim() !== "").length

const errorAt = (index: number): string | undefined => {
    const value = rows.value[index] ?? ""

    if (overBy(value) > 0) {
        return ctrans(":count characters too long", { count: String(overBy(value)) })
    }

    if (value.trim() === "") {
        return undefined
    }

    return props.errors?.[`${props.name}.${sentIndex(index)}`]
}

const listError = computed(() => props.errors?.[props.name])

const focusRow = async (index: number) => {
    await nextTick()
    inputs.value[index]?.focus()
}

const addAfter = (index: number) => {
    if (isFull.value) {
        return
    }

    rows.value.splice(index + 1, 0, "")
    emit("edited")
    focusRow(index + 1)
}

const remove = (index: number) => {
    if (rows.value.length === 1) {
        rows.value[0] = ""
    } else {
        rows.value.splice(index, 1)
    }

    emit("edited")
    focusRow(Math.max(0, index - 1))
}

const removeIfEmpty = (index: number, event: KeyboardEvent) => {
    if (rows.value[index] === "" && rows.value.length > 1) {
        event.preventDefault()
        remove(index)
    }
}

const pasteLines = (index: number, event: ClipboardEvent) => {
    const pasted = event.clipboardData?.getData("text") ?? ""
    const lines = pasted.split(/\r?\n/).map((line) => line.trim()).filter(Boolean)

    if (lines.length < 2) {
        return
    }

    event.preventDefault()

    const room = props.maxItems - rows.value.length + 1
    const placed = lines.slice(0, Math.max(1, room))

    rows.value.splice(index, 1, ...placed)
    emit("edited")
    focusRow(index + placed.length - 1)
}
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-2">
            <span :id="`${id}-label`" class="text-xs text-gray-500">{{ label }}</span>
            <span class="text-xs tabular-nums text-gray-400">{{ filledCount }} / {{ maxItems }}</span>
        </div>

        <ul class="mt-1 space-y-1.5" :aria-labelledby="`${id}-label`">
            <li v-for="(value, index) in rows" :key="index">
                <div class="flex items-center gap-1">
                    <div class="relative flex-1">
                        <input
                            :id="`${id}-${index}`"
                            :ref="(element) => (inputs[index] = element as HTMLInputElement)"
                            v-model="rows[index]"
                            type="text"
                            :aria-label="`${label} ${index + 1}`"
                            :aria-invalid="!!errorAt(index)"
                            :aria-describedby="errorAt(index) ? `${id}-${index}-error` : undefined"
                            class="w-full rounded-md pr-14 text-sm"
                            :class="errorAt(index)
                                ? 'border-[#d03b3b] focus:border-[#d03b3b] focus:ring-[#d03b3b]'
                                : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'"
                            @input="emit('edited')"
                            @keydown.enter.prevent="addAfter(index)"
                            @keydown.backspace="removeIfEmpty(index, $event)"
                            @paste="pasteLines(index, $event)" />
                        <span
                            class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-xs tabular-nums"
                            :class="overBy(value) > 0 ? 'text-[#d03b3b]' : 'text-gray-400'"
                            aria-hidden="true">
                            {{ value.trim().length }}/{{ maxLength }}
                        </span>
                    </div>

                    <button
                        type="button"
                        :aria-label="ctrans('Remove :item', { item: `${label} ${index + 1}` })"
                        class="rounded p-1.5 text-gray-400 transition hover:text-[#d03b3b] focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        @click="remove(index)">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </button>
                </div>

                <p v-if="errorAt(index)" :id="`${id}-${index}-error`" class="mt-0.5 text-xs text-[#d03b3b]">{{ errorAt(index) }}</p>
            </li>
        </ul>

        <p v-if="listError" class="mt-1 text-xs text-[#d03b3b]">{{ listError }}</p>

        <button
            type="button"
            :disabled="isFull"
            class="mt-2 inline-flex items-center gap-1.5 rounded px-1 py-0.5 text-xs text-indigo-600 underline-offset-2 transition hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 disabled:cursor-not-allowed disabled:text-gray-400 disabled:no-underline"
            @click="addAfter(rows.length - 1)">
            <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
            {{ isFull ? ctrans("Google allows :max", { max: String(maxItems) }) : addLabel }}
        </button>
    </div>
</template>
