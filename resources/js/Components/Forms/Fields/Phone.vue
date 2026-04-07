<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:52:06 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">

import { faExclamationCircle, faCheckCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { VueTelInput } from 'vue-tel-input'
import 'vue-tel-input/vue-tel-input.css'
import { ref, watch } from 'vue'
import { trans } from 'laravel-vue-i18n'
library.add(faExclamationCircle, faCheckCircle)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])

let defaultCountry = null
if (props.options !== undefined && props.options.defaultCountry) {
    defaultCountry = props.options.defaultCountry
}

const phone = ref(props.form[props.fieldName] || '')
const phoneError = ref('')

const onValidate = (data: any) => {
    if (!phone.value) {
        phoneError.value = ''
        return
    }
    
    if (data?.number) {
        phoneError.value = data.valid ? '' : 'Invalid phone number format'
        return
    }

    if (!phone.value.startsWith('+')) {
        phoneError.value = 'Invalid phone number format'
        return
    }

    if (!data.valid) {
        phoneError.value = 'Invalid phone number format'
        return
    }
    phoneError.value = ''
}

watch(phone, (val) => {
    props.form[props.fieldName] = val
})
</script>

<template>
    <div class="relative rounded-md">
    
        <div class="relative">
            <VueTelInput
                v-if="form && fieldName"
                v-model="phone"
                @validate="onValidate"
                :defaultCountry="defaultCountry"
                :styleClasses="[
                    form.errors[fieldName] ? 'errorShake' : '',
                    'ring-1 ring-gray-300 focus-within:shadow-none focus-within:ring-2 focus-within:ring-gray-500 rounded-md'
                ]"
                :inputOptions="{
                    showDialCode: true,
                    placeholder: fieldData.placeholder || trans('Enter a phone number'),
                    styleClasses: 'placeholder:text-gray-400 rounded-r-lg qwezxc focus:border-none focus:ring-0'
                }"
                mode="international"
                :autoFormat="true"
                :validCharactersOnly="true"
            />
            <p
                v-if="!form.errors[fieldName]"
                class="absolute left-0 text-xs"
                :class="phoneError ? 'text-red-500 -bottom-10' : 'text-gray-400 -bottom-5'"
            >
                <template v-if="phoneError">
                    Example: +44 7400 123456 (UK) | {{ phoneError }} | {{ props.fieldData.value }}
                </template>
                <template v-else>
                    Example: +44 7400 123456 (UK) | {{ props.fieldData.value }}
                </template>
            </p>
        </div>

        <div v-if="form.errors[fieldName] || form.recentlySuccessful " class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]" class="h-5 w-5 text-red-500" aria-hidden="true"/>
            <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful" class="mt-1.5  h-5 w-5 text-green-500" aria-hidden="true"/>
        </div>
    </div>
    <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="email-error">{{ form.errors[fieldName] }}</p>

</template>

