<script setup lang="ts">
import { computed } from 'vue'
import InputNumberCssProperty from '@/Components/Workshop/Properties/InputNumberCssProperty.vue'


// Define the expected shape
type CssProperty = {
  value: number | null
  unit: string
}

const emits = defineEmits<{
    (e: 'update:modelValue', value: string | number): void
}>()

// Accepting model from parent — might be undefined or partial
const rawModel = defineModel<Partial<CssProperty>>({
  default: () => ({})
})

// Normalizing the model
const normalizedModel = computed<CssProperty>({
  get() {
    return {
      value: rawModel.value?.value ?? null,
      unit: rawModel.value?.unit ?? 'px',
    }
  },
  set(val) {
    rawModel.value = val
  }
})
</script>

<template>
  <div>
    <InputNumberCssProperty :modelValue="normalizedModel" @update:modelValue="(val)=> emits('update:modelValue',val)" />
  </div>
</template>
