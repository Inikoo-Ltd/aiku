<script setup lang="ts">
import { computed, ref, watch, inject } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSync, faUndo, faTimes, faTimesCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { useLocaleStore } from "@/Stores/locale"
import { router } from '@inertiajs/vue3'
import Button from "./Elements/Buttons/Button.vue"

library.add(faSync, faUndo, faTimes, faTimesCircle)

interface CurrencyData {
    id: number
    code: string
    name: string
    symbol: string
}

interface RefundRoute {
    name: string
    parameters: {
        organisation: number
        payment: number
    }
}

interface Showcase {
    amount: string
    state: string
    currency: { data: CurrencyData }
}

const props = defineProps<{
    showcase: Showcase
    refundRoute?: RefundRoute
    isVisible: boolean
}>()

const layout = inject('layout')

const emit = defineEmits<{
    close: []
}>()

// Refund modal state
const refundAmount = ref('')
const refundReason = ref('')
const refundType = ref('full') // 'partial' or 'full'
const isProcessingRefund = ref(false)
const refundErrors = ref({}) // Server errors

// Client-side validation errors
const validationErrors = ref({
    amount: '',
    reason: ''
})

// Dynamic theme colors based on layout.app.theme
const themeColors = computed(() => {
    const theme = layout?.app?.theme || [
        "#f43f5e", "#F5F5F5", "#E3B7C8", "#000000",
        "#D6C7E2", "#000000", "#fca5a5", "#374151"
    ]

    return {
        // 0-1: Main Layout (primary bg & primary text color)
        primaryBg: theme[0] || "#f43f5e",
        primaryText: theme[1] || "#F5F5F5",

        // 2-3: Navigation and box (bg & text color)
        navBg: theme[2] || "#E3B7C8",
        navText: theme[3] || "#000000",

        // 4-5: Button and mini-box (bg & text color)
        buttonBg: theme[4] || "#D6C7E2",
        buttonText: theme[5] || "#000000",

        // 6-7: SecondaryLink bg and text
        secondaryBg: theme[6] || "#fca5a5",
        secondaryText: theme[7] || "#374151"
    }
})

// Helper function to convert hex to rgba with opacity
const hexToRgba = (hex: string, opacity: number) => {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    return `rgba(${r}, ${g}, ${b}, ${opacity})`
}

// Dynamic styles for theme application with opacity
const dynamicStyles = computed(() => ({
    '--theme-primary-bg': themeColors.value.primaryBg,
    '--theme-primary-bg-05': hexToRgba(themeColors.value.primaryBg, 0.05),
    '--theme-primary-bg-10': hexToRgba(themeColors.value.primaryBg, 0.1),
    '--theme-primary-bg-20': hexToRgba(themeColors.value.primaryBg, 0.2),
    '--theme-primary-bg-30': hexToRgba(themeColors.value.primaryBg, 0.3),
    '--theme-primary-bg-80': hexToRgba(themeColors.value.primaryBg, 0.8),
    '--theme-primary-text': themeColors.value.primaryText,
    '--theme-nav-bg': themeColors.value.navBg,
    '--theme-nav-bg-10': hexToRgba(themeColors.value.navBg, 0.1),
    '--theme-nav-bg-20': hexToRgba(themeColors.value.navBg, 0.2),
    '--theme-nav-text': themeColors.value.navText,
    '--theme-button-bg': themeColors.value.buttonBg,
    '--theme-button-bg-10': hexToRgba(themeColors.value.buttonBg, 0.1),
    '--theme-button-bg-20': hexToRgba(themeColors.value.buttonBg, 0.2),
    '--theme-button-text': themeColors.value.buttonText,
    '--theme-secondary-bg': themeColors.value.secondaryBg,
    '--theme-secondary-bg-10': hexToRgba(themeColors.value.secondaryBg, 0.1),
    '--theme-secondary-bg-20': hexToRgba(themeColors.value.secondaryBg, 0.2),
    '--theme-secondary-text': themeColors.value.secondaryText
}))

// Computed properties
const normalizedShowcase = computed(() => {
    return {
        ...props.showcase,
        currency: props.showcase.currency.data,
    }
})

const isRefund = computed(() => {
    return parseFloat(normalizedShowcase.value.amount) < 0
})

const canRefund = computed(() => {
    // Only allow refund for completed payments, not already refunds, and must have refund route
    return normalizedShowcase.value.state === 'completed' &&
        !isRefund.value &&
        props.refundRoute
})

const maxRefundAmount = computed(() => {
    return Math.abs(parseFloat(normalizedShowcase.value.amount))
})

// Validation methods
const validateRefundAmount = () => {
    const amount = parseFloat(refundAmount.value)

    if (refundAmount.value === '' || isNaN(amount)) {
        validationErrors.value.amount = trans('Amount cannot be empty')
        return false 
    }

    if (amount < 0) {
        validationErrors.value.amount = trans('Amount cannot be negative')
        return false
    }

    if (amount === 0) {
        validationErrors.value.amount = trans('Please enter an amount greater than zero')
        return false
    }

    if (amount > maxRefundAmount.value) {
        validationErrors.value.amount = trans('Invalid amount. Maximum refund') + ': ' +
            useLocaleStore().currencyFormat(normalizedShowcase.value.currency.code, maxRefundAmount.value)
        return false
    }

    validationErrors.value.amount = ''
    return true
}

const validateRefundReason = () => {
    if (!refundReason.value.trim()) {
        validationErrors.value.reason = trans('Refund reason is required')
        return false
    }

    validationErrors.value.reason = ''
    return true
}

const isFormValid = computed(() => {
    const isAmountValid = refundType.value === 'full' || validateRefundAmount()
    const isReasonValid = refundReason.value.trim() !== ''
    return isAmountValid && isReasonValid && !validationErrors.value.amount && !validationErrors.value.reason
})

// Clear validation errors when user starts typing
const clearAmountError = () => {
    validationErrors.value.amount = ''
}

const clearReasonError = () => {
    validationErrors.value.reason = ''
}

// Watch for real-time amount validation
watch(refundAmount, () => {
    if (refundType.value === 'partial') {
        validateRefundAmount()
    }
})

// Watch for modal visibility changes to reset form
watch(() => props.isVisible, (newValue) => {
    if (newValue) {
        // Reset form when modal opens
        refundAmount.value = maxRefundAmount.value.toString()
        refundReason.value = ''
        refundType.value = 'full'
        refundErrors.value = {}
        validationErrors.value = { amount: '', reason: '' }
        isProcessingRefund.value = false
    }
})

// Methods
const closeRefundModal = () => {
    refundAmount.value = ''
    refundReason.value = ''
    refundType.value = 'full'
    isProcessingRefund.value = false
    refundErrors.value = {}
    validationErrors.value = { amount: '', reason: '' }
    emit('close')
}

const processRefund = async () => {
    // Validate reason field on submit
    const isReasonValid = validateRefundReason()
    const isAmountValid = refundType.value === 'full' || validateRefundAmount()

    if (!isReasonValid || !isAmountValid) {
        return
    }

    if (!props.refundRoute) {
        alert('Refund route not available')
        return
    }

    isProcessingRefund.value = true

    try {
        const refundAmountValue = refundType.value === 'full' ? maxRefundAmount.value : parseFloat(refundAmount.value)

        const refundData = {
            type: 'payment_refund',
            amount: refundAmountValue,
            reason: refundReason.value,
            refund_type: refundType.value,
            currency_code: normalizedShowcase.value.currency.code,
            original_payment_id: props.refundRoute.parameters.payment
        }

        // Menggunakan Inertia router untuk POST request
        router.post(
            route(props.refundRoute.name, props.refundRoute.parameters),
            refundData,
            {
                onSuccess: (response) => {
                    console.log('Refund processed successfully:', response)
                    closeRefundModal()
                },
                onError: (errors) => {
                    console.error('Refund failed:', errors)
                    refundErrors.value = errors
                    isProcessingRefund.value = false
                },
                onFinish: () => {
                    // This runs regardless of success or error
                }
            }
        )

    } catch (error) {
        console.error('Unexpected error during refund:', error)
        alert('An unexpected error occurred. Please try again.')
        isProcessingRefund.value = false
    }
}

// const handleMouseOver = (event) => {
//     if (!isProcessingRefund.value) {
//         event.target.style.backgroundColor = hexToRgba(themeColors.value.primaryBg, 0.8)
//     }
// }

// const handleMouseOut = (event) => {
//     if (!isProcessingRefund.value) {
//         event.target.style.backgroundColor = themeColors.value.buttonBg
//     } else {
//         event.target.style.backgroundColor = '#6B7280'
//     }
// }

// Handle reason field blur
const handleReasonBlur = () => {
    validateRefundReason()
}
</script>

<template>
    <!-- Refund Modal -->
    <div v-if="isVisible" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50"
        :style="dynamicStyles">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b" :style="{
                backgroundColor: 'var(--theme-primary-bg-05)',
                borderColor: 'var(--theme-primary-bg-20)'
            }">
                <h3 class="text-lg font-semibold flex items-center gap-2" :style="{ color: 'var(--theme-primary-bg)' }">
                    <FontAwesomeIcon icon="fal fa-undo" />
                    {{ trans('Process Payment Refund') }}
                </h3>
                <button @click="closeRefundModal" class="hover:opacity-70 transition-opacity"
                    :style="{ color: 'var(--theme-primary-bg)' }">
                    <FontAwesomeIcon icon="fal fa-times" class="w-5 h-5" />
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4">
                <!-- Server Error Messages -->
                <div v-if="Object.keys(refundErrors).length > 0" class="border rounded-lg p-4" :style="{
                    backgroundColor: 'var(--theme-secondary-bg-10)',
                    borderColor: 'var(--theme-secondary-bg-20)'
                }">
                    <div class="flex">
                        <FontAwesomeIcon icon="fal fa-times-circle" class="w-5 h-5 mt-0.5 mr-3"
                            :style="{ color: 'var(--theme-secondary-bg)' }" />
                        <div>
                            <h4 class="text-sm font-medium mb-1" :style="{ color: 'var(--theme-secondary-text)' }">
                                {{ trans('Please correct the following errors') }}:
                            </h4>
                            <ul class="text-sm space-y-1" :style="{ color: 'var(--theme-secondary-text)' }">
                                <li v-for="(error, field) in refundErrors" :key="field">
                                    <span v-if="Array.isArray(error)">{{ error[0] }}</span>
                                    <span v-else>{{ error }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Original Payment Info -->
                <div class="rounded-lg p-4 bg-gray-100">
                    <h4 class="text-sm font-medium mb-2">
                        {{ trans('Original Payment') }}
                    </h4>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">{{ trans('Amount') }}:</span>
                        <span class="font-semibold">
                            {{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
                            normalizedShowcase.amount) }}
                        </span>
                    </div>
                </div>

                <!-- Refund Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ trans('Refund Type') }}</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input v-model="refundType" type="radio" value="full"
                                class="h-4 w-4 border-gray-300 focus:ring-2" :style="{
                                    color: 'var(--theme-primary-bg)',
                                    '--tw-ring-color': 'var(--theme-primary-bg-30)'
                                }" @change="clearAmountError">
                            <span class="ml-2 text-sm text-gray-700">{{ trans('Full Refund') }}</span>
                        </label>
                        <label class="flex items-center">
                            <input v-model="refundType" type="radio" value="partial"
                                class="h-4 w-4 border-gray-300 focus:ring-2" :style="{
                                    color: 'var(--theme-primary-bg)',
                                    '--tw-ring-color': 'var(--theme-primary-bg-30)'
                                }" @change="clearAmountError">
                            <span class="ml-2 text-sm text-gray-700">{{ trans('Partial Refund') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Refund Amount Input -->
                <div v-if="refundType === 'partial'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ trans('Refund Amount') }}
                    </label>
                    <div class="relative">
                        <input v-model="refundAmount" type="number" step="0.01" :max="maxRefundAmount" min="0.01"
                            class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:border-transparent" :class="{
                                'border-red-300': validationErrors.amount,
                                'border-gray-300': !validationErrors.amount
                            }" :style="{
                                '--tw-ring-color': 'var(--theme-primary-bg-30)',
                                backgroundColor: validationErrors.amount ? 'var(--theme-secondary-bg-10)' : 'white'
                            }">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <span class="text-gray-500 text-sm">{{ normalizedShowcase.currency.code }}</span>
                        </div>
                    </div>
                    <!-- Amount Validation Error -->
                    <p v-if="validationErrors.amount" class="mt-1 text-sm text-red-500">
                        {{ validationErrors.amount }}
                    </p>
                </div>

                <!-- Full Refund Amount Display -->
                <div v-if="refundType === 'full'" class="border rounded-lg p-4" :style="{
                    backgroundColor: 'var(--theme-primary-bg-10)',
                    borderColor: 'var(--theme-primary-bg-20)'
                }">
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium" :style="{ color: 'var(--theme-secondary-text)' }">
                            {{ trans('Full Refund Amount') }}:
                        </span>
                        <span class="font-bold" :style="{ color: 'var(--theme-primary-bg)' }">
                            {{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code, maxRefundAmount) }}
                        </span>
                    </div>
                </div>

                <!-- Refund Reason -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ trans('Refund Reason') }}
                        <span class="text-red-500">*</span>
                    </label>
                    <textarea v-model="refundReason" rows="3" @input="clearReasonError" @blur="handleReasonBlur"
                        class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:border-transparent" :class="{
                            'border-red-300': validationErrors.reason,
                            'border-gray-300': !validationErrors.reason
                        }" :style="{
                                '--tw-ring-color': 'var(--theme-primary-bg-30)',
                                backgroundColor: validationErrors.reason ? 'var(--theme-secondary-bg-10)' : 'white'
                            }" :placeholder="trans('Please provide a reason for this refund...')"></textarea>
                    <!-- Reason Validation Error -->
                    <p v-if="validationErrors.reason" class="mt-1 text-sm text-red-500">
                        {{ validationErrors.reason }}
                    </p>
                </div>

                <!-- Refund Summary -->
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">{{ trans('Refund Summary') }}</h4>
                    <div class="rounded-lg p-4 space-y-2" :style="{ backgroundColor: 'var(--theme-primary-bg-10)' }">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ trans('Type') }}:</span>
                            <span class="font-medium">{{ trans('Payment Refund') }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ trans('Amount') }}:</span>
                            <span class="font-bold text-base" :style="{ color: 'var(--theme-primary-bg)' }">
                                -{{ useLocaleStore().currencyFormat(
                                normalizedShowcase.currency.code,
                                refundType === 'full' ? maxRefundAmount : parseFloat(refundAmount) || 0
                                ) }}
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">{{ trans('Currency') }}:</span>
                            <span class="font-medium">{{ normalizedShowcase.currency.code }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-end gap-3 p-6 border-t"
                :style="{ borderColor: 'var(--theme-primary-bg-20)' }">
                <button @click="closeRefundModal"
                    class="px-4 py-1 rounded-md transition-colors duration-200 hover:opacity-80 bg-gray-300 h-[34px] text-sm"
                    :disabled="isProcessingRefund">
                    {{ trans('Cancel') }}
                </button>
                <Button @click="processRefund" :disabled="isProcessingRefund" :icon="faUndo" :loading="isProcessingRefund"
                    :label="isProcessingRefund ? trans('Processing...') : trans('Process Refund') ">
                </Button>
            </div>
        </div>
    </div>
</template>
