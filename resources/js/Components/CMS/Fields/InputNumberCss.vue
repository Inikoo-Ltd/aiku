<script setup lang="ts">
import { computed } from 'vue'
import InputNumberCssProperty from '@/Components/Workshop/Properties/InputNumberCssProperty.vue'

// Define the expected shape
type CssProperty = {
  value: number | null
  unit: string
}

// Default structure
const defaultModel: CssProperty = { value: null, unit: 'px' }

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
    <InputNumberCssProperty v-model="normalizedModel" />
  </div>
</template>
