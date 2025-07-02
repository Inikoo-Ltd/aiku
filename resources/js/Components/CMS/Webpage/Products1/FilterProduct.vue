<script setup lang="ts">
import { defineProps, defineEmits } from 'vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { blueprint } from './BlueprintFilter'
import { debounce } from 'lodash-es'

// Props
const props = defineProps<{
  modelValue: Record<string, any>
  productCategory: number
}>()

// Emits
const emit = defineEmits(['update:modelValue'])

// Debounced update function to avoid unnecessary emit bursts
const debouncedUpdate = debounce((val: Record<string, any>) => {
  // Only update the nested `data`, keep same reference to avoid rerender
  props.modelValue.data = val
  emit('update:modelValue', props.modelValue)
}, 400)

// Handler when SideEditor emits changes
const updateValue = (val: Record<string, any>) => {
  debouncedUpdate(val)
}
</script>

<template>
  <aside class="w-full lg:w-64">
    <h3 class="font-medium mb-3">Filters</h3>

    <SideEditor
      :blueprint="blueprint(productCategory).blueprint"
      :modelValue="modelValue.data"
      @update:modelValue="updateValue"
    />
  </aside>
</template>

<style scoped>
::v-deep(.p-accordionheader) {
  padding: 0.65rem;
}

::v-deep(.p-accordioncontent-content) {
  padding: 0.5rem;
}

::v-deep(.multiselect-options) {
  margin-left: 0rem !important;
}
</style>
