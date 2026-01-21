<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
import { ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { InputNumber } from "primevue"
import InformationIcon from "@/Components/Utils/InformationIcon.vue"

const props = defineProps<{
    form: {
        [key: string]: {
            percentage_off: number
            trigger_item_quantity: number
        } 
    }
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
        currency_code: string
    }
}>()

const emits = defineEmits()


const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
};

const value = ref(setFormValue(props.form, props.fieldName));

watch(value, (newValue) => {
    // Update the form field value when the value ref changes
    updateFormValue(newValue);
    props.form.errors[props.fieldName] = ''
});

const updateFormValue = (newValue) => {
    let target = props.form;
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue);
    } else {
        target[props.fieldName] = newValue;
    }
    emits("update:form", target);
};

// const offerType = 'Category Ordered'
// const offerType = 'Amount AND Order Number'
// const offerType = 'Category Quantity Ordered'
const offerType = props.fieldData.offer.type
</script>


<template>
    <div class="relative max-w-2xl w-full">

        <div v-if="['Amount AND Order Number', 'Category Quantity Ordered'].includes(offerType)" class="xborder-b border-gray-300 border-dashed pb-4 mb-2">
            <!-- <div class="font-bold text-orange-600 text-lg">
                {{ trans("Trigger") }}
            </div> -->

            <div class="xpl-4 space-y-4">
                <div v-if="['Category Quantity Ordered'].includes(offerType) && form[fieldName].hasOwnProperty('trigger_item_quantity')" class="flex flex-col grid-cols-7 gap-x-4">
                    <div class="col-span-3">
                        {{ trans("Minimum quantity") }}
                    </div>
                    <div class="col-span-4">
                        <InputNumber
                            :modelValue="get(form, [fieldName, 'trigger_item_quantity'], 1)"
                            @input="(e) => set(form, [fieldName, 'trigger_item_quantity'], e.value)"
                            inputId="trigger_item_quantity"
                            :min="1"
                            placeholder="Enter a number"
                            :suffix="' ' + (get(form, [fieldName, 'trigger_item_quantity'], 1) > 1 ? trans('items') : trans('item'))"
                        />
                    </div>
                </div>

                <!-- Section: Amounts -->
                <div v-if="['Amount AND Order Number'].includes(offerType) && form[fieldName].hasOwnProperty('trigger_min_amount')" class="flex flex-col">
                    <div class="col-span-3">
                        {{ trans("Min. order amount") }}
                        <InformationIcon information="Minimum of amount of the order" />
                    </div>
                    <div class="col-span-4">
                        <InputNumber
                            :modelValue="get(form, [fieldName, 'trigger_min_amount'], 1)"
                            @input="(e) => set(form, [fieldName, 'trigger_min_amount'], e.value)"
                            inputId="trigger_min_amount"
                            :min="1"
                            placeholder="Enter a number"
                            mode="currency"
                            :currency="props.fieldData?.currency_code || ''"
                            :max-fraction-digits="2"
                        />
                    </div>
                </div>

                <!-- Section: Minimum order -->
                <div v-if="['Amount AND Order Number'].includes(offerType) && form[fieldName].hasOwnProperty('trigger_order_number')" class="flex flex-col">
                    <div class="col-span-3">
                        {{ trans("Min. order") }}
                        <InformationIcon information="The order count required to activate the discount (e.g., 7 = 7th order)" />
                    </div>
                    <div class="col-span-4">
                        <InputNumber
                            :modelValue="get(form, [fieldName, 'trigger_order_number'], 1)"
                            @input="(e) => set(form, [fieldName, 'trigger_order_number'], e.value)"
                            inputId="trigger_order_number"
                            :min="1"
                            placeholder="Enter a number"
                            :suffix="' ' + (get(form, [fieldName, 'trigger_order_number'], 1) > 1 ? trans('orders') : trans('order'))"
                        />
                    </div>
                </div>
            </div>

        </div>

        <div v-if="['Category Ordered', 'Category Quantity Ordered'].includes(offerType) && form[fieldName].hasOwnProperty('percentage_off')" class="w-full">
            <!-- <div class="font-bold xtext-center text-green-600 text-lg">
                {{ trans("Discount") }}
            </div> -->

            <!-- <div class="pl-4"> -->
                <!-- Section: discounts -->
                <div v-if="['Category Ordered', 'Category Quantity Ordered'].includes(offerType) && form[fieldName].hasOwnProperty('percentage_off')" class="flex flex-col">
                
                    <div class="col-span-3">
                        {{ trans("Percentage off") }}
                    </div>
                    <div class="col-span-4">
                        <InputNumber
                            :modelValue="get(form, [fieldName, 'percentage_off'], 0)"
                            @input="(e) => set(form, [fieldName, 'percentage_off'], e.value)"
                            inputId="percentage_off"
                            :min="0"
                            :max="100"
                            suffix="%"
                            placeholder="Enter between 0-100"
                        />
                    </div>
                </div>
            <!-- </div> -->
        </div>
        <!-- {{ form[fieldName] }} -->
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>