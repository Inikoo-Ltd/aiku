<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faAlignLeft, faAlignCenter, faAlignRight } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { ref, watch } from "vue"

library.add(faAlignLeft, faAlignCenter, faAlignRight)

const props = defineProps<{
  modelValue: string
  fieldData?: {
    options: {
      label: string
      value: string
      icon: string | string[]
    }[]
  }
}>()

const emit = defineEmits(['update:modelValue'])

const value = ref(props.modelValue)

watch(() => props.modelValue, (v) => {
  if (v !== value.value) value.value = v
})

watch(value, (v) => {
  emit('update:modelValue', v)
})
</script>

<template>
<div class="py-1">
  <div class="flex gap-x-2">
    <div
      v-for="option in fieldData?.options"
      :key="option.value"
      @click="value = option.value"
      class="flex items-center justify-center bg-gray-100 rounded p-2 ring-1 ring-gray-300 cursor-pointer"
      :class="value === option.value ? 'bg-gray-300' : 'hover:bg-gray-200'"
    >
      <FontAwesomeIcon :icon="option.icon" />
    </div>
  </div>
</div>
</template>
