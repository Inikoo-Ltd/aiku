<script setup lang="ts">
import { ref, watch } from 'vue'
import { Switch } from '@headlessui/vue'
import { isNull, get } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faCheck } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faTimes, faCheck)

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
        noIcon?: boolean
        suffixImage?: string
    }
}>()

const emits = defineEmits()

const updateFormValue = (newValue) => {
    let target = props.form
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue)
    } else {
        target[props.fieldName] = newValue
    }
    emits("update:form", target,newValue)
}

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        if (isNull(data[fieldName]) || data[fieldName] == ''){
            updateFormValue(false)
            return false
        } 
        else{
            return data[fieldName]
        } 
       
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return false
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName))

watch(value, (newValue) => {
    // Update the form field value when the value ref changes
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ''
})



// console.log(props.form, props.fieldName)
</script>
<template>
    <div>
        <Switch
            v-model="value"
            @update:modelValue="() => form.errors[fieldName] = null"
            class="pr-1 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white/75"
            :class="[
                value ? 'bg-indigo-500' : 'bg-indigo-100',
                form.errors[fieldName] ? 'errorShake' : ''
            ]" 
        >
            <span aria-hidden="true" :class="value ? 'translate-x-6 bg-white ' : 'translate-x-0 bg-gray-50'"
                class="flex items-center justify-center pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition">
                <template v-if="!fieldData.noIcon">
                    <FontAwesomeIcon v-if="value" icon='fal fa-check' class='text-sm text-green-500' fixed-width aria-hidden='true' />
                    <FontAwesomeIcon v-else icon='fal fa-times' class='text-sm text-red-500' fixed-width aria-hidden='true' />
                </template>
            </span>
        </Switch>

        <slot v-if="fieldData.suffixImage" name="suffix-image">
            <img :src="fieldData.suffixImage" class="inline-block h-8 w-8 ml-2  object-cover" />
        </slot>

        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>