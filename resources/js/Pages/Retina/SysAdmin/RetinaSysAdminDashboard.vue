<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Stats from "@/Components/DataDisplay/Stats.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PalletCustomer, FulfilmentCustomerStats } from "@/types/Pallet"
import { computed, inject, ref } from "vue"
import CustomerShowcaseStats from "@/Components/Showcases/Grp/CustomerShowcaseStats.vue"

import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import TabSelector from "@/Components/Elements/TabSelector.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Link } from "@inertiajs/vue3"
import Tag from "@/Components/Tag.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Dialog from "primevue/dialog"
import { get } from "lodash-es"
import ButtonPrimeVue from "primevue/button"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLink, faLongArrowRight } from "@far"
import {
	faArrowAltFromBottom,
	faArrowAltFromTop,
	faPencil,
	faWallet,
	faSync,
	faCalendarAlt,
	faEnvelope,
	faPhone,
	faChevronRight,
	faExternalLink,
	faMapMarkerAlt,
	faAddressCard,
} from "@fal"
// import Modal from '@/Components/Utils/Modal.vue'
import { Address, AddressManagement } from "@/types/PureComponent/Address"
// import ModalAddress from '@/Components/Utils/ModalAddress.vue'
import CountUp from "vue-countup-v3"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"

import { faCheck } from "@fas"
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue"
import Modal from "@/Components/Utils/Modal.vue"

library.add(
	faWallet,
	faLink,
	faSync,
	faCalendarAlt,
	faEnvelope,
	faPhone,
	faChevronRight,
	faExternalLink,
	faMapMarkerAlt,
	faAddressCard,
	faLongArrowRight,
	faCheck,
	faArrowAltFromBottom,
	faArrowAltFromTop
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	addresses: AddressManagement
	address_update_route: routeType
	balance: {
		current: number
		credit_transactions: number
	}
	currency_code: string
    address_management: {
        updateRoute: routeType
        addresses: AddressManagement
        address_update_route: routeType,
        address_modal_title: string
    }
	// customer: PalletCustomer
	fulfilment_customer: {
		radioTabs: {
			[key: string]: boolean
		}
		number_pallets?: number
		number_pallets_state_received?: number
		number_stored_items?: number
		number_pallets_deliveries?: number
		number_pallets_returns?: number
		customer: {
			address: Address
		}
	}
	updateRoute: routeType
	stats: {
		[key: string]: FulfilmentCustomerStats
	}
	warehouse_summary: {
		[key: string]: number
	}
	webhook: {
		webhook_access_key: string | null
		domain: string
		route: routeType
	}
	rental_agreement: {
		stats?: {
			data: {
				id: number
				slug: string
				reference: string
				state: string
				billing_cycle: string
				pallets_limit: number
				route: routeType
			}
		}
		createRoute: routeType
		updated_at: string
	}
	recurring_bill: {
		route: routeType
		status: string // 'former' and 'current'
		start_date: string
		end_date: string
		total: number
		currency_code: string
	}
	status: string
	additional_data: {
		product: String
		size_and_weight: string
		shipments_per_week: string
	}
	approveRoute: routeType
	tab: string
	customer: PalletCustomer
	statss: {}
}>()

console.log(props)

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", layoutStructure)

// Tabs radio: v-model
// const radioValue = ref<string[]>(Object.keys(props.data.fulfilment_customer.radioTabs).filter(key => props.data.fulfilment_customer.radioTabs[key]))
const radioValue = computed(() => {
	return Object.keys(props.fulfilment_customer.radioTabs).filter(
		(key) => props.fulfilment_customer.radioTabs[key]
	)
})

// Tabs radio: options
const optionRadio = [
	{
		value: "pallets_storage",
		label: trans("Pallet Storage"),
	},
	{
		value: "items_storage",
		label: trans("Dropshipping"),
	},
	/*    {
            value: 'dropshipping',
            label: trans('Dropshipping')
        },*/
	{
		value: "space_rental",
		label: trans("Space (Parking)"),
	},
]

const isLoadingButtonRentalAgreement = ref(false)
const isLoading = ref<string | boolean>(false)
const visible = ref(false)
const _CustomerDataForm = ref()
const isModalAddress = ref(false);

const isModalUploadOpen = ref(false)
const customerID = ref()
const customerName = ref()

function openRejectedModal(customer: any) {
	customerID.value = customer.id
	customerName.value = customer.name
	isModalUploadOpen.value = true
}
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead"></PageHeading>
	<!--   <Stats class="ml-4 pb-2" :stats="stats" /> -->
	<div class="px-4 py-5 md:px-6 lg:px-8 grid md:grid-cols-2 gap-x-8 lg:gap-x-12 gap-y-3">
		<div class="space-y-3">
			<!-- Section: Radio -->
			<div class="space-y-3 relative w-full max-w-[500px]">
				<!-- Section: Profile box -->
				<!-- <Transition name="headlessui" mode="out-in"> -->
				<div class="col-span-2 grid">
					<div class="w-full">
						<div class="rounded-lg shadow-sm ring-1 ring-gray-300">
							<dl class="flex flex-wrap">
								<!-- Section: Field -->
								<div class="flex flex-col gap-y-2 w-full py-6">
									<!-- Field: Contact name -->
									<div
										v-if="fulfilment_customer.customer.contact_name"
										class="flex items-center w-full flex-none gap-x-4 px-6">
										<dt v-tooltip="'Contact name'" class="flex-none">
											<FontAwesomeIcon
												icon="fal fa-address-card"
												class="text-gray-400"
												fixed-width
												aria-hidden="true" />
										</dt>
										<dd class="text-gray-500">
											{{ fulfilment_customer?.customer.contact_name }}
										</dd>
									</div>

									<!-- Field: Company name -->
									<div
										v-if="fulfilment_customer?.customer.company_name"
										class="flex items-center w-full flex-none gap-x-4 px-6">
										<dt v-tooltip="'Company name'" class="flex-none">
											<FontAwesomeIcon
												icon="fal fa-building"
												class="text-gray-400"
												fixed-width
												aria-hidden="true" />
										</dt>
										<dd class="text-gray-500">
											{{ fulfilment_customer?.customer.company_name }}
										</dd>
									</div>

									<!-- Field: Email -->
									<div
										v-if="fulfilment_customer?.customer?.email"
										class="flex items-center w-full flex-none gap-x-4 px-6">
										<dt v-tooltip="'Email'" class="flex-none">
											<FontAwesomeIcon
												icon="fal fa-envelope"
												class="text-gray-400"
												fixed-width
												aria-hidden="true" />
										</dt>
										<a
											:href="`mailto:${fulfilment_customer?.customer?.email}`"
											v-tooltip="'Click to send email'"
											class="text-gray-500 hover:text-gray-700"
											>{{ fulfilment_customer?.customer?.email }}</a
										>
									</div>

									<!-- Field: Phone -->
									<div
										v-if="fulfilment_customer?.customer?.phone"
										class="flex items-center w-full flex-none gap-x-4 px-6">
										<dt v-tooltip="'Phone'" class="flex-none">
											<FontAwesomeIcon
												icon="fal fa-phone"
												class="text-gray-400"
												fixed-width
												aria-hidden="true" />
										</dt>
										<a
											:href="`tel:${fulfilment_customer?.customer?.phone}`"
											v-tooltip="'Click to make a phone call'"
											class="text-gray-500 hover:text-gray-700"
											>{{ fulfilment_customer?.customer?.phone }}</a
										>
									</div>

									<!-- Field: Address -->
									<div
										v-if="fulfilment_customer?.customer?.address"
										class="flex items w-full flex-none gap-x-4 px-6">
										<dt v-tooltip="'Address'" class="flex-none">
											<FontAwesomeIcon
												icon="fal fa-map-marker-alt"
												class="text-gray-400"
												fixed-width
												aria-hidden="true" />
										</dt>
										<dd class="w-full max-w-96 text-gray-500">
											<div
												class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
												<span
													class=""
													v-html="
														fulfilment_customer?.customer?.address
															.formatted_address
													" />

												<div
													@click="() => (isModalAddress = true)"
													class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
													<span>{{ trans("Edit") }}</span>
												</div>
											</div>
										</dd>
									</div>
								</div>
							</dl>
						</div>
					</div>
				</div>

				<!-- Information -->
				<div
					v-if="
						additional_data.product ||
						additional_data.size_and_weight ||
						additional_data.shipments_per_week
					"
					class="col-span-2 grid">
					<div class="w-full">
						<div class="rounded-lg shadow-lg ring-1 ring-gray-300 bg-white p-6">
							<div class="flex justify-between items-center mb-4">
								<h2 class="text-xl font-semibold text-gray-900">Information</h2>
							</div>
							<div
								class="relative px-5 py-2 ring-1 ring-gray-300 rounded-lg bg-gray-50 shadow-sm space-y-2">
								<div class="text-gray-600">
									<strong class="text-gray-500">Product:</strong>
									{{ additional_data.product }}
								</div>
								<div class="text-gray-600">
									<strong class="text-gray-500">Size & Weight:</strong>
									{{ additional_data.size_and_weight }}
								</div>
								<div class="text-gray-600">
									<strong class="text-gray-500">Shipments Per Week:</strong>
									{{ additional_data.shipments_per_week }}
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Box Group: Pallets -->
				<!-- <CustomerShowcaseStats v-if="rental_agreement?.stats" :stats="stats" /> -->
			</div>
		</div>

		<!-- Section: Radiobox, Recurring bills balance, Rental agreement-->
		<div v-if="status == 'approved'" class="w-full max-w-lg space-y-4 justify-self-end">
			<div
				class="bg-indigo-50 border border-indigo-300 text-gray-700 flex flex-col justify-between px-4 py-5 sm:p-6 rounded-lg tabular-nums">
				<div class="w-full flex justify-between items-center">
					<div>
						<div class="text-base capitalize">
							{{ trans("balance") }}
						</div>
						<div class="text-gray-700/60 text-sm leading-4 font-normal">
							{{ balance?.credit_transactions }} credit transactions
						</div>
					</div>
					<div class="flex flex-col items-end">
						<!-- Amount Display -->
						<div class="text-2xl font-bold">
							<CountUp
								:endVal="balance.current"
								:duration="1.5"
								:scrollSpyOnce="true"
								:options="{
									formattingFn: (value) =>
										locale.currencyFormat(currency_code, value),
								}" />
						</div>
					</div>
				</div>
			</div>

			<TabSelector
				:optionRadio="optionRadio"
				:radioValue="radioValue"
				:updateRoute="updateRoute" />

			<div class="border-t border-gray-200 pt-4 w-full max-w-full">
				<!-- Section: Recurring Bills -->
				<div
					v-if="recurring_bill"
					class="block group relative w-full gap-x-2 border border-gray-300 px-4 py-4 rounded-lg mb-4">
					<div
						class="pl-2 leading-none text-lg"
						:style="{
							borderLeft: `4px solid ${layout.app.theme[0]}`,
						}">
						<div class="block text-lg font-semibold">{{ trans("Current Bill") }}</div>
						<div class="text-sm flex items-center gap-x-1">
							{{
								locale.currencyFormat(
									recurring_bill.currency_code,
									recurring_bill.total || 0
								)
							}}
						</div>
					</div>

					<!-- State Date & End Date -->
					<div class="pl-1 mt-4 w-80 lg:w-96 grid grid-cols-9 gap-x-3">
						<div class="col-span-4 text-sm">
							<div class="text-gray-400">{{ trans("Start date") }}</div>
							<div class="font-medium">
								{{ useFormatTime(recurring_bill?.start_date) }}
							</div>
						</div>

						<div class="flex justify-center items-center">
							<FontAwesomeIcon
								icon="fal fa-chevron-right"
								class="text-xs"
								fixed-width
								aria-hidden="true" />
						</div>

						<div class="col-span-4 text-sm">
							<div class="text-gray-400">{{ trans("End date") }}</div>
							<div class="font-medium">
								{{ useFormatTime(recurring_bill?.end_date) }}
							</div>
						</div>
					</div>

					<div class="pl-1 mt-6 w-full flex items-end justify-between">
						<div class="flex h-fit"></div>
					</div>
				</div>

				<!-- Section: Rental Agreement -->
				<div class="rounded-lg ring-1 ring-gray-300">
					<div
						class="border-b border-gray-300 py-2 px-2 pl-4 flex items-center justify-between">
						<div class="">
							{{ trans("Rental Agreement") }}
							<span
								v-if="rental_agreement?.stats?.data?.reference"
								class="text-gray-400 text-sm"
								>#{{ rental_agreement?.stats?.data?.reference }}</span
							>
						</div>
					</div>

					<!-- Stats -->
					<div v-if="rental_agreement.stats" class="p-5 space-y-2">
						<div class="flex gap-x-1 items-center text-sm">
							<div class="">{{ trans("Last updated") }}:</div>
							<div class="text-gray-500">
								{{ useFormatTime(rental_agreement?.updated_at) }}
							</div>
						</div>
						<div class="flex gap-x-1 items-center text-sm">
							<div class="">{{ trans("Billing Cycle") }}:</div>
							<div class="text-gray-500 capitalize">
								{{ rental_agreement?.stats?.data.billing_cycle }}
							</div>
						</div>
						<div class="flex gap-x-1 items-center text-sm">
							<div class="">{{ trans("Pallet Limit") }}:</div>
							<div class="text-gray-500">
								{{
									rental_agreement?.stats?.data.pallets_limit ||
									`(${trans("No limit")})`
								}}
							</div>
						</div>
					</div>

					<div v-else class="text-center py-16">
						<div class="text-gray-500 text-xs mb-1">
							The rental agreement is not created yet.
						</div>
						<Link
							:href="
								route(
									rental_agreement.createRoute.name,
									rental_agreement.createRoute.parameters
								)
							"
							@start="() => (isLoadingButtonRentalAgreement = true)"
							@cancel="() => (isLoadingButtonRentalAgreement = false)">
							<Button
								type="secondary"
								label="Create Rental Agreement"
								:loading="isLoadingButtonRentalAgreement" />
						</Link>
					</div>
				</div>
			</div>
		</div>
	</div>
	<Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
    <CustomerAddressManagementModal
      :addresses="address_management.addresses"
      :updateRoute="address_management.address_update_route"
    />
  </Modal>
	<!--  <div class="grid ml-4 grid-cols-1 gap-5 sm:grid-cols-3">
        <div
            class="h-fit bg-slate-50 border border-slate-200 text-retina-600 p-6 flex flex-col justify-between rounded-lg shadow overflow-hidden">
            <div class="w-full">
                <h2 v-if="customer?.name" class="text-3xl font-bold">{{ customer?.name }}</h2>
                <h2 v-else class="text-3xl font-light italic brightness-75">
                    {{ trans("No name") }}
                </h2>
                <div class="text-lg">
                    {{ customer?.shop }}
                </div>
            </div>

            <div class="mt-4 space-y-3 text-sm text-slate-500">
                <div class="border-l-2 border-slate-500 pl-4">
                    <h3 class="font-light">Member since</h3>
                    <address class="text-base font-medium not-italic text-gray-600">
                        <p>{{ useFormatTime(customer?.created_at) || "-" }}</p>
                    </address>
                </div>

                <div class="border-l-2 border-slate-500 pl-4">
                    <h3 class="font-light">{{ trans("Billing Cycle") }}</h3>
                    <address class="text-base font-medium not-italic text-gray-600 capitalize">
                        <p>{{ rental_agreement?.billing_cycle }}</p>
                    </address>
                </div>

                <div class="border-l-2 border-slate-500 pl-4">
                    <h3 class="font-light">{{ trans("Pallet Limit") }}</h3>
                    <address class="text-base font-medium not-italic text-gray-600">
                        <p>{{ rental_agreement?.pallets_limit || `(${trans("No limit")})` }}</p>
                    </address>
                </div>
            </div>
        </div>
    </div> -->
</template>
