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
import { Link, usePage } from '@inertiajs/vue3'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import RecommendationCustomerRecentlyBoughtSlideIris from "@/Components/Iris/Recommendations/RecommendationCustomerRecentlyBoughtSlideIris.vue"
import Icon from "@/Components/Icon.vue"
import { faSquareArrowUpRight } from "@fortawesome/free-solid-svg-icons"

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
	shop_slug: string
	organisation_slug: string
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

const layout = inject('layout', layoutStructure)

const routeInvoice = (invoice) => {
	return route('grp.org.accounting.invoices.show', {
		organisation: layout?.currentParams?.organisation,
		invoice: invoice.slug
	})

}

const routeOrder = (order) => {
	if (!(layout?.currentParams?.organisation && (layout?.currentParams?.shop || order.shop_slug) && order.customer_slug && order.slug)) return '';
	return route('grp.org.shops.show.crm.customers.show.orders.show', {
		organisation: layout?.currentParams?.organisation,
		shop: layout?.currentParams?.shop ?? order.shop_slug,
		customer: order.customer_slug,
		order: order.slug
	})
}

</script>

<template>
	<div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6" :style="dynamicStyles">
		<!-- Column 1: Payment & Customer Information -->
		<div class="space-y-6">
			<!-- Section: Payment Summary -->
			<div class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
					<h3 class="text-lg font-medium flex items-center gap-2">
						{{ trans('Payment Summary') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">

                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
                            <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
                            {{ trans('Date') }}
                        </dt>
                        <dd class="text-sm">
                            {{ useFormatTime(normalizedShowcase.date, {
                            formatTime: 'hm'
                        }) }}
                        </dd>
                    </div>

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
                    <!-- Currency -->
                    <div class="flex items-center justify-between">
                        <dt class="text-sm font-medium text-gray-600">{{ trans('Currency') }}</dt>
                        <dd class="text-sm">
                            {{ normalizedShowcase.currency.name }} ({{ normalizedShowcase.currency.code }})
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
							{{ trans('Payment Service Provider') }}
						</dt>
						<dd class="text-sm flex items-center gap-2">
							<button @click="openAccountModal"
								class="text-gray-400 hover:text-gray-600 transition-colors duration-200"
								v-tooltip="trans('View Account Information')">
								<FontAwesomeIcon icon="fal fa-eye" class="w-4 h-4" />
							</button>
							{{ normalizedShowcase.paymentServiceProvider.name }}
						</dd>
					</div>

                    <!-- Payment Amount -->
                    <div v-if="normalizedShowcase.creditTransaction"  class="flex items-center justify-between rounded-lg">
                        <dt class="text-sm font-medium">

                        </dt>
                        <dd class="text-lg font-semibold">
                            {{trans('This is associated with a credit transaction')}}
                        </dd>
                    </div>


				</dl>
			</div>



		</div>

		<!-- Column 2: Order & Account Information -->
		<div class="space-y-6">
			<!-- Section: Order data -->
			<div v-if="normalizedShowcase.order_data?.data" class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-shopping-cart" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Order Information') }}
					</h3>
					<div>

					</div>
				</div>
				
				<div class="px-6 py-4 space-y-4">
					<!-- Order data: Order Reference -->
					<div class="flex items-center justify-between rounded-lg">
						<dt class="text-sm font-medium">{{ trans('Order Reference') }}</dt>
						<Link :href="routeOrder(normalizedShowcase.order_data.data)" class="text-sm primaryLink" xstyle="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.order_data.data.reference }}
						</Link>
					</div>

					<!-- Order data: Order Status -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Order Status') }}</dt>
						<dd>
							<Icon :data="normalizedShowcase.order_data?.data.state_icon" />
							<!-- <Tag :label="normalizedShowcase.order_data.state_label"
								:theme="getStateTheme(normalizedShowcase.order_data.state)" /> -->
						</dd>
					</div>

					<!-- Order data: Payment Status -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							{{ trans('Payment Status') }}
							<FontAwesomeIcon
								:icon="normalizedShowcase.order_data.data.is_fully_paid ? 'fal fa-check-circle' : 'fal fa-times-circle'"
								:class="normalizedShowcase.order_data.data.is_fully_paid ? 'text-green-500' : 'text-red-500'" />
						</dt>
						<dd class="text-sm font-medium"
							:class="normalizedShowcase.order_data.data.is_fully_paid ? 'text-green-700' : 'text-red-700'">
							{{ normalizedShowcase.order_data.data.is_fully_paid ? trans('Fully Paid') : trans('Unpaid') }}
						</dd>
					</div>

					<!-- Order data: Net Amount -->
					<div class="flex items-center justify-between border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Net Amount') }}</dt>
						<dd class="text-sm">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.order_data.data.net_amount) }}
						</dd>
					</div>

					<!-- Order data: Payment Amount -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Payment Amount') }}</dt>
						<dd class="text-sm">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.order_data.data.payment_amount) }}
						</dd>
					</div>

					<!-- Order data: Total Amount -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Total Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.order_data.data.total_amount) }}
						</dd>
					</div>

					<!-- Order data: Created/Cancelled -->
					<div class="border-t border-gray-200 pt-4 space-y-3">
						<div v-if="normalizedShowcase.order_data.data.created_at" class="flex items-center justify-between">
							<dt v-tooltip="trans('Date of order created')" class="text-sm font-medium text-gray-600 flex items-center gap-2">
								<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
								{{ trans('Created') }}
							</dt>
							<dd class="text-sm">
								{{ useFormatTime(normalizedShowcase.order_data.data.created_at, { formatTime: 'hm' }) }}
							</dd>
						</div>

						<div v-if="normalizedShowcase.order_data.data.cancelled_at"
							class="flex items-center justify-between">
							<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
								<FontAwesomeIcon icon="fal fa-times-circle" class="text-red-400" />
								{{ trans('Cancelled') }}
							</dt>
							<dd class="text-sm text-red-600">
								{{ useFormatTime(normalizedShowcase.order_data.data.cancelled_at, {
								formatTime: 'hm'
								}) }}
							</dd>
						</div>
					</div>
				</div>
			</div>

			<!-- Section: Invoice data -->
			<div v-if="normalizedShowcase.invoice_data" class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-file-invoice-dollar" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Invoice Information') }}
					</h3>
				</div>

				<div class="px-6 py-4 space-y-4">
					<!-- Invoice data: Invoice Reference -->
					<div class="flex items-center justify-between rounded-lg">
						<dt class="text-sm font-medium">{{ trans('Invoice Reference') }}</dt>
						<Link :href="routeInvoice(normalizedShowcase.invoice_data)" class="text-sm primaryLink" xstyle="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.invoice_data.reference }}
						</Link>
					</div>
					
					<!-- Invoice data: Paid at -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Paid at') }}</dt>
						<dd class="text-sm">
							{{ useFormatTime(normalizedShowcase.invoice_data.paid_at, { formatTime: 'hm' }) }}
						</dd>
					</div>

					<!-- Invoice data: Net Amount -->
					<div class="flex items-center justify-between border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Net Amount') }}</dt>
						<dd class="text-sm">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code,
							normalizedShowcase.invoice_data.net_amount) }}
						</dd>
					</div>

					<!-- Invoice data: Total Amount -->
					<div class="flex items-center justify-between">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Total Amount') }}</dt>
						<dd class="text-lg font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ useLocaleStore().currencyFormat(normalizedShowcase.currency.code, normalizedShowcase.invoice_data.total_amount) }}
						</dd>
					</div>
				</div>
			</div>

			<div v-if="normalizedShowcase.creditTransaction?.type == 'Top up' && normalizedShowcase.customer.name" class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200 flex">
					<h3 class="text-lg font-medium flex items-center gap-2  w-full">
						<FontAwesomeIcon icon="fal fa-user" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Associated Customer Detail') }}
					</h3>
					<Link v-if=" normalizedShowcase.customer.organisation_slug && normalizedShowcase.customer.shop_slug && normalizedShowcase.customer.slug" 
					:href="route('grp.org.shops.show.crm.customers.show', {
						organisation: normalizedShowcase.customer.organisation_slug, 
						shop: normalizedShowcase.customer.shop_slug, 
						customer: normalizedShowcase.customer.slug 
					})">
						<FontAwesomeIcon :icon="faSquareArrowUpRight" 
						:style="{ color: themeColors.buttonBg }" 
						class="hover:animate-pulse cursor-pointer justify-self-end self-center text-xl" />
					</Link>
				</div>
				<dl class="px-6 py-4 space-y-4">
					<!-- Contact Name -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Contact Name') }}</dt>
						<dd class="text-sm font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.customer.name }}
						</dd>
					</div>
					<!-- Name -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Company Name') }}</dt>
						<dd class="text-sm font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.customer.contact_name }}
						</dd>
					</div>
					<!-- E-Mail -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Email') }}</dt>
						<dd class="text-sm font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.customer.email }}
						</dd>
					</div>
					<!-- Phone -->
					<div class="flex items-center justify-between ">
						<dt class="text-sm font-medium text-gray-600">{{ trans('Phone') }}</dt>
						<dd class="text-sm font-semibold" :style="{ color: themeColors.primaryBg }">
							{{ normalizedShowcase.customer.phone }}
						</dd>
					</div>
				</dl>
			</div>

			<!-- Section: Credit Transaction Information -->
			<div v-if="normalizedShowcase.creditTransaction"
				class="rounded-lg shadow-sm ring-1 ring-gray-900/5 bg-white">
				<div class="px-6 py-4 border-b border-gray-200">
					<h3 class="text-lg font-medium flex items-center gap-2">
						<FontAwesomeIcon icon="fal fa-piggy-bank" :style="{ color: themeColors.buttonBg }" />
						{{ trans('Associted Credit Transaction') }}
					</h3>
				</div>
				<dl class="px-6 py-4 space-y-4">


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
						<dd class="text-sm">{{ normalizedShowcase.creditTransaction.type }}</dd>
					</div>

					<!-- Transaction Date -->
					<div class="flex items-center justify-between border-t border-gray-200 pt-4">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							<FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" />
							{{ trans('Transaction Date') }}
						</dt>
						<dd class="text-sm">
							{{ useFormatTime(normalizedShowcase.creditTransaction.created_at, {
							formatTime: 'hm'
							}) }}
						</dd>
					</div>
				</dl>
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
						<dd class="text-sm">{{ normalizedShowcase.paymentServiceProvider.name }}</dd>
					</div>

					<!-- Provider Code -->
					<div class="flex items-center justify-between py-3 border-b border-gray-100">
						<dt class="text-sm font-medium text-gray-600 flex items-center gap-2">
							{{ trans('Provider Code') }}
						</dt>
						<dd class="text-sm">{{ normalizedShowcase.paymentServiceProvider.code }}</dd>
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
