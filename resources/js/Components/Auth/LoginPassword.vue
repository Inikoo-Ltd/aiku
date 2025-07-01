<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->


<script setup lang="ts">
import { ref } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle, faEye, faEyeSlash } from '@fas'
import { faSpinnerThird } from '@fad'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
library.add(faExclamationCircle, faCheckCircle, faEye, faEyeSlash, faSpinnerThird)

const props = withDefaults(defineProps<{
    'form': any
    'fieldName': string
    'showProcessing'?: boolean
    'placeholder'?: string
    'options'?: {} | []
    'fieldData'?: {} | []
}>(), {
    showProcessing: true
})

const handleChange = (form) => {
    if (form.fieldType === 'edit') {
        form.clearErrors()
    } else {
        form.clearErrors(props.fieldName)
    }
}

const showPassword = ref(true);

</script>

<template>
    <div class="w-full relative rounded-md shadow-sm">
        <div class="flex" :class="form.errors[fieldName] ? 'errorShake' : ''">
            <input v-bind="$attrs" @input="handleChange(form)" v-model="form[fieldName]" :type="showPassword ? 'password' : 'text'"  autocomplete="off"
                :placeholder="(props.placeholder ? trans(props.placeholder) : '')" class="text-gray-700 placeholder-gray-400 shadow-sm focus:ring-gray-500 focus:border-gray-500 w-full border-gray-300 rounded-l-md" />
            <button type="button" @click="showPassword = !showPassword" :id="'show-password-' +  fieldName"
                class="w-min px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-none focus:ring-1 focus:ring-gray-500 focus:border-gray-500">
                <FontAwesomeIcon aria-hidden="true" class="h-5 w-5 text-gray-400" :icon="showPassword ? 'fas fa-eye' : 'fas fa-eye-slash' " />
            </button>
        </div>

        <!-- Icon: Error, Success, Loading -->
        <div class="absolute inset-y-0 right-11 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon v-if="form.errors[fieldName]" icon="fas fa-exclamation-circle" class="h-5 w-5 text-red-500" aria-hidden="true" />
            <FontAwesomeIcon v-if="form.recentlySuccessful" icon="fas fa-check-circle" class="h-5 w-5 text-green-500" aria-hidden="true" />
            <FontAwesomeIcon v-if="form.processing && showProcessing" icon="fad fa-spinner-third" class="h-5 w-5 animate-spin"/>
        </div>
    </div>
    <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="password-error">{{ form.errors[fieldName] }}</p>
</template>


