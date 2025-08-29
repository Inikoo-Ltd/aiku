<script setup lang="ts">
import { computed, ref, inject } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink } from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle, faUndo, faTimes, faEye } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Tag from "@/Components/Tag.vue"
import { useLocaleStore } from "@/Stores/locale"
import { usePage } from '@inertiajs/vue3'

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle, faUndo, faTimes, faEye)

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
const page = usePage()

// Check if we're in a customer menu context
const isInCustomerMenu = computed(() => {
	const currentRoute = page.url
	return currentRoute.includes('/customers/')
})

// Modal state for Account Information
const isAccountModalVisible = ref(false)

// Functions to control modal
const openAccountModal = () => {
	isAccountModalVisible.value = true
}

const closeAccountModal = () => {
	isAccountModalVisible.value = false
}

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
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Payment Amount -->
					<div class="flex items-center justify-between rounded-lg">
						<dt class="text-sm font-medium">
							{{ isRefund ? trans('Refund Amount') : trans('Payment Amount') }}
						</dt>
						<dd class="text-lg font-semibold"
							:style="{ color: isRefund ? '#e00909' : themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.amount) }}
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
							{{ trans('Payment Method') }}
						</dt>
						<dd class="text-sm text-gray-900 flex items-center gap-2">
							<button @click="openAccountModal"
								class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
								v-tooltip="trans('View Account Information')">
								<FontAwesomeIcon icon="fal fa-eye" class="w-4 h-4" />
							</button>
							{{ normalizedShowcase.paymentServiceProvider.name }}
						</dd>
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
			<div v-if="!isInCustomerMenu" class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-male" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Customer Information') }}
					</h3>
				</div>
				<dl class="px-6 py-4">

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
				</dl>
			</div>
		</div>

		<!-- Column 2: Order & Account Information -->
		<div class="space-y-6">
			<!-- Section: Order Information -->
			<div v-if="normalizedShowcase.parentData" class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-file-invoice" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Order Information') }}
					</h3>
				</div>

				<!-- If parent_data exists -->
				<div v-if="normalizedShowcase.parentData" class="px-6 py-4 space-y-4">
					<!-- Order Reference -->
					<div class="flex items-center justify-between rounded-lg">
						<dt class="text-sm font-medium">{{ trans('Order Reference') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">{{
							normalizedShowcase.parentData.reference }}</dd>
					</div>

					<!-- Total Amount -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Total Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.parentData.total_amount) }}
						</dd>
					</div>

					<!-- Order Status -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Order Status') }}</dt>
						<dd>
							<Tag :label="normalizedShowcase.parentData.state_label"
								:theme="getStateTheme(normalizedShowcase.parentData.state)" />
						</dd>
					</div>

					<!-- Payment Status -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">

							{{ trans('Payment Status') }}
							<FontAwesomeIcon
								:icon="normalizedShowcase.parentData.is_fully_paid ? 'fal fa-check-circle' : 'fal fa-times-circle'"
								:class="normalizedShowcase.parentData.is_fully_paid ? 'text-green-500' : 'text-red-500'" />
						</dt>
						<dd class="text-sm font-medium"
							:class="normalizedShowcase.parentData.is_fully_paid ? 'text-green-700' : 'text-red-700'">
							{{ normalizedShowcase.parentData.is_fully_paid ? trans('Fully Paid') : trans('Unpaid') }}
						</dd>
					</div>

					<!-- Net Amount -->
					<div class="flex items-center justify-between border-t border-gray-200 pt-4">
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
								{{ useFormatTime(normalizedShowcase.parentData.created_at, {
								formatTime: 'hm'
								}) }}
							</dd>
						</div>

						<div v-if="normalizedShowcase.parentData.cancelled_at"
							class="flex items-center justify-between">
							<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
								<FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400" />
								{{ trans('Cancelled') }}
							</dt>
							<dd class="text-sm text-red-600">
								{{ useFormatTime(normalizedShowcase.parentData.cancelled_at, {
								formatTime: 'hm'
								}) }}
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
					<div class="flex items-center justify-between rounded-lg">
						<dt class="text-sm font-medium">{{ trans('Payment Reference') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.buttonBg }">#{{
							normalizedShowcase.creditTransaction.payment_reference }}</dd>
					</div>

					<!-- Transaction Amount -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.creditTransaction.amount) }}
						</dd>
					</div>

					<!-- Transaction Type -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Type') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.creditTransaction.type }}</dd>
					</div>

					<!-- Transaction Date -->
					<div class="flex items-center justify-between border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
							{{ trans('Transaction Date') }}
						</dt>
						<dd class="text-sm text-gray-900">
							{{ useFormatTime(normalizedShowcase.creditTransaction.created_at, {
							formatTime: 'hm'
							}) }}
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
							<strong>{{ trans('Info') }}:</strong> {{ trans('No credit transaction associated with thispayment') }}.
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Account Information Modal -->
	<div v-if="isAccountModalVisible"
		class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50" :style="dynamicStyles">
		<div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
			<!-- Modal Header -->
			<div class="flex items-center justify-between p-6 border-b">
				<h3 class="text-lg font-semibold flex items-center gap-2">
					<FontAwesomeIcon icon="fal fa-building" />
					{{ trans('Account Information') }}
				</h3>
				<button @click="closeAccountModal" class="hover:opacity-70 transition-opacity">
					<FontAwesomeIcon icon="fal fa-times" class="w-5 h-5" />
				</button>
			</div>

			<!-- Modal Body -->
			<div class="p-6">
				<dl class="space-y-4">
					<!-- Payment Account -->
					<div class="p-4 rounded-lg" :style="{
						background: `linear-gradient(to right, ${themeColors.buttonBg}, #ffffff)`
					}">
						<dt class="text-sm font-medium mb-2" :style="{ color: themeColors.buttonText }">{{
							trans('Payment Account') }}</dt>
						<dd class="space-y-1">
							<div class="text-lg font-semibold" :style="{ color: themeColors.buttonText }">{{
								normalizedShowcase.paymentAccount.name }}</div>
							<div class="text-sm" :style="{ color: themeColors.buttonText }">{{ trans('Code') }}: {{
								normalizedShowcase.paymentAccount.code }}</div>
						</dd>
					</div>

					<!-- Service Provider -->
					<div class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							{{ trans('Service Provider') }}
						</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.paymentServiceProvider.name }}</dd>
					</div>

					<!-- Provider Code -->
					<div class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							{{ trans('Provider Code') }}
						</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.paymentServiceProvider.code }}</dd>
					</div>
				</dl>
			</div>

			<!-- Modal Footer -->
			<div class="flex items-center justify-end py-4 px-6 border-gray-200">
				<button @click="closeAccountModal"
					class="px-4 py-2 rounded-md transition-colors duration-200 hover:opacity-80 text-sm" :style="{
						backgroundColor: themeColors.buttonBg,
						color: themeColors.buttonText
					}">
					{{ trans('Close') }}
				</button>
			</div>
		</div>
	</div>

</template>
