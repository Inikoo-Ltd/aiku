<script setup lang="ts">
import { computed } from 'vue'
import IconPicker from '@/Components/Pure/IconPicker.vue'

const emits = defineEmits<{
  (e: 'update:modelValue', value: string | number | string[]): void
}>()

const model = defineModel<string[] | string>()

const props = withDefaults(
  defineProps<{
    iconList?: Array<string | [string, string]>
    listType?: string
    valueType?: string // "fontawesome" | "string" | "svg" | "array"
  }>(),
  {
    valueType: 'array',
    listType: 'extend'
  }
)

// Set fallback default if model is undefined
const resolvedModel = computed({
  get() {
    return model.value ?? ['fal', 'user']
  },
  set(val) {
    model.value = val
  }
})
</script>

<template>
  <div class="flex items-center gap-3 w-full">
    <div class="border-2 rounded-md p-2 w-fit">
      <IconPicker v-model="resolvedModel" v-bind="props" />
    </div>

    <div
      class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 text-sm cursor-not-allowed select-none"
    >
      {{ resolvedModel[1] }}
    </div>
  </div>
</template>

<style scoped></style>
