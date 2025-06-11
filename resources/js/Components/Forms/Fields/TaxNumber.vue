<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { set, get } from 'lodash-es'
import { checkVAT, countries } from 'jsvat-next';
import { ref, watch } from "vue"
import { debounce } from "lodash-es"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    refForms?: any
    fieldData?: {
        country: Array<string>
    }
}>()
 
const emits = defineEmits()

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName] ? data[fieldName] : { value: '' }
    }
}

const getNestedValue = (obj: Object, keys: Array<string>) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName))
const vatValidationResult = ref<string | null>(null)

// Fungsi validasi VAT
const validateVAT = (vat: string) => {
    if (!vat.value) {
        vatValidationResult.value = null;
        set(props.form, ['errors', props.fieldName], '');
        return;
    }

    const validation = checkVAT(vat.value, countries);
    vatValidationResult.value = validation.isValid ? "Valid VAT" : "Invalid VAT";

    // Handle invalid VAT
    if (!validation.isValid) {
        set(props.form, ['errors', props.fieldName], 'Invalid VAT number.');
        props.form.reset();
        return;
    }

    // Handle country mismatch
    if (props.fieldData?.country && validation.country?.isoCode.short) {
        if (validation.country.isoCode.short !== props.fieldData.country) {
            set(props.form, ['errors', props.fieldName], 'VAT number does not match with the address.');
            props.form.reset();
            return;
        }
    }

    // Valid VAT and no mismatch, update the form value
    updateFormValue(validation);
    set(props.form, ['errors', props.fieldName], '');
};


// Watch dengan debounce
const debouncedValidation = debounce((newValue: string) => {
    validateVAT(newValue)
}, 500)

const updateFormValue = (newValue) => {
    let target = props.form;
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue);
    } else {
        target[props.fieldName] = newValue;
    }
    emits("update:form", target);
};

const updateVat = (e) => {
    debouncedValidation(value.value)
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <PureInput v-model="value.value" @update:model-value="updateVat"/>
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>
