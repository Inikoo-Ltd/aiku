<script setup lang="ts">
import { computed } from "vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLink } from '@far'
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import Tag from "@/Components/Tag.vue"
import { useLocaleStore } from "@/Stores/locale"

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faMoneyBillWave, faBuilding, faCreditCard, faFileInvoice, faCheckCircle, faTimesCircle)

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
}

const props = defineProps<{
	data: Showcase
	tab: string
}>()

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

console.log(props.data)
</script>

<template>
	<div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">
		<!-- Column 1: Payment & Customer Information -->
		<div class="space-y-6">
			<!-- Section: Payment Summary -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium text-gray-900 flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-money-bill-wave" class="text-blue-600" />
						{{ trans('Payment Summary') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Payment Amount -->
					<div class="flex items-center justify-between p-4 bg-gradient-to-r rounded-lg"
						:class="isRefund ? 'from-red-50 to-pink-50' : 'from-blue-50 to-indigo-50'">
						<dt class="text-sm font-medium text-gray-600">
							{{ isRefund ? trans('Refund Amount') : trans('Payment Amount') }}
						</dt>
						<dd class="text-2xl font-bold" :class="isRefund ? 'text-red-600' : 'text-blue-600'">
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
							<FontAwesomeIcon icon="fal fa-credit-card" class="text-gray-400" />
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
						<FontAwesomeIcon icon="fal fa-male" class="text-green-600" />
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
						<FontAwesomeIcon icon="fal fa-file-invoice" class="text-orange-600" />
						{{ trans('Order Information') }}
					</h3>
				</div>

				<!-- If parent_data exists -->
				<div v-if="normalizedShowcase.parentData" class="px-6 py-4 space-y-4">
					<!-- Order Reference -->
					<div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Order Reference') }}</dt>
						<dd class="text-lg font-bold text-gray-900">{{ normalizedShowcase.parentData.reference }}</dd>
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
						<dd class="text-lg font-semibold text-gray-900">
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
						<FontAwesomeIcon icon="fal fa-credit-card" class="text-indigo-600" />
						{{ trans('Credit Transaction') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Transaction ID -->
					<div class="flex items-center justify-between p-4 bg-indigo-50 rounded-lg">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction ID') }}</dt>
						<dd class="text-lg font-bold text-indigo-700">#{{ normalizedShowcase.creditTransaction.id }}
						</dd>
					</div>

					<!-- Transaction Type -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Type') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.creditTransaction.type }}</dd>
					</div>

					<!-- Transaction Amount -->
					<div class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Transaction Amount') }}</dt>
						<dd class="text-lg font-semibold text-gray-900">
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
						<dd class="text-sm text-gray-900">#{{ normalizedShowcase.creditTransaction.payment_id }}</dd>
					</div>

					<!-- Payment Reference -->
					<div v-if="normalizedShowcase.creditTransaction.payment_reference"
						class="flex items-center justify-between py-2">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Reference') }}</dt>
						<dd class="text-sm text-gray-900">{{ normalizedShowcase.creditTransaction.payment_reference }}
						</dd>
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
						<FontAwesomeIcon icon="fal fa-building" class="text-purple-600" />
						{{ trans('Account Information') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Payment Account -->
					<div class="p-4 bg-purple-50 rounded-lg">
						<dt class="text-sm font-medium text-gray-600 mb-2">{{ trans('Payment Account') }}</dt>
						<dd class="space-y-1">
							<div class="text-lg font-semibold text-purple-700">{{ normalizedShowcase.paymentAccount.name
								}}</div>
							<div class="text-sm text-gray-500">{{ trans('Code') }}: {{
								normalizedShowcase.paymentAccount.code }}</div>
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
						<dd class="text-2xl font-bold text-purple-700">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
									normalizedShowcase.paymentAccount.number_payments) }}
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
</template>