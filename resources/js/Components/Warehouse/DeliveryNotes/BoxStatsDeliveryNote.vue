<script setup lang="ts">
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import ShipmentSection from "@/Components/Warehouse/DeliveryNotes/ShipmentSection.vue"
import { trans } from "laravel-vue-i18n"
import { Address } from "@/types/PureComponent/Address"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube, faBarcodeRead } from "@fal"
import { faCubes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link, router } from "@inertiajs/vue3"
import { inject, ref, toRaw } from "vue"
import { routeType } from "@/types/route"
import { set } from 'lodash-es'
import { notify } from "@kyvg/vue3-notification"
import { useTruncate } from "@/Composables/useTruncate"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Fieldset, InputNumber } from "primevue"
import Icon from "@/Components/Icon.vue"
import axios from "axios"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
library.add(faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube, faCubes, faBarcodeRead)

const props = defineProps<{
	boxStats: {
		customer: {
			reference: string
			company_name: string
			contact_name: string
			email: string
			phone: string
			address: Address
		}
		customer_client?: {
			name: string
			contact_name?: string
			email?: string
			phone?: string
		}
		delivery_address: Address,
		products: {
			estimated_weight: number
		}
		packer: {
			id: number
			username: string
		}
		picker: {
			id: number
			username: string
		}
		order?: {
			reference: string
			route: routeType
		}
		state: string
		parcels: {
			weight: number
			dimensions: [number, number, number]
		}[]
		shipments: {
			id: number
			name: string
			tracking: string
			label?: string
			label_type?: string
			combined_label_url?: string
			is_printable?: boolean
            formatted_tracking_urls: {
				url: string
				tracking: string
			}[]
		}[]
        platform?: {
            logo: string
            name: string
        }
		address: {

		}
		shipments_routes: {
			submit_route: routeType
			fetch_route: routeType
			delete_route: routeType
		}
	}
	routes: {
		pickers_list: routeType
		packers_list: routeType
		update: routeType
	}
	deliveryNote: {
		state: string
	}

	updateRoute: routeType
}>()

/* console.log(props.boxStats) */

const locale = inject('locale', aikuLocaleStructure)


// Section: Parcels
const isLoadingSubmitParcels = ref(false)
const isModalParcels = ref(false)
const parcelsCopy = ref([...toRaw(props.boxStats?.parcels || [])])
const onDeleteParcel = (index: number) => {
	parcelsCopy.value.splice(index, 1)
}
const onSubmitParcels = () => {
	router.patch(route(props.updateRoute.name, props.updateRoute.parameters),
		{
			parcels: parcelsCopy.value,
			// parcels: [{
			// 	weight: 1,
			// 	dimensions: [5, 5, 5],
			// }],
		},
		{
			preserveScroll: true,
			onStart: () => {
				isLoadingSubmitParcels.value = true
			},
			onSuccess: () => {
				isModalParcels.value = false
				set(listError, 'box_stats_parcel', false)
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

const listError = inject('listError', {})


</script>

<template>
	<div class="grid grid-cols-2 lg:grid-cols-3 xdivide-x xdivide-gray-300 border-b border-gray-200">
		<!-- Box: Order -->
        <BoxStatPallet class="py-2 px-3 border-r border-gray-200" icon="fal fa-user">
			<div class="text-xs md:text-sm">
				<div class="font-semibold xmb-2 text-base">
					{{ trans("Order") }}
				</div>
				
				<div class="space-y-0.5 pl-1">
					<!-- Field: Order reference -->
					<Link v-if="boxStats?.order" :href="route(boxStats?.order?.route?.name, boxStats?.order?.route?.parameters)"
						class="w-fit flex items-center gap-3 gap-x-1.5 primaryLink cursor-pointer">
						<dt class="flex-none">
							<FontAwesomeIcon icon='fal fa-shopping-cart' fixed-width aria-hidden='true' class="text-gray-500" />
						</dt>
						<dd class="text-gray-500 " v-tooltip="trans('Order')">
							{{ boxStats?.order?.reference }}
						</dd>
					</Link>
					<!-- Field: Reference Number -->
					<Link as="a"
						v-if="boxStats?.customer.reference"
						:href="route(boxStats?.customer.route.name, boxStats?.customer.route.parameters)"
						class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
						<dt v-tooltip="'Company name'" class="flex-none">
							<FontAwesomeIcon icon="fal fa-id-card-alt" class="text-gray-400" fixed-width aria-hidden="true" />
						</dt>
						<dd class="text-gray-500" v-tooltip="'Reference'">
							#{{ boxStats?.customer.reference }}
						</dd>
					</Link>
					<!-- Field: Contact name -->
					<div v-if="boxStats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2"
						v-tooltip="trans('Contact name')">
						<dt class="flex-none">
							<FontAwesomeIcon icon="fal fa-user" class="text-gray-400" fixed-width aria-hidden="true" />
						</dt>
						<dd class="text-gray-500">{{ boxStats?.customer.contact_name }}</dd>
					</div>
					<!-- Field: Company name -->
					<div v-if="boxStats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2"
						v-tooltip="trans('Company name')">
						<dt class="flex-none">
							<FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width aria-hidden="true" />
						</dt>
						<dd class="text-gray-500">{{ boxStats?.customer.company_name }}</dd>
					</div>
					<!-- Field: Email -->
					<div v-if="boxStats?.customer.email" class="pl-1 flex items-center w-full flex-none gap-x-2">
						<dt v-tooltip="'Email'" class="flex-none">
							<FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width aria-hidden="true" />
						</dt>
						<a :href="`mailto:${boxStats?.customer.email}`" v-tooltip="'Click to send email'"
							class="text-gray-500 hover:text-gray-700 truncate">{{ boxStats?.customer.email }}</a>
					</div>
					<!-- Field: Phone -->
					<div v-if="boxStats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
						<dt v-tooltip="'Phone'" class="flex-none">
							<FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
						</dt>
						<a :href="`tel:${boxStats?.customer.phone}`" v-tooltip="'Click to make a phone call'"
							class="text-gray-500 hover:text-gray-700">{{ boxStats?.customer.phone }}</a>
					</div>
					<!-- Field: Channel -->
					<dl v-if="boxStats?.platform?.name" class="pl-1 flex items-center w-full gap-x-2">
						<dt class="flex-none">
							<div class="block w-full rounded h-[18px]">
								<img :src="boxStats?.platform?.logo"  :alt="boxStats?.platform?.name" class="w-full h-full object-contain rounded" />
							</div>
						</dt>
						<dt class="text-gray-500 hover:text-gray-700">
							{{ boxStats?.platform?.name }}
						</dt>
					</dl>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box: Shipping -->
		<BoxStatPallet class="py-2 px-3 border-r border-gray-200" icon="fal fa-user">
			<div class="text-xs md:text-sm">
				<div class="font-semibold xmb-2 text-base">
					{{ trans("Shipping") }}
				</div>
				
				<div v-if="boxStats?.delivery_address" class="space-y-0.5 pl-2">
					<div class="border border-gray-300 p-4 rounded-lg">
						<div v-if="boxStats.customer_client" class="mb-3">
							<div class="xtext-xs text-gray-600 leading-snug">
								<div>
									<strong>Name:</strong> {{ boxStats.customer_client.contact_name || boxStats.customer_client.name }}
								</div>
								<div v-if="boxStats.customer_client.email">
									<strong>Email:</strong> {{ boxStats.customer_client.email }}
								</div>
								<div v-if="boxStats.customer_client.phone">
									<strong>Phone:</strong> {{ boxStats.customer_client.phone }}
								</div>
							</div>
						</div>
						<div v-html="boxStats.delivery_address.formatted_address"
							class="xtext-xs text-gray-600 leading-snug">
						</div>
					</div>
				</div>

				<div v-else class="text-gray-500 italic pl-2">
					{{ trans("No shipping information available.") }}
				</div>
			</div>
		</BoxStatPallet>

		<!-- Box: Delivery Note -->
		<BoxStatPallet class="py-2.5 pl-2.5 pr-3 border-t md:border-t-0 border-r border-gray-200" icon="fal fa-user">
			<div class="text-xs md:text-sm">
				<div class="font-semibold xmb-2 text-base">
					{{ trans("Delivery Note") }}
				</div>
				
				<div class="space-y-0.5 pl-2">
					<div v-if="boxStats?.picker?.contact_name">
						<dl v-tooltip="trans('Picker name')"
							class=" border-l-4 border-indigo-300 bg-indigo-100 pl-1 flex items-center w-fit pr-3 flex-none gap-x-1.5">
							<dt class="flex-none">
								{{ trans("Picker") }}:
							</dt>
							<dd class="text-gray-500">
								{{ boxStats?.picker?.contact_name }}
							</dd>
						</dl>
						<div class="mt-2 border-t border-gray-300 w-full" />
					</div>
		
					<!-- Current State -->
					<dl xv-tooltip="trans('Current progress')" class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
						<dt class="flex-none">
							<!-- <FontAwesomeIcon
								icon="fal fa-weight"
								fixed-width
								aria-hidden="true"
								class="text-gray-500" /> -->
							<Icon :data="boxStats?.state_icon" />
						</dt>
						<dd class="text-gray-500">
							{{ boxStats.state_label }}
						</dd>
					</dl>

					<!-- Total Items -->
					<dl class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
						<dt class="flex-none">
							<FontAwesomeIcon v-tooltip="trans('Total items')" icon="fal fa-cube" fixed-width aria-hidden="true" class="text-gray-500" />
						</dt>
						<dd class="text-gray-500">
							{{ locale.number(boxStats.products?.number_items || 0) }} items
						</dd>
					</dl>
					
					<!-- Weight -->
					<dl class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
						<dt class="flex-none">
							<FontAwesomeIcon v-tooltip="trans('Estimated weight of all items')" icon="fal fa-weight" fixed-width aria-hidden="true" class="text-gray-500" />
						</dt>
						<dd class="text-gray-500">
							{{ locale.number(boxStats?.products.estimated_weight) || '-' }} kilograms
						</dd>
					</dl>
		
					<!-- Section: Parcels -->
					<div v-if="['packed', 'dispatched', 'finalised'].includes(deliveryNote?.state)" class="flex gap-x-1 py-0.5"
						:class="listError.box_stats_parcel ? 'errorShake' : ''">
						<FontAwesomeIcon v-tooltip="trans('Parcels')" icon='fas fa-cubes' class='mt-1 text-gray-400' fixed-width
							aria-hidden='true' />
						<div class=" group w-full">
							<div class="leading-4 xtext-base flex justify-between w-full py-1">
								<div>{{ trans("Parcels") }} ({{ boxStats?.parcels?.length ?? 0 }})</div>
		
								<!-- Can't edit Parcels if Shipment has set AND already dispatched-->
								<template v-if="!(boxStats?.shipments?.length > 1) && deliveryNote?.state === 'packed'">
									<div v-if="boxStats?.parcels?.length"
										@click="async () => (isModalParcels = true, parcelsCopy = [...props.boxStats?.parcels || []])"
										class="cursor-pointer text-gray-400 hover:text-gray-600">
										{{ trans("Edit") }}
										<FontAwesomeIcon icon="fal fa-pencil" size="sm" class="text-gray-400" fixed-width
											aria-hidden="true" />
									</div>
									<div v-else-if="!isLoadingSubmitParcels"
										@click="async () => (parcelsCopy = [{ weight: 1, dimensions: [5, 5, 5] }], onSubmitParcels())"
										class="cursor-pointer text-gray-400 hover:text-gray-600">
										{{ trans("Add") }}
										<FontAwesomeIcon icon="fas fa-plus" size="sm" class="text-gray-400" fixed-width
											aria-hidden="true" />
									</div>
									<div v-else>
										<LoadingIcon />
									</div>
								</template>
							</div>
		
							<ul v-if="boxStats?.parcels?.length" class="list-disc pl-4 ">
								<li v-for="(parcel, parcelIdx) in boxStats?.parcels" :key="parcelIdx"
									class="xtabular-nums">
									<span class="truncate">
										{{ parcel.weight }} kg
									</span>
		
									<span class="text-gray-500 truncate">
										({{ parcel.dimensions?.[0] }}x{{ parcel.dimensions?.[1] }}x{{ parcel.dimensions?.[2] }}
										cm)
									</span>
								</li>
							</ul>
						</div>
					</div>
		
					<!-- Section: Shipments -->
					<dl v-if="['packed', 'finalised', 'dispatched'].includes(deliveryNote?.state)" class="flex items-xcenter w-fit pr-3 flex-none gap-x-1.5">
						<dt class="flex-none mt-1">
							<FontAwesomeIcon v-tooltip="trans('Shipment')" icon="fal fa-shipping-fast" fixed-width aria-hidden="true" class="text-gray-500" />
						</dt>
						<dd class="text-gray-500">
							<ShipmentSection :shipments="boxStats.shipments" :shipments_routes="boxStats.shipments_routes" :address="boxStats.address" />
						</dd>
					</dl>
				</div>
			</div>
		</BoxStatPallet>

		<!-- Modal: Parcels -->
		<Modal v-if="true" :isOpen="isModalParcels" @onClose="isModalParcels = false" width="w-full max-w-lg">
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
						<TransitionGroup v-if="parcelsCopy?.length" name="list">
							<div v-for="(parcel, parcelIndex) in parcelsCopy" :key="parcelIndex"
								class="grid grid-cols-12 items-center gap-x-6">
								<div @click="() => onDeleteParcel(parcelIndex)" class="flex justify-center">
									<FontAwesomeIcon icon="fal fa-trash-alt"
										class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width
										aria-hidden="true" />
								</div>
								<div class="col-span-2 flex items-center space-x-2">
									<InputNumber :min="0.001" v-model="parcel.weight" class="w-16" size="small"
										placeholder="0" fluid />
								</div>
								<div class="col-span-9 flex items-center gap-x-1 font-light">
									<InputNumber :min="0.001" v-model="parcel.dimensions[0]" class="w-16" size="small"
										placeholder="0" fluid />
									<div class="text-gray-400">x</div>
									<InputNumber :min="0.001" v-model="parcel.dimensions[1]" class="w-16" size="small"
										placeholder="0" fluid />
									<div class="text-gray-400">x</div>
									<InputNumber :min="0.001" v-model="parcel.dimensions[2]" class="w-16" size="small"
										placeholder="0" fluid />
									<!-- <button class="text-gray-600">≡</button> -->

									<!-- <Popover>
										<template #button="{ open }">
											<Button
												@click="() => (open ? false : onOpenModalAddService())"
												:style="action.style"
												:label="action.label"
												:icon="action.icon"
												:key="`ActionButton${action.label}${action.style}`"
												:tooltip="action.tooltip" />
										</template>

										<template #content="{ close: closed }">
											<div class="w-[350px]">

											</div>
										</template>
									</Popover> -->
								</div>
							</div>
						</TransitionGroup>
						<div v-else class="text-center text-gray-400">
							{{ trans('No parcels') }}
						</div>
					</div>

					<!-- Repeat for more rows -->
					<div class=" grid grid-cols-12 mt-2">
						<div></div>
						<div @click="() => parcelsCopy.push({ weight: 1, dimensions: [5, 5, 5] })"
							class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
							<FontAwesomeIcon icon="fas fa-plus" class="text-gray-500" fixed-width aria-hidden="true" />
							{{ trans("Add another parcel") }}
						</div>
					</div>
				</Fieldset>

				<div class="flex justify-end mt-3">
					<Button :style="'save'" :loading="isLoadingSubmitParcels" :label="'save'" xdisabled="
							!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id.api_shipper ? true : formTrackingNumber.tracking_number)
						" full @click="() => onSubmitParcels()" />
				</div>
			</div>
		</Modal>
	</div>
</template>
