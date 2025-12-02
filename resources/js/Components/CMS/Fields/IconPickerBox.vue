<script setup lang="ts">
import { computed } from 'vue'
import IconPicker from '@/Components/Pure/IconPicker.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faTimes)

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
    // return model.value ?? ['fal', 'user']
    return model.value ?? null
  },
  set(val) {
    model.value = val
  }
})
</script>

<template>
  <div class="flex items-center gap-3 w-full">
    <div>
      <IconPicker v-model="resolvedModel" v-bind="props"  class="border-2 rounded p-2 w-fit cursor-pointer hover:bg-gray-200"/>
    </div>

    <div
      class="flex-1 px-3 py-2 border border-gray-300 rounded bg-gray-100 text-gray-500 text-sm cursor-not-allowed select-none"
    >
      {{ resolvedModel?.[1] ?? 'No icon' }}
    </div>

    <div @click="resolvedModel = null"
      class="cursor-pointer h-full py-1 px-1"
      :class="resolvedModel ? 'text-red-400/80 hover:text-red-600' : 'pointer-events-none opacity-50'"
    >
      <FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
    </div>
  </div>
</template>

<style scoped></style>
