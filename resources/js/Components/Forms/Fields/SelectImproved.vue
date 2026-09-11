<script setup lang="ts">
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import { ref, computed } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tag from '@/Components/Tag.vue'
import { Switch } from '@headlessui/vue'
import { faTimes } from '@fal'
import { faCheck, faTimes as fasTimes } from '@fas'

library.add(faTimes, faCheck, fasTimes)

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
        enableHideToggle?: boolean
        toggle_value?: boolean
        multiple?: boolean
    }
}>()

const _multiselect = ref(null)

const isMultiple = computed(() => props.fieldData?.multiple ?? true)

const formSelectedValue = computed({
    get: () => props.form[props.fieldName] ?? null,
    set: (newVal) => {
        props.form[props.fieldName] = newVal ?? null
        props.form.errors[props.fieldName] = null
    }
})

const toggleFieldName = computed(() => `${props.fieldName}_show`)
const localToggleValue = ref<boolean>(props.fieldData?.toggle_value ?? false)

const isSelectShown = computed({
    get: () => {
        if (!(props.fieldData?.enableHideToggle ?? false)) {
            return true
        }

        return toggleFieldName.value in props.form ? !!props.form[toggleFieldName.value] : localToggleValue.value
    },
    set: (newVal: boolean) => {
        if (toggleFieldName.value in props.form) {
            props.form[toggleFieldName.value] = newVal
        } else {
            localToggleValue.value = newVal
        }
    }
})

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
        <!-- Toggle: show/hide select -->
        <div v-if="fieldData?.enableHideToggle ?? false" class="mb-2">
            <Switch
                v-model="isSelectShown"
                class="pr-1 relative inline-flex h-6 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75"
                :class="isSelectShown ? 'bg-indigo-500' : 'bg-indigo-100'"
            >
                <span
                    aria-hidden="true"
                    :class="isSelectShown ? 'translate-x-6 bg-white' : 'translate-x-0 bg-gray-50'"
                    class="flex items-center justify-center pointer-events-none h-full w-1/2 transform rounded-full shadow-lg ring-0 transition"
                >
                    <FontAwesomeIcon v-if="isSelectShown" icon="fas fa-check" class="text-xs text-green-500" fixed-width aria-hidden="true" />
                    <FontAwesomeIcon v-else icon="fas fa-times" class="text-xs text-red-500" fixed-width aria-hidden="true" />
                </span>
            </Switch>
        </div>

        <template v-if="isSelectShown">
        <!-- Single select -->
        <div v-if="!isMultiple" class="w-full max-w-64">
            <Select
                v-model="formSelectedValue"
                :options="optionsList"
                :optionLabel="labelProp"
                :optionValue="valueProp"
                :placeholder="fieldData?.placeholder ?? trans('Select an option')"
                filter
                showClear
                class="w-full md:w-80"
            />
        </div>

        <template v-else>
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
        </template>
        </template>

        <p v-if="form.errors?.[fieldName]" class="mt-2 text-sm text-red-600">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
