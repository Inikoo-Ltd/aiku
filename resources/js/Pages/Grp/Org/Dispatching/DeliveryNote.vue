<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCube, faChair, faHandPaper, faExternalLink, faFolder, faBoxCheck, faPrint, faExchangeAlt, faUserSlash } from '@fal'
import { faArrowRight, faCheck } from '@fas'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import AlertMessage from '@/Components/Utils/AlertMessage.vue'
import BoxNote from '@/Components/Pallet/BoxNote.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import { Timeline as TSTimeline } from '@/types/Timeline'
import { computed, provide, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import BoxStatsDeliveryNote from '@/Components/Warehouse/DeliveryNotes/BoxStatsDeliveryNote.vue'
import TableSKOSOrdered from '@/Components/Warehouse/DeliveryNotes/TableSKOSOrdered.vue'
import TablePickings from '@/Components/Warehouse/DeliveryNotes/TablePickings.vue'
import { routeType } from '@/types/route'
import Tabs from '@/Components/Navigation/Tabs.vue'
import type { DeliveryNote } from '@/types/warehouse'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { get, set } from 'lodash'
import PureInput from '@/Components/Pure/PureInput.vue'


library.add(faFolder, faBoxCheck, faPrint, faExchangeAlt, faUserSlash, faCube, faChair, faHandPaper, faExternalLink, faArrowRight, faCheck)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: TSTabs
    items?: {}
    pickings?: {}
    alert?: {
        status: string
        title?: string
        description?: string
    }
    delivery_note: DeliveryNote
    notes?: {
        note_list: {
            label: string
            note: string
            editable?: boolean
            bgColor?: string
            textColor?: string
            color?: string
            lockMessage?: string
            field: string  // customer_notes, public_notes, internal_notes
        }[]
        // updateRoute: routeType
    }
    timelines: {
        [key: string]: TSTimeline
    }
    box_stats: {}
    routes: {
        update: routeType
        products_list: routeType
        pickers_list: routeType
        packers_list: routeType
        set_queue: routeType
    }
    delivery_note_state: {
        label: string
        value: string
    }
    shipments: {
        submit_route: routeType
        fetch_route: routeType
        delete_route: routeType
    }
}>()

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        items: TableSKOSOrdered,
        pickings: TablePickings,
    }

    return components[currentTab.value]
})

// Section: To Queue
const isModalToQueue = ref(false)

// Section: Picker
const selectedPicker = ref(props.box_stats.picker)
const disable = ref(props.box_stats.state)
const isLoading = ref<{ [key: string]: boolean }>({})
const isLoadingToQueue = ref(false)
const onSetToQueue = () => {
    router.patch(
        route(props.routes.set_queue.name, {
            ...props.routes.set_queue.parameters,
            user: selectedPicker.value.id,
        }),
        {
            
        },
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalToQueue.value = false
            },
            onStart: () => isLoadingToQueue.value = true,
            onFinish: () => isLoadingToQueue.value = false,
            preserveScroll: true,
        }
    )
}
const onUpdatePicker = () => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        {
            picker_user_id: selectedPicker.value.id
        },
        {
            onError: (error) => {
                notify({
                    title: trans("Something went wrong"),
                    text: error.message,
                    type: "error",
                })
            },
            onSuccess: () => {
                isModalToQueue.value = false
            },
            onStart: () => isLoadingToQueue.value = true,
            onFinish: () => isLoadingToQueue.value = false,
            preserveScroll: true,
        }
    )
}

// Section: Shipment
const isLoadingButton = ref<string | boolean>(false)
const isLoadingData = ref<string | boolean>(false)
const formTrackingNumber = useForm({ shipping_id: "", tracking_number: "" })
const isModalShipment = ref(false)
const optionShippingList = ref([])
const onOpenModalTrackingNumber = async () => {
	isLoadingData.value = "addTrackingNumber"
	try {
		const xxx = await axios.get(
			route(props.shipments.fetch_route.name, props.shipments.fetch_route.parameters)
		)
		optionShippingList.value = xxx?.data?.data || []
	} catch (error) {
		console.error(error)
		notify({
			title: trans("Something went wrong."),
			text: trans("Failed to retrieve shipper list"),
			type: "error",
		})
	}
	isLoadingData.value = false
}
const onSubmitShipment = () => {
	// formAddService.historic_asset_id = optionShippingList.value.filter(
	// 	(service) => service.id == formAddService.service_id
	// )[0].historic_asset_id
	// isLoadingButton.value = "addTrackingNumber"

	formTrackingNumber
		.transform((data) => ({
			shipper_id: data.shipping_id?.id,
			tracking: data.shipping_id?.api_shipper ? undefined : data.tracking_number,
		}))
		.post(route(props.shipments.submit_route.name, { ...props.shipments.submit_route.parameters }), {
			preserveScroll: true,
			onStart: () => {
				isLoadingButton.value = "addTrackingNumber"
			},
			onSuccess: () => {
				isModalShipment.value = false
				formTrackingNumber.reset()
			},
			onError: (errors) => {
				// TODO: Make condition if the error related to delivery address then set to true
				// set(listError.value, 'box_stats_delivery_address', true) // To make the Box stats delivery address error
				notify({
					title: trans("Something went wrong."),
					text: trans("Failed to add Shipment. Please try again."),
					type: "error",
				})
			},
			onFinish: () => {
				isLoadingButton.value = false
			},
		})
}

const listError = ref({
	box_stats_parcel: false
})
provide("listError", listError.value)
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" isButtonGroupWithBorder>
        <template #otherBefore>
            <Button 
				v-if="delivery_note_state.value == 'packed' && !(box_stats?.shipments?.length)"
				@click="() => box_stats.parcels?.length ? (isModalShipment = true, onOpenModalTrackingNumber()) : set(listError, 'box_stats_parcel', true)"
				v-tooltip="box_stats.parcels?.length ? '' : trans('Please add at least one parcel')"
				:label="trans('Shipment')"
				icon="fal fa-shipping-fast"
				type="tertiary"
			/>
        </template>
        <template #button-to-queue ="{ action }">
            <Button
                @click="isModalToQueue = true"
                :label="action.label"
                :icon="action.icon"
                :iconRight="action.iconRight"
                :type="action.type"
            />
        </template>
        <template #button-group-change-picker ="{ action }">
            <Button
                @click="isModalToQueue = true"
                :label="action.label"
                :icon="action.icon"
                type="tertiary"
                class="border-transparent rounded-l-none"
            />
        </template>
    </PageHeading>

    <!-- Section: Pallet Warning -->
    <div v-if="alert?.status" class="p-2 pb-0">
        <AlertMessage :alert />
    </div>
    
    <!-- Section: Box Note -->
    <div class="relative">
        <Transition name="headlessui">
            <div v-if="notes?.note_list?.some(item => !!(item?.note?.trim()))" class="p-2 grid sm:grid-cols-3 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
                <BoxNote
                    v-for="(note, index) in notes.note_list"
                    :key="index+note.label"
                    :noteData="note"
                    :updateRoute="routes.update"
                />
            </div>
        </Transition>
    </div>

    <!-- Section: Timeline -->
    <div v-if="timelines" class="mt-4 sm:mt-1 border-b border-gray-200 pb-2">
        <Timeline
            :options="timelines"
            :state="delivery_note.state"
            :slidesPerView="6"
        />
    </div>
    
    <BoxStatsDeliveryNote
        v-if="box_stats"
        :boxStats="box_stats"
        :routes
        :deliveryNote="delivery_note"
        :updateRoute="routes.update"
        :shipments
    />

    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <div class="pb-12">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :routes :state="delivery_note.state" />
    </div>

    <Modal
        :isOpen="isModalToQueue"
        @close="isModalToQueue = false"
        width="w-full max-w-lg"
        :title
    >
        <div class="mt-1 flex flex-col items-start w-full pr-3 gap-y-1.5">
            <div class="mx-auto font-semibold text-lg">
                {{ trans("Select Picker") }}
            </div>

            <div class="mt-4 flex items-center w-full gap-x-1.5">
                <dd class="flex-1">
                    <!-- Label for Picker -->
                    <div class="text-sm font-medium">
                        {{ trans("Select picker") }}
                    </div>
                    <PureMultiselectInfiniteScroll
                        v-model="selectedPicker"
                        xxxupdate:modelValue="
                            (selectedPicker) => onSubmitPickerPacker(selectedPicker, 'picker')
                        "
                        required
                        :fetchRoute="routes.pickers_list"
                        :placeholder="trans('Select picker')"
                        labelProp="contact_name"
                        valueProp="id"
                        object
                        clearOnBlur
                        :loading="isLoading['picker' + selectedPicker?.id]"
                        :disabled="disable == 'picker_assigned' || disable == 'packing' || disable == 'packed' || disable == 'finalised' || disable == 'settled'"
                        >
                        <template #singlelabel="{ value }">
                            <div
                                class="w-full text-left pl-3 pr-2 text-sm whitespace-nowrap truncate">
                                {{ value.contact_name }}
                            </div>
                        </template>

                        <template #option="{ option, isSelected, isPointed }">
                            <div class="w-full text-left text-sm whitespace-nowrap truncate">
                                {{ option.contact_name }}
                            </div>
                        </template>
                    </PureMultiselectInfiniteScroll>
                </dd>
            </div>

            <div class="w-full mt-2">
                <Button
                    @click="delivery_note_state.value === 'queued' ? onUpdatePicker() : onSetToQueue()"
                    :label="delivery_note_state.value === 'queued' ? trans('Change picker') : trans('Set Picker')"
                    :iconRight="['fas', 'fa-arrow-right']"
                    full
                    :loading="isLoadingToQueue"
                    :disabled="!selectedPicker"
                    v-tooltip="selectedPicker ? '' : trans('Select picker before set to queue')"
                >

                </Button>
            </div>
        </div>
    </Modal>

    <!-- Modal: Shipment -->
	<Modal
		v-if="delivery_note_state.value == 'packed'"
		:isOpen="isModalShipment"
		@onClose="isModalShipment = false"
		width="w-full max-w-2xl"
	>
		<div class="text-center font-bold mb-4">
			{{ trans('Add shipment') }}
		</div>

		<div class="w-full mt-3">
			<span class="text-xs px-1 my-2">{{ trans("Shipping options") }}: </span>

			<div class="grid grid-cols-3 gap-x-2 gap-y-2 mb-2">
				<div v-if="isLoadingData === 'addTrackingNumber'"
					v-for="sip in 3"
					class="skeleton w-full max-w-52 h-20 rounded"
				>
					
				</div>

				<div v-else
					v-for="(shipment, index) in optionShippingList.filter(shipment => shipment.api_shipper)"
					@click="() => formTrackingNumber.shipping_id = shipment"
					class="relative w-full max-w-52 h-20 border rounded-md px-5 py-3 cursor-pointer"
					:class="[
						formTrackingNumber.shipping_id?.id == shipment.id
							? 'bg-indigo-200 border-indigo-300'
							: 'hover:bg-gray-100 border-gray-300',
					]"

				>
					<div class="font-bold tesm">{{ shipment.name }}</div>
					<div class="text-xs text-gray-500 italic">
						{{ shipment.phone }}
					</div>
					<div class="text-xs text-gray-500 italic">
						{{ shipment.tracking_url }}
					</div>
					
					<FontAwesomeIcon v-tooltip="trans('Barcode print')" icon="fal fa-print" class="text-gray-500 absolute top-3 right-3" fixed-width aria-hidden="true" />
				</div>

			</div>

			<div class="">
				<PureMultiselectInfiniteScroll
					v-model="formTrackingNumber.shipping_id"
					:fetchRoute="shipments.fetch_route"
					required
					:placeholder="trans('Select shipping')"
					object
					@optionsList="(e) => optionShippingList = e"
				>
					<template #singlelabel="{ value }">
						<div class="w-full text-left pl-4">
							{{ value.name }}
							<span class="text-sm text-gray-400">({{ value.code }})</span>
						</div>
					</template>

					<template #option="{ option, isSelected, isPointed }">
						<div class="">
							{{ option.name }}
							<span class="text-sm text-gray-400">({{ option.code }})</span >
						</div>
					</template>
				</PureMultiselectInfiniteScroll>

				<p
					v-if="get(formTrackingNumber, ['errors', 'shipping_id'])"
					class="mt-2 text-sm text-red-500">
					{{ formTrackingNumber.errors.shipping_id }}
				</p>
			</div>

			<!-- Tracking number -->
			<div v-if="formTrackingNumber.shipping_id && !formTrackingNumber.shipping_id?.api_shipper" class="mt-3">
				<span class="text-xs px-1 my-2">{{ trans("Tracking number") }}: </span>
				<PureInput
					v-model="formTrackingNumber.tracking_number"
					placeholder="ABC-DE-1234567"
					xxkeydown.enter="() => onSubmitAddService(action, closed)" />
				<p
					v-if="get(formTrackingNumber, ['errors', 'tracking_number'])"
					class="mt-2 text-sm text-red-600">
					{{ formTrackingNumber.errors.tracking_number }}
				</p>
			</div>

			<!-- TODO: show the list of the error from delivery address -->
			<div
				v-if="Object.keys(get(formTrackingNumber, ['errors'], {}))?.length"
				class="mt-2 text-sm text-red-600">
				<p v-for="errorx in formTrackingNumber?.errors?.address">
					{{ errorx }}
				</p>
			</div>

			<div class="flex justify-end mt-3">
				<Button
					:style="'save'"
					:loading="isLoadingButton == 'addTrackingNumber'"
					:label="'save'"
					:disabled="
						!formTrackingNumber.shipping_id || !(formTrackingNumber.shipping_id?.api_shipper ? true : formTrackingNumber.tracking_number)
					"
					full
					@click="() => onSubmitShipment()" />
			</div>

			<!-- Loading: fetching service list -->
			<div
				v-if="isLoadingData === 'addTrackingNumber'"
				class="bg-white/50 absolute inset-0 flex place-content-center items-center">
				<FontAwesomeIcon
					icon="fad fa-spinner-third"
					class="animate-spin text-5xl"
					fixed-width
					aria-hidden="true" />
			</div>
		</div>
	</Modal>
</template>
