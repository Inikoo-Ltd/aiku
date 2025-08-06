<script setup lang="ts">
import { ref, computed } from 'vue'
import { faInfinity, faPlus, faTrash } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import PureMultiselect from './PureMultiselect.vue'
import PureInputNumber from './PureInputNumber.vue'

library.add(faInfinity, faPlus, faTrash)

const props = withDefaults(defineProps<{
  modelValue: {
    type: string
    steps: Array<{ from: number, to: number | string, price: number }>
  }
}>(), {})

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
}>()

const options = ref([
  { value: 'Step Order Estimated Weight', label: 'Step Order Estimated Weight' },
  { value: 'shipping_zone', label: 'Shipping Zone' },
])

// Sort step, ensure 'INF' always at the end
const sortedSteps = computed(() => {
  const steps = [...props.modelValue.steps]
  return steps.sort((a, b) => {
    if (a.to === 'INF') return 1
    if (b.to === 'INF') return -1
    return (a.to as number) - (b.to as number)
  })
})

// Add new step before INF
function addStepBeforeInfinity() {
  const steps = [...props.modelValue.steps]
  const lastStep = steps.find(step => step.to === 'INF')
  const insertIndex = lastStep ? steps.indexOf(lastStep) : steps.length

  const newFrom = insertIndex > 0 ? (steps[insertIndex - 1]?.to || 0) : 0

  steps.splice(insertIndex, 0, {
    from: newFrom,
    to: newFrom + 1,
    price: 0,
  })

  emit('update:modelValue', { ...props.modelValue, steps })
}

// Optional: remove step (except INF row)
function removeStep(index: number) {
  const steps = [...props.modelValue.steps]
  if (steps[index].to === 'INF') return // protect INF row
  steps.splice(index, 1)
  emit('update:modelValue', { ...props.modelValue, steps })
}
</script>

<template>
  <div class="space-y-4 text-sm">
    <!-- Type Selector -->
    <div>
      <label class="block mb-1 font-medium text-gray-700">Type</label>
      <PureMultiselect
        :modelValue="modelValue.type"
        :placeholder="'Select Type'"
        :options="options"
        :required="true"
        caret
        label="label"
        valueProp="value"
        mode="single"
      />
    </div>

    <!-- Steps Table -->
    <div>
      <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-gray-500 border-b pb-1">
        <div class="col-span-3">From</div>
        <div class="col-span-3">To</div>
        <div class="col-span-4">Price</div>
        <div class="col-span-2 text-center">Action</div>
      </div>

      <div
        v-for="(item, index) in sortedSteps"
        :key="index"
        class="grid grid-cols-12 gap-2 py-2 border-b items-center"
      >
        <!-- From -->
        <PureInputNumber
          v-model="item.from"
          placeholder="From"
          class="col-span-3"
        />

        <!-- To -->
        <div class="col-span-3 h-full">
          <template v-if="item.to !== 'INF'">
            <PureInputNumber
              v-model="item.to"
              placeholder="To"
            />
          </template>
          <div
            v-else
            class="flex justify-center items-center h-full border border-gray-300 rounded-md text-gray-600"
          >
            <FontAwesomeIcon :icon="faInfinity" />
          </div>
        </div>

        <!-- Price -->
        <PureInputNumber
          v-model="item.price"
          placeholder="Price"
          class="col-span-4"
        />

        <!-- Action -->
        <div class="col-span-2 flex items-center justify-center gap-2">
          <button
            v-if="item.to !== 'INF'"
            class="text-red-500 hover:text-red-700"
            @click="removeStep(index)"
            title="Remove Step"
          >
            <FontAwesomeIcon :icon="faTrash" />
          </button>
        </div>
      </div>

      <!-- Add Button -->
      <div class="pt-3 text-right">
        <button
          class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800"
          @click="addStepBeforeInfinity"
        >
          <FontAwesomeIcon :icon="faPlus" />
          Add Step
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* You can style further if needed */
</style>
