<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { ref, watch, computed } from 'vue'
import { isNull, get } from 'lodash-es'
import { faTimes, faCheck } from '@fas'
import { faInfoCircle } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import PureInput from '@/Components/Pure/PureInput.vue'
import InputSwitch from 'primevue/inputswitch'

library.add(faTimes, faCheck, faInfoCircle)

const props = defineProps<{
    form: any
    fieldName: string | string[]
}>()

const emits = defineEmits(['update:form'])

const providerSchemas: Record<string, Array<{ key: string; label: string; type: string }>> = {
    "reviews.io": [
        { key: "url", label: trans("API URL"), type: "text" },
        { key: "store", label: trans("Store"), type: "text" },
        { key: "apikey", label: trans("API Key"), type: "text" },
    ],
    "trust_pilot": [
        { key: "template_id", label: trans("Template ID"), type: "text" },
        { key: "business_unit_id", label: trans("Business Unit ID"), type: "text" },
        { key: "url", label: trans("Review URL"), type: "text" },
        { key: "email", label: trans("Email"), type: "text" },
    ],
    "aiku": [],
}

const getNestedValue = (obj: any, keys: string[]) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const updateFormValue = (newValue: any) => {
    let target = props.form

    if (Array.isArray(props.fieldName)) {
        let obj = target
        const keys = props.fieldName

        keys.slice(0, -1).forEach((key) => {
            if (!obj[key]) obj[key] = {}
            obj = obj[key]
        })

        obj[keys[keys.length - 1]] = newValue
    } else {
        target[props.fieldName] = newValue
    }

    emits("update:form", target, newValue)
}

const setFormValue = (data: any, fieldName: any) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        if (isNull(data[fieldName]) || data[fieldName] === '') {
            return null
        } else {
            return data[fieldName]
        }
    }
}

const initialValue = setFormValue(props.form, props.fieldName) || {}

const provider = ref(initialValue?.provider || '')
const data = ref(initialValue?.data || {})


watch(provider, (newProvider) => {
    const newValue = {
        provider: newProvider,
        data: {}
    }

    data.value = {}
    updateFormValue(newValue)
})

const updateDataField = (key: string, value: any) => {
    data.value = {
        ...data.value,
        [key]: value
    }

    updateFormValue({
        provider: provider.value,
        data: data.value,
    })
}

const enabled = ref(initialValue?.enabled ?? false)

watch(enabled, (val) => {
    updateFormValue({
        provider: provider.value,
        data: data.value,
        enabled: val
    })
})

const approvalRequired = computed({
    get: () => data.value?.approval_required ?? false,
    set: (val: boolean) => updateDataField('approval_required', val),
})

const currentSchema = computed(() => {
    return providerSchemas[provider.value] || []
})

const fieldNameString = computed(() =>
    Array.isArray(props.fieldName) ? props.fieldName.join('.') : props.fieldName
)
</script>

<template>
    <div class="flex flex-col gap-4">

        <div class="flex flex-col gap-1">
            <label class="text-sm">{{ trans('Enable') }}</label>
            <InputSwitch v-model="enabled" />
        </div>


        <!-- AIKU INTERNAL PROVIDER SETTINGS -->
        <div class="flex flex-col gap-3"  :class="{ 'opacity-50 pointer-events-none': !enabled }">
            <div class="flex flex-col gap-1">
                <label class="flex items-center gap-1 text-sm">
                    {{ trans('Require Approval Before Publishing') }}
                    <FontAwesomeIcon icon="fal fa-info-circle" class="opacity-50 hover:opacity-100 cursor-pointer" v-tooltip="trans('When enabled, customer reviews must be approved by an admin before they are published.')" fixed-width aria-hidden="true" />
                </label>
                <InputSwitch v-model="approvalRequired" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="flex items-center gap-1 text-sm">
                    {{ trans('Hours After Dispatch Before Review Is Available') }}
                    <FontAwesomeIcon icon="fal fa-info-circle" class="opacity-50 hover:opacity-100 cursor-pointer" v-tooltip="trans('Number of hours after an order is dispatched before the review menu appears to the customer.')" fixed-width aria-hidden="true" />
                </label>
                <PureInput type="number" :modelValue="data['hours_after_dispatched'] ?? 24"
                    @update:modelValue="updateDataField('hours_after_dispatched', Number($event))" />
            </div>
        </div>


    </div>
    <p v-if="get(form, ['errors', fieldNameString])" class="mt-2 text-sm text-red-600" :id="`${fieldNameString}-error`">
        {{ form.errors[fieldNameString] }}
    </p>
</template>
