<script setup lang="ts">
import { computed, toRefs } from 'vue'
import Slider from 'primevue/slider'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'

const props = withDefaults(defineProps<{
  modelValue: Record<string, any> | null,
  name?: string,
  min?: number,
  max?: number
}>(), {
  name: 'price'
})

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

</script>


<template>

    <!-- Range Slider -->
    <Slider
      v-model="rangeValue"
      :min="minVal"
      :max="maxVal"
      :range="true"
      class="w-full my-5"
    />

    <!-- Number Inputs -->
    <div class="grid grid-cols-1 gap-3">
      <PureInputNumber
        :model-value="modelValue?.[minKey]"
        :min="minVal"
        class="w-full"
        placeholder="Min Price"
        suffix="€"
        @update:modelValue="val => updateSingleField(minKey, val)"
      />
      <PureInputNumber
        :model-value="modelValue?.[maxKey]"
        :max="maxVal"
        class="w-full"
        placeholder="Max Price"
        suffix="€"
        @update:modelValue="val => updateSingleField(maxKey, val)"
      />
    </div>
</template>

