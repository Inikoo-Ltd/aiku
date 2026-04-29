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
import { ref, watch, onBeforeMount  } from 'vue'
import { trans } from 'laravel-vue-i18n'
library.add(faExclamationCircle, faCheckCircle)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])

let defaultCountry = null
if (props.options !== undefined && props.options.defaultCountry) {
    defaultCountry = props.options.defaultCountry
}

const phone = ref(props.form[props.fieldName] || '')
const phoneError = ref('')
const isInitialInvalid = ref(false)

onBeforeMount(() => {
    if (phone.value && !phone.value.startsWith('+')) {
        phoneError.value = trans('Invalid phone number format')
        isInitialInvalid.value = true
    }
})

const onValidate = (data: any) => {
    const raw = phone.value?.trim()

    if (isInitialInvalid.value) {
        if (!raw) {
            return
        }

        if (raw.startsWith('+') && raw.replace(/\D/g, '').length > 4) {
            isInitialInvalid.value = false
        } else {
            return
        }
    }

    if (!raw) {
        phoneError.value = ''
        return
    }

    const digits = raw.replace(/\D/g, '')

    if (digits.length <= 4) {
        phoneError.value = ''
        return
    }

    phoneError.value = data?.valid ? '' : trans('Invalid phone number format')
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
                :class="phoneError ? 'text-red-500 -bottom-5' : 'text-gray-400 -bottom-5'"
            >
                <template v-if="phoneError">
                    <b>{{ props.fieldData.value }}</b>
                    <FontAwesomeIcon icon="fas fa-exclamation-circle" class="h-3 w-3  ml-2 mr-1 text-red-500" aria-hidden="true"/>
                    <span> {{ phoneError }}</span>
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

