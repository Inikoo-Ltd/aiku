<script setup lang="ts">
import { computed, toRefs, inject } from 'vue'
import Slider from 'primevue/slider'
import InputNumber from 'primevue/inputnumber'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { trans } from 'laravel-vue-i18n'

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
const maxVal = props.max ?? 100 // disesuaikan agar realistis untuk harga

const minKey = `between[${props.name}_min]`
const maxKey = `between[${props.name}_max]`

// Jaga agar slider dan input tetap sinkron dengan angka murni
const rangeValue = computed({
  get: () => [
    Number(modelValue.value?.[minKey] ?? minVal),
    Number(modelValue.value?.[maxKey] ?? maxVal)
  ],
  set: ([newMin, newMax]) => {
    emits('update:modelValue', {
      ...modelValue.value,
      [minKey]: Number(newMin),
      [maxKey]: Number(newMax)
    })
  }
})

function updateSingleField(field: string, value: number | null) {
  emits('update:modelValue', {
    ...modelValue.value,
    [field]: Number(value ?? 0)
  })
}
</script>

<template>
  <div class="space-y-4">
    <!-- Labels -->
    <div class="flex justify-between text-sm text-gray-600 font-medium">
      <span>{{ trans('Min') }}</span>
      <span>{{ trans('Max') }}</span>
    </div>

    <!-- Slider -->
    <Slider
      v-model="rangeValue"
      :min="minVal"
      :max="maxVal"
      :step="1"
      :range="true"
      class="w-full my-2"
    />

    <!-- Input Fields -->
    <div class="grid grid-cols-2 gap-4">
      <div class="flex flex-col">
        <label class="block mb-1 text-sm text-gray-600">{{ trans('Min') }}</label>
        <InputNumber
          :model-value="modelValue?.[minKey]"
          :min="minVal"
          :max="maxVal"
          mode="currency"
          :currency="layout?.iris?.currency?.code || 'EUR'"
          :locale="layout?.iris?.locale || 'en-US'"
          :minFractionDigits="2"
          :maxFractionDigits="2"
          :step="100"
          inputClass="!w-full text-sm"
          class="w-full"
          @update:modelValue="val => updateSingleField(minKey, val)"
        />
      </div>

      <div class="flex flex-col">
        <label class="block mb-1 text-sm text-gray-600">{{ trans('Max') }}</label>
        <InputNumber
          :model-value="modelValue?.[maxKey]"
          :min="minVal"
          :max="maxVal"
          mode="currency"
          :currency="layout?.iris?.currency?.code || 'EUR'"
          :locale="layout?.iris?.locale || 'en-US'"
          :minFractionDigits="2"
          :maxFractionDigits="2"
          :step="100"
          inputClass="!w-full text-sm"
          class="w-full"
          @update:modelValue="val => updateSingleField(maxKey, val)"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.p-inputnumber,
.p-inputnumber-input {
  width: 100% !important;
  box-sizing: border-box;
}
</style>
