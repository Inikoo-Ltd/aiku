<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, inject, provide, ref, watch } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from '@/types/route'
import { PageHeading as PageHeadingTypes } from  '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { Action } from '@/types/Action'
import { BoxStats, PDRNotes, UploadPallet } from '@/types/Pallet'
import { Table as TableTS } from '@/types/Table'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'
import '@/Composables/Icon/PalletReturnStateEnum'
import '@/Composables/Icon/Pallet/PalletType'


import TablePalletReturnPallets from "@/Components/Tables/Grp/Org/Fulfilment/TablePalletReturnPallets.vue"
// import TableServices from "@/Components/Tables/Grp/Org/Fulfilment/TableServices.vue"
// import TablePhysicalGoods from "@/Components/Tables/Grp/Org/Fulfilment/TablePhysicalGoods.vue"
import TableStoredItems from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemReturnStoredItems.vue"
import TableFulfilmentTransactions from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentTransactions.vue"
import RetinaBoxStatsReturn from "@/Components/Retina/Storage/RetinaBoxStatsReturn.vue"

import Popover from "@/Components/Popover.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faSpinnerThird } from '@fad'
import { faStickyNote, faPaperclip, faConciergeBell, faCube } from '@fal'
import { faNarwhal } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'
library.add(faCube, faConciergeBell, faPaperclip, faNarwhal, faSpinnerThird, faStickyNote)

// import '@/Composables/Icon/PalletStateEnum.ts'
// import '@/Composables/Icon/PalletDeliveryStateEnum.ts'
// import '@/Composables/Icon/PalletReturnStateEnum.ts'
import { trans } from 'laravel-vue-i18n'
import { get } from 'lodash-es'
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ModalAddPalletReturn from "@/Components/Segmented/ModalAddPalletReturn.vue"
import Modal from "@/Components/Utils/Modal.vue"

interface UploadSection {
    title: {
        label: string
        information: string
    }
    progressDescription: string
    upload_spreadsheet: UploadPallet
    preview_template: {
        header: string[]
        rows: {}[]
    }
}

const props = defineProps<{
	title: string
	tabs: TSTabs
	data?: {}
	history?: {}
	pageHead: PageHeadingTypes
    attachments?: TableTS
    attachmentRoutes: {
        attachRoute: routeType
        detachRoute: routeType
    }
    interest: {
        pallets_storage: boolean
        items_storage: boolean
        dropshipping: boolean
    }
    
    upload_spreadsheet: UploadPallet

	updateRoute: {
        route: routeType
    }

    palletRoute : {
		index : routeType,
		store : routeType
	}

    box_stats: BoxStats
    notes_data: {
        [key: string]: PDRNotes
    }

	goods?: {}
    stored_items?: {}
    services?: {}
    service_list_route: routeType
    address_management:{
      can_open_address_management: boolean
      updateRoute: routeType
      addresses: AddressManagement
      address_update_route: routeType,
      address_modal_title: string
    }
    physical_goods?: {}
    physical_good_list_route: routeType
    stored_item_list_route : routeType
    stored_items_add_route : routeType
    routeStorePallet : routeType
    route_check_stored_items : routeType
    address_update_route: routeType
    option_attach_file?: {
		name: string
		code: string
	}[]
    pallets_route: routeType
    upload_pallet: UploadSection
}>()



const layout = inject('layout', layoutStructure)
const isModalStoredItems = ref(false)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const timeline = ref({ ...props.data?.data })
const openModal = ref(false)

const isLoadingButton = ref<string | boolean>(false)
const isLoadingData = ref<string | boolean>(false)

const formAddService = useForm({ service_id: '', quantity: 1, historic_asset_id: null })
const formAddPhysicalGood = useForm({ outer_id: '', quantity: 1, historic_asset_id: null })

const component = computed(() => {
	const components: Component = {
		goods: TablePalletReturnPallets,
        stored_items: TableStoredItems,
        services: TableFulfilmentTransactions,
        physical_goods: TableFulfilmentTransactions,
		history: TableHistories,
        attachments: TableAttachments
	}
	return components[currentTab.value]
})


// Tabs: Services
const dataServiceList = ref([])
const onOpenModalAddService = async () => {
    isLoadingData.value = 'addService'
    console.log('props', props.service_list_route.name)
    try {
        const xxx = await axios.get(
            route(props.service_list_route.name, props.service_list_route.parameters)
        )
        dataServiceList.value = xxx?.data?.data || []
    } catch (error) {
        console.error(error)
        notify({
            title: 'Something went wrong.',
            text: 'Failed to fetch Services list',
            type: 'error',
        })
    }
    isLoadingData.value = false
}
const onSubmitAddService = (data: Action, closedPopover: Function) => {
    const selectedHistoricAssetId = dataServiceList.value.filter(service => service.id == formAddService.service_id)[0].historic_asset_id
    
    formAddService.historic_asset_id = selectedHistoricAssetId
    isLoadingButton.value = 'addService'

    formAddService.post(
        route(data.route?.name, {...data.route?.parameters }),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddService.reset()
                handleTabUpdate('services')
            },
            onError: (errors) => {
                console.error('Error during form submission:', errors)
            },
            onFinish: () => {
                isLoadingButton.value = false
            }
        }
    )
}


// Tabs: Physical Goods
const dataPGoodList = ref([])
const onOpenModalAddPGood = async () => {
    isLoadingData.value = 'addPGood'
    try {
        const xxx = await axios.get(
            route(props.physical_good_list_route.name, props.physical_good_list_route.parameters)
        )
        dataPGoodList.value = xxx.data.data
    } catch (error) {
        console.error(error)
        notify({
            title: 'Something went wrong.',
            text: 'Failed to fetch Physical Goods list',
            type: 'error',
        })
    }
    isLoadingData.value = false
}

const deliveryListError = ref<string[]>([])
provide('deliveryListError', deliveryListError.value)
const onClickDisabledSubmit = () => {

    if (!props.data?.data?.estimated_delivery_date) {
        if (!deliveryListError.value?.includes('estimated_delivery_date')) {
            deliveryListError.value?.push('estimated_delivery_date');
        }
    } else {
        const index = deliveryListError.value?.indexOf('estimated_delivery_date');
        if (index > -1) {
            deliveryListError.value?.splice(index, 1);
        }
    }
}

const onSubmitAddPhysicalGood = (data: Action, closedPopover: Function) => {
    const selectedHistoricAssetId = dataPGoodList.value.filter(pgood => pgood.id == formAddPhysicalGood.outer_id)[0].historic_asset_id
    formAddPhysicalGood.historic_asset_id = selectedHistoricAssetId

    isLoadingButton.value = 'addPGood'
    formAddPhysicalGood.post(
        route( data.route?.name, data.route?.parameters ),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddPhysicalGood.reset()
                isLoadingButton.value = false
                handleTabUpdate('physical_goods')
            },
            onError: (errors) => {
                isLoadingButton.value = false
                console.error('Error during form submission:', errors)
            },
        }
    )
}

watch(
	props,
	(newValue) => {
		timeline.value = newValue.data.data
	},
	{ deep: true }
)


// Method: open modal Upload
const isModalUploadOpen = ref(false)

const isModalUploadFileOpen = ref(false)


// Section: add pallet
const openModalAddPallet = ref(false)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template v-if="notes_data?.warehouse?.note" #afterSubNav>
            <div class="px-4">
                <div class="mt-3 py-1 rounded mx-auto px-3 flex gap-x-2 text-sm"
                    :style="{
                        backgroundColor: layout.app.theme[0],
                        color: layout.app.theme[1],
                    }"
                >
                    <div class="flex items-center">
                        <FontAwesomeIcon icon='fal fa-sticky-note' class='text-xl' fixed-width aria-hidden='true' />
                    </div>
                    <div class="font-semibold line-clamp-2" v-tooltip="notes_data.warehouse.note">
                        {{ notes_data.warehouse.label }}:
                        <span class="text-gray-200 font-normal">{{ notes_data.warehouse.note }}</span>
                    </div>
                </div>
            </div>
        </template>
        
        <!-- Button: Add Pallet -->
        <template #button-delete-return="{ action }">
            <div>
                <ModalConfirmationDelete
                    :routeDelete="action.route"
                    isFullLoading
                    :title="action.title"
                    :isWithMessage="action.ask_why"
                    :whyLabel="action.why_label"
                    :description="action.description"
                >
                    <template #default="{ isOpenModal, changeModel }">
                        <Button
                            @click="() => changeModel()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :iconRight="action.iconRight"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>

        <!-- Button: Upload -->
        <template #button-upload="{ action }">
            <Button v-if="currentTab === 'pallets' || currentTab === 'stored_items'" @click="() => isModalUploadOpen = true"
                :style="action.style" :icon="action.icon" v-tooltip="action.tooltip"
            />
            <div v-else></div>
        </template>

        <!-- Button: Upload -->
        <template #button-modal-add-pallet="{ action }">
            
            <Button
                v-if="currentTab == 'pallets'"
                :label="trans('Add pallet')"
                type="secondary"
                icon="fal fa-plus"
                :tooltip="trans('Select pallets via modal')"
                @click="() => openModalAddPallet = true"
                class="border-none rounded-[4px]"
            />
            <div v-else></div>
        </template>

        <!-- Button: Add pallet -->
        <template #button-group-add-pallet="{ action }">
            <Button
                v-if="currentTab == 'pallets'"
                :style="action.style"
                :label="action.label"
                :icon="action.icon"
                :iconRight="action.iconRight"
                :key="`ActionButton${action.label}${action.style}`"
                :tooltip="action.tooltip"
                @click="() => openModal = true"
                class="border-none rounded-[4px]"
            />
            <div v-else></div>
        </template>

        <!-- Button: Add Stored Items -->
        <template #button-group-add-stored-item="{ action }">
            <Button
                v-if="currentTab === 'stored_items'"
                :style="action.style"
                :label="action.label"
                :icon="action.icon"
                :iconRight="action.iconRight"
                :key="`ActionButton${action.label}${action.style}`"
                :tooltip="action.tooltip"
                class="border-none rounded-[4px]"
                @click="() => isModalStoredItems = true"
            />
            <div v-else />
        </template>

        <!-- Button: Add services -->
        <template #button-group-add-service="{ action }">
            <div class="relative" v-if="currentTab === 'sesasrvices'">
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="() => open ? false : onOpenModalAddService()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                            class="border-none rounded-[4px]"
                        />
                    </template>

                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Services') }}: </span>
                            <div class="">
                                <PureMultiselect
                                    v-model="formAddService.service_id"
                                    autofocus
                                    caret
                                    required
                                    searchable
                                    placeholder="Physical Goods"
                                    :options="dataServiceList"
                                    label="name"
                                    valueProp="id"
                                >
                                    <template #label="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ value.code }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">{{ option.name }} <span class="text-sm" :class="isSelected ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span></div>
                                    </template>
                                </PureMultiselect>
                                <p v-if="get(formAddService, ['errors', 'service_id'])" class="mt-2 text-sm text-red-500">
                                    {{ formAddService.errors.service_id }}
                                </p>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput
                                    v-model="formAddService.quantity"
                                    :placeholder="trans('Quantity')"
                                    @keydown.enter="() => onSubmitAddService(action, closed)"
                                />
                                <p v-if="get(formAddService, ['errors', 'quantity'])" class="mt-2 text-sm text-red-500">
                                    {{ formAddService.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addService'"
                                    :label="'save'"
                                    :disabled="!formAddService.service_id || !(formAddService.quantity > 0)"
                                    full
                                    @click="() => onSubmitAddService(action, closed)"
                                />
                            </div>
                                
                            <!-- Loading: fetching service list -->
                            <div v-if="isLoadingData === 'addService'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
            <div v-else />
        </template>


        <!-- Button: Add physical good (single) -->
        <template #button-group-add-physical-good="{ action }">
            <div class="relative" v-if="currentTab === 'phssssysical_goods'">
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="open ? false : onOpenModalAddPGood()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                            class="border-none rounded-[4px]"
                        />
                    </template>
                    
                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Physical Goods') }}: </span>
                            <div>
                                <PureMultiselect
                                    v-model="formAddPhysicalGood.outer_id"
                                    autofocus
                                    caret
                                    required
                                    searchable
                                    placeholder="Physical Goods"
                                    :options="dataPGoodList"
                                    label="name"
                                    valueProp="id"
                                >
                                    <template #label="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-gray-400">({{ value.code }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">{{ option.name }} <span :class="isSelected ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span></div>
                                    </template>
                                </PureMultiselect>
                                <p v-if="get(formAddPhysicalGood, ['errors', 'outer_id'])" class="mt-2 text-sm text-red-500">
                                    {{ formAddPhysicalGood.errors.outer_id }}
                                </p>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput
                                    v-model="formAddPhysicalGood.quantity"
                                    :placeholder="trans('Quantity')"
                                    @keydown.enter="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                                <p v-if="get(formAddPhysicalGood, ['errors', 'quantity'])"
                                    class="mt-2 text-sm text-red-500">
                                    {{ formAddPhysicalGood.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addPGood'"
                                    :disabled="!formAddPhysicalGood.outer_id || !(formAddPhysicalGood.quantity > 0)"
                                    :label="'save'"
                                    full
                                    @click="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                            </div>

                            <!-- Loading: fetching pgood list -->
                            <div v-if="isLoadingData === 'addPGood'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
            <div v-else />
        </template>

        <template #other>
            <Button
                v-if="currentTab === 'attachments'"
                @click="() => isModalUploadFileOpen = true"
                :label="trans('Attach file')"
                icon="fal fa-upload"
                type="secondary"
            />
        </template>
    </PageHeading>

    <div class="border-b border-gray-200">
        <Timeline
            :options="timeline.timeline"
            :state="timeline.state"
            :slidesPerView="Object.entries(timeline.timeline).length"
        />
    </div>

    <!-- Box: Stats -->
    <RetinaBoxStatsReturn
        :data_pallet="data?.data"
        :box_stats
        :updateRoute
        :notes_data
        :address_management
        :address_update_route
    />

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"  />
    <component
        :is="component"
        :data="props[currentTab]"
        :key="timeline.state"
        :state="timeline.state"
        :tab="currentTab"
        :route_checkmark="currentTab == 'pallets' ? routeStorePallet : route_check_stored_items"
        :palletReturn="data?.data"
        :detachRoute="attachmentRoutes.detachRoute"
    >
        <template #button-empty-state-attachments="{ action }">
            <Button
                v-if="currentTab === 'attachments'"
                @click="() => isModalUploadFileOpen = true"
                :label="trans('Attach file')"
                icon="fal fa-upload"
                type="secondary"
            />
        </template>
    </component>

    <!-- <UploadExcel
        v-model="isModalUploadOpen"
        scope="Pallet delivery"
        :title="{
            label: 'Upload your new pallet deliveries',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        progressDescription="Adding Pallet Deliveries"        
        :upload_spreadsheet
        :additionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
    /> -->
    
    <UploadExcel
        v-if="upload_pallet"
        v-model="isModalUploadOpen"
        :title="upload_pallet.title"
        :progressDescription="upload_pallet.progressDescription"
        :upload_spreadsheet="upload_pallet.upload_spreadsheet"
        :preview_template="upload_pallet.preview_template"
        :additionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
    />
    
    <UploadAttachment
        v-model="isModalUploadFileOpen"
        scope="attachment"
        :title="{
            label: 'Upload your file',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        progressDescription="Adding Pallet Deliveries"
        :attachmentRoutes
        :options="props.option_attach_file"
    />
    
    <Modal :isOpen="openModalAddPallet" @onClose="openModalAddPallet = false" width="w-full max-w-4xl">
        <ModalAddPalletReturn
            :fetchRoute="pallets_route"
            :palletReturn="data?.data"
        >

        </ModalAddPalletReturn>

    </Modal>
</template>
