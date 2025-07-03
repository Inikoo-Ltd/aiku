<script setup lang="ts">
import { computed, toRefs, inject } from 'vue'
import Slider from 'primevue/slider'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'

const props = withDefaults(defineProps<{
  modelValue: Record<string, any> | null,
  name?: string,
  min?: number,
  max?: number
}>(), {
  name: 'price'
})
const layout = inject('layout', layoutStructure)
const emits = defineEmits<{
  (e: 'update:modelValue', value: Record<string, any>): void
}>()

const { modelValue } = toRefs(props)
const minVal = props.min ?? 0
const maxVal = props.max ?? 9999

const minKey = `between[${props.name}_min]`
const maxKey = `between[${props.name}_max]`

// Internal two-way model for the slider [min, max]
const rangeValue = computed({
  get: () => [
    modelValue.value?.[minKey] ?? minVal,
    modelValue.value?.[maxKey] ?? maxVal
  ],
  set: ([newMin, newMax]) => {
    emits('update:modelValue', {
      ...modelValue.value,
      [minKey]: newMin,
      [maxKey]: newMax
    })
  }
})

function updateSingleField(field: string, value: number) {
  emits('update:modelValue', {
    ...modelValue.value,
    [field]: value
  })
}
console.log(layout)
</script>


<template>

  <!-- Range Slider -->
  <div class="space-y-4">
    <!-- Label Min-Max di atas Slider -->
    <div class="flex justify-between text-sm text-gray-600 font-medium">
      <span>Min</span>
      <span>Max</span>
    </div>

    <!-- Slider -->
    <Slider v-model="rangeValue" :min="minVal" :max="maxVal" :range="true" class="w-full my-2" />

    <!-- Input Angka -->
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block mb-1 text-sm text-gray-600">Min Price</label>
        <PureInputNumber :model-value="modelValue?.[minKey]" :min="minVal" class="w-full" placeholder="Min" :suffix="layout?.iris?.currency?.symbol || '€'"
          @update:modelValue="val => updateSingleField(minKey, val)" />
      </div>
      <div>
        <label class="block mb-1 text-sm text-gray-600">Max Price</label>
        <PureInputNumber :model-value="modelValue?.[maxKey]" :max="maxVal" class="w-full" placeholder="Max" :suffix="layout?.iris?.currency?.symbol || '€'"
          @update:modelValue="val => updateSingleField(maxKey, val)" />
      </div>
    </div>
  </div>

</template>
