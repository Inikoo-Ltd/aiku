<script setup lang="ts">
import { ref, watch, shallowRef } from 'vue'
import { faInfinity, faPlus, faTrash } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InputNumber from 'primevue/inputnumber'
import Button from '../Elements/Buttons/Button.vue'
import PureMultiselect from './PureMultiselect.vue'

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

// Available pricing types (aligned with backend)
const options = ref([
  { value: 'Step Order Items Net Amount', label: 'Items net amount' },
  { value: 'Step Order Estimated Weight', label: 'Weight' },
  { value: 'TBC', label: 'To be confirmed' }
])

// Deep copy for local editing
const localSteps = shallowRef(
  JSON.parse(JSON.stringify(props.modelValue.steps))
)

// Sync if modelValue.steps changes from outside
watch(() => props.modelValue.steps, (newVal) => {
  localSteps.value = JSON.parse(JSON.stringify(newVal))
}, { deep: true })

function updateStep(index: number, field: 'from' | 'to' | 'price', value: number | string) {
  localSteps.value[index][field] = value

  const updatedSteps = localSteps.value.map(step => ({ ...step }))
  emit('update:modelValue', {
    ...props.modelValue,
    steps: updatedSteps
  })
}

function addStepBeforeInfinity() {
  const steps = [...localSteps.value.map(step => ({ ...step }))]
  const infIndex = steps.findIndex(step => step.to === 'INF')
  const insertIndex = infIndex !== -1 ? infIndex : steps.length

  const previous = insertIndex > 0 ? steps[insertIndex - 1] : null
  const newFrom = previous ? (typeof previous.to === 'number' ? previous.to : 0) : 0
  const newTo = newFrom + 1

  steps.splice(insertIndex, 0, { from: newFrom, to: newTo, price: 0 })
  localSteps.value = steps
  emit('update:modelValue', {
    ...props.modelValue,
    steps: steps
  })
}

function removeStep(index: number) {
  const steps = [...localSteps.value]
  if (steps[index].to === 'INF') return
  steps.splice(index, 1)
  localSteps.value = steps
  emit('update:modelValue', {
    ...props.modelValue,
    steps: steps
  })
}
</script>

<template>
  <div class="space-y-4 text-sm">

    <div class="flex justify-end mb-2">
      <Button :icon="faPlus" label="Add Step" type="create" size="xs"  @click="()=>addStepBeforeInfinity()" />
    </div>
    <!-- Type Selector -->
    <div>
      <label class="block mb-1 font-medium text-gray-700">Type</label>
      <PureMultiselect
        :modelValue="modelValue.type"
        @update:modelValue="type => emit('update:modelValue', { ...props.modelValue, type })"
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
    <div v-if="modelValue.type !== 'TBC'">
      <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-gray-500 border-b pb-1">
        <div class="col-span-3">From</div>
        <div class="col-span-3">To</div>
        <div class="col-span-4">Price</div>
        <div class="col-span-2 text-center">Action</div>
      </div>

      <div
        v-for="(item, index) in localSteps"
        :key="index"
        class="grid grid-cols-12 gap-2 py-2 border-b items-center"
      >
        <!-- From -->
        <div class="col-span-3">
          <InputNumber
            :modelValue="item.from"
            @update:modelValue="val => updateStep(index, 'from', val)"
            inputClass="w-full"
          />
        </div>

        <!-- To -->
        <div class="col-span-3 h-full">
          <template v-if="item.to !== 'INF'">
            <InputNumber
              :modelValue="item.to"
              @update:modelValue="val => updateStep(index, 'to', val)"
              inputClass="w-full"
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
        <div class="col-span-4">
          <InputNumber
            :modelValue="item.price"
            @update:modelValue="val => updateStep(index, 'price', val)"
            inputClass="w-full"
          />
        </div>

        <!-- Action -->
        <div class="col-span-2 flex items-center justify-center gap-2">
          <div
            v-if="item.to !== 'INF'"
            class="text-red-500 hover:text-red-700"
            @click="removeStep(index)"
            title="Remove Step"
          >
            <FontAwesomeIcon :icon="faTrash" />
          </div>
        </div>
      </div>

      <!-- Add Button -->
      <!-- <div class="pt-3 text-right">
        <div
          class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800"
          @click="()=>addStepBeforeInfinity()"
        >
          <FontAwesomeIcon :icon="faPlus" />
          Add Step
        </div>
      </div> -->
    </div>
  </div>
</template>
