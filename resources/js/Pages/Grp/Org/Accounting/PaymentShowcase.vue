<script setup lang="ts">
import { computed, ref, inject } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink } from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle, faUndo, faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Tag from "@/Components/Tag.vue"
import { useLocaleStore } from "@/Stores/locale"
import { router } from '@inertiajs/vue3'

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle, faUndo, faTimes)

interface Country {
	code: string
	iso3: string
	name: string
}

interface Address {
	id: number
	address_line_1: string
	address_line_2: string
	sorting_code: string
	postal_code: string
	locality: string
	dependent_locality: string
	administrative_area: string
	country_code: string
	country_id: number
	checksum: string
	created_at: string
	updated_at: string
	country: Country
	formatted_address: string
	can_edit: any
	can_delete: any
}

interface CustomerData {
	slug: string
	reference: string
	name: string
	contact_name: string
	company_name: string
	location: string[]
	address: Address
	email: string
	phone: string
	created_at: string
	number_current_customer_clients: number | null
	state?: string
	is_dropshipping?: boolean
}

interface ParentData {
	id: number
	reference: string
	slug: string
	state: string
	state_label: string
	state_icon: {
		tooltip: string
		icon: string
		class: string
		color: string
		app: {
			name: string
			type: string
		}
	}
	public_notes: string | null
	customer_name: string
	customer_slug: string
	currency_code: string
	net_amount: string
	customer_notes: string | null
	shipping_notes: string | null
	payment_amount: string
	total_amount: string
	is_fully_paid: boolean
	unpaid_amount: number
	created_at: string
	updated_at: string
	submitted_at: string
	in_warehouse_at: string
	handling_at: string
	packed_at: string | null
	finalised_at: string | null
	dispatched_at: string | null
	cancelled_at: string | null
}

interface CurrencyData {
	id: number
	code: string
	name: string
	symbol: string
}

interface PaymentAccountData {
	slug: string
	name: string
	number_payments: number
	code: string
	created_at: string
	updated_at: string
}

interface PaymentServiceProviderData {
	slug: string
	code: string
	name: string
	created_at: string
}

interface CreditTransactionData {
	id: number
	payment_id: number
	type: string
	amount: string
	running_amount: string
	payment_reference: string | null
	payment_type: string | null
	currency_code: string | null
	created_at: string
}

interface Showcase {
	parent_type: string | null
	amount: string
	state: string
	customer: { data: CustomerData }
	parent_data: { data: ParentData } | null
	currency: { data: CurrencyData }
	paymentAccount: { data: PaymentAccountData }
	paymentServiceProvider: { data: PaymentServiceProviderData }
	credit_transaction: { data: CreditTransactionData } | null
	refund_route?: {
		name: string
		parameters: {
			organisation: number
			payment: number
		}
	}
}

const props = defineProps<{
	data: Showcase
	tab: string
}>()

const layout = inject('layout')

// console.log(layout)

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

// Dynamic styles for theme application
const dynamicStyles = computed(() => ({
	'--theme-primary-bg': themeColors.value.primaryBg,
	'--theme-primary-text': themeColors.value.primaryText,
	'--theme-nav-bg': themeColors.value.navBg,
	'--theme-nav-text': themeColors.value.navText,
	'--theme-button-bg': themeColors.value.buttonBg,
	'--theme-button-text': themeColors.value.buttonText,
	'--theme-secondary-bg': themeColors.value.secondaryBg,
	'--theme-secondary-text': themeColors.value.secondaryText
}))

// Refund modal state
const showRefundModal = ref(false)
const refundAmount = ref('')
const refundReason = ref('')
const refundType = ref('partial') // 'partial' or 'full'
const isProcessingRefund = ref(false)
const refundErrors = ref({})

// Normalize the showcase data so that the nested properties (like customer.data)
// are flattened and available directly in the template.
const normalizedShowcase = computed(() => {
	const customer = { ...props.data.customer.data }
	if (!customer.state) {
		customer.state = props.data.state
	}
	return {
		...props.data,
		customer,
		currency: props.data.currency.data,
		paymentAccount: props.data.paymentAccount.data,
		paymentServiceProvider: props.data.paymentServiceProvider.data,
		parentData: props.data.parent_data?.data || null,
		creditTransaction: props.data.credit_transaction?.data || null,
	}
})

const isRefund = computed(() => {
	return parseFloat(normalizedShowcase.value.amount) < 0
})

const canRefund = computed(() => {
	// Only allow refund for completed payments, not already refunds, and must have refund route
	return normalizedShowcase.value.state === 'completed' &&
		!isRefund.value &&
		props.data.refund_route
})

const maxRefundAmount = computed(() => {
	return Math.abs(parseFloat(normalizedShowcase.value.amount))
})

const mapPaymentState = computed(() => {
	const state = normalizedShowcase.value.state
	return state === 'in_process' ? 'processing' : state
})

const getStateTheme = (state: string) => {
	switch (state) {
		case 'completed':
		case 'active':
			return 3 // green
		case 'cancelled':
		case 'lost':
			return 7 // red
		case 'pending':
			return 5 // yellow
		default:
			return 99 // default
	}
}

// Refund functions
const openRefundModal = () => {
	showRefundModal.value = true
	refundAmount.value = maxRefundAmount.value.toString()
	refundReason.value = ''
	refundType.value = 'partial'
}

const closeRefundModal = () => {
	showRefundModal.value = false
	refundAmount.value = ''
	refundReason.value = ''
	refundType.value = 'partial'
	isProcessingRefund.value = false
	refundErrors.value = {}
}

const processRefund = async () => {
	if (!props.data.refund_route) {
		alert('Refund route not available')
		return
	}

	isProcessingRefund.value = true

	try {
		const refundAmountValue = refundType.value === 'full' ? maxRefundAmount.value : parseFloat(refundAmount.value)

		const refundData = {
			type: 'payment_refund', // Sesuai dengan chat yang menyebutkan "Payment Refund"
			amount: refundAmountValue,
			reason: refundReason.value,
			refund_type: refundType.value,
			currency_code: normalizedShowcase.value.currency.code,
			original_payment_id: props.data.refund_route.parameters.payment
		}

		// Menggunakan Inertia router untuk POST request
		router.post(
			route(props.data.refund_route.name, props.data.refund_route.parameters),
			refundData,
			{
				onSuccess: (response) => {
					console.log('Refund processed successfully:', response)
					closeRefundModal()
					// Optionally reload the page to show updated data
					// router.reload({ only: ['data'] })
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

const validateRefundAmount = () => {
	const amount = parseFloat(refundAmount.value)
	if (isNaN(amount) || amount <= 0) {
		return false
	}
	if (amount > maxRefundAmount.value) {
		return false
	}
	return true
}

const handleMouseOver = (event) => {
	if (!isProcessingRefund.value) {
		event.target.style.backgroundColor = themeColors.value.buttonBg
	}
}

const handleMouseOut = (event) => {
	if (!isProcessingRefund.value) {
		event.target.style.backgroundColor = themeColors.value.buttonBg
	} else {
		event.target.style.backgroundColor = '#6B7280'
	}
}

// console.log(props.data)
</script>

<template>
	<div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6" :style="dynamicStyles">
		<!-- Column 1: Payment & Customer Information -->
		<div class="space-y-6">
			<!-- Section: Payment Summary -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-money-bill-wave" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Payment Summary') }}
					</h3>
					<!-- Refund Button  -->
					<!-- Hide because BE not ready yet -->
					<button v-if="canRefund && layout?.app?.environment !== 'production'" @click="openRefundModal"
						class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200"
						:style="{ 
							backgroundColor: themeColors.buttonBg, 
							color: themeColors.buttonText
						}"
						@mouseover="$event.target.style.backgroundColor = themeColors.primaryBg"
						@mouseout="$event.target.style.backgroundColor = themeColors.buttonBg">
						<FontAwesomeIcon icon="fal fa-undo" class="w-4 h-4"  />
						{{ trans('Process Refund') }}
					</button>

				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Payment Amount -->
					<div class="flex items-center justify-between p-4 rounded-lg"
						:style="{ 
							background: `linear-gradient(to right, ${themeColors.buttonBg}, #ffffff)`
						}">
						<dt class="text-sm font-medium" :style="{ color: themeColors.buttonText }">
							{{ isRefund ? trans('Refund Amount') : trans('Payment Amount') }}
						</dt>
						<dd class="text-2xl font-bold" :style="{ color: isRefund ? '#e00909' : themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.amount) }}
						</dd>
					</div>

					<!-- Payment Type -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Type') }}</dt>
						<dd>
							<Tag :label="isRefund ? 'Refund' : 'Payment'" :theme="isRefund ? 7 : 3" />
						</dd>
					</div>

					<!-- Payment Status -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Status') }}</dt>
						<dd class="capitalize">
							<Tag :label="mapPaymentState" :theme="getStateTheme(mapPaymentState)" />
						</dd>
					</div>

					<!-- Payment Method -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-credit-card" :style="{ color: themeColors.primaryBg }" />
							{{ trans('Payment Method') }}
						</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.paymentServiceProvider.name }}</dd>
					</div>

					<!-- Currency -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Currency') }}</dt>
						<dd class="text-sm text-gray-900">
							{{ normalizedShowcase.currency.name }} ({{ normalizedShowcase.currency.code }})
						</dd>
					</div>
				</dl>
			</div>

			<!-- Section: Customer Profile -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-male" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Customer Information') }}
					</h3>
				</div>
				<dl class="px-6 py-4">
					<!-- Customer State -->
					<div class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Status') }}</dt>
						<dd class="capitalize">
							<Tag :label="mapPaymentState" :theme="getStateTheme(mapPaymentState)" />
						</dd>
					</div>

					<!-- Contact name -->
					<div v-if="normalizedShowcase.customer?.contact_name"
						class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-male" class="text-gray-400" />
							{{ trans('Contact name') }}
						</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.customer.contact_name }}</dd>
					</div>

					<!-- Company name -->
					<div v-if="normalizedShowcase.customer?.company_name"
						class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-building" class="text-gray-400" />
							{{ trans('Company name') }}
						</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.customer.company_name }}</dd>
					</div>

					<!-- Email -->
					<div v-if="normalizedShowcase.customer?.email"
						class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" />
							{{ trans('Email') }}
						</dt>
						<dd class="text-sm text-blue-600 hover:text-blue-800">
							<a :href="`mailto:${normalizedShowcase.customer.email}`">
								{{ normalizedShowcase.customer.email }}
							</a>
						</dd>
					</div>

					<!-- Phone -->
					<div v-if="normalizedShowcase.customer?.phone"
						class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" />
							{{ trans('Phone') }}
						</dt>
						<dd class="text-sm text-blue-600 hover:text-blue-800">
							<a :href="`tel:${normalizedShowcase.customer.phone}`">
								{{ normalizedShowcase.customer.phone }}
							</a>
						</dd>
					</div>

					<!-- Address -->
					<div v-if="normalizedShowcase.customer?.address" class="py-3">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2 mb-2">
							<FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" />
							{{ trans('Address') }}
						</dt>
						<dd class="text-sm text-gray-900 bg-gray-50 p-3 rounded-lg">
							<div v-if="normalizedShowcase.customer.address.address_line_1" class="font-medium">
								{{ normalizedShowcase.customer.address.address_line_1 }}
							</div>
							<div v-if="normalizedShowcase.customer.address.address_line_2">
								{{ normalizedShowcase.customer.address.address_line_2 }}
							</div>
							<div v-if="normalizedShowcase.customer.address.locality">
								{{ normalizedShowcase.customer.address.locality }}
							</div>
							<div v-if="normalizedShowcase.customer.address.postal_code">
								{{ normalizedShowcase.customer.address.postal_code }}
							</div>
							<div v-if="normalizedShowcase.customer.address.country">
								{{ normalizedShowcase.customer.address.country.name }}
							</div>
						</dd>
					</div>

					<!-- Created at -->
					<div v-if="normalizedShowcase.customer?.created_at" class="flex items-center justify-between py-3">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
							{{ trans('Created at') }}
						</dt>
						<dd class="text-sm text-gray-900">
							<time :datetime="normalizedShowcase.customer.created_at">
								{{ useFormatTime(normalizedShowcase.customer.created_at) }}
							</time>
						</dd>
					</div>
				</dl>
			</div>
		</div>

		<!-- Column 2: Order & Account Information -->
		<div class="space-y-6">
			<!-- Section: Order Information -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-file-invoice" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Order Information') }}
					</h3>
				</div>

				<!-- If parent_data exists -->
				<div v-if="normalizedShowcase.parentData" class="px-6 py-4 space-y-4">
					<!-- Order Reference -->
					<div class="flex items-center justify-between p-4 rounded-lg"
						:style="{ 
						background: `linear-gradient(to right, ${themeColors.buttonBg}, #ffffff)`
						}">
						<dt class="text-sm font-medium" :style="{ color: themeColors.buttonText }">{{ trans('Order Reference') }}</dt>
						<dd class="text-lg font-bold" :style="{ color: themeColors.primaryBg }">{{ normalizedShowcase.parentData.reference }}</dd>
					</div>

					<!-- Order Status -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Order Status') }}</dt>
						<dd>
							<Tag :label="normalizedShowcase.parentData.state_label"
								:theme="getStateTheme(normalizedShowcase.parentData.state)" />
						</dd>
					</div>

					<!-- Total Amount -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Total Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.parentData.total_amount) }}
						</dd>
					</div>

					<!-- Payment Status -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon
								:icon="normalizedShowcase.parentData.is_fully_paid ? 'fal fa-check-circle' : 'fal fa-times-circle'"
								:class="normalizedShowcase.parentData.is_fully_paid ? 'text-green-500' : 'text-red-500'" />
							{{ trans('Payment Status') }}
						</dt>
						<dd class="text-sm font-medium"
							:class="normalizedShowcase.parentData.is_fully_paid ? 'text-green-700' : 'text-red-700'">
							{{ normalizedShowcase.parentData.is_fully_paid ? trans('Fully Paid') : trans('Unpaid') }}
						</dd>
					</div>

					<!-- Net Amount -->
					<div class="flex items-center justify-between py-2 border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Net Amount') }}</dt>
						<dd class="text-sm text-gray-900">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.parentData.net_amount) }}
						</dd>
					</div>

					<!-- Payment Amount -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Amount') }}</dt>
						<dd class="text-sm text-gray-900">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.parentData.payment_amount) }}
						</dd>
					</div>

					<!-- Order Dates -->
					<div class="border-t border-gray-200 pt-4 space-y-3">
						<div v-if="normalizedShowcase.parentData.created_at" class="flex items-center justify-between">
							<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
								<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
								{{ trans('Created') }}
							</dt>
							<dd class="text-sm text-gray-900">
								{{ useFormatTime(normalizedShowcase.parentData.created_at) }}
							</dd>
						</div>

						<div v-if="normalizedShowcase.parentData.cancelled_at"
							class="flex items-center justify-between">
							<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
								<FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400" />
								{{ trans('Cancelled') }}
							</dt>
							<dd class="text-sm text-red-600">
								{{ useFormatTime(normalizedShowcase.parentData.cancelled_at) }}
							</dd>
						</div>
					</div>
				</div>

				<!-- If no parent_data -->
				<div v-else class="px-6 py-8">
					<div class="text-center">
						<FontAwesomeIcon icon="fal fa-file-invoice" class="text-gray-300 text-4xl mb-4" />
						<p class="text-gray-500">{{ trans('No order associated with this payment') }}</p>
					</div>
				</div>
			</div>

			<!-- Section: Credit Transaction Information -->
			<div v-if="normalizedShowcase.creditTransaction"
				class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-credit-card" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Credit Transaction') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Transaction ID -->
					<div class="flex items-center justify-between p-4 rounded-lg"
						:style="{ backgroundColor: themeColors.buttonBg }">
						<dt class="text-sm font-medium" :style="{ color: themeColors.buttonText }">{{ trans('Transaction ID') }}</dt>
						<dd class="text-lg font-bold" :style="{ color: themeColors.buttonText }">#{{ normalizedShowcase.creditTransaction.id }}</dd>
					</div>

					<!-- Transaction Type -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Type') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.creditTransaction.type }}</dd>
					</div>

					<!-- Transaction Amount -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.creditTransaction.amount) }}
						</dd>
					</div>

					<!-- Running Amount -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Running Amount') }}</dt>
						<dd class="text-sm text-gray-900">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
								normalizedShowcase.creditTransaction.running_amount) }}
						</dd>
					</div>

					<!-- Payment ID -->
					<div class="flex items-center justify-between py-2 border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment ID') }}</dt>
						<dd class="text-sm" :style="{ color: themeColors.primaryBg }">#{{ normalizedShowcase.creditTransaction.payment_id }}</dd>
					</div>

					<!-- Payment Reference -->
					<div v-if="normalizedShowcase.creditTransaction.payment_reference"
						class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Reference') }}</dt>
						<dd class="text-sm" :style="{ color: themeColors.primaryBg }">{{ normalizedShowcase.creditTransaction.payment_reference }}</dd>
					</div>

					<!-- Payment Type -->
					<div v-if="normalizedShowcase.creditTransaction.payment_type"
						class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Type') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.creditTransaction.payment_type }}</dd>
					</div>

					<!-- Transaction Date -->
					<div class="flex items-center justify-between py-2 border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
							{{ trans('Transaction Date') }}
						</dt>
						<dd class="text-sm text-gray-900">
							{{ useFormatTime(normalizedShowcase.creditTransaction.created_at) }}
						</dd>
					</div>
				</dl>
			</div>

			<!-- Section: Payment Account Information -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-building" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Account Information') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Payment Account -->
					<div class="p-4 rounded-lg"
						:style="{ 
	background: `linear-gradient(to right, ${themeColors.buttonBg}, #ffffff)`
						}">
						<dt class="text-sm font-medium mb-2" :style="{ color: themeColors.buttonText }">{{ trans('Payment Account') }}</dt>
						<dd class="space-y-1">
							<div class="text-lg font-semibold" :style="{ color: themeColors.buttonText }">{{ normalizedShowcase.paymentAccount.name }}</div>
							<div class="text-sm" :style="{ color: themeColors.buttonText }">{{ trans('Code') }}: {{ normalizedShowcase.paymentAccount.code }}</div>
						</dd>
					</div>

					<!-- Service Provider -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Service Provider') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.paymentServiceProvider.name }}</dd>
					</div>

					<!-- Provider Code -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Provider Code') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.paymentServiceProvider.code }}</dd>
					</div>

					<!-- Total Payments -->
					<div class="flex items-center justify-between py-2 border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Total Payments') }}</dt>
						<dd class="text-2xl font-bold" :style="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.paymentAccount.number_payments }}
						</dd>
					</div>
				</dl>
			</div>

			<!-- Section: Credit Transaction Status -->
			<div v-if="normalizedShowcase.credit_transaction === null"
				class="rounded-lg bg-yellow-50 border-l-4 border-yellow-400 p-4">
				<div class="flex">
					<div class="ml-3">
						<p class="text-sm text-yellow-700">
							<strong>{{ trans('Info') }}:</strong> {{ trans('No credit transaction associated with this payment') }}.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Refund Modal -->
	<div v-if="showRefundModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
		<div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
			<!-- Modal Header -->
			<div class="flex items-center justify-between p-6 border-b border-gray-200">
				<h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
					<FontAwesomeIcon icon="fal fa-undo" class="text-red-600" />
					{{ trans('Process Payment Refund') }}
				</h3>
				<button @click="closeRefundModal" class="text-gray-400 hover:text-gray-600 transition-colors">
					<FontAwesomeIcon icon="fal fa-times" class="w-5 h-5" />
				</button>
			</div>

			<!-- Modal Body -->
			<div class="p-6 space-y-4">
				<!-- Error Messages -->
				<div v-if="Object.keys(refundErrors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4">
					<div class="flex">
						<FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400 w-5 h-5 mt-0.5 mr-3" />
						<div>
							<h4 class="text-sm font-medium text-red-800 mb-1">{{ trans('Please correct the following errors') }}:</h4>
							<ul class="text-sm text-red-700 space-y-1">
								<li v-for="(error, field) in refundErrors" :key="field">
									<span v-if="Array.isArray(error)">{{ error[0] }}</span>
									<span v-else>{{ error }}</span>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<!-- Original Payment Info -->
				<div class="bg-gray-50 rounded-lg p-4">
					<h4 class="text-sm font-medium text-gray-700 mb-2">{{ trans('Original Payment') }}</h4>
					<div class="flex justify-between items-center">
						<span class="text-sm text-gray-600">{{ trans('Amount') }}:</span>
						<span class="font-semibold text-gray-900">
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
							<input v-model="refundType" type="radio" value="partial"
								class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
							<span class="ml-2 text-sm text-gray-700">{{ trans('Partial Refund') }}</span>
						</label>
						<label class="flex items-center">
							<input v-model="refundType" type="radio" value="full"
								class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
							<span class="ml-2 text-sm text-gray-700">{{ trans('Full Refund') }}</span>
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
							class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
							:class="{ 'border-red-300 bg-red-50': !validateRefundAmount() && refundAmount }">
						<div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
							<span class="text-gray-500 text-sm">{{ normalizedShowcase.currency.code }}</span>
						</div>
					</div>
					<p v-if="!validateRefundAmount() && refundAmount" class="mt-1 text-sm text-red-600">
						{{ trans('Invalid amount. Maximum refund') }}: {{
							useLocaleStore().currencyFormat(normalizedShowcase.currency.code, maxRefundAmount) }}
					</p>
				</div>

				<!-- Full Refund Amount Display -->
				<div v-if="refundType === 'full'" class="bg-red-50 border border-red-200 rounded-lg p-4">
					<div class="flex justify-between items-center">
						<span class="text-sm font-medium text-red-800">{{ trans('Full Refund Amount') }}:</span>
						<span class="text-lg font-bold text-red-900">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code, maxRefundAmount) }}
						</span>
					</div>
				</div>

				<!-- Refund Reason -->
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-2">
						{{ trans('Refund Reason') }} <span class="text-red-500">*</span>
					</label>
					<textarea v-model="refundReason" rows="3"
						class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
						:placeholder="trans('Please provide a reason for this refund...')"></textarea>
				</div>

				<!-- Refund Summary -->
				<div class="border-t border-gray-200 pt-4">
					<h4 class="text-sm font-medium text-gray-700 mb-3">{{ trans('Refund Summary') }}</h4>
					<div class="bg-red-50 rounded-lg p-4 space-y-2">
						<div class="flex justify-between text-sm">
							<span class="text-gray-600">{{ trans('Type') }}:</span>
							<span class="font-medium">{{ trans('Payment Refund') }}</span>
						</div>
						<div class="flex justify-between text-sm">
							<span class="text-gray-600">{{ trans('Amount') }}:</span>
							<span class="font-medium text-red-700">
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
			<div class="flex items-center justify-end gap-3 p-6 border-t border-gray-200">
				<button @click="closeRefundModal"
					class="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors duration-200"
					:disabled="isProcessingRefund">
					{{ trans('Cancel') }}
				</button>
				<button @click="processRefund"
					:disabled="isProcessingRefund || !refundReason.trim() || (refundType === 'partial' && !validateRefundAmount())"
					class="px-4 py-2 text-white rounded-lg transition-colors duration-200 flex items-center gap-2"
					:class="{
					'cursor-not-allowed opacity-50': isProcessingRefund || !refundReason.trim() || (refundType === 'partial' && !validateRefundAmount())
					}"
					:style="{ 
					backgroundColor: (isProcessingRefund || !refundReason.trim() || (refundType === 'partial' && !validateRefundAmount())) 
						? '#6B7280' 
						: themeColors.buttonBg
					}"
					@mouseover="handleMouseOver"
					@mouseout="handleMouseOut">
					<FontAwesomeIcon v-if="isProcessingRefund" icon="fal fa-sync" class="w-4 h-4 animate-spin" />
					<FontAwesomeIcon v-else icon="fal fa-undo" class="w-4 h-4" />
					{{ isProcessingRefund ? trans('Processing...') : trans('Process Refund') }}
			</button>
			</div>
		</div>
	</div>
</template>