<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->


<script setup lang="ts">
import { ref, defineExpose } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle, faEye, faEyeSlash } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExclamationCircle, faCheckCircle, faEye, faEyeSlash)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])

const handleChange = (form) => {
    props.form.errors[props.fieldName] = null
    if (form.fieldType === 'edit') {
        form.clearErrors()
    }
}

// let type = 'text'
// if (props.options !== undefined && props.options.type) {
//     type = props.options.type;
// }

const showPassword = ref(true);

defineExpose({
    showPassword
})

</script>

<template>
    <div class="relative rounded-md shadow-sm"
        :class="form.errors[fieldName] ? 'errorShake' : ''"
    >
        <div class="flex">
            <input @input="handleChange(form)" v-model="form[fieldName]" :type="showPassword ? 'password' : 'text'"
                autocomplete="off" :placeholder="fieldData.placeholder"
                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 w-full border-gray-300 rounded-l-md" />
                <div v-if="form.errors[fieldName] || form.recentlySuccessful"
            class="absolute inset-y-0 right-11 pr-3 flex items-center pointer-events-none">
            <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]" class="h-5 w-5 text-red-500"
                aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful"
                class="mt-1.5  h-5 w-5 text-green-500" aria-hidden="true" />

        </div>
            <button type="button" @click="showPassword = !showPassword"
                class="w-min px-3 py-2 border border-gray-300 text-sm font-medium rounded-r-md text-gray-700 bg-gray-50 hover:bg-gray-100 focus:outline-hidden focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                <font-awesome-icon aria-hidden="true" class="h-5 w-5 text-gray-400"
                    :icon="showPassword ? 'fas fa-eye' : 'fas fa-eye-slash'" />
            </button>
        </div>
    </div>
    <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="email-error">{{ form.errors[fieldName] }}</p>
</template>
