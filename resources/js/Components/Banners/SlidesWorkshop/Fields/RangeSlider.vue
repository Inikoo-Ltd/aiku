<script setup lang="ts">
import { computed } from "vue"

const props = defineProps<{
    modelValue: number
    fieldData?: {
        label?: string
        range?: {
            min: number
            max: number
            step: number
        }
    }
}>()

const emit = defineEmits<{
    (e: "update:modelValue", value: number): void
}>()

const value = computed({
    get: () => props.modelValue ?? props.fieldData?.range?.min ?? 0,
    set: (v: number) => emit("update:modelValue", v)
})
</script>

<template>
    <div class="flex flex-col space-y-2 p-2 w-full">
        <p class="text-gray-600">
            {{ fieldData?.label }}:
            <span class="font-bold">{{ value }} px</span>
        </p>

        <input v-model="value" type="range" class="w-full range accent-amber-400 hover:accent-amber-300"
            :min="fieldData?.range?.min" :max="fieldData?.range?.max" :step="fieldData?.range?.step" />
    </div>
</template>