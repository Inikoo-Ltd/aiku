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
import { set, get } from 'lodash-es'
import ListSelector from "@/Components/ListSelector.vue"
import { routeType } from "@/types/route"
import { watch, ref } from "vue"
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        withQuantity?: boolean
        routeFetch: routeType
        key_quantity? : string
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
    console.log('dfdfdf')
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

</script>
<template>
    <div class="relative">
        <div class="relative">
            <ListSelector 
                :modelValue="value" 
                @update:modelValue="(e)=>updateFormValue(e)"
                :key_quantity="fieldData.key_quantity"
                :withQuantity="fieldData.withQuantity || false" :route-fetch="fieldData.routeFetch" 
                class="mt-4" 
            />
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>