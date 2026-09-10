<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import Multiselect from '@vueform/multiselect'
import "@vueform/multiselect/themes/default.css"
import { set, get, debounce } from 'lodash-es'
import { checkVAT, countries } from "jsvat-next"
import { ref, computed, watch, inject, type Ref } from "vue"
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faCopy, faCheck } from '@fal'
import { faSpinnerThird } from '@fad'
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { useFormatTime } from '@/Composables/useFormatTime'
import { taxNumberStatus } from '@/Composables/useTaxNumberValidation'
import { Tooltip } from 'floating-vue'
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { router } from "@inertiajs/vue3"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import { notify } from "@kyvg/vue3-notification"

library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faCopy)
defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName?: string
    options?: any
    refForms?: any
    fieldData?: any
    country_code?: string // in ISO246 format, e.g. 'DE', 'FR'
}>()


const emits = defineEmits()

const injectedRegistrationWarning = inject<Ref<Record<string, any>> | null>('registrationWarning', null)
const registrationWarning = injectedRegistrationWarning ?? ref<Record<string, any>>({})
const isWarningRenderedByHost = injectedRegistrationWarning !== null

const setFormValue = (data: Object, fieldName: string) => {
    if (Array.isArray(fieldName)) {
        return getNestedValue(data, fieldName)
    } else {
        return data[fieldName] ? data[fieldName] : { value: '' }
    }
}

const getNestedValue = (obj: Object, keys: Array<string>) => {
    return keys.reduce((acc, key) => {
        if (acc && typeof acc === "object" && key in acc) return acc[key]
        return null
    }, obj)
}

// Helper function to get the actual value with conditional access
const getActualValue = (valueObj: any): string => {
    if (!valueObj) return ''

    // Priority 1: Check form data structure first (maintain backward compatibility)

    // Case 1: value.value.number exists (nested object structure)
    if (valueObj.value && typeof valueObj.value === 'object' && valueObj.value.number !== undefined) {
        return valueObj.value.number
    }

    // Case 2: value.value is a string
    if (valueObj.value && typeof valueObj.value === 'string') {
        return valueObj.value
    }

    // Case 3: direct string value
    if (typeof valueObj === 'string') {
        return valueObj
    }

    // Priority 2: Fallback to fieldData if form data is empty/invalid
    if (props.fieldData?.value?.number && (!valueObj || !valueObj.value)) {
        return props.fieldData.value.number
    }

    // Default fallback
    return valueObj.value || ''
}

const value = ref(setFormValue(props.form, props.fieldName))
const vatValidationResult = ref<string | null>(null)
const isFormDirty = ref(false)

// Popover refs
const statusPopover = ref()
const countryPopover = ref()
const datePopover = ref()
const isModalOpen = ref(false)

// Computed properties for validation status display
const validationStatus = computed(() => {
    if (!props.fieldData?.value || isFormDirty.value) return null

    const { status, valid, invalid_checked_at, checked_at, country } = props.fieldData.value

    return {
        status,
        valid,
        invalid_checked_at,
        checked_at,
        country,
        vatNumber: props.fieldData.value.number
    }
})

const formatDate = (dateString: string | null) => {
    if (!dateString) return null

    try {
        // Using the composable with 'hm' format (Nov 2, 2023, 3:03 PM)
        return useFormatTime(dateString, {
            formatTime: 'dd MMM yyyy',
            // localeCode: 'id' // Indonesian locale
        })
    } catch (error) {
        console.error('Error formatting date:', error)
        return dateString
    }
}

const getStatusIcon = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'fa-exclamation-circle'
    }
    if (status === 'valid' || valid) {
        return 'fa-check-circle'
    }
    return 'fa-spinner-third'
}

const getStatusColor = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return 'text-red-600'
    }
    if (status === 'valid' || valid) {
        return 'text-green-600'
    }
    return 'text-yellow-600'
}

const getStatusText = (status: string, valid: boolean) => {
    if (status === 'invalid' || !valid) {
        return trans('Invalid')
    }
    if (status === 'valid' || valid) {
        return trans('Valid')
    }
    return trans('Pending')
}

const countryOptions = computed(() => {
    const countriesAddressData = props.options?.countriesAddressData ?? props.fieldData?.options?.countriesAddressData ?? {}

    return Object.values(countriesAddressData).map((country: any) => ({
        value: country.code,
        label: country.label
    }))
})

const number = ref(getActualValue(value.value))
const countryCode = ref(
    props.fieldData?.value?.country?.data?.code
    || props.fieldData?.country
    || props.country_code
    || ''
)
const isCountryPickedByUser = ref(false)

const isValidVatNumber = (prefixedNumber: string) => checkVAT(prefixedNumber, countries).isValid

const currentTaxNumberStatus = computed(() => taxNumberStatus(number.value, countryCode.value, isValidVatNumber))

const isCountryMissing = computed(() => currentTaxNumberStatus.value === 'country_missing')

const updateFormValue = () => {
    const payload = {
        number: number.value,
        value: number.value,
        country_code: countryCode.value
    }

    if (Array.isArray(props.fieldName)) {
        set(props.form, props.fieldName, payload)
    } else {
        props.form[props.fieldName] = payload
    }

    value.value = payload
    emits("update:form", props.form)
}

const taxNumberErrorKey = computed(() => `${props.fieldName}`)

const setTaxNumberWarning = (message: string | null) => {
    set(registrationWarning.value, ['tax_number'], message)
}

const clearTaxNumberError = () => {
    props.form.clearErrors?.(taxNumberErrorKey.value)
}

const validateTaxNumber = () => {
    const status = currentTaxNumberStatus.value

    if (status === 'empty' || status === 'country_missing') {
        vatValidationResult.value = null
        setTaxNumberWarning(null)

        return
    }

    vatValidationResult.value = status === 'valid' ? trans("Valid tax number") : trans("Invalid tax number")

    setTaxNumberWarning(
        status === 'valid' ? null : '🤔 ' + trans('Tax number looks invalid. Are you sure you want to save it?')
    )
}

const debouncedValidation = debounce(() => validateTaxNumber(), 500)

const updateVat = (newInputValue: string) => {
    isFormDirty.value = true
    number.value = newInputValue
    clearTaxNumberError()
    updateFormValue()
    debouncedValidation()
}

const updateCountry = (newCountryCode: string) => {
    isCountryPickedByUser.value = true
    countryCode.value = newCountryCode ?? ''
    clearTaxNumberError()
    updateFormValue()
    validateTaxNumber()
}

watch(() => props.country_code, (newCountryCode) => {
    if (!newCountryCode || isCountryPickedByUser.value) {
        return
    }

    countryCode.value = newCountryCode

    if (number.value) {
        updateFormValue()
        validateTaxNumber()
    }
}, { immediate: true })

// Watch for changes in fieldData to reset the dirty state and show validation status
watch(
    () => props.fieldData,
    (newFieldData, oldFieldData) => {
        // Reset dirty state when fieldData is updated from server
        if (newFieldData && newFieldData !== oldFieldData) {
            // Check if the validation status has changed (indicating a server update)
            const hasValidationData = newFieldData?.value?.status || newFieldData?.value?.checked_at
            if (hasValidationData) {
                isFormDirty.value = false
            }
        }
    },
    { deep: true }
)

const isLoadingMarkValid = ref(false);
const markAsValid = () => {
    router.patch(route('grp.models.customer.update', {
            customer: props.fieldData.mark_as_valid_button.cus_id
        }), {
            mark_tax_number_valid: true
        }, 
        {
            onStart: () => (
                isLoadingMarkValid.value = true
            ),
            onFinish: () => {
                isLoadingMarkValid.value = false
                reload?.()
                notify({
                    title: ctrans("Success"),
                    text: ctrans("Marked Tax Number as Valid"),
                    type: "success",
                })
            },
            onError: (errors: any) => {
                notify({
                    title: ctrans("Error Occured"),
                    text: errors?.message || ctrans("Unknown error occurred"),
                    type: "error",
                })
            },
        }
    )
}

</script>

<template>
    <div class="relative">
        <div class="relative">
            <div class="flex gap-x-2">
                <Multiselect
                    class="w-44"
                    searchable
                    :options="countryOptions"
                    :model-value="countryCode"
                    @update:model-value="updateCountry"
                    :placeholder="trans('Country')"
                    :canDeselect="false"
                    :canClear="false"
                    name="tax_number_country_code"
                />
                <PureInput
                    :model-value="number"
                    @update:model-value="updateVat"
                    :prefix="{
                        label: countryCode
                    }"
                />
            </div>
            <p v-if="isCountryMissing" class="mt-1 text-sm text-red-600">
                {{ trans('Tax number needs its country') }}
            </p>
            <span class="italic text-xs" v-if="fieldData?.europeanUnion">
                <span style="color: red">*</span> {{ trans("This will affect your VAT Rate") }}
                <FontAwesomeIcon v-on:click="isModalOpen = true" v-tooltip="ctrans('Click to view detailed explanation')" icon='fal fa-info-circle' class="opacity-60 hover:opacity-100 cursor-pointer" fixed-width aria-hidden='true' />
            </span>
        </div>

        <!-- Validation Status Display -->
        <div v-if="validationStatus" class="mt-3 p-3 bg-gray-50 rounded-lg border">
            <div class="flex items-start justify-between">
                <div class="flex items-center space-x-2">
                    <FontAwesomeIcon 
                        :icon="getStatusIcon(validationStatus.status, validationStatus.valid)"
                        :class="getStatusColor(validationStatus.status, validationStatus.valid)" 
                        class="text-sm" />

                    <div class="space-y-2">
                        <p class="text-sm">
                            <span class="font-medium "
                                :class="getStatusColor(validationStatus.status, validationStatus.valid)">
                                {{ getStatusText(validationStatus.status, validationStatus.valid) }}
                            </span>

                            <!-- Country -->
                            <Tooltip class="inline ml-1">
                                <div class="inline hover:underline cursor-default">({{ validationStatus.country?.data?.name }})</div>

                                <template #popper>
                                    <div class="p-1 max-w-xs">
                                        <div class="space-y-2">
                                            <div class="text-sm space-y-1">
                                                <p><span class="font-medium">{{ trans('Country') }}:</span> {{ validationStatus.country?.data?.name }}</p>
                                                <p><span class="font-medium">{{ trans('Country Code') }}:</span> {{ validationStatus.country?.data?.code }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Tooltip>

                            <!-- Last checked date -->
                            <span
                                v-tooltip="trans('Last checked :date', { date: formatDate(validationStatus?.checked_at) || '-' })"
                                class="ml-1 cursor-default hover:underline">
                                {{ formatDate(validationStatus.checked_at) }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            <div v-if="validationStatus.status == 'invalid' && fieldData.mark_as_valid_button?.show" class="flex items-start pt-2">
                <ModalConfirmation
                    :title="ctrans('Are you sure you want to proceed?')"
                    :description="ctrans(`Please make sure you've checked the VAT Details with upmost detail first before proceeding`)"
                    hideCancel
                >
                    <template #default="{ isOpenModal, changeModel }">
                        <Button :style="'secondary'" :icon="faCheck" :loading="isLoadingMarkValid" :label="'Mark as Valid'" @click="() => changeModel()" />
                    </template>
                    <template #btn-yes="{ closeModal }">
                        <Button
                            :label="trans('Confirm')"
                            @click="
                                () => {
                                    markAsValid()
                                    closeModal()
                                }
                            "
                            type="negative"
                            :icon="faWarning" />
                    </template>
                </ModalConfirmation>
            </div>
        </div>
    </div>

    <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-[500px]">
        <slot name="modal" :closeModal="() => isModalOpen = false" >
            <div class="font-bold">
                <FontAwesomeIcon icon='fal fa-info-circle' class="opacity-100 text-red-500" fixed-width aria-hidden='true'/> {{ trans("VAT Information") }}
            </div>
            <div class="text-sm mt-3">
                {{ trans('In order to benefit from VAT-Free purchases, you are required to enter a VALID Tax Number, using certain country code (Matching with the Country that issued your Tax Number) as prefix.') }}
                <br>
                <br>
                {{ trans('Example') }}:
                <br>- BG12345678 <FontAwesomeIcon icon='fas fa-check-circle' v-tooltip="trans('Benefit from VAT-Free purchase')" class="opacity-100 text-green-500" fixed-width aria-hidden='true'/>
                <br>- UK12345678 <FontAwesomeIcon icon='fas fa-times-circle' v-tooltip="trans('Did not benefit from VAT-Free purchase')" class="opacity-100 text-red-500" fixed-width aria-hidden='true'/>
                <br>
                <br>
                    {{ trans('List of Valid Country Codes') }}:
                <br>
                <span class="font-semibold">
                    {{fieldData?.europeanUnion}}
                </span>
            </div>
        </slot>
    </Modal>

    <p v-if="!isWarningRenderedByHost && registrationWarning.tax_number" class="mt-2 text-sm text-amber-600">
        {{ registrationWarning.tax_number }}
    </p>

    <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
        {{ form.errors[fieldName] }}
    </p>
</template>