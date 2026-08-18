<script setup lang="ts">
import { computed, shallowRef, watch } from 'vue'
import { faInfinity, faPlus, faTrash } from '@far'
import { faMoneyBill, faWeight, faQuestionCircle } from '@fal'
import { faCheckCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import InputNumber from 'primevue/inputnumber'
import Button from '../Elements/Buttons/Button.vue'
import { trans } from 'laravel-vue-i18n'

library.add(faInfinity, faPlus, faTrash, faMoneyBill, faWeight, faQuestionCircle, faCheckCircle)

const props = withDefaults(defineProps<{
  modelValue: {
    type: string
    steps: Array<{ from: number, to: number | string, price: number | string }>
  }
  currency?: {
    code: string
  }
}>(), {})

const emit = defineEmits<{
  (e: 'update:modelValue', value: any): void
}>()

// Available pricing types (aligned with backend)
const options = computed(() => [
  {
    value: 'Step Order Items Net Amount',
    label: trans('Items net amount'),
    icon: faMoneyBill
  },
  {
    value: 'Step Order Estimated Weight',
    label: trans('Weight'),
    icon: faWeight
  },
  {
    value: 'TBC',
    label: trans('To be confirmed'),
    icon: faQuestionCircle
  }
])

const isWeightPricing = computed(() => props.modelValue.type === 'Step Order Estimated Weight')

const currencyInputProps = computed(() => props.currency?.code
  ? { mode: 'currency', currency: props.currency.code }
  : {})

const rangeInputProps = computed(() => isWeightPricing.value
  ? { suffix: ' kg' }
  : currencyInputProps.value)

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

  const followingStep = steps[insertIndex + 1]
  if (followingStep) {
    followingStep.from = newTo
  }

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
  <div class="space-y-5 text-sm">

    <!-- Type Selector -->
    <div>
      <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-500">
        {{ trans('Type') }}
      </label>

      <div role="radiogroup" :aria-label="trans('Type')" class="flex flex-wrap gap-2">
        <button
          v-for="option in options"
          :key="option.value"
          type="button"
          role="radio"
          :aria-checked="modelValue.type === option.value"
          class="group flex flex-1 items-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 py-2 text-left transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400"
          :class="modelValue.type === option.value
            ? 'border-indigo-300 bg-indigo-50/60'
            : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
          @click="emit('update:modelValue', { ...props.modelValue, type: option.value })"
        >
          <FontAwesomeIcon
            :icon="option.icon"
            class="h-3.5 w-3.5 shrink-0"
            :class="modelValue.type === option.value ? 'text-indigo-500' : 'text-gray-400 group-hover:text-gray-500'"
            aria-hidden="true"
          />

          <span
            class="flex-1 text-xs leading-4"
            :class="modelValue.type === option.value ? 'font-medium text-indigo-800' : 'text-gray-700'"
          >
            {{ option.label }}
          </span>

          <FontAwesomeIcon
            v-show="modelValue.type === option.value"
            icon="fas fa-check-circle"
            class="h-3 w-3 shrink-0 text-indigo-500"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>

    <!-- Steps Table -->
    <div v-if="modelValue.type !== 'TBC'" class="rounded-lg border border-gray-200">
      <div class="flex items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-2 rounded-t-lg">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
          {{ isWeightPricing ? trans('Weight steps') : trans('Amount steps') }}
        </div>
        <Button :icon="faPlus" :label="trans('Add Step')" type="create" size="xs" @click="() => addStepBeforeInfinity()" />
      </div>

      <div class="grid grid-cols-12 gap-2 border-b border-gray-200 px-3 py-2 text-xs font-semibold text-gray-500">
        <div class="col-span-3">{{ trans('From') }}</div>
        <div class="col-span-3">{{ trans('To') }}</div>
        <div class="col-span-4">{{ trans('Price') }}</div>
        <div class="col-span-2 pr-1 text-right">{{ trans('Action') }}</div>
      </div>

      <div
        v-for="(item, index) in localSteps"
        :key="index"
        class="grid grid-cols-12 items-center gap-2 px-3 py-2 border-b border-gray-100 last:border-b-0 hover:bg-gray-50/60"
      >
        <!-- From -->
        <div class="col-span-3">
          <InputNumber
            :modelValue="item.from"
            @update:modelValue="val => updateStep(index, 'from', val)"
            v-bind="rangeInputProps"
            inputClass="w-full"
            fluid
          />
        </div>

        <!-- To -->
        <div class="col-span-3 h-full">
          <template v-if="item.to !== 'INF'">
            <InputNumber
              :modelValue="item.to"
              @update:modelValue="val => updateStep(index, 'to', val)"
              v-bind="rangeInputProps"
              inputClass="w-full"
              fluid
            />
          </template>
          <div
            v-else
            v-tooltip="trans('No upper limit')"
            class="flex h-full items-center justify-center gap-1.5 rounded-md border border-dashed border-gray-300 bg-gray-50 py-2 text-gray-500"
          >
            <FontAwesomeIcon :icon="faInfinity" aria-hidden="true" />
          </div>
        </div>

        <!-- Price -->
        <div class="col-span-4 flex items-center gap-2">
          <div
            v-if="item.price === 'TBC'"
            class="flex h-full flex-1 items-center rounded-md border border-dashed border-gray-300 bg-gray-50 px-3 py-2 italic text-gray-500"
          >
            {{ trans('To be confirmed') }}
          </div>
          <InputNumber
            v-else
            :modelValue="item.price"
            @update:modelValue="val => updateStep(index, 'price', val)"
            v-bind="currencyInputProps"
            inputClass="w-full"
            class="flex-1"
            fluid
          />

          <div
            class="cursor-pointer select-none rounded border px-2 py-1 text-xs font-medium transition-colors"
            :class="item.price === 'TBC' ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-500 hover:text-gray-700'"
            :title="trans('To be confirmed: the price is decided later, it is not free')"
            @click="updateStep(index, 'price', item.price === 'TBC' ? 0 : 'TBC')"
          >
            TBC
          </div>
        </div>

        <!-- Action -->
        <div class="col-span-2 flex items-center justify-end pr-1">
          <div
            v-if="item.to !== 'INF'"
            class="cursor-pointer rounded p-1.5 text-red-500 hover:bg-red-50 hover:text-red-700"
            @click="removeStep(index)"
            :title="trans('Remove Step')"
          >
            <FontAwesomeIcon :icon="faTrash" />
          </div>
        </div>
      </div>
    </div>

    <!-- To be confirmed -->
    <div v-else class="flex items-start gap-2 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-3 py-2.5 text-xs text-gray-500">
      <FontAwesomeIcon :icon="faQuestionCircle" class="mt-0.5 shrink-0 text-gray-400" fixed-width aria-hidden="true" />
      <span>{{ trans('No steps are needed, the shipping price of this zone is quoted after the order is placed') }}</span>
    </div>
  </div>
</template>
