<script setup lang="ts">
import { ref, watch } from 'vue'
import { Switch } from '@headlessui/vue'
import { isNull, get as getLodash } from 'lodash-es'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimes, faCheck } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import ModalConfirmation from '@/Components/Utils/ModalConfirmation.vue'
import { trans } from 'laravel-vue-i18n'
import { faWarning } from '@fortawesome/free-solid-svg-icons'
import Button from '@/Components/Elements/Buttons/Button.vue'
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
        warningText?: string
        warnTitle?: string
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
    console.log(value.value, props);
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ''
})

const clearAndWarn = () => {
    props.form.errors[props.fieldName] = null;
    if(!props.fieldData?.warningText) return false;
    return true;
}

</script>
<template>
    <div>
        <ModalConfirmation :title="fieldData?.warnTitle ?? trans('Are you sure you want to proceed?')" :description="fieldData?.warningText ?? trans('Enabling this would have direct consequences')" hideCancel>
            <template #default="{ isOpenModal, changeModel }">
                <Switch
                    v-model="value"
                    @update:modelValue="() => {
                        if(clearAndWarn())  {
                            value = !value;
                            changeModel()
                        }
                    }"
                    class="pr-1 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
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
            </template>
            <template #btn-yes="{ closeModal }">
                <Button :label="trans('Confirm')" @click="() => {value = !value; closeModal()}" type="negative" :icon="faWarning" />
            </template>
        </ModalConfirmation>

        <slot v-if="fieldData.suffixImage" name="suffix-image">
            <img :src="fieldData.suffixImage" class="inline-block h-8 w-8 ml-2  object-cover" />
        </slot>

        <p v-if="getLodash(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>