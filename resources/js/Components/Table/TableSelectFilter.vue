<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-17 09:39
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed } from 'vue'
import Select from 'primevue/select'

const props = defineProps<{
    label: string
    options: Record<string, string>
    value: string | null
    flags?: Record<string, string>
}>()

const emits = defineEmits<{
    (e: 'change', value: string | null): void
}>()

const optionList = computed(() =>
    Object.entries(props.options ?? {}).map(([value, label]) => ({ value, label }))
)

const selectedValue = computed({
    get: () => props.value ?? '',
    set: (newValue: string | null) => emits('change', newValue || null),
})

const flagSource = (optionValue: string) => {
    const countryCode = props.flags?.[optionValue]
    return countryCode ? `/flags/${countryCode.toLowerCase()}.png` : null
}
</script>

<template>
    <div class="flex items-center gap-x-1.5">
        <label class="text-xs text-gray-500 whitespace-nowrap">{{ label }}</label>
        <Select
            v-model="selectedValue"
            :options="optionList"
            optionLabel="label"
            optionValue="value"
            size="small"
            :filter="optionList.length > 10"
            class="min-w-36"
        >
            <template #value="{ value: currentValue }">
                <div class="flex items-center gap-x-2">
                    <img v-if="currentValue && flagSource(currentValue)" :src="flagSource(currentValue)!" :alt="flags?.[currentValue]" class="h-3 w-4 object-cover" />
                    <span>{{ options[currentValue ?? ''] ?? currentValue }}</span>
                </div>
            </template>
            <template #option="{ option }">
                <div class="flex items-center gap-x-2">
                    <img v-if="option.value && flagSource(option.value)" :src="flagSource(option.value)!" :alt="flags?.[option.value]" class="h-3 w-4 object-cover" />
                    <span>{{ option.label }}</span>
                </div>
            </template>
        </Select>
    </div>
</template>
