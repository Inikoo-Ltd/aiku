<script setup lang="ts">
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import { trans } from "laravel-vue-i18n"
import NeedToPay from "@/Components/Utils/NeedToPay.vue"
import { Address } from "@/types/PureComponent/Address"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube } from "@fal"
import { faCubes } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import { Link, router } from "@inertiajs/vue3"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import { inject, ref, toRaw } from "vue"
import { routeType } from "@/types/route"
import { set, values } from "lodash"
import { notify } from "@kyvg/vue3-notification"
import { useTruncate } from "@/Composables/useTruncate"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Fieldset, InputNumber } from "primevue"
import Icon from "@/Components/Icon.vue"
library.add(faIdCardAlt, faEnvelope, faPhone, faGift, faBoxFull, faWeight, faCube, faCubes)

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
        state: string
		parcels: {
			weight: number
			dimensions: [number, number, number]
		}[]
		shipments: {
			id: number
			name: string
			tracking: string
			pdf_label?: string
			combined_label_url?: string
		}[]
	}
	shipments: {
		delete_route: routeType
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

// console.log(props.boxStats)

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
			// 	dimensions: [40, 40, 40],
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

// Section: Shipment
const isDeleteShipment = ref<number | null>(null)
const onDeleteShipment = (idShipment: number) => {
	router.delete(route(props.shipments.delete_route.name, { 
		...props.shipments.delete_route.parameters,
		shipment: idShipment,
	}),
	{
		preserveScroll: true,
		onStart: () => {
			isDeleteShipment.value = idShipment
		},
		onSuccess: () => {
			notify({
				title: trans("Success!"),
				text: trans("Shipment has deleted."),
				type: "success",
			})
		},
		onError: (errors) => {
			notify({
				title: trans("Something went wrong."),
				text: trans("Failed to delete shipment. Please try again or contact administrator."),
				type: "error",
			})
		},
		onFinish: () => {
			isDeleteShipment.value = null
			isLoadingSubmitParcels.value = false
		},
	})
}

const listError = inject('listError', {})

const base64ToPdf = (base: string) => {
	// Convert base64 to byte array
	const byteCharacters = atob(base);
	const byteNumbers = Array.from(byteCharacters, char => char.charCodeAt(0));
	const byteArray = new Uint8Array(byteNumbers);

	// Create a Blob and generate object URL
	const blob = new Blob([byteArray], { type: 'application/pdf' });
	const blobUrl = URL.createObjectURL(blob);

	// Create a temporary link to trigger download
	const link = document.createElement('a');
	link.href = blobUrl;
	link.download = 'file.pdf';
	link.click();

	// Clean up the object URL
	URL.revokeObjectURL(blobUrl);
}
</script>

<template>
	<div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
		<BoxStatPallet class="py-2 px-3" icon="fal fa-user">
			<!-- Field: Reference Number -->
			<Link
				as="a"
				v-if="boxStats?.customer.reference"
				:href="'route(boxStats?.customer.route.name, boxStats?.customer.route.parameters)'"
				class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
				<dt v-tooltip="'Company name'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-id-card-alt"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500" v-tooltip="'Reference'">
					#{{ boxStats?.customer.reference }}
				</dd>
			</Link>

			<!-- Field: Contact name -->
			<div
				v-if="boxStats?.customer.contact_name"
				class="pl-1 flex items-center w-full flex-none gap-x-2"
				v-tooltip="trans('Contact name')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-user"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ boxStats?.customer.contact_name }}</dd>
			</div>

			<!-- Field: Company name -->
			<div
				v-if="boxStats?.customer.company_name"
				class="pl-1 flex items-center w-full flex-none gap-x-2"
				v-tooltip="trans('Company name')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-building"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd class="text-sm text-gray-500">{{ boxStats?.customer.company_name }}</dd>
			</div>

			<!-- Field: Email -->
			<div
				v-if="boxStats?.customer.email"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Email'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-envelope"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`mailto:${boxStats?.customer.email}`"
					v-tooltip="'Click to send email'"
					class="text-sm text-gray-500 hover:text-gray-700 truncate"
					>{{ boxStats?.customer.email }}</a
				>
			</div>

			<!-- Field: Phone -->
			<div
				v-if="boxStats?.customer.phone"
				class="pl-1 flex items-center w-full flex-none gap-x-2">
				<dt v-tooltip="'Phone'" class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-phone"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<a
					:href="`tel:${boxStats?.customer.phone}`"
					v-tooltip="'Click to make a phone call'"
					class="text-sm text-gray-500 hover:text-gray-700"
					>{{ boxStats?.customer.phone }}</a
				>
			</div>

			<!-- Field: Address -->
			<div
				v-if="boxStats?.customer?.address"
				class="pl-1 flex items w-full flex-none gap-x-2"
				v-tooltip="trans('Shipping address')">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-shipping-fast"
						class="text-gray-400"
						fixed-width
						aria-hidden="true" />
				</dt>
				<dd
					class="w-full text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50"
					v-html="boxStats?.customer.address.formatted_address"></dd>
			</div>
		</BoxStatPallet>

		<!-- Box: 2 -->
		<BoxStatPallet class="py-2.5 pl-2.5 pr-3" icon="fal fa-user">
			<template v-if="boxStats?.picker?.contact_name">
				<div v-tooltip="trans('Picker name')"
					class="border-l-4 border-yellow-300 bg-yellow-100 pl-1 flex items-center w-fit pr-3 flex-none gap-x-1.5">
					<dt class="flex-none">
						{{ trans("Picker") }}:
					</dt>
					<dd class="text-gray-500">
						{{ boxStats?.picker?.contact_name}}
					</dd>
				</div>
				<div class="border-t border-gray-300 w-full" />
			</template>
			
			<div
				xv-tooltip="trans('Current progress')"
				class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
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
			</div>

			<div
				v-tooltip="trans('Estimated weight of all products')"
				class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-weight"
						fixed-width
						aria-hidden="true"
						class="text-gray-500" />
				</dt>
				<dd class="text-gray-500">
					{{ locale.number(boxStats?.products.estimated_weight) || 0 }} kilograms
				</dd>
			</div>

			<div
				v-tooltip="trans('Total items')"
				class="flex items-center w-fit pr-3 flex-none gap-x-1.5">
				<dt class="flex-none">
					<FontAwesomeIcon
						icon="fal fa-cube"
						fixed-width
						aria-hidden="true"
						class="text-gray-500" />
				</dt>
				<dd class="text-gray-500">
					{{ locale.number(boxStats.products?.number_items|| 0) }} items
				</dd>
			</div>

			<!-- Section: Parcels -->
			<div v-if="deliveryNote?.state === 'packed' || deliveryNote?.state === 'dispatched'" class="flex gap-x-1 py-0.5" :class="listError.box_stats_parcel ? 'errorShake' : ''">
				<FontAwesomeIcon v-tooltip="trans('Parcels')" icon='fas fa-cubes' class='text-gray-400' fixed-width aria-hidden='true' />
				<div class="group w-full">
					<div class="leading-4 text-base flex justify-between w-full py-1">
						<div>{{ trans("Parcels") }} ({{ boxStats?.parcels?.length ?? 0 }})</div>

						<!-- Can't edit Parcels if Shipment has set AND already dispatched-->
						<template v-if="!(boxStats?.shipments?.length > 1) && deliveryNote?.state === 'packed'">
							<div v-if="boxStats?.parcels?.length" @click="async () => (isModalParcels = true, parcelsCopy = [...props.boxStats?.parcels || []])" class="cursor-pointer text-gray-400 hover:text-gray-600">
								{{ trans("Edit") }}
								<FontAwesomeIcon icon="fal fa-pencil" size="sm" class="text-gray-400" fixed-width aria-hidden="true" />
							</div>
							<div v-else-if="!isLoadingSubmitParcels" @click="async () => (parcelsCopy = [{ weight: 1, dimensions: [40, 40, 40]}], onSubmitParcels())" class="cursor-pointer text-gray-400 hover:text-gray-600">
								{{ trans("Add") }}
								<FontAwesomeIcon icon="fas fa-plus" size="sm" class="text-gray-400" fixed-width aria-hidden="true" />
							</div>
							<div v-else>
								<LoadingIcon />
							</div>
						</template>
					</div>
					
					<ul v-if="boxStats?.parcels?.length" class="list-disc pl-4">
						<li v-for="(parcel, parcelIdx) in boxStats?.parcels" :key="parcelIdx" class="text-sm tabular-nums">
							<span class="truncate">
								{{ parcel.weight }} kg
							</span>

							<span class="text-gray-500 truncate">
								({{ parcel.dimensions?.[0] }}x{{ parcel.dimensions?.[1] }}x{{ parcel.dimensions?.[2] }} cm)
							</span>
						</li>
					</ul>
				</div>
			</div>

			<!-- Section: Shipments -->
			<div v-if="boxStats.shipments?.length" class="flex gap-x-1 py-0.5" xxclass="listError.box_stats_parcel ? 'errorShake' : ''">
				<FontAwesomeIcon v-tooltip="trans('Shipments')" icon='fal fa-shipping-fast' class='text-gray-400' fixed-width aria-hidden='true' />
				<div class="group w-full">
					<div class="leading-4 text-base flex justify-between w-full py-1">
						<div>{{ trans("Shipments") }} ({{ boxStats.shipments?.length ?? 0 }})</div>

					</div>
					
					<ul v-if="boxStats.shipments" class="list-disc pl-4">
						<li v-for="(sments, shipmentIdx) in boxStats.shipments" :key="shipmentIdx" class="hover:bg-gray-100 text-sm tabular-nums">
							<div class="flex justify-between">
								<a v-if="sments.combined_label_url" v-tooltip="trans('Click to open file')" target="_blank" :href="sments.combined_label_url" class="">
									{{ sments.name }}
									<FontAwesomeIcon icon="fal fa-external-link" class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
								</a>
								
								<div v-else-if="sments.pdf_label" v-tooltip="trans('Click to download file')" @click="base64ToPdf(sments.pdf_label)" class="group cursor-pointer">
									<span class="truncate">
										{{ sments.name }}
									</span>
									<span v-if="sments.tracking" class="text-gray-400">
										({{ useTruncate(sments.tracking, 14) }})
									</span>
									<FontAwesomeIcon icon="fal fa-external-link" class="text-gray-400 group-hover:text-gray-700" fixed-width aria-hidden="true" />
								</div>
								
								<div v-else>
									<span class="truncate">
										{{ sments.name }}
									</span>
									<span v-if="sments.tracking" class="text-gray-400">
										({{ useTruncate(sments.tracking, 14) }})
									</span>
								</div>

								<div v-if="isDeleteShipment === sments.id" class="px-1">
									<LoadingIcon />
								</div>
								<div v-else @click="() => onDeleteShipment(sments.id)" v-tooltip="trans('Remove shipment')" class="cursor-pointer px-1">
									<FontAwesomeIcon icon="fal fa-times" class="text-red-400 hover:text-red-600" fixed-width aria-hidden="true" />
								</div>
							</div>
						</li>
					</ul>
				</div>
			</div>
			
		</BoxStatPallet>

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
						<TransitionGroup v-if="parcelsCopy?.length" name="list">
							<div v-for="(parcel, parcelIndex) in parcelsCopy" :key="parcelIndex" class="grid grid-cols-12 items-center gap-x-6">
								<div @click="() => onDeleteParcel(parcelIndex)" class="flex justify-center">
									<FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width aria-hidden="true" />
								</div>
								<div class="col-span-2 flex items-center space-x-2">
									<InputNumber :min="0.001" v-model="parcel.weight" class="w-16" size="small" placeholder="0" fluid />
								</div>
								<div class="col-span-9 flex items-center gap-x-1 font-light">
									<InputNumber :min="0.001" v-model="parcel.dimensions[0]" class="w-16" size="small" placeholder="0" fluid />
									<div class="text-gray-400">x</div>
									<InputNumber :min="0.001" v-model="parcel.dimensions[1]" class="w-16" size="small" placeholder="0" fluid />
									<div class="text-gray-400">x</div>
									<InputNumber :min="0.001" v-model="parcel.dimensions[2]" class="w-16" size="small" placeholder="0" fluid />
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
						<div @click="() => parcelsCopy.push({ weight: 1, dimensions: [40, 40, 40]})" class="hover:bg-gray-200 cursor-pointer border border-dashed border-gray-400 col-span-11 text-center py-1.5 text-xs rounded">
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
						@click="() => onSubmitParcels()" />
				</div>
			</div>
		</Modal>
	</div>
</template>
