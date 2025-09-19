<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import { set, get, debounce } from 'lodash-es'
import { checkVAT, countries } from 'jsvat-next';
import { ref } from "vue"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    refForms?: any
    fieldData?: any
}>()

const emits = defineEmits()

const setFormValue = (data: Object, fieldName: string) => {
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

// Helper function to get the actual value with conditional access
const getActualValue = (valueObj: any): string => {
    if (!valueObj) return ''

    // Priority 1: Check form data structure first (maintain backward compatibility)

    // Case 1: value.value.number exists (nested object structure)
    if (valueObj.value && typeof valueObj.value === 'object' && valueObj.value.number !== undefined) {
        return valueObj.value.number
    }

    // Case 2: value.value is a string
    if (valueObj.value && typeof valueObj.value === 'string') {
        return valueObj.value
    }

    // Case 3: direct string value
    if (typeof valueObj === 'string') {
        return valueObj
    }

    // Priority 2: Fallback to fieldData if form data is empty/invalid
    if (props.fieldData?.value?.number && (!valueObj || !valueObj.value)) {
        return props.fieldData.value.number
    }

    // Default fallback
    return valueObj.value || ''
}

// Helper function to set the actual value with conditional structure
const setActualValue = (valueObj: any, newValue: string): any => {
    if (!valueObj) {
        return { value: newValue }
    }

    // Maintain existing structure patterns (backward compatibility)

    // Case 1: if original structure has value.value.number
    if (valueObj.value && typeof valueObj.value === 'object' && 'number' in valueObj.value) {
        return {
            ...valueObj,
            value: {
                ...valueObj.value,
                number: newValue
            }
        }
    }

    // Case 2: if original structure has value.value as string
    if (valueObj.value !== undefined && typeof valueObj.value === 'string') {
        return {
            ...valueObj,
            value: newValue
        }
    }

    // Case 3: Check if we should create fieldData-like structure
    // Only if fieldData exists and form data seems empty
    if (props.fieldData?.value?.number && (!valueObj.value || valueObj.value === '')) {
        return {
            value: {
                number: newValue,
                // Preserve other fieldData properties if they exist
                ...(props.fieldData.value.id && { id: props.fieldData.value.id }),
                ...(props.fieldData.value.type && { type: props.fieldData.value.type }),
                ...(props.fieldData.value.country_id && { country_id: props.fieldData.value.country_id }),
                ...(props.fieldData.value.status && { status: 'pending' }), // Reset status on new input
                valid: false // Reset validation on new input
            }
        }
    }

    // Case 4: direct value (fallback)
    return { value: newValue }
}

const value = ref(setFormValue(props.form, props.fieldName))
const vatValidationResult = ref<string | null>(null)

const validateVAT = (vatInput: any) => {
    const vatNumber = getActualValue(vatInput)

    if (!vatNumber) {
        vatValidationResult.value = null;
        set(props.form, ['errors', props.fieldName], '');
       /*  return; */
    }

    const validation = checkVAT(vatNumber, countries);
    vatValidationResult.value = validation.isValid ? trans("Valid tax number") : trans("Invalid tax number");

    // Handle invalid VAT
    if (!validation.isValid) {
        set(props.form, ['errors', props.fieldName], trans('Invalid VAT number'));
        // props.form.reset();
        return updateFormValue(validation);; 
    }

    // Valid VAT and no mismatch, update the form value
    updateFormValue(validation);
    set(props.form, ['errors', props.fieldName], '');
};

const debouncedValidation = debounce((newValue: any) => {
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

const updateVat = (newInputValue: string) => {
    // Update the value ref with the new input while preserving structure
    value.value = setActualValue(value.value, newInputValue)
    debouncedValidation(value.value)
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <PureInput :model-value="getActualValue(value)" @update:model-value="updateVat" />
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>