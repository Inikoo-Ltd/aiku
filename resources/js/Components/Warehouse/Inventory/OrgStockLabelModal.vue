<!--
  - Author Louis Perez
  - Created on 23-09-2026-11h-34m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { routeType } from "@/types/route"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

type LabelField = {
    key: string
    label: string
    available: boolean
    checked: boolean
    reason: string | null
}

type LabelLevel = {
    key: string
    label: string
    printable: boolean
}

type LabelOptions = {
    sizes: Record<string, { key: string; label: string }[]>
    default_size: string
    layouts: { key: string; label: string }[]
    fields: Record<string, LabelField[]>
    levels: LabelLevel[]
    custom_text_max_length: number
}

const props = defineProps<{
    isOpen: boolean
    level: string
    labelRoute: routeType
    options: LabelOptions
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const printableLevels = computed<LabelLevel[]>(() => (props.options?.levels ?? []).filter((level) => level.printable))

const activeLevel = ref(props.level)

const fields = computed<LabelField[]>(() => props.options?.fields?.[activeLevel.value] ?? [])

const levelSizes = computed(() => props.options?.sizes?.[activeLevel.value] ?? [])

const selected = ref<Record<string, boolean>>({})
const customText = ref("")
const size = ref<string | undefined>(props.options?.default_size)
const layout = ref("single")

const resetFromOptions = () => {
    selected.value = Object.fromEntries(fields.value.map((field) => [field.key, field.available && field.checked]))

    const sizes = levelSizes.value
    size.value = sizes.some((option) => option.key === props.options?.default_size)
        ? props.options.default_size
        : sizes[0]?.key

    layout.value = "single"
}

watch(() => [props.isOpen, props.level], ([isOpen]) => {
    if (!isOpen) {
        return
    }

    const preferred = printableLevels.value.find((level) => level.key === props.level)
    activeLevel.value = (preferred ?? printableLevels.value[0])?.key ?? props.level
    resetFromOptions()
}, { immediate: true })

watch(activeLevel, resetFromOptions)

const customTextField = computed(() => fields.value.find((field) => field.key === "with_custom_text"))

const pdfUrl = computed(() => {
    const query: Record<string, string> = {
        ...props.labelRoute.parameters,
        level: activeLevel.value,
        layout: layout.value,
        size: size.value,
    }

    fields.value.forEach((field) => {
        query[field.key] = selected.value[field.key] ? "1" : "0"
    })

    if (selected.value.with_custom_text && customText.value.trim()) {
        query.custom_text = customText.value.trim()
    }

    return route(props.labelRoute.name, query)
})

const openPdf = () => {
    window.open(pdfUrl.value, "_blank")
    emits("onClose")
}

const optionClass = (isActive: boolean) => [
    "rounded border px-2 py-1 text-xs transition",
    isActive
        ? "border-[--app-accent] bg-[--app-accent] text-[--app-accent-text]"
        : "border-gray-300 text-gray-600 hover:border-[--app-accent] hover:text-[--app-accent]",
]
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="emits('onClose')" width="w-full max-w-md">
        <div class="flex flex-col gap-4 p-2">
            <div class="flex items-center justify-between gap-3">
                <div class="text-lg font-semibold">
                    {{ activeLevel === "unit" ? ctrans("Unit label") : ctrans("SKO label") }}
                </div>
                <div v-if="printableLevels.length > 1" class="flex gap-1.5">
                    <button
                        v-for="levelOption in printableLevels"
                        :key="levelOption.key"
                        type="button"
                        :class="optionClass(activeLevel === levelOption.key)"
                        @click="activeLevel = levelOption.key">
                        {{ levelOption.label }}
                    </button>
                </div>
            </div>

            <div v-if="!printableLevels.length" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ ctrans("This item has no barcode yet, so there is nothing to print.") }}
            </div>

            <div class="flex flex-col gap-1.5">
                <label
                    v-for="field in fields"
                    :key="field.key"
                    class="flex w-max items-center gap-2 text-sm"
                    :class="field.available ? 'text-gray-700' : 'cursor-not-allowed text-gray-400'">
                    <input
                        type="checkbox"
                        v-model="selected[field.key]"
                        :disabled="!field.available"
                        class="h-4 w-4 rounded border-gray-300 text-[--app-accent] focus:ring-[--app-accent] disabled:opacity-50" />
                    <span v-tooltip="field.reason ? { content: field.reason, placement: 'top' } : undefined">
                        {{ field.label }}
                    </span>
                </label>
            </div>

            <textarea
                v-if="customTextField?.available"
                v-model="customText"
                rows="2"
                :maxlength="options.custom_text_max_length"
                :disabled="!selected.with_custom_text"
                :placeholder="ctrans('Custom text')"
                class="w-full rounded-md border-gray-300 py-1.5 px-2 text-sm focus:border-[--app-accent] focus:ring-[--app-accent] disabled:bg-gray-50 disabled:text-gray-400" />

            <div>
                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Size (mm)") }}
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="sizeOption in levelSizes"
                        :key="sizeOption.key"
                        type="button"
                        :class="optionClass(size === sizeOption.key)"
                        :disabled="layout === 'sheet'"
                        @click="size = sizeOption.key">
                        {{ sizeOption.label }}
                    </button>
                </div>
                <div v-if="layout === 'sheet'" class="mt-1.5 text-xs text-gray-400">
                    {{ ctrans("The A4 sheet is die cut to 63.5 x 29.6, so the size is fixed.") }}
                </div>
            </div>

            <div>
                <div class="mb-1.5 text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Layout") }}
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="layoutOption in options.layouts"
                        :key="layoutOption.key"
                        type="button"
                        :class="optionClass(layout === layoutOption.key)"
                        @click="layout = layoutOption.key">
                        {{ layoutOption.label }}
                    </button>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="emits('onClose')" />
                <Button :label="ctrans('PDF')" icon="fal fa-file-pdf" :disabled="!printableLevels.length" @click="openPdf" />
            </div>
        </div>
    </Modal>
</template>
