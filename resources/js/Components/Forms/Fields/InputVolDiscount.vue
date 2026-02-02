<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { get, cloneDeep } from 'lodash-es'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import { trans } from 'laravel-vue-i18n'
import { ref, onMounted } from 'vue'
import Toggle from '@/Components/Pure/Toggle.vue'

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
  form: any
  fieldName: string
  fieldData?: {
    initial_value: object
  }
}>()

const ready = ref(false)

const isInvalidValue = (val: any) => {
  return val == null || Array.isArray(val) || typeof val !== 'object'
}

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

</script>

<template>
  <div v-if="ready" class="grid grid-cols-2 gap-6 mb-5">
    <!-- ACTIVE (span 2 columns) -->
    <div class="flex flex-col col-span-2">
      <label class="mb-1 text-sm font-medium text-gray-700">
        {{ trans('Active Offers') }}
      </label>
      <Toggle v-model="form[fieldName].volume_discount.active" />
    </div>

    <!-- ITEM QUANTITY -->
    <div class="flex flex-col">
      <label class="mb-1 text-sm font-medium text-gray-700">
        {{ trans('Item Quantity') }}
      </label>
      <PureInputNumber v-model="form[fieldName].volume_discount.item_quantity" :min-value="0" />
    </div>

    <!-- DISCOUNT -->
    <div class="flex flex-col">
      <label class="mb-1 text-sm font-medium text-gray-700">
        {{ trans('Discount') }}
      </label>
      <PureInputNumber v-model="form[fieldName].volume_discount.percentage_off" :min-value="0" :max-value="100"
        suffix="%" />
    </div>
  </div>

  <p v-if="get(form, ['errors', fieldName])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
    {{ form.errors[fieldName] }}
  </p>
</template>

<style scoped>
:deep(input[type='number']) {
  padding: 0.5rem !important;
}
</style>
