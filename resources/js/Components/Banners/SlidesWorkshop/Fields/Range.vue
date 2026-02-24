<script setup lang="ts">
import { computed } from "vue"

const props = defineProps<{
  modelValue: number
  fieldName?: string
  fieldData?: {
    placeholder: string
    readonly: boolean
    copyButton: boolean
    timeRange: {
      max: number
      min: number
      step: number
      range: string[]
    }
  }
  counter?: boolean
}>()

const emit = defineEmits<{
  (e: "update:modelValue", value: number): void
}>()


const value = computed({
  get: () => {
    if (!props.modelValue) return 0
    return props.modelValue / 1000
  },
  set: (v: number) => {
    emit("update:modelValue", v * 1000)
  }
})
</script>

<template>
  <div class="flex flex-col space-y-2 p-2 w-full">
    <p class="text-gray-600">
      Duration: <span class="font-bold">{{ value }}</span> seconds
    </p>

    <input
      v-model="value"
      type="range"
      class="w-full range accent-amber-400 hover:accent-amber-300"
      :min="fieldData?.timeRange.min"
      :max="fieldData?.timeRange.max"
      :step="fieldData?.timeRange.step"
    />

    <ul class="flex justify-between w-full px-2.5">
      <li
        v-for="item in fieldData?.timeRange.range"
        :key="item"
        class="flex justify-center relative"
      >
        <span class="absolute">{{ item }}</span>
      </li>
    </ul>
  </div>
</template>

<style scoped></style>
