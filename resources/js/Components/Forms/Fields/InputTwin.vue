<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { get } from 'lodash-es'
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        fields: object
    }
}>()

const emits = defineEmits()


console.log('twin',props)
</script>
<template>
    <div 
        v-for="(value, index) in form[fieldName]" 
        :key="index"
        class="flex items-start gap-6 mb-5"
    >
        <div
            v-for="(field, findex) in fieldData.fields"
            :key="findex"
            class="flex flex-col min-w-[120px]"
        >
            <label
                v-if="field.label"
                class="mb-1 text-sm font-medium text-gray-700"
            >
                {{ field.label }}
            </label>

            <PureInputNumber
                v-model="value[field.key]"
                v-bind="field"
                class="p-0"
            />
        </div>
    </div>

    <p
        v-if="get(form, ['errors', fieldName])"
        class="mt-2 text-sm text-red-600"
        :id="`${fieldName}-error`"
    >
        {{ form.errors[fieldName] }}
    </p>
</template>

<style scoped>
v-deep(input[type=number]) {
    padding-top: 0.5rem !important;
    padding-right: 0.5rem !important;
    padding-bottom: 0.5rem !important;
    padding-left: 0.5rem !important;
}
</style>
