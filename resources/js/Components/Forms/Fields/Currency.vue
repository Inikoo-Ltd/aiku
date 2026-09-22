<template>
    <div class="">
        <div class="relative">
            <Multiselect
                v-model="form[fieldName]"
                :options="props.options"
                :class="{ 'pr-8': form.errors[fieldName] || form.recentlySuccessful }"
                :placeholder="props.fieldData.placeholder ?? 'Select your currency'"
                :searchable="true"
                :canClear="!props.fieldData.required"
                :canDeselect="!props.fieldData.required"
            />
            <div v-if="form.errors[fieldName] || form.recentlySuccessful"
                class="absolute inset-y-2/4 right-0 pr-3 flex items-center pointer-events-none bg-red-500">
                <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]" class="h-5 w-5 text-red-500"
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful"
                    class="mt-1.5  h-5 w-5 text-green-500" fixed-width aria-hidden="true" />
            </div>
        </div>
        <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-600" id="email-error">{{ form.errors[fieldName] }}</p>
    </div>
</template>

<script setup lang="ts">
import Multiselect from '@vueform/multiselect'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExclamationCircle, faCheckCircle);

const props = defineProps<{
    'form',
    'fieldName',
    'options',
    fieldData?: {
        placeholder: string,
        required: boolean,
} }>()

// Note:
// Value of the currency (form[fieldName]) is a number
// The value of option Afghan Afghani (AFN) is 1
</script>

<style src="@vueform/multiselect/themes/default.css"></style>
