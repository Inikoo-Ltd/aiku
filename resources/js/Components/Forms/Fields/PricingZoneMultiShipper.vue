<script setup lang="ts">
import { faTrash } from '@fal'
import { faPlus } from '@far'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { set, get } from 'lodash-es'
import { ref, computed, watch } from "vue"
import { trans } from 'laravel-vue-i18n'
import PurePricingZone from "@/Components/Pure/PurePricingZone.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
library.add(faTrash, faPlus)
defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: {
        shippers: Array<{ id: number, name: string, code: string }>
    }
    fieldData?: {
        currency?: { code: string }
    }
}>()

const emits = defineEmits()

const setFormValue = (data: Object, fieldName: String) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName]
    }
}

const getNestedValue = (obj: Object, keys: Array) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

const value = ref(setFormValue(props.form, props.fieldName) || [])

const updateFormValue = (newValue) => {
    let target = props.form
    if (Array.isArray(props.fieldName)) {
        set(target, props.fieldName, newValue)
    } else {
        target[props.fieldName] = newValue
    }
    emits("update:form", target)
}

watch(value, (newValue) => {
    updateFormValue(newValue)
    props.form.errors[props.fieldName] = ''
}, { deep: true })

const shippers = computed(() => props.options?.shippers ?? [])

const availableShippers = computed(() =>
    shippers.value.filter(shipper => !value.value.some(entry => entry.shipper_id === shipper.id))
)

const newShipperId = ref(null)

const shipperName = (shipperId: number) => shippers.value.find(shipper => shipper.id === shipperId)?.name ?? shipperId

const addShipper = () => {
    if (!newShipperId.value) {
        return
    }
    value.value = [
        ...value.value,
        { shipper_id: newShipperId.value, type: 'Step Order Items Net Amount', steps: [{ from: 0, to: 'INF', price: 0 }] }
    ]
    newShipperId.value = null
}

const removeShipper = (index: number) => {
    value.value = value.value.filter((_, i) => i !== index)
}

const updateEntry = (index: number, newEntry: any) => {
    value.value = value.value.map((entry, i) => i === index ? newEntry : entry)
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="(entry, index) in value"
            :key="entry.shipper_id"
            class="border rounded-md p-3 space-y-2"
        >
            <div class="flex items-center justify-between">
                <div class="font-medium text-gray-700">{{ shipperName(entry.shipper_id) }}</div>
                <div class="text-red-500 hover:text-red-700 cursor-pointer" @click="removeShipper(index)">
                    <FontAwesomeIcon :icon="faTrash" />
                </div>
            </div>
            <PurePricingZone
                :modelValue="entry"
                @update:modelValue="newEntry => updateEntry(index, newEntry)"
                :currency="fieldData?.currency"
            />
        </div>

        <div class="flex items-center gap-2">
            <PureMultiselect
                v-model="newShipperId"
                :options="availableShippers"
                :placeholder="trans('Add shipper')"
                label="name"
                valueProp="id"
                mode="single"
                caret
                class="flex-1"
            />
            <Button :icon="faPlus" :label="trans('Add shipper')" type="create" size="xs" @click="addShipper" />
        </div>
    </div>
    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>
