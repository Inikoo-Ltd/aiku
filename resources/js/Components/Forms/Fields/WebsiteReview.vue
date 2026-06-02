<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { ref, watch, computed } from 'vue'
import { isNull, get } from 'lodash-es'
import { faTimes, faCheck } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
import PureInput from '@/Components/Pure/PureInput.vue'
import InputSwitch from 'primevue/inputswitch'

library.add(faTimes, faCheck)

const props = defineProps<{
    form: any
    fieldName: string | string[]
}>()

const emits = defineEmits(['update:form'])

const providerSchemas: Record<string, Array<{ key: string; label: string; type: string }>> = {
    "reviews.io": [
        { key: "url", label: "API URL", type: "text" },
        { key: "store", label: "Store", type: "text" },
        { key: "apikey", label: "API Key", type: "text" },
    ],
    "trust_pilot": [
        { key: "template_id", label: "Template ID", type: "text" },
        { key: "business_unit_id", label: "Business Unit ID", type: "text" },
        { key: "url", label: "Review URL", type: "text" },
        { key: "email", label: "Email", type: "text" },
    ],
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


watch(data, (newData) => {
    updateFormValue({
        provider: provider.value,
        data: newData
    })
}, { deep: true })

const updateDataField = (key: string, value: any) => {
    data.value = {
        ...data.value,
        [key]: value
    }
}

const enabled = ref(initialValue?.enabled ?? false)

watch(enabled, (val) => {
    updateFormValue({
        provider: provider.value,
        data: data.value,
        enabled: val
    })
})

const currentSchema = computed(() => {
    return providerSchemas[provider.value] || []
})
</script>

<template>
    <div class="flex flex-col gap-4">

        <div class="flex flex-col gap-1">
            <label class="text-sm">{{ trans('Enable') }}</label>
            <InputSwitch v-model="enabled" />
        </div>

        <!-- PROVIDER SELECT -->
        <div  class="flex flex-col gap-1" :class="{ 'opacity-50 pointer-events-none': !enabled }">
            <label class="text-sm">{{ trans('Provider') }}</label>
            <select v-model="provider" class="border rounded px-3 py-2">
                <option disabled value="">{{ trans('Select Provider') }}</option>
                <option value="reviews.io">reviews.io</option>
                <option value="trust_pilot">trust_pilot</option>
            </select>
        </div>

        <!-- DYNAMIC FIELDS -->
        <div v-if="provider" class="flex flex-col gap-3"  :class="{ 'opacity-50 pointer-events-none': !enabled }">
            <div v-for="field in currentSchema" :key="field.key" class="flex flex-col gap-1">
                <label class="text-xs">
                    {{ field.label }}
                </label>

                <PureInput :type="field.type" :modelValue="data[field.key] || ''"
                    @update:modelValue="updateDataField(field.key, $event)" />
            </div>
        </div>

    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>