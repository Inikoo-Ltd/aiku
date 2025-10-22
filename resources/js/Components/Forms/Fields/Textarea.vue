<script setup lang="ts">

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faExclamationCircle, faCheckCircle)

const props = defineProps(['form', 'fieldName', 'options', 'fieldData'])

let type = 'text'
if (props.options !== undefined && props.options.type) {
    type = props.options.type
}

defineOptions({
    inheritAttrs: false
})

const attributes = {
    ...props.fieldData
}
delete attributes.value

</script>


<template>
    <div class="relative">
        <label :for="fieldName" class="block text-sm font-medium"></label>
        <div class="rounded-md shadow-sm">
            <textarea
                v-model.trim="form[fieldName]"
                @update:modelValue="() => form.errors[fieldName] = null"
                :id="fieldName" v-bind="attributes"
                :name="fieldName"
                :rows="attributes.rows || 3"
                class="block w-full rounded-md border-gray-300 placeholder:text-gray-400 shadow-sm focus:ring-indigo-500 sm:text-sm"
                :class="form.errors[fieldName] ? 'errorShake' : ''"

            />
        </div>

        <!-- Section: Counter -->
        <div v-if="fieldData.counter"
            class="grid grid-flow-col text-xs italic text-gray-500 mt-2 space-x-12 justify-start">
            <p class="">
                <!-- {{ pageBody.layout.profile.fields.about.notes }} -->
                Characters: {{ form[fieldName]?.length || 0 }}
            </p>
            <p class="">
                <!-- {{ pageBody.layout.profile.fields.about.notes }} -->
                Words: {{ form[fieldName]?.trim().split(/\s+/).filter(Boolean).length || 0 }}
            </p>
        </div>
    </div>

    <!-- Section: Error & Success -->
    <div>
        <Transition name="spin-to-down">
            <div v-if="form.errors[fieldName] || form.recentlySuccessful"
                class="absolute top-3 right-0 pr-3 flex items-center pointer-events-none">
                <FontAwesomeIcon icon="fas fa-exclamation-circle" v-if="form.errors[fieldName]"
                    class="h-5 w-5 text-red-500" aria-hidden="true" />
                <FontAwesomeIcon icon="fas fa-check-circle" v-if="form.recentlySuccessful"
                    class="mt-1.5  h-5 w-5 text-green-500" aria-hidden="true" />
            </div>
        </Transition>
        
        <Transition name="spin-to-down">
            <p v-if="form.errors[fieldName]" class="mt-2 text-sm text-red-500" :id="fieldName + '_error'">
                *{{ form.errors[fieldName] }}
            </p>
        </Transition>
    </div>
</template>
