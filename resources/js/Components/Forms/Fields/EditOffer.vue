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
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
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
    <div class="relative">

        <div class="max-w-2xl space-y-3">
            <div>
                Trigger
            </div>

            <div v-if="['Category Quantity Ordered'].includes(offerType)" class="grid grid-cols-7 gap-x-4 gap-y-2">
                <div class="col-span-3">
                    Minimum quantity
                </div>

                <div class="col-span-4">
                    <PureInput
                        v-model="value"
                        :inputName="fieldName"
                        :readonly="fieldData?.readonly"
                        :type="fieldData?.type ?? 'text'"
                        :placeholder="fieldData?.placeholder"
                    />
                </div>
            </div>

            <!-- Section: Amounts -->
            <div v-if="['Amount AND Order Number'].includes(offerType)" class="grid grid-cols-7 gap-x-4 gap-y-2">
                <div class="col-span-3">
                    Order amount
                    <InformationIcon information="Minimum of amount of the order" />
                </div>

                <div class="col-span-4">
                    <InputNumber
                        vxmodel="value"
                        :modelValue="5"
                        inputId="minmax"
                        :min="0"
                        placeholder="Enter between 0-100"
                    />
                </div>
            </div>

            <!-- Section: Minimum order -->
            <div v-if="['Amount AND Order Number'].includes(offerType)" class="grid grid-cols-7 gap-x-4 gap-y-2">
                <div class="col-span-3">
                    Min. order
                    <InformationIcon information="The order count required to activate the discount (e.g., 7 = 7th order)" />
                </div>

                <div class="col-span-4">
                    <InputNumber
                        vxmodel="value"
                        :modelValue="5"
                        inputId="minmax"
                        :min="1"
                        placeholder="Enter between 0-100"
                    />
                </div>
            </div>

            <!-- Section: discounts -->
            <div v-if="['Category Ordered', 'Category Quantity Ordered'].includes(offerType)" class="grid grid-cols-7 gap-x-4 gap-y-2 text-green-500">
                
                <div class="col-span-3">
                    Discounts
                </div>

                <div class="col-span-4">
                    <InputNumber
                        v-model="form[fieldName]['percentage_off']"
                        inputId="percentage_off"
                        :min="0"
                        :max="100"
                        suffix="%"
                        placeholder="Enter between 0-100"
                    />
                </div>
            </div>
        </div>
    
    </div>
    
    <div class="relative">
        <pre>{{ form[fieldName] }}</pre>
    </div>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>