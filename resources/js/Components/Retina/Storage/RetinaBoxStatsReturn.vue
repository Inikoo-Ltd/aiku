<script setup lang='ts'>
import { trans } from "laravel-vue-i18n"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import DatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import { useFormatTime, useDaysLeftFromToday } from '@/Composables/useFormatTime'
import { notify } from '@kyvg/vue3-notification'
import { router } from '@inertiajs/vue3'

import Popover from '@/Components/Popover.vue'
import { PalletDelivery, BoxStats, PalletReturn, PDRNotes } from '@/types/Pallet'
import Modal from '@/Components/Utils/Modal.vue'
import { Switch, SwitchGroup, SwitchLabel } from "@headlessui/vue"

import { computed, inject, ref } from 'vue'
import { capitalize } from '@/Composables/capitalize'
import { routeType } from "@/types/route"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import RetinaBoxNote from "@/Components/Retina/Storage/RetinaBoxNote.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import ModalAddress from '@/Components/Utils/ModalAddress.vue'
import Textarea from "primevue/textarea"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faBuilding, faIdCardAlt, faMapMarkerAlt, faPenSquare } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue"
import ModalAddressCollection from "@/Components/Utils/ModalAddressCollection.vue"
library.add(faBuilding, faIdCardAlt, faMapMarkerAlt, faPenSquare)


const props = defineProps<{
    box_stats: BoxStats
    data_pallet: PalletDelivery | PalletReturn
    updateRoute: {
        route: routeType
    }
	addresses: {}
	address_update_route: routeType
    notes_data: {
        [key: string]: PDRNotes
    }
}>()

const layout = inject('layout', layoutStructure)


const isModalAddress = ref(false)
const isModalAddressCollection = ref(false)
const enabled = ref(props.data_pallet?.is_collection || false)
const isLoading = ref<string | boolean>(false)
const textValue = ref(props.box_stats?.collection_notes)

// Computed property to intercept changes via v-model
const computedEnabled = computed({
	get() {
		return enabled.value
	},
	set(newValue: boolean) {
		const addressID = props.box_stats.fulfilment_customer.address?.address_customer?.value.id
		const address = props.box_stats.fulfilment_customer.address?.address_customer?.value

		if (!newValue) {
			// Prepare the address data for creating a new record.
			const filterDataAddress = { ...address }
			delete filterDataAddress.formatted_address
			delete filterDataAddress.country
			delete filterDataAddress.id // Remove id to create a new one

			router[
				props.box_stats.fulfilment_customer.address.routes_address.store.method || "post"
			](
				route(
					props.box_stats.fulfilment_customer.address.routes_address.store.name,
					props.box_stats.fulfilment_customer.address.routes_address.store.parameters
				),
				{
					delivery_address_id: props.addresses.pinned_address_id,
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
					route(props.box_stats.fulfilment_customer.address.routes_address.delete.name, {
						...props.box_stats.fulfilment_customer.address.routes_address.delete
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

function updateCollectionNotes() {
	router.patch(
		route(props.updateRoute.route.name, props.updateRoute.route.parameters),
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

// Method: On change estimated date
// const onChangeEstimateDate = async (close: Function) => {
//     try {
//         router.patch(
//             route(props.updateRoute.route.name, props.updateRoute.route.parameters),
//             {
//                 estimated_delivery_date: props.data_pallet.estimated_delivery_date
//             },
//             {
//                 onStart: () => isLoadingSetEstimatedDate.value = true,
//                 onError: () => {
//                     notify({
//                         title: "Failed",
//                         text: "Failed to update the Delivery date, try again.",
//                         type: "error",
//                     })
//                 },
//                 onSuccess: () => close(),
//                 onFinish: () => isLoadingSetEstimatedDate.value = false,
//             })
//     } catch (error) {
//         console.log(error)
//         notify({
//             title: "Failed",
//             text: "Failed to update the Delivery date, try again.",
//             type: "error",
//         })
//     }
// }

// const disableBeforeToday = (date: Date) => {
//     const today = new Date()
//     // Set time to 00:00:00 for comparison purposes
//     today.setHours(0, 0, 0, 0)
//     return date < today
// }
</script>

<template>
    <div class="h-min grid sm:grid-cols-2 lg:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
        <!-- Box: Detail -->
        <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class=" pb-2 py-5 px-3"
            :tooltip="trans('Detail')" :label="capitalize(data_pallet.state)" icon="fal fa-truck-couch">

            <!-- Field: Reference -->
            <div as="a" v-if="box_stats.fulfilment_customer.customer.reference"
                class="flex items-center w-fit flex-none gap-x-2">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Reference</span>
                    <FontAwesomeIcon icon='fal fa-id-card-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class=" text-gray-500">{{ box_stats.fulfilment_customer.customer.reference }}</dd>
            </div>

            <!-- Field: Contact name -->
            <div v-if="box_stats.fulfilment_customer.customer.contact_name"
                class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Contact name'" class="flex-none">
                    <span class="sr-only">Contact name</span>
                    <FontAwesomeIcon icon='fal fa-user' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class=" text-gray-500">{{ box_stats.fulfilment_customer.customer.contact_name }}</dd>
            </div>


            <!-- Field: Company name -->
            <div v-if="box_stats.fulfilment_customer.customer.company_name"
                class="flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Company name</span>
                    <FontAwesomeIcon icon='fal fa-building' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class=" text-gray-500">{{ box_stats.fulfilment_customer.customer.company_name }}</dd>
            </div>
            
            <!-- Field: Delivery Address -->
            <div class="flex flex-col w-full gap-y-2 mb-1">
				<!-- Top Row: Icon and Switch -->
				<div class="flex items-center gap-x-2">
					<dt v-tooltip="trans('Pallet Return\'s address')" class="flex-none">
						<span class="sr-only">Delivery address</span>
						<FontAwesomeIcon
							icon="fal fa-map-marker-alt"
							size="xs"
							class="text-gray-400"
							fixed-width
							aria-hidden="true" />
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

				<!-- Bottom Row: Address Display -->
				<div
					v-if="data_pallet.is_collection !== true"
					class="w-full text-xs text-gray-500">
					Send to:
					<div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
						<span
							v-html="
                            box_stats.fulfilment_customer?.address?.value?.formatted_address
							" />
						<div
							@click="() => (isModalAddressCollection = true)"
							class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
							<span>Edit</span>
						</div>
					</div>
				</div>

				<!-- Alternative Display for Collection -->
				<div v-else class="w-full">
				Collection by:
					<Textarea
						v-model="textValue"
						@blur="updateCollectionNotes"
						autoResize
						rows="5"
						class="w-full"
						cols="30"
						placeholder="typing..." />
				</div>
			</div>
        </BoxStatPallet>


        <!-- Box: Notes -->
        <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class="pb-2 pt-2 px-3"
            :tooltip="trans('Notes')" :percentage="0">
            <!-- Customer reference -->
            <div class="mb-1">
                <PalletEditCustomerReference
                    :dataPalletDelivery="data_pallet"
                    :updateRoute="updateRoute.route"
					:disabled="data_pallet?.state !== 'in_process' && data_pallet?.state !== 'submitted'"
                />
            </div>

            <div class="grid gap-y-3 mb-3">
                <RetinaBoxNote
                    :noteData="notes_data.return"
                    :updateRoute="updateRoute.route"
                />

            </div>
            
            <div class="border-t border-gray-300 pt-1">
                <div class="flex items-center w-full flex-none gap-x-2" 
                    :class='box_stats.delivery_state.class'>
                    <dt class="flex-none">
                        <span class="sr-only">{{ box_stats.delivery_state.tooltip }}</span>
                        <FontAwesomeIcon
                            :icon='box_stats.delivery_state.icon'
                            size="xs"
                            fixed-width aria-hidden='true' />
                    </dt>
                    <dd class="">{{ box_stats?.delivery_state?.tooltip }}</dd>
                </div>
            </div>
        </BoxStatPallet>


        <!-- Box: Order summary -->
        <BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
            <section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
                <h2 id="summary-heading" class="text-lg font-medium">Order summary</h2>

                <OrderSummary :order_summary="box_stats.order_summary" :currency_code="box_stats?.order_summary?.currency?.data?.code" />

                <!-- <div class="mt-6">
                    <button type="submit"
                        class="w-full rounded-md border border-transparent bg-indigo-600 px-4 py-3 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-gray-50">Checkout</button>
                </div> -->
            </section>
        </BoxStatPallet>
    </div>

    <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
        <ModalAddress
            :addresses="addresses"
            :updateRoute="address_update_route"
        />
    </Modal>

    <Modal :isOpen="isModalAddressCollection" @onClose="() => (isModalAddressCollection = false)">
		<ModalAddressCollection :addresses="addresses"
		:updateRoute="address_update_route" />
	</Modal>
</template>