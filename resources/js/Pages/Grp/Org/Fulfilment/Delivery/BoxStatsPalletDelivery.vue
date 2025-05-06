<script setup lang="ts">
import JsBarcode from "jsbarcode"
import { inject, onMounted, ref } from "vue"
import DatePicker from "@vuepic/vue-datepicker"
import { useFormatTime, useDaysLeftFromToday } from "@/Composables/useFormatTime"
import { PalletDelivery, BoxStats } from "@/types/Pallet"
import { capitalize } from "@/Composables/capitalize"
import { trans } from "laravel-vue-i18n"
import Popover from "@/Components/Popover.vue"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { Link, router } from "@inertiajs/vue3"

import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle, faExpandArrows, faHashtag, faFileInvoice } from "@fal"
import { faLevelDown } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue"
// import { useTruncate } from '@/Composables/useTruncate'

library.add(faQuestionCircle, faExpandArrows, faHashtag, faLevelDown, faFileInvoice)

const locale = inject("locale", aikuLocaleStructure)
const props = defineProps<{
	dataPalletDelivery: PalletDelivery
	boxStats: BoxStats
	updateRoute: routeType
}>()

const disableBeforeToday = (date: Date) => {
	const today = new Date()
	// Set time to 00:00:00 for comparison purposes
	today.setHours(0, 0, 0, 0)
	return date < today
}

const isLoadingSetEstimatedDate = ref(false)
const onChangeEstimateDate = async (close: Function) => {
	router.patch(
		route(props.updateRoute.name, props.updateRoute.parameters),
		{
			estimated_delivery_date: props.dataPalletDelivery.estimated_delivery_date,
		},
		{
			onStart: () => (isLoadingSetEstimatedDate.value = true),
			onError: () => {
				notify({
					title: "Failed",
					text: "Failed to update the Delivery date, try again.",
					type: "error",
				})
			},
			onSuccess: () => close(),
			onFinish: () => (isLoadingSetEstimatedDate.value = false),
		}
	)
}

onMounted(() => {
	JsBarcode("#palletDeliveryBarcode", route().v().params.palletDelivery, {
		lineColor: "rgb(41 37 36)",
		width: 2,
		height: 50,
		displayValue: false,
	})
})

// Section: Edit Customer Reference
const cloneCustomerReference = ref(props.dataPalletDelivery.customer_reference)
const showModalEdit = ref(false)
const isLoadingSaveCustomer = ref(false)
const onUpdateCustomerReference = () => {
	// console.log('eee', cloneCustomerReference.value)
	router.patch(
		route(props.updateRoute.name, props.updateRoute.parameters),
		{
			customer_reference: cloneCustomerReference.value,
		},
		{
			onStart: () => (isLoadingSaveCustomer.value = true),
			onError: () => {
				notify({
					title: trans("Failed"),
					text: trans("Failed to update the Customer reference, try again."),
					type: "error",
				})
			},
			onSuccess: () => {
				;(showModalEdit.value = false),
					notify({
						title: trans("Success"),
						text: trans("Customer reference updated successfully."),
						type: "success",
					}),
					(cloneCustomerReference.value = props.dataPalletDelivery.customer_reference)
			},
			onFinish: () => (isLoadingSaveCustomer.value = false),
		}
	)
}
</script>

<template>
	<div
		class="h-min grid grid-cols-2 sm:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
		<!-- Box: Customer -->
		<BoxStatPallet
			class="py-1 sm:py-2 px-3"
			:label="dataPalletDelivery?.customer_name"
			icon="fal fa-user">
			<!-- Field: Reference -->
			<Link
				as="a"
				v-if="boxStats?.fulfilment_customer?.customer?.reference"
				:href="
					route('grp.org.fulfilments.show.crm.customers.show', [
						route().params.organisation,
						boxStats.fulfilment_customer.fulfilment.slug,
						boxStats.fulfilment_customer.slug,
					])
				"
				class="flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
				<dt v-tooltip="'Company name'" class="flex-none">
					<span class="sr-only">Reference</span>
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-gray-500">{{ boxStats.fulfilment_customer.customer.reference }}</dd>
			</Link>

			<!-- Field: Contact name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.contact_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Contact name'" class="flex-none">
					<span class="sr-only">Contact name</span>
					<FontAwesomeIcon
						icon="fal fa-user"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-gray-500">
					{{ boxStats.fulfilment_customer.customer.contact_name }}
				</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.company_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Company name'" class="flex-none">
					<span class="sr-only">Company name</span>
					<FontAwesomeIcon
						icon="fal fa-building"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-gray-500">
					{{ boxStats.fulfilment_customer.customer.company_name }}
				</dd>
			</div>

			<!-- Field: Email -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.email"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Email')" class="flex-none">
					<span class="sr-only">Email</span>
					<FontAwesomeIcon
						icon="fal fa-envelope"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`mailto:${boxStats.fulfilment_customer?.customer.email}`"
					class="text-gray-500 white w-full truncate"
					>{{ boxStats.fulfilment_customer?.customer.email }}</a
				>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer.phone"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Phone'" class="flex-none">
					<span class="sr-only">Phone</span>
					<FontAwesomeIcon
						icon="fal fa-phone"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-gray-500">{{ boxStats.fulfilment_customer?.customer.phone }}</dd>
			</div>
		</BoxStatPallet>

		<!-- Box: Status -->
		<BoxStatPallet
			class="py-1 sm:py-2 px-3"
			:label="capitalize(dataPalletDelivery?.state)"
			icon="fal fa-truck-couch">
			<!-- Customer reference -->
			<div class="mb-1">
				<PalletEditCustomerReference :dataPalletDelivery :updateRoute />
				<!-- :disabled="dataPalletDelivery?.state !== 'in_process' && dataPalletDelivery?.state !== 'submit'" -->
			</div>

			<!-- Barcode -->
			<div
				class="mb-4 h-full w-full py-1 px-2 flex flex-col bg-gray-100 ring-1 ring-gray-300 rounded items-center">
				<svg id="palletDeliveryBarcode" class="w-full h-full"></svg>
				<div class="text-xxs text-gray-500">
					{{ route().params.palletDelivery }}
				</div>
			</div>

			<!-- <pre>{{ dataPalletDelivery }}</pre> -->
			<div class="flex items-center w-full flex-none gap-x-2 mb-2">
				<dt class="flex-none">
					<span class="sr-only">{{ boxStats?.delivery_state.tooltip }}</span>
					<FontAwesomeIcon
						:icon="boxStats?.delivery_state.icon"
						:class="boxStats?.delivery_state.class"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-gray-500" :class="boxStats?.delivery_state.class">
					{{ boxStats?.delivery_state.tooltip }}
				</dd>
			</div>

			<!-- Set estimated date -->
			<div class="flex items-center w-full gap-x-2">
				<dt v-tooltip="'Estimated received date'" class="flex-none">
					<span class="sr-only">{{ boxStats?.delivery_state.tooltip }}</span>
					<FontAwesomeIcon
						:icon="['fal', 'calendar-day']"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>

				<Popover
					v-if="
						dataPalletDelivery.state === 'in_process' ||
						dataPalletDelivery.state === 'submitted' ||
						dataPalletDelivery.state === 'confirmed'
					"
					position=""
					style="z-index: 20">
					<template #button>
						<div
							v-if="dataPalletDelivery.estimated_delivery_date"
							v-tooltip="
								useDaysLeftFromToday(dataPalletDelivery.estimated_delivery_date)
							"
							class="group text-gray-500 text-left"
							:class="[dataPalletDelivery.state === 'in_process' ? 'underline' : '']">
							{{ useFormatTime(dataPalletDelivery?.estimated_delivery_date) }}
							<FontAwesomeIcon
								icon="fal fa-pencil"
								size="sm"
								class="text-gray-400 group-hover:text-gray-600"
								fixed-width
								aria-hidden="true" />
						</div>

						<div v-else class="text-gray-500 hover:text-gray-600 underline">
							{{ trans("Set estimated date") }}
						</div>
					</template>

					<template #content="{ close }">
						<DatePicker
							v-model="dataPalletDelivery.estimated_delivery_date"
							@update:modelValue="() => onChangeEstimateDate(close)"
							inline
							auto-apply
							:xxdisabled-dates="disableBeforeToday"
							:enable-time-picker="false" />
						<div
							v-if="isLoadingSetEstimatedDate"
							class="absolute inset-0 bg-white/70 flex items-center justify-center">
							<LoadingIcon class="text-5xl" />
						</div>
					</template>
				</Popover>

				<div v-else class="text-gray-500">
					<span v-if="dataPalletDelivery.estimated_delivery_date">{{
						useFormatTime(dataPalletDelivery.estimated_delivery_date)
					}}</span>
					<span v-else class="text-gray-400">{{
						trans("Estimated date is not set")
					}}</span>
				</div>
			</div>

			<!-- Stats: count Pallets, Services, Physical Goods -->
			<div class="border-t border-gray-300 mt-2 pt-2 space-y-0.5">
				<div v-tooltip="trans('Count of pallets')" class="w-fit flex items-center gap-x-3">
					<dt class="flex-none">
						<FontAwesomeIcon
							icon="fal fa-pallet"
							size="xs"
							class="text-gray-400"
							fixed-width
							aria-hidden="true" />
					</dt>
					<dd class="text-gray-500 text-base font-medium tabular-nums">
						{{ dataPalletDelivery.number_pallets }}
						<span class="text-gray-400 font-normal">{{
							dataPalletDelivery.number_pallets > 1
								? trans("Pallets")
								: trans("Pallet")
						}}</span>
					</dd>
				</div>
				<div
					v-if="dataPalletDelivery.number_boxes != 0"
					v-tooltip="trans('Count of boxes')"
					class="w-fit flex items-center gap-x-3">
					<dt class="flex-none">
						<FontAwesomeIcon
							icon="fal fa-box"
							size="xs"
							class="text-gray-400"
							fixed-width
							aria-hidden="true" />
					</dt>
					<dd class="text-gray-500 text-base font-medium tabular-nums">
						{{ dataPalletDelivery.number_boxes }}
						<span class="text-gray-400 font-normal">{{
							dataPalletDelivery.number_boxes > 1 ? trans("Boxes") : trans("Box")
						}}</span>
					</dd>
				</div>
				<div
					v-if="dataPalletDelivery.number_oversizes != 0"
					v-tooltip="trans('Count of oversizes')"
					class="w-fit flex items-center gap-x-3">
					<dt class="flex-none">
						<FontAwesomeIcon
							icon="fal fa-expand-arrows"
							size="xs"
							class="text-gray-400"
							fixed-width
							aria-hidden="true" />
					</dt>
					<dd class="text-gray-500 text-base font-medium tabular-nums">
						{{ dataPalletDelivery.number_oversizes }}
						<span class="text-gray-400 font-normal">{{
							dataPalletDelivery.number_oversizes > 1
								? trans("Oversizes")
								: trans("Oversize")
						}}</span>
					</dd>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box: Order summary -->
		<BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
			<section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
				<!-- <h2 id="summary-heading" class="text-lg font-medium">Order summary</h2> -->
				<div v-if="boxStats?.invoice" class="mb-1 text-sm text-gray-500 ">
					<FontAwesomeIcon
						icon="fal fa-file-invoice"
						size="xs"
						fixed-width
						class="text-gray-400"
						:tooltip="'invoice'"
						aria-hidden="true" />
					<Link
						:href="
							route(
								boxStats?.invoice?.route?.name,
								boxStats?.invoice?.route?.parameters
							)
						"
						method="get"
						v-tooltip="'Recurring Bill'"
						class="primaryLink">
						{{ boxStats?.invoice?.reference }}
					</Link>
				</div>
				<div class="text-gray-500 mb-2" v-if="boxStats?.recurring_bill">
					<Link
						:href="
							route(
								boxStats?.recurring_bill?.route?.name,
								boxStats?.recurring_bill?.route?.parameters
							)
						"
						method="get"
						v-tooltip="'Recurring Bill'"
						class="primaryLink">
						{{ boxStats?.recurring_bill?.reference }}
					</Link>
				</div>
				<OrderSummary
					:order_summary="boxStats?.order_summary"
					:currency_code="boxStats?.order_summary.currency.data.code" />

				<!-- <div class="mt-6">
                    <button type="submit"
                        class="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-50">Checkout</button>
                </div> -->
			</section>
		</BoxStatPallet>
	</div>
</template>
