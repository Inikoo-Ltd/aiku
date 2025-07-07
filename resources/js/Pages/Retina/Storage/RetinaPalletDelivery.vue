<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref, watch, inject, provide } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Timeline from '@/Components/Utils/Timeline.vue'
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { get } from 'lodash-es'
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
import { Table } from '@/types/Table'
import { PalletDelivery, BoxStats, PDRNotes, UploadPallet } from '@/types/Pallet'
import { Tabs as TSTabs } from '@/types/Tabs'
import { PageHeading as PageHeadingTypes } from  '@/types/PageHeading'
import type { Component } from 'vue'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'

import TableFulfilmentTransactions from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentTransactions.vue"
import RetinaTablePalletDeliveryPallets from '@/Components/Tables/Retina/RetinaTablePalletDeliveryPallets.vue'
// import TableServices from "@/Components/Tables/Grp/Org/Fulfilment/TableServices.vue"
// import TablePhysicalGoods from "@/Components/Tables/Grp/Org/Fulfilment/TablePhysicalGoods.vue"
import TableStoredItems from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItems.vue"
import RetinaBoxStatsDelivery from "@/Components/Retina/Storage/RetinaBoxStatsDelivery.vue"
import ModalConfirmationDelete from '@/Components/Utils/ModalConfirmationDelete.vue'

import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'
import { Table as TableTS } from '@/types/Table'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSeedling, faShare, faSpellCheck, faCheck, faCheckDouble, faCross, faUser, faFilePdf, faTruckCouch, faPallet, faCalendarDay, faConciergeBell, faCube, faSortSizeUp, faBox, faPencil, faPaperclip } from '@fal'
import { Action } from '@/types/Action'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import axios from 'axios'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
library.add(faSeedling, faShare, faSpellCheck, faCheck, faCheckDouble, faCross, faUser, faFilePdf, faTruckCouch, faPallet, faCalendarDay, faConciergeBell, faCube, faSortSizeUp, faBox, faPencil, faPaperclip)

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

    data: {
        data: PalletDelivery
    }
    history?: {}
    pageHead: PageHeadingTypes
    updateRoute: {
        route: routeType
    }
    
    interest: {
        pallets_storage: boolean
        items_storage: boolean
        dropshipping: boolean
    }
    upload_spreadsheet: UploadPallet

    attachmentRoutes: {
        attachRoute: routeType
        detachRoute: routeType
    }

    storedItemsRoute: {
        index: routeType
        store: routeType
    }
    box_stats: BoxStats
    notes_data: PDRNotes[]
    public_notes: any
    pallet_limits?: {
        status: string
        message: string
    }

    goods?: Table
    stored_items?: Table

    services?: Table
    service_list_route: routeType

    physical_goods?: Table
    physical_good_list_route: routeType

    attachments: {}
    option_attach_file?: {
		name: string
		code: string
	}[]
    upload_pallet: UploadSection
    upload_stored_item: UploadSection
}>()

const layout = inject('layout', layoutStructure)

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const isLoading = ref<string | boolean>(false)
const timeline = ref({ ...props.data.data })

const formAddPallet = useForm({ notes: '', customer_reference: '', type : 'pallet' })
const formMultiplePallet = useForm({ number_pallets: 1, type : 'pallet' })
const formAddService = useForm({ service_id: '', quantity: 1, historic_asset_id: null })
const formAddPhysicalGood = useForm({ outer_id: '', quantity: 1, historic_asset_id: null })


const component = computed(() => {
    const components: Component = {
        goods: RetinaTablePalletDeliveryPallets,
        stored_items: TableStoredItems,
        services: TableFulfilmentTransactions,
        physical_goods: TableFulfilmentTransactions,
        history: TableHistories,
        attachments: TableAttachments
    }
    return components[currentTab.value]

})

const isLoadingData = ref<string | boolean>(false)
const isLoadingButton = ref<string | boolean>(false)


// Method: Add multiple pallet
const onAddMultiplePallet = (data: {route: routeType}, closedPopover: Function) => {
    isLoading.value = 'addMultiplePallet'
    formMultiplePallet.post(route(
        data.route.name,
        data.route.parameters
    ), {
        preserveScroll: true,
        onSuccess: () => {
            closedPopover()
            formMultiplePallet.reset('number_pallets','type')
            isLoading.value = false
            const index = deliveryListError.value?.indexOf('number_pallets');
            handleTabUpdate('goods')
            if (index > -1) {
                deliveryListError.value?.splice(index, 1);
            }  // Delete the error
        },
        onError: (errors) => {
            isLoading.value = false
            console.error('Error during form submission:', errors)
        },
    })
}


// To re-render Table after click Submit (so the Table retrieve the new props)
const tableKey = ref(1)
const changeTableKey = () => {
    tableKey.value = tableKey.value + 1
}


const changePalletType=(form,fieldName,value)=>{
    form[fieldName] = value
}


// Tabs: Pallet
const isModalUploadOpen = ref(false)
const onAddPallet = (data: {route: routeType}, closedPopover: Function) => {
    isLoading.value = 'addSinglePallet'
    formAddPallet.post(route(
        data.route.name,
        data.route.parameters
    ), {
        preserveScroll: true,
        onSuccess: () => {
            closedPopover()
            formAddPallet.reset('notes', 'customer_reference','type')
            isLoading.value = false
            handleTabUpdate('goods')
            const index = deliveryListError.value?.indexOf('number_pallets');
            if (index > -1) {
                deliveryListError.value?.splice(index, 1);
            }  // Delete the error
        },
        onError: (errors) => {
            isLoading.value = false
            console.error('Error during form submission:', errors)
        },
    })
}
const onSubmitPallet = async (action: routeType) => {
    isLoading.value = 'submitPallet'
    router.post(route(action.name, action.parameters), {}, {
        onError: (e) => {
            console.warn('Error on Submit', e)
            notify({
                title: trans('Something went wrong.'),
                text: e?.message,
                type: 'error',
            })
        },
        onSuccess: (e) => {
            console.log('on success', e)
            changeTableKey()
        },
        onFinish: (e) => {
            // console.log('11111', e)
            isLoading.value = false
        }
    })
}



// Tabs: Services
const dataServiceList = ref([])
const onOpenModalAddService = async () => {
    isLoadingData.value = 'addService'
    try {
        const xxx = await axios.get(
            route(props.service_list_route.name, props.service_list_route.parameters)
        )
        dataServiceList.value = xxx.data.data
    } catch (error) {
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
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to add service, please try again.',
                    type: 'error',
                })
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
        console.log(error)
        notify({
            title: 'Something went wrong.',
            text: 'Failed to fetch Physical Goods list',
            type: 'error',
        })
    }
    isLoadingData.value = false
}
const onSubmitAddPhysicalGood = (data: Action, closedPopover: Function) => {
    const selectedHistoricAssetId = dataPGoodList.value.filter(pgood => pgood.id == formAddPhysicalGood.outer_id)[0].historic_asset_id
    formAddPhysicalGood.historic_asset_id = selectedHistoricAssetId

    isLoadingButton.value = 'addPGood'
    formAddPhysicalGood.post(
        route(data.route?.name, data.route?.parameters),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddPhysicalGood.reset()
                handleTabUpdate('physical_goods')
            },
            onError: (errors) => {
                notify({
                    title: 'Something went wrong.',
                    text: 'Failed to add physical good, please try again.',
                    type: 'error',
                })
            },
            onFinish: () => {
                isLoadingButton.value = false
            }
        }
    )
}



watch(() => props.data, (newValue) => {
    timeline.value = newValue.data
}, { deep: true })

const typePallet = [
    { label : 'Carton', value : 'box'}, 
    { label : 'Pallet', value : 'pallet'}, 
    { label : 'Oversize', value : 'oversize'}
]

// To set error
const deliveryListError = ref<string[]>([])
provide('deliveryListError', deliveryListError.value)
const onClickDisabledSubmit = () => {
    if (props.data?.data?.number_pallets < 1) {
        if (!deliveryListError.value?.includes('number_pallets')) {
            deliveryListError.value?.push('number_pallets');
        }
    } else {
        const index = deliveryListError.value?.indexOf('number_pallets');
        if (index > -1) {
            deliveryListError.value?.splice(index, 1);
        }
    }

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

const isModalUploadFileOpen = ref(false)

const palletLabel = computed(() => {
  const selected = typePallet.find(item => item.value === formMultiplePallet.type)
  return selected ? selected.label : 'pallets'
})

// Section: Upload spreadsheet
const isModalUploadPallet = ref(false)
const isModalUploadStoredItemOpen = ref(false)
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <!-- Button: Upload -->
        <template #button-group-upload="{ action }">

            <Menu v-slot="{ close }" as="div" class="relative inline-block text-left">
                <div>
                    <MenuButton class="">
                        <Button
                            v-if="currentTab === 'goods'"
                            :label="action.label"
                            :style="action.style"
                            :icon="action.icon"
                            v-tooltip="action.tooltip"
                            class="rounded-l-ms rounded-r-none border-0"
                        />
                        <div v-else></div>
                    </MenuButton>
                </div>

                <transition name="headlessui2">
                    <MenuItems class="z-10 absolute right-0 p-1 mt-2 w-fit origin-top-right rounded-md bg-white shadow-lg ring-1 ring-indigo-500/50 focus:outline-none" >
                        <div @click="() => (isModalUploadPallet = true, close())" class="whitespace-nowrap px-3 py-1 rounded hover:bg-gray-200 cursor-pointer">
                            <FontAwesomeIcon icon='fal fa-upload' class='' fixed-width aria-hidden='true' />
                            {{ trans("Upload goods") }}
                        </div>
                        <div @click="() => (isModalUploadStoredItemOpen = true, close())" class="whitespace-nowrap px-3 py-1 rounded hover:bg-gray-200 cursor-pointer">
                            <FontAwesomeIcon icon='fal fa-upload' class='' fixed-width aria-hidden='true' />
                            {{ trans("Upload Customer's SKU") }}
                        </div>
                    </MenuItems>
                </transition>
            </Menu>

        </template>
        
        <!-- Button: delete Delivery -->
        <template #button-delete-delivery="{ action }">
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

        <!-- Button: Add multiple pallets -->
        <template #button-group-multiple="{ action }">
            <Popover  position="-right-32" class="md:relative h-full" :class="deliveryListError.includes('number_pallets') ? 'errorShake' : ''">
                <template #button>
                    <Button
                        :style="action.style"
                        :icon="action.icon"
                        :iconRight="action.iconRight"
                        :key="`ActionButton${action.label}${action.style}`"
                        :tooltip="trans('Add multiple items')"
                        class="rounded-none border-none"
                    />
                </template>

                <template #content="{ close: closed }">
                    <div class="w-[350px]">
                        <span class="text-xs  my-2">{{ trans('Type') }}: </span>
                        <div class="flex items-center">
                            <div v-for="(typeData, typeIdx) in typePallet" :key="typeIdx" class="relative py-3 mr-4">
                                <div>
                                    <input type="checkbox" :id="typeData.value" :value="typeData.value"
                                        :checked="formMultiplePallet.type == typeData.value"
                                        @input="changePalletType(formMultiplePallet,'type',typeData.value)"
                                        class="rounded border-gray-300 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                                        :style="{
                                            color: layout.app.theme[0]
                                        }"
                                    >
                                    <label :for="typeData.value" class="pl-2 cursor-pointer">{{ typeData.label }}</label>
                                </div>
                            </div>
                        </div>
                        <span class="text-xs px-1 my-2">Number of {{ palletLabel }} : </span>
                        <div>
                            <PureInput
                                v-model="formMultiplePallet.number_pallets"
                                placeholder="1-100"
                                type="number"
                                :minValue="1"
                                :maxValue="100"
                                autofocus
                                @update:modelValue="() => formMultiplePallet.errors.number_pallets = ''"
                                @keydown.enter="() => formMultiplePallet.number_pallets ? onAddMultiplePallet(action, closed) : ''"
                            />
                            <p v-if="get(formMultiplePallet, ['errors', 'customer_reference'])" class="mt-2 text-sm text-red-500">
                                {{ formMultiplePallet.errors.number_pallets }}
                            </p>
                        </div>
                        <div class="flex justify-end mt-3">
                            <Button
                                :style="'save'"
                                :loading="isLoading === 'addMultiplePallet'"
                                label="save"
                                full
                                @click="() => onAddMultiplePallet(action, closed)"
                            />
                        </div>
                    </div>
                </template>
            </Popover>
            <!-- <div v-else></div> -->
        </template>

        <!-- Button: Add pallet (single) -->
        <template #button-group-storage="{ action }">
            <div v-if="currentTab !== 'cccccccpallets'" class="md:relative" :class="deliveryListError.includes('number_pallets') ? 'errorShake' : ''">
                <Popover>
                    <template #button>
                        <Button :style="action.style" :label="action.label" :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="trans('Add single item')"
                            class="rounded-l-none rounded-r-none border-none " />
                    </template>

                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Type') }}: </span>
                            <div class="flex items-center">
                                <div v-for="(typeData, typeIdx) in typePallet" :key="typeIdx"
                                    class="relative py-3 mr-4">
                                    <div>
                                        <input type="checkbox" :id="typeData.value" :value="typeData.value"
                                            :checked="formAddPallet.type == typeData.value"
                                            @input="changePalletType(formAddPallet, 'type', typeData.value)"
                                            class="rounded border-gray-300 focus:ring-indigo-500 h-4 w-4 cursor-pointer"
                                            :style="{
                                                color: layout.app.theme[0]
                                            }"    
                                        >
                                        <label :for="typeData.value" class="pl-2 cursor-pointer">{{ typeData.label }}</label>
                                    </div>
                                </div>
                            </div>
                            <span class="text-xs px-1 my-2">{{ trans('Reference') }}: </span>
                            <div>
                                <PureInput v-model="formAddPallet.customer_reference" placeholder="Reference"
                                    autofocus />
                                <p v-if="get(formAddPallet, ['errors', 'customer_reference'])"
                                    class="mt-2 text-sm text-red-600">
                                    {{ formAddPallet.errors.customer_reference }}
                                </p>
                            </div>

                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Notes') }}: </span>
                                <textarea v-model="formAddPallet.notes" placeholder="Notes"
                                    class="block w-full rounded-md border-gray-300 shadow-sm placeholder:text-gray-400 focus:border-gray-500 focus:ring-gray-500 sm:text-sm" />
                                <p v-if="get(formAddPallet, ['errors', 'notes'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddPallet.errors.notes }}
                                </p>
                            </div>

                            <div class="flex justify-end mt-3">
                                <Button
                                    :style="'save'"
                                    :loading="isLoading === 'addSinglePallet'"
                                    :label="'save'"
                                    full
                                    @click="() => onAddPallet(action, closed)"
                                />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
            <div v-else />
        </template>

        
        <!-- Button: Add service (single) -->
        <template #button-group-service="{ action }">
            <div class="relative" v-if="currentTab !== 'cccccccservices'">
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="() => open ? false : onOpenModalAddService()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :tooltip="action.tooltip"
                            :key="`ActionButton${action.label}${action.style}`"
                            class="border-none rounded-sm"
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
                                    placeholder="Select service"
                                    :options="dataServiceList"
                                    label="name"
                                    valueProp="id"
                                    @keydown.enter="() => onSubmitAddService(action, closed)"
                                >
                                    <template #label="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ value.code }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">{{ option.name }} <span class="text-sm" :class="isSelected ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span></div>
                                    </template>
                                </PureMultiselect>
                                <p v-if="get(formAddService, ['errors', 'service_id'])"
                                    class="mt-2 text-sm text-red-500">
                                    {{ formAddService.errors.service_id }}
                                </p>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput v-model="formAddService.quantity" placeholder="Quantity" @keydown.enter="() => onSubmitAddService(action, closed)" />
                                <p v-if="get(formAddService, ['errors', 'quantity'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddService.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    @click="() => onSubmitAddService(action, closed)"
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addService'"
                                    :disabled="!formAddService.service_id || !(formAddService.quantity > 0)"
                                    label="Save"
                                    full
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
        <template #button-group-physical-good="{ action }">
            <div class="relative" v-if="currentTab !== 'ccccccphysical_goods'">
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="() => open ? false : onOpenModalAddPGood()"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                            class="border-none rounded-l-none rounded-r-md"
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
                                    searchable
                                    required
                                    placeholder="Physical Goods"
                                    :options="dataPGoodList"
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
                                <p v-if="get(formAddPhysicalGood, ['errors', 'outer_id'])"
                                    class="mt-2 text-sm text-red-600">
                                    {{ formAddPhysicalGood.errors.outer_id }}
                                </p>
                            </div>
                            <div class="mt-3">
                                <span class="text-xs px-1 my-2">{{ trans('Quantity') }}: </span>
                                <PureInput
                                    v-model="formAddPhysicalGood.quantity"
                                    placeholder="Quantity"
                                    @keydown.enter="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                                <p v-if="get(formAddPhysicalGood, ['errors', 'quantity'])"
                                    class="mt-2 text-sm text-red-600">
                                    {{ formAddPhysicalGood.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    :key="'button' + formAddPhysicalGood.outer_id + formAddPhysicalGood.quantity"
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addPGood'"
                                    :disabled="!formAddPhysicalGood.outer_id || !(formAddPhysicalGood.quantity > 0)"
                                    :label="'save'"
                                    full
                                    @click="() => onSubmitAddPhysicalGood(action, closed)"
                                />
                            </div>

                            <!-- Loading: fetching service list -->
                            <div v-if="isLoadingData === 'addPGood'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
            <div v-else />
        </template>

        <!-- Button: Submit -->
        <template #button-submit="{ action }">
            <div class="relative">
                <Button
                    @click="onSubmitPallet(action.route)"
                    v-bind="action"
                    :loading="isLoading === 'submitPallet'"
                />

                <div v-if="action.disabled" v-tooltip="action.tooltip" @click="() => onClickDisabledSubmit()" class="cursor-pointer absolute inset-0">

                </div>
            </div>
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
    
    <!-- Box: Public Notes -->
    <div v-if="public_notes" class="bg-pink-500 text-white font-bold p-4 rounded flex justify-between items-center">
        <span class="flex-grow">{{ public_notes || 'public Notes'}}</span>
    </div>

    <!-- Section: Pallet Warning -->
    <div v-if="pallet_limits?.status">
        <div class="p-4"
            :class="{
                'bg-yellow-50': pallet_limits?.status === 'almost',
                'bg-orange-200': pallet_limits?.status === 'limit',
                'bg-red-200': pallet_limits?.status === 'exceeded',
            }"
        >
            <div class="flex">
                <div class="flex-shrink-0">
                    <font-awesome-icon :icon="['fad', 'exclamation-triangle']" class="h-5 w-5 text-amber-500"
                        aria-hidden="true"
                        :class="{
                            'text-yellow-50': pallet_limits?.status === 'almost',
                            'text-orange-200': pallet_limits?.status === 'limit',
                            'text-red-600': pallet_limits?.status === 'exceeded',
                        }"
            />
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">{{ trans('Attention needed') }}</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>{{ pallet_limits?.message }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Section: Timeline -->
    <div v-if="timeline.state != 'in_process'" class="border-b border-gray-200">
        <Timeline :options="timeline.timeline" :state="timeline.state" :slidesPerView="6" />
    </div>


    <!-- Box: Stats -->
    <RetinaBoxStatsDelivery
        :data_pallet="data.data"
        :box_stats="box_stats"
        :updateRoute
        :notes_data
    />

    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab]"
        :state="timeline.state"
        :key="timeline.state"
        :tab="currentTab"
        :tableKey="tableKey"
        :storedItemsRoute="storedItemsRoute"
        @renderTableKey="() => (console.log('emit render', changeTableKey()))"
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

    <!-- <UploadExcel :propName="'pallet deliveries'" description="Adding Pallet Deliveries" :routes="{
        upload: get(dataModal, 'uploadRoutes', {}),
        download: props.uploadRoutes.download,
        history: props.uploadRoutes.history
    }" :dataModal="dataModal" /> -->

    <UploadExcel
        v-model="isModalUploadOpen"
        scope="Pallet delivery"
        :title="{
            label: 'Upload your new pallet deliveries',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
        progressDescription="Adding Pallet Deliveries"        
        :upload_spreadsheet
        :additionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
    />

    <UploadExcel
        v-model="isModalUploadPallet"
        :title="upload_pallet.title"
        :progressDescription="upload_pallet.progressDescription"
        :upload_spreadsheet="upload_pallet.upload_spreadsheet"
        :preview_template="upload_pallet.preview_template"
        :additionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
    />

    <UploadExcel
        v-model="isModalUploadStoredItemOpen"
        :title="upload_stored_item.title"
        :progressDescription="upload_stored_item.progressDescription"
        :preview_template="upload_stored_item.preview_template"
        :upload_spreadsheet="upload_stored_item.upload_spreadsheet"
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

    <!-- <pre>{{ props.pallets }}</pre> -->
    <!-- <pre>{{ $inertia.page.props.queryBuilderProps.pallets.columns }}</pre> -->
</template>
