<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faWeight, faMapPin } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { trans } from "laravel-vue-i18n"
import { inject, ref } from "vue"
import { Address } from "@/types/PureComponent/Address"
import Modal from "@/Components/Utils/Modal.vue"
import AddressEditModal from "@/Components/Utils/AddressEditModal.vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { get } from "lodash"
import { routeType } from "@/types/route"
import { Rating } from "primevue"
import { faStar } from "@fas"
import { faCube, faFolder } from "@far"

const props = defineProps<{
	summary: {
		order_summary: {
			net_amount: string
			gross_amount: string
			tax_amount: string
			goods_amount: string
			services_amount: string
			charges_amount: string
		}
		order_properties: {
			weight: number
			customer_order_number: number
			customer_order_ordinal: string
			customer_order_ordinal_tooltip: string
		}
		products: {}
		delivery_notes: {}
		invoices: {}
		customer: {}
	}
	order: {
		id: number
		is_collection: boolean
	}
	review_summary?: {
		family_review: number
		total_family_review: number
		total_product_review: number
		overall_review: number
		average_review: number
	}
	is_unable_dispatch?: boolean
	contact_address?: Address | null
	isInBasket?: boolean
	updateRoute: routeType
	missed_offers: {}
}>()

const layout = inject("layout", retinaLayoutStructure)
const isModalShippingAddress = ref(false)


</script>

<template>
	<div class="py-4 grid grid-cols-2 md:grid-cols-3 gap-x-4 gap-y-6 px-4">
		<div class="col-span-2 grid grid-cols-2 gap-y-4">
			<!-- Section: Billing Address -->
			<div class="">
				<div class="font-semibold">
					<FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
					{{ trans("Billing Address") }}
				</div>
				<div
					v-if="summary?.customer?.addresses?.billing?.formatted_address"
					class="pl-6 pr-3"
					v-html="summary?.customer?.addresses?.billing?.formatted_address"></div>
				<div v-else class="text-gray-400 italic pl-6 pr-3">
					{{ trans("No billing address") }}
				</div>
			</div>

			<div class="">
				<!-- Field: Collection (toggle) -->

				<div
					v-if="get(props.order, ['is_collection'], false)"
					class="bg-gray-50 w-full text-center py-2 border border-gray-300 rounded">
					<FontAwesomeIcon
						:icon="faMapPin"
						class="text-gray-500"
						fixed-width
						aria-hidden="true" />
					{{ trans("This order is for collection only") }}.
				</div>

				<!-- Section: Delivery Address -->
				<div v-if="!get(props.order, ['is_collection'], false)" class="">
					<div class="font-semibold">
						<FontAwesomeIcon
							:icon="faClipboard"
							class=""
							fixed-width
							aria-hidden="true" />
						{{ trans("Delivery Address") }}
					</div>
					<div
						v-if="summary?.customer?.addresses?.delivery?.formatted_address"
						class="pl-6 pr-3"
						v-html="summary?.customer?.addresses?.delivery?.formatted_address"></div>
					<div v-else class="text-gray-400 italic pl-6 pr-3">
						{{ trans("No delivery address") }}
					</div>

					<div v-if="is_unable_dispatch" class="pl-6 pr-4 text-red-500 mt-2 text-xs">
						<FontAwesomeIcon
							icon="fas fa-exclamation-triangle"
							class="mr-1"
							fixed-width
							aria-hidden="true" />{{
							trans(
								"We cannot deliver to :_country, please update the address or contact support.",
								{ _country: summary?.customer?.addresses?.delivery?.country?.name }
							)
						}}
					</div>
				</div>
			</div>

			<!-- review summary -->
			<div
				class="col-span-2 relative overflow-hidden rounded-xl border border-amber-100 bg-gradient-to-r from-amber-50 via-white to-sky-50 p-3 shadow-sm">
				<div
					class="absolute -right-10 -top-10 h-24 w-24 rounded-full bg-amber-200/20 blur-2xl"></div>

				<div class="relative flex items-center justify-between gap-3">
					<div>
						<h2 class="text-sm font-semibold text-gray-900">
							{{ trans("Review Summary") }}
						</h2>

						<div class="mt-2 flex flex-wrap gap-1.5">
							<div
								class="flex items-center gap-1 rounded-md border border-amber-200 bg-white/70 px-2 py-1 text-[11px] font-medium text-amber-700"
								:v-tooltip="trans('overall review')">
								<FontAwesomeIcon :icon="faStar" class="text-[10px]" />
								<span>{{ review_summary?.overall_review }}/1</span>
							</div>

							<div
								class="flex items-center gap-1 rounded-md border border-blue-200 bg-white/70 px-2 py-1 text-[11px] font-medium text-blue-700"
								:v-tooltip="trans('family review')">
								<FontAwesomeIcon :icon="faFolder" class="text-[10px]" />
								<span>
									{{ review_summary?.family_review }}/{{
										review_summary?.total_family_review
									}}
								</span>
							</div>

							<div
								class="flex items-center gap-1 rounded-md border border-emerald-200 bg-white/70 px-2 py-1 text-[11px] font-medium text-emerald-700"
								:v-tooltip="trans('product review')">
								<FontAwesomeIcon :icon="faCube" class="text-[10px]" />
								<span>
									{{ review_summary?.product_review }}/{{
										review_summary?.total_product_review
									}}
								</span>
							</div>
						</div>
					</div>

					<div
						class="flex items-center gap-2 rounded-lg border border-white/60 bg-white/80 px-3 py-2 shadow-sm backdrop-blur">
						<div class="text-center">
							<div
								class="bg-gradient-to-r from-amber-500 to-orange-500 bg-clip-text text-xl font-bold leading-none text-transparent">
								{{ review_summary?.average_review?.toFixed(1) ?? "0.0" }}
							</div>

							<div class="mt-0.5 text-[10px] text-gray-500">
								{{ ctrans("Avg Rating") }}
							</div>
						</div>

						<Rating
							:modelValue="review_summary?.average_review ?? 0"
							:readonly="true"
							:disabled="true"
							:cancel="false"
							class="scale-75 origin-right" />
					</div>
				</div>
			</div>
		</div>

		<!-- Section: amount of balance, charges, shipping, tax -->
		<div class="col-span-2 md:col-span-1">
			<div>
				<!-- Field: weight -->
				<dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
					<dt v-tooltip="trans('Weight')" class="flex-none pl-1">
						<FontAwesomeIcon
							:icon="faWeight"
							fixed-width
							aria-hidden="true"
							class="text-gray-500" />
					</dt>
					<dd
						class="text-gray-500 sep"
						v-tooltip="trans('Estimated weight of all products')">
						{{ summary?.order_properties?.weight || 0 }}
					</dd>
				</dl>
			</div>

			<div class="border border-gray-200 p-2 rounded">
				<OrderSummary
					:order_summary="summary.order_summary"
					:currency_code="layout?.iris?.currency?.code" />
			</div>
		</div>

		<!-- Section: Edit Delivery address -->
		<Modal
			v-if="address_management"
			:isOpen="isModalShippingAddress"
			@onClose="() => (isModalShippingAddress = false)"
			width="w-full max-w-lg"
			closeButton>
			<AddressEditModal
				:addresses="address_management.addresses"
				:address="summary?.customer?.addresses?.delivery"
				:updateRoute="address_management.address_update_route"
				@submitted="() => (isModalShippingAddress = false)"
				closeButton
				:copyAddress="contact_address">
				<template #copy_address="{ address, isEqual }">
					<div v-if="isEqual" class="text-gray-500 text-sm">
						{{ trans("Same as the contact address") }}
						<FontAwesomeIcon
							v-if="isEqual"
							v-tooltip="trans('Same as contact address')"
							icon="fal fa-check"
							class="text-green-500"
							fixed-width
							aria-hidden="true" />
					</div>

					<div
						v-else
						class="underline text-sm text-gray-500 hover:text-blue-700 cursor-pointer">
						{{ trans("Copy from contact address") }}
					</div>
				</template>
			</AddressEditModal>
		</Modal>
	</div>
</template>

<style scoped>
:deep(.rating .p-rating-option-active .p-rating-icon) {
	color: #f59e0b !important;
}
</style>
