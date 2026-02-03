<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { faTrash } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { get, cloneDeep } from 'lodash-es'
import { ref, onMounted, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
  form: any
  fieldName: string
  fieldData?: {
    initial_value: object
  }
}>()

const ready = ref(false)

/**
 * Check invalid initial value
 */
const isInvalidValue = (val: any) => {
  return val == null || Array.isArray(val) || typeof val !== 'object'
}

/**
 * Factory: create volume_discount ONLY when needed
 */
const createVolumeDiscount = () => ({
  item_quantity: 0,
  percentage_off: 0,
})

/**
 * Init on mount
 */
onMounted(() => {
  const current = props.form[props.fieldName]

  if (isInvalidValue(current) && props.fieldData?.initial_value) {
    const value = cloneDeep(props.fieldData.initial_value)
    props.form[props.fieldName] = value
    props.form.defaults(props.fieldName, value)
    props.form.reset()
  }

  ready.value = true
})

/**
 * Computed v-models (NULL SAFE)
 */
const itemQuantity = computed({
  get() {
    return props.form[props.fieldName].volume_discount?.item_quantity ?? null
  },
  set(val) {
    if (!props.form[props.fieldName].volume_discount) {
      props.form[props.fieldName].volume_discount = createVolumeDiscount()
    }
    props.form[props.fieldName].volume_discount.item_quantity = val
  },
})

const percentageOff = computed({
  get() {
    return props.form[props.fieldName].volume_discount?.percentage_off ?? null
  },
  set(val) {
    if (!props.form[props.fieldName].volume_discount) {
      props.form[props.fieldName].volume_discount = createVolumeDiscount()
    }
    props.form[props.fieldName].volume_discount.percentage_off = val
  },
})

/**
 * Delete → send volume_discount: null
 */
const removeVolumeDiscount = () => {
  props.form[props.fieldName].volume_discount = null
}
</script>

<template>
  <div v-if="ready" class="grid grid-cols-3 gap-6 mb-5 items-end">
    <!-- ITEM QUANTITY -->
    <div class="flex flex-col">
      <label class="mb-1 text-sm font-medium text-gray-700">
        {{ trans('Item Quantity') }}
      </label>
      <PureInputNumber v-model="itemQuantity" :min-value="0" class="w-full" />
    </div>

    <!-- DISCOUNT -->
    <div class="flex flex-col">
      <label class="mb-1 text-sm font-medium text-gray-700">
        {{ trans('Discount') }}
      </label>
      <PureInputNumber v-model="percentageOff" :min-value="0" :max-value="100" suffix="%" class="w-full" />
    </div>

    <!-- ACTION -->
    <div class="flex justify-start" v-if="itemQuantity && percentageOff">
      <Button type="negative" :icon="faTrash" class="h-[42px] w-[42px] flex items-center justify-center"
        @click="removeVolumeDiscount" />
    </div>
  </div>

  <!-- ERROR -->
  <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
    {{ form.errors[fieldName] }}
  </p>
</template>

<style scoped>
:deep(input[type='number']) {
  padding: 0.5rem !important;
}
</style>
