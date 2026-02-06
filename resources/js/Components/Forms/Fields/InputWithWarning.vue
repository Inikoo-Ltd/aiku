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
import { faWarning } from "@fortawesome/free-solid-svg-icons"

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
        showWarning?: boolean
        warningTitle?: string
        warningBody?: string
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
</script>
<template>
    <div class="relative">
        <div class="relative">
            <PureInput
                v-model="value"
                :inputName="fieldName"
                :readonly="fieldData?.readonly"
                :type="fieldData?.type ?? 'text'"
                :placeholder="fieldData?.placeholder"
                :maxlength="fieldData?.maxLength"
                :copyButton="fieldData?.copyButton"
                :isError="!!form.errors[fieldName]"
                :class="
                    !!form.errors[fieldName] ? 'errorShake' : ''
                "
            >
                <!-- Icon: Error, Success, Loading -->
                <template #stateIcon>
                    <div class="mr-2 h-full flex items-center pointer-events-none">
                        <FontAwesomeIcon v-if="get(form, ['errors', `${fieldName}`])" icon="fas fa-exclamation-circle"
                            class="h-5 w-5 text-red-500" aria-hidden="true" />
                        <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle"
                            class="h-5 w-5 text-green-500" aria-hidden="true" />
                        <!-- <FontAwesomeIcon v-if="form.processing" icon="fad fa-spinner-third" class="h-5 w-5 animate-spin" /> -->
                    </div>
                </template>
            </PureInput>

            <div v-if="fieldData?.showWarning" class="w-full grid grid-cols-1 py-2 mt-2 bg-yellow-200 rounded-md border-yellow-400 border text-yellow-600">
                <span v-if="fieldData?.warningTitle" class="font-medium px-3 text-xs" :class="[fieldData?.warningBody ? 'border-b border-yellow-400 pb-1' : '']">
                    <FontAwesomeIcon :icon="faWarning"/> {{ fieldData?.warningTitle }}
                </span>
                <span v-if="fieldData?.warningBody" class="font-medium px-3 text-xs" :class="[fieldData?.warningTitle ? 'pt-1' : '']">
                    {{ fieldData?.warningBody }}
                </span>
            </div>

        </div>

        <!-- Counter: Letters and Words -->
        <div v-if="props.options?.counter"
            class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start tabular-nums">
            <p class="">{{ trans('Characters') }}: {{ form[fieldName]?.length ?? 0 }}</p>
            <p class="">
                {{ trans('Words') }}: {{ form[fieldName]?.trim().split(/\s+/).filter(Boolean).length ?? 0 }}
            </p>
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>