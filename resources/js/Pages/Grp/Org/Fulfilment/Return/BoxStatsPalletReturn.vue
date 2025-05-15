<!--
  -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import JsBarcode from "jsbarcode"
import { computed, onMounted, ref, inject, toRaw } from "vue"
import { capitalize } from "@/Composables/capitalize"
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue"
import { PalletReturn, BoxStats } from "@/types/Pallet"
import { cloneDeep } from "lodash-es"
import { Link, router, useForm } from "@inertiajs/vue3"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import DatePicker from '@vuepic/vue-datepicker'
import Modal from "@/Components/Utils/Modal.vue"
import { routeType } from "@/types/route"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { Switch, SwitchGroup, SwitchLabel } from "@headlessui/vue"
import Popover from '@/Components/Popover.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle, faPencil, faPenSquare, faCalendarDay } from "@fal"
import { faCubes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import DeliveryAddressManagementModal from "@/Components/Utils/DeliveryAddressManagementModal.vue"
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue"
import { notify } from "@kyvg/vue3-notification"
import Textarea from "primevue/textarea"

import InputNumber from "primevue/inputnumber"
import Fieldset from "primevue/fieldset"
import { retinaUseDaysLeftFromToday, useFormatTime } from "@/Composables/useFormatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { AddressManagement } from "@/types/PureComponent/Address";
library.add(faQuestionCircle, faPencil, faPenSquare, faCalendarDay, faCubes)

const props = defineProps<{

	dataPalletReturn: PalletReturn
	boxStats: BoxStats
  address_management:{
    updateRoute: routeType
    addresses: AddressManagement
    address_update_route: routeType
    address_modal_title: string
  },

}>()

onMounted(() => {
	JsBarcode("#palletReturnBarcode", route().v().params.palletReturn, {
		lineColor: "rgb(41 37 36)",
		width: 2,
		height: 50,
		displayValue: false,
	})
})

const deliveryListError = inject('deliveryListError', [])

// Method: Create new address
const isModalAddress = ref(false)
const isDeliveryAddressManagementModal = ref(false)
const enabled = ref(props.dataPalletReturn?.is_collection || false)
const isLoading = ref<string | boolean>(false)
const textValue = ref(props.boxStats?.collection_notes)
const isLoadingSetEstimatedDate = ref<string | boolean>(false)

// ✅ Auto-select collectionBy based on existing notes
const collectionBy = ref(props.boxStats?.collection_notes ? 'thirdParty' : 'myself')

// Computed property to intercept changes via v-model
const computedEnabled = computed({
	get() {
		return enabled.value
	},
	set(newValue: boolean) {
		const addressID = props.boxStats.fulfilment_customer.address?.address_customer?.value.id
		const address = props.boxStats.fulfilment_customer.address?.address_customer?.value

		if (!newValue) {
			// Prepare the address data for creating a new record.
			const filterDataAddress = { ...address }
			delete filterDataAddress.formatted_address
			delete filterDataAddress.country
			delete filterDataAddress.id // Remove id to create a new one

			router[
				props.boxStats.fulfilment_customer.address.routes_address.store.method || "post"
			](
				route(
					props.boxStats.fulfilment_customer.address.routes_address.store.name,
					props.boxStats.fulfilment_customer.address.routes_address.store.parameters
				),
				{
					delivery_address_id: props.address_management.addresses?.current_selected_address_id || props.address_management.addresses?.pinned_address_id || props.address_management.addresses?.home_address_id,
				},
				{
					preserveScroll: true,
					onFinish: () => {},
					onSuccess: () => {
						notify({
							title: trans("Success"),
							text: trans("Set the address to selected address."),
							type: "success",
						})
					},
					onError: () =>
						notify({
							title: trans("Something went wrong"),
							text: trans("Failed to submit the address, try again"),
							type: "error",
						}),
				}
			)
		} else {
			try {
				router.delete(
					route(props.boxStats.fulfilment_customer.address.routes_address.delete.name, {
						...props.boxStats.fulfilment_customer.address.routes_address.delete
							.parameters,
					}),
					{
						preserveScroll: true,
						onStart: () => (isLoading.value = "onDelete" + addressID),
						onFinish: () => {
							isLoading.value = false
						},
					}
				)
				notify({
					title: trans("Success"),
					text: trans("Set the address to follow collection."),
					type: "success",
				})
			} catch (error) {
				console.error("Error disabling collection:", error)
				notify({
					title: trans("Something went wrong"),
					text: trans("Failed to disable collection."),
					type: "error",
				})
			}
		}
		// Finally, update the ref value.
		enabled.value = newValue
	},
})

// Update collection type (myself or thirdParty)
function updateCollectionType() {
	const payload: Record<string, any> = {
		collection_by: collectionBy.value,
	}

	if (collectionBy.value === 'myself') {
		payload.collection_notes = null
		textValue.value = null // also clear in frontend
	}

	router.patch(
		route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
		payload,
		{
			preserveScroll: true,
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Collection type updated successfully"),
					type: "success",
				})
			},
			onError: () => {
				notify({
					title: trans("Something went wrong"),
					text: trans("Failed to update collection type"),
					type: "error",
				})
			},
		}
	)
}

function updateCollectionNotes() {
	router.patch(
		route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
		{ collection_notes: textValue.value },
		{
			preserveScroll: true,
			onSuccess: () => {
				notify({
					title: trans("Success"),
					text: trans("Text updated successfully"),
					type: "success",
				})
			},
			onError: () => {
				notify({
					title: trans("Something went wrong"),
					text: trans("Failed to update text"),
					type: "error",
				})
			},
		}
	)
}

const onChangeEstimateDate = async (close: Function) => {
	try {
		router.patch(
			route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
			{
				estimated_delivery_date: props.dataPalletReturn.estimated_delivery_date
			},
			{
				onStart: () => isLoadingSetEstimatedDate.value = true,
				onError: () => {
					notify({
						title: "Failed",
						text: "Failed to update the Delivery date, try again.",
						type: "error",
					})
				},
				onSuccess: () => {
					const index = deliveryListError?.indexOf('estimated_delivery_date')
					if (index > -1) {
						deliveryListError?.splice(index, 1)
					}
					close()
				},
				onFinish: () => isLoadingSetEstimatedDate.value = false,
			}
		)
	} catch (error) {
		console.log(error)
		notify({
			title: "Failed",
			text: "Failed to update the Delivery date, try again.",
			type: "error",
		})
	}
}

// Disable selecting past dates
const disableBeforeToday = (date: Date) => {
	const today = new Date()
	today.setHours(0, 0, 0, 0)
	return date < today
}

// Section: Parcels
// const formTrackingNumber = useForm({ shipping_id: "", tracking_number: "" })
const isLoadingSubmitParcels = ref(false)
const isModalParcels = ref(false)
const parcelsCopy = ref([...toRaw(props.boxStats?.parcels || [])])
const onDeleteParcel = (index: number) => {
	parcelsCopy.value.splice(index, 1)
}
const onSubmitShipment = () => {
	router.patch(route(props.address_management.updateRoute.name, { ...props.address_management.updateRoute.parameters }), 
		{
			parcels: parcelsCopy.value,
		},
		{
			preserveScroll: true,
			onStart: () => {
				isLoadingSubmitParcels.value = true
			},
			onSuccess: () => {
				isModalParcels.value = false
				// formTrackingNumber.reset()
			},
			onError: (errors) => {
				notify({
					title: trans("Something went wrong."),
					text: trans("Failed to add Shipment. Please try again or contact administrator."),
					type: "error",
				})
			},
			onFinish: () => {
				isLoadingSubmitParcels.value = false
			},
		})
}
</script>

<template>
	<div
		class="h-min grid sm:grid-cols-2 lg:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
		<!-- Box: Customer -->
		<BoxStatPallet class="py-1 sm:py-2 px-3">
			<!-- Field: Platform -->
			<div v-if="boxStats.platform" class="pl-0.5 flex items-center w-full flex-none gap-x-2">
				<div v-tooltip="trans('Platform')" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-parachute-box"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</div>
				<div class="flex items-center gap-x-2">
					{{ boxStats.platform.name }}
					<img v-if="boxStats.platform.code === 'tiktok'" v-tooltip="boxStats.platform.name" src="https://cdn-icons-png.flaticon.com/512/3046/3046126.png" alt="" class="h-6">
					<img v-if="boxStats.platform.code === 'shopify'" v-tooltip="boxStats.platform.name" src="https://cdn-icons-png.flaticon.com/256/5968/5968919.png" alt="" class="h-6">
					<img v-if="boxStats.platform.code === 'woocommerce'" v-tooltip="boxStats.platform.name" src="https://e7.pngegg.com/pngimages/490/140/png-clipart-computer-icons-e-commerce-woocommerce-wordpress-social-media-icon-bar-link-purple-violet-thumbnail.png" alt="" class="h-12">
				</div>
			</div>


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
				<dt v-tooltip="trans('Customer Reference')" class="flex-none">
					<span class="sr-only">Reference</span>
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd v-if="boxStats.is_platform_address">{{ boxStats.platform_customer?.id }}</dd>
				<dd v-else>{{ boxStats.fulfilment_customer.customer.reference }}</dd>
			</Link>

			<!-- Field: Contact name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer?.contact_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Contact name')" class="flex-none">
					<span class="sr-only">Contact name</span>
					<FontAwesomeIcon
						icon="fal fa-user"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd v-if="boxStats.is_platform_address">{{ boxStats.platform_customer?.first_name + ' ' + boxStats.platform_customer?.last_name }}</dd>
				<dd v-else>{{ boxStats.fulfilment_customer.customer.contact_name }}</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="boxStats?.fulfilment_customer?.customer?.company_name"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Company name')" class="flex-none">
					<span class="sr-only">Company name</span>
					<FontAwesomeIcon
						icon="fal fa-building"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd>{{ boxStats.fulfilment_customer.customer.company_name }}</dd>
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
                    v-if="boxStats.is_platform_address"
					:href="`mailto:${boxStats.platform_customer?.email}`"
					class="hover:underline w-full pr-4 break-words leading-none">
					{{ boxStats.platform_customer?.email }}
				</a>
                <a
                    v-else
					:href="`mailto:${boxStats.fulfilment_customer?.customer.email}`"
					class="hover:underline w-full pr-4 break-words leading-none">
					{{ boxStats.fulfilment_customer?.customer.email }}
				</a>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="boxStats.is_platform_address ? boxStats.platform_customer?.phone : boxStats?.fulfilment_customer?.customer?.phone"
				class="flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="trans('Phone')" class="flex-none">
					<span class="sr-only">Phone</span>
					<FontAwesomeIcon
						icon="fal fa-phone"
						size="xs"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a v-if="boxStats.is_platform_address">{{ boxStats.platform_customer?.phone }}</a>
				<a v-else>{{ boxStats.fulfilment_customer?.customer.phone }}</a>
			</div>

			<!-- Field: Delivery Address -->
			<div class="flex items-center w-full flex-none gap-x-2" :class="deliveryListError.includes('estimated_delivery_date') ? 'errorShake' : ''">
				<dt v-tooltip="trans('Estimated delivery date')" class="flex-none">
					<span class="sr-only">{{ boxStats?.delivery_state?.tooltip }}</span>
					<FontAwesomeIcon :icon="['fal', 'calendar-day']" class="text-gray-400" :class="boxStats?.delivery_status?.class" fixed-width aria-hidden="true" size="xs" />
				</dt>
				<Popover v-if="dataPalletReturn.state === 'in_process'" position="">
					<template #button>
						<div v-if="dataPalletReturn?.estimated_delivery_date"
							v-tooltip="retinaUseDaysLeftFromToday(dataPalletReturn?.estimated_delivery_date)"
							class="group ">ffff
							{{ useFormatTime(dataPalletReturn?.estimated_delivery_date) }}
							<FontAwesomeIcon icon="fal fa-pencil" size="sm" class="text-gray-400 group-hover:text-gray-600" fixed-width aria-hidden="true" />
						</div>
						<div v-else class=" hover:text-gray-600 underline">
							{{ trans('Set estimated delivery') }}
						</div>
					</template>
					<template #content="{ close }">
						<DatePicker v-model="dataPalletReturn.estimated_delivery_date"
							@update:modelValue="() => onChangeEstimateDate(close)" inline auto-apply
							:xdisabled-dates="disableBeforeToday" :enable-time-picker="false" />
						<div v-if="isLoadingSetEstimatedDate" class="absolute inset-0 bg-white/70 flex items-center justify-center">
							<LoadingIcon class="text-5xl" />
						</div>
					</template>
				</Popover>
				<div v-else>
					<dd :class="dataPalletReturn?.estimated_delivery_date ? '' : 'text-gray-400'">
						{{ dataPalletReturn?.estimated_delivery_date ? useFormatTime(dataPalletReturn?.estimated_delivery_date) : trans('(Not Set)') }}
					</dd>
				</div>
			</div>
			<!-- Delivery Address / Collection by Section -->
			<div class="flex flex-col w-full gap-y-2 mb-1">
				<!-- Top Row: Icon dan Switch -->
				<div class="flex items-center gap-x-2">
					<dt v-tooltip="trans('Pallet Return\'s address')" class="flex-none">
						<span class="sr-only">Delivery address</span>
						<FontAwesomeIcon icon="fal fa-map-marker-alt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
					</dt>
					<SwitchGroup as="div" class="flex items-center">
						<Switch
							v-model="computedEnabled"
							:class="[computedEnabled ? 'bg-indigo-600' : 'bg-gray-200']"
							class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
							<span
								aria-hidden="true"
								:class="[computedEnabled ? 'translate-x-5' : 'translate-x-0']"
								class="pointer-events-none inline-block h-5 w-5 transform bg-white rounded-full shadow transition duration-200 ease-in-out" />
						</Switch>
						<SwitchLabel as="span" class="ml-3 text-sm font-medium text-gray-900">
							{{ trans("Collection") }}
						</SwitchLabel>
					</SwitchGroup>
				</div>

				<div v-if="dataPalletReturn.is_collection" class="w-full">
					<span class="block mb-1">{{ trans("Collection by:") }}</span>
					<div class="flex space-x-4">
						<label class="inline-flex items-center">
							<input
								type="radio"
								value="myself"
								v-model="collectionBy"
								@change="updateCollectionType"
								class="form-radio"
							/>
							<span class="ml-2">{{ trans("My Self") }}</span>
						</label>
						<label class="inline-flex items-center">
							<input
								type="radio"
								value="thirdParty"
								v-model="collectionBy"
								@change="updateCollectionType"
								class="form-radio"
							/>
							<span class="ml-2">{{ trans("Third Party") }}</span>
						</label>
					</div>

					<div v-if="collectionBy === 'thirdParty'" class="mt-3">
						<Textarea
							v-model="textValue"
							@blur="updateCollectionNotes"
							autoResize
							rows="5"
							class="w-full"
							cols="30"
							placeholder="Type additional notes..."
						/>
					</div>
				</div>
				<div v-else class="w-full text-xs text-gray-500">
					Send to:
					<div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
						<span v-html="boxStats.fulfilment_customer?.address?.value?.formatted_address" />
						<div
							@click="() => (isDeliveryAddressManagementModal = true)"
							class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
              <span>{{trans('Edit')}}</span>
						</div>
					</div>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box Stats: 2 -->
		<BoxStatPallet
			class="py-1 sm:py-2 px-3"
			:label="capitalize(dataPalletReturn?.state)"
			icon="fal fa-truck-couch">
			<div class="flex gap-x-1">
				<FontAwesomeIcon v-tooltip="trans('Parcels')" icon='fas fa-cubes' class='text-gray-400' fixed-width aria-hidden='true' />
				<div class="group w-full">
					<div class="leading-4 text-sm flex justify-between w-full">
						<div>{{ trans("Parcels") }} ({{ boxStats.parcels?.length }})</div>
						<div @click="() => (isModalParcels = true, parcelsCopy = [...props.boxStats?.parcels || []])" class="cursor-pointer text-gray-400 hover:text-gray-600">
							{{ trans("Edit") }}
							<FontAwesomeIcon icon="fal fa-pencil" size="sm" class="text-gray-400" fixed-width aria-hidden="true" />
						</div>
						
					</div>
					<ul class="list-disc pl-4">
						<li v-for="(parcel, parcelIdx) in boxStats.parcels" :key="parcelIdx" class="text-xs tabular-nums">
							<span class="truncate">
								{{ parcel.weight }} kg
							</span>

							<span class="text-gray-500 truncate">
								({{ parcel.dimension[0] }}x{{ parcel.dimension[1] }}x{{ parcel.dimension[2] }} cm)
							</span>
						</li>
					</ul>
				</div>
			</div>

			<!-- Customer reference -->
			<div class="mb-1" v-if="address_management">
				<PalletEditCustomerReference
          :dataPalletDelivery="dataPalletReturn"
          :updateRoute="address_management.updateRoute"
        />
			</div>

			<!-- Barcode -->
			<div
				class="mb-4 h-full w-full lg:max-w-72 mx-auto py-1 px-2 flex flex-col bg-gray-100 ring-1 ring-gray-300 rounded items-center">
				<svg id="palletReturnBarcode" class="w-full h-full"></svg>
				<div class="text-xs text-gray-500">
					{{ route().params.palletReturn }}
				</div>
			</div>

			<div v-if="boxStats?.delivery_state" class="border-t border-gray-300 pt-1.5">
				<div
					class="flex items-center flex-none gap-x-2 w-fit"
					:class="boxStats?.delivery_state.class"
					v-tooltip="trans('Delivery status')">
					<dt class="flex-none">
						<span class="sr-only">{{ boxStats.delivery_state.tooltip }}</span>
						<FontAwesomeIcon
							:icon="boxStats.delivery_state.icon"
							fixed-width
							aria-hidden="true" />
					</dt>
					<dd class="text-xs">{{ boxStats.delivery_state.tooltip }}</dd>
				</div>
			</div>

		</BoxStatPallet>

		<!-- Box: Order summary -->
		<BoxStatPallet
			v-if="boxStats?.order_summary"
			class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
			<section
				aria-labelledby="summary-heading"
				class="lg:max-w-xl rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
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
						v-tooltip="'Invoice'"
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
					:order_summary="boxStats.order_summary"
					:currency_code="boxStats.order_summary.currency.data.code" />
			</section>
		</BoxStatPallet>
	</div>

	<Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
		<CustomerAddressManagementModal
      :addresses="boxStats.fulfilment_customer.address"
      :updateRoute="address_management.updateRoute"
    />
	</Modal>
	<Modal :isOpen="isDeliveryAddressManagementModal" @onClose="() => (isDeliveryAddressManagementModal = false)">
		<DeliveryAddressManagementModal
    	:address_modal_title="address_management.address_modal_title"
		:addresses="address_management.addresses"
		:updateRoute="address_management.address_update_route"
    />
	</Modal>

	<!-- Modal: Shipment -->
	<Modal
		v-if="true"
		:isOpen="isModalParcels"
		@onClose="isModalParcels = false"
		width="w-full max-w-lg"
	>
		<div class="text-center font-bold mb-4">
			{{ trans('Add shipment') }}
		</div>

		<div>
			<Fieldset :legend="`${trans('Parcels')} (${parcelsCopy?.length})`">
				<!-- Header Row -->
				<div class="grid grid-cols-12 items-center gap-x-6 mb-2">
					<div class="flex justify-center">
						<!-- <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" /> -->
					</div>

					<div class="col-span-2 flex items-center space-x-1">
						<FontAwesomeIcon icon="fal fa-weight" class="" fixed-width aria-hidden="true" />
						<span>kg</span>
					</div>
					<div class="col-span-9 flex items-center space-x-1">
						<FontAwesomeIcon icon="fal fa-ruler-triangle" class="" fixed-width aria-hidden="true" />
						<span>cm</span>
					</div>
				</div>

				<!--  -->
				<div class="grid gap-y-1 max-h-64 overflow-y-auto pr-2">
					<!-- {{parcelsCopy.length}} xx {{ boxStats.parcels.length }} -->
					<TransitionGroup v-if="parcelsCopy?.length" name="list-to-down">
						<div v-for="(parcel, parcelIndex) in parcelsCopy" :key="parcelIndex" class="grid grid-cols-12 items-center gap-x-6">
							<div @click="() => onDeleteParcel(parcelIndex)" class="flex justify-center">
								<FontAwesomeIcon icon="fal fa-trash-alt" class="hover:text-red-500 cursor-pointer" fixed-width aria-hidden="true" />
							</div>
							<div class="col-span-2 flex items-center space-x-2">
								<InputNumber v-model="parcel.weight" class="w-16" size="small" placeholder="0" fluid />
							</div>
							<div class="col-span-9 flex items-center gap-x-1 font-light">
								<InputNumber v-model="parcel.dimension[0]" class="w-16" size="small" placeholder="0" fluid />
								<div class="text-gray-400">x</div>
								<InputNumber v-model="parcel.dimension[1]" class="w-16" size="small" placeholder="0" fluid />
								<div class="text-gray-400">x</div>
								<InputNumber v-model="parcel.dimension[2]" class="w-16" size="small" placeholder="0" fluid />
								<button class="text-gray-600">≡</button>
							</div>
						</div>
					</TransitionGroup>
					<div v-else>
						{{ trans('No parcels') }}
					</div>
				</div>

				<!-- Repeat for more rows -->
				<div class=" grid grid-cols-12 mt-2">
					<div></div>
					<div @click="() => parcelsCopy.push({ weight: 0, dimension: [0,0,0]})" class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
						<FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
						{{ trans("Add another parcel") }}
					</div>
				</div>
			</Fieldset>

			<div class="flex justify-end mt-3">
				<Button
					:style="'save'"
					:loading="isLoadingSubmitParcels"
					:label="'save'"
					xdisabled="
						!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id.api_shipper ? true : formTrackingNumber.tracking_number)
					"
					full
					@click="() => onSubmitShipment()" />
			</div>
		</div>
	</Modal>
</template>

<style scoped lang="scss">
:deep(.country) {
	@apply font-medium text-sm;
}
</style>
