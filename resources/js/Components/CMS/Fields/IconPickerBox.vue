<script setup lang="ts">
import IconPicker from '@/Components/Pure/IconPicker.vue'

const emits = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
}>()

const model = defineModel()

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
</script>

<template>
  <div class="flex items-center gap-3 w-full">
    <div class="border-2 rounded-md p-2 w-fit">
      <IconPicker v-model="model" v-bind="props" />
    </div>

    <div
      class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-100 text-gray-500 text-sm cursor-not-allowed select-none"
    >
      {{ model[1] }}
    </div>
  </div>
</template>

<style scoped></style>
