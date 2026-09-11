<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import { faTimes } from '@fal'

library.add(faTimes)

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: {
        options?: Record<string, any>[] | Record<string, Record<string, any>>
        placeholder?: string
        labelProp?: string
        valueProp?: string
        tagLabelProp?: string
        tagUppercase?: boolean
    }
}>()

const _multiselect = ref(null)

const labelProp = computed(() => props.fieldData?.labelProp ?? 'label')
const valueProp = computed(() => props.fieldData?.valueProp ?? 'value')
const tagLabelProp = computed(() => props.fieldData?.tagLabelProp ?? labelProp.value)

const optionsList = computed<Record<string, any>[]>(() => Object.values(props.fieldData?.options ?? {}))

const formSelectedValues = computed({
    get: () => props.form[props.fieldName] || [],
    set: (newVal) => {
        const oldVal = props.form[props.fieldName] || []
        const addedValues = newVal.filter((value: string | number) => !oldVal.includes(value))

        if (addedValues.length > 0) {
            props.form[props.fieldName] = [...oldVal, ...addedValues]
            props.form.errors[props.fieldName] = null
        } else {
            props.form[props.fieldName] = oldVal
        }
    }
})

const findOptionByValue = (value: string | number) => {
    return optionsList.value.find((option) => option[valueProp.value] === value)
}

const getTagLabel = (value: string | number) => {
    const label = String(findOptionByValue(value)?.[tagLabelProp.value] ?? value)

    return (props.fieldData?.tagUppercase ?? false) ? label.toUpperCase() : label
}

const onRemoveValue = (value: string | number) => {
    props.form[props.fieldName] = formSelectedValues.value.filter((selectedValue: string | number) => selectedValue !== value)
    props.form.errors[props.fieldName] = null
}
</script>

<template>
    <div class="w-full max-w-md">
        <!-- Multiselect -->
        <div class="w-full max-w-64">
            <MultiSelect
                ref="_multiselect"
                v-model="formSelectedValues"
                :options="optionsList"
                :optionLabel="labelProp"
                :optionValue="valueProp"
                :optionDisabled="(option) => formSelectedValues.includes(option[valueProp])"
                :placeholder="fieldData?.placeholder ?? trans('Select options')"
                :maxSelectedLabels="3"
                filter
                class="w-full md:w-80"
                :showClear="false"
            >
                <template #footer>
                    <div class="cursor-pointer border-t border-gray-300 p-2 flex flex-col gap-y-2 justify-center items-center text-center">
                        <Button
                            @click="() => (_multiselect?.hide())"
                            :label="trans('Close')"
                            full
                            type="tertiary"
                        />
                    </div>
                </template>
            </MultiSelect>
        </div>

        <!-- Selected values list (sync with form value) -->
        <div v-if="formSelectedValues.length" class="flex flex-wrap mt-2 gap-x-2 gap-y-1">
            <Tag
                v-for="value in formSelectedValues"
                :key="value"
                v-tooltip="findOptionByValue(value)?.[labelProp]"
                :label="getTagLabel(value)"
                stringToColor
            >
                <template #closeButton>
                    <div @click="() => onRemoveValue(value)" class="cursor-pointer bg-white/60 hover:bg-black/10 px-1 text-red-500 rounded-sm">
                        <FontAwesomeIcon icon="fal fa-times" class="text-xs" aria-hidden="true" />
                    </div>
                </template>
            </Tag>
        </div>

        <p v-if="form.errors?.[fieldName]" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
