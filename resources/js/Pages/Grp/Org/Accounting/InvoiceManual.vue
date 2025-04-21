<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import { Link } from '@inertiajs/vue3'
import Popover from '@/Components/Popover.vue'
import DatePicker from "@vuepic/vue-datepicker"

import { computed, defineAsyncComponent, inject, ref, watch } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import AddressSelector from "@/Components/DataDisplay/AddressSelector.vue"
import ModelDetails from "@/Components/ModelDetails.vue"
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue"
import OperationsInvoiceShowcase from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue"
import Button from '@/Components/Elements/Buttons/Button.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import { trans } from 'laravel-vue-i18n'
import BoxStatPallet from '@/Components/Pallet/BoxStatPallet.vue'
import { Calculation, ProductTransaction } from '@/types/Invoices'
import { routeType } from '@/types/route'
import OrderSummary from '@/Components/Summary/OrderSummary.vue'
import { FieldOrderSummary } from '@/types/Pallet'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faIdCardAlt, faMapMarkedAlt, faPhone, faChartLine, faCreditCard, faCube, faFolder, faPercent, faCalendarAlt, faDollarSign, faMapMarkerAlt, faPencil, faDraftingCompass } from '@fal'
import { faClock, faFileInvoice, faFilePdf } from '@fas'
import { faCheck } from '@far'
import { usePage } from '@inertiajs/vue3';

library.add(faCheck, faIdCardAlt, faMapMarkedAlt, faPhone, faFolder, faCube, faChartLine, faCreditCard, faClock, faFileInvoice, faPercent, faCalendarAlt, faDollarSign, faFilePdf, faMapMarkerAlt, faPencil, faDraftingCompass)

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))


// import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import TableInvoiceTransactions from "@/Components/Tables/Grp/Org/Accounting/TableInvoiceTransactions.vue";
import { Address } from '@/types/PureComponent/Address'
import { Icon } from '@/types/Utils/Icon'
// import AddressLocation from '@/Components/Elements/Info/AddressLocation.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { InvoiceResource } from '@/types/invoice'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import NeedToPay from '@/Components/Utils/NeedToPay.vue'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import InvoiceManualTransactions from '@/Components/Accounting/InvoiceManualTransactions.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { Action } from '@/types/Action'
import { get } from 'lodash-es'
// const locale = useLocaleStore()
const locale = inject('locale', aikuLocaleStructure)


const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    tabs: {
        current: string
        navigation: {}
    }

    box_stats: {
        customer: {
            company_name: string
            contact_name: string
            route: routeType
            location: string[]
            phone: string
            reference: string
            slug: string
        }
        information: {
            recurring_bill: {
                reference: string
                route: routeType
            }
            routes: {
                fetch_payment_accounts: routeType
                submit_payment: routeType
            }
            paid_amount: number | null
            pay_amount: number | null
        }
    }
    exportPdfRoute: routeType
    order_summary: FieldOrderSummary[][]
    recurring_bill_route: routeType
    invoice: InvoiceResource
    items: {}
    payments: {}
    details: {}
    history: {}

    outbox: {
        state: string
        workshop_route: routeType
    }

    // service_lists?: [],
    service_list_route: routeType

    // physical_good_lists?: []
    physical_good_list_route: routeType
    
    pallet_list_route: routeType
}>()

const currentTab = ref<string>(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        items: InvoiceManualTransactions,
        // items: TableInvoiceTransactions,
        payments: TablePayments,
        details: ModelDetails,
        history: ModelChangelog,
    }

    return components[currentTab.value]
})



// Section: Payment invoice
// const listPaymentMethod = ref([])
// const isLoadingFetch = ref(false)
// const fetchPaymentMethod = async () => {
//     try {
//         isLoadingFetch.value = true
//         const { data } = await axios.get(route(props.box_stats.information.routes.fetch_payment_accounts.name, props.box_stats.information.routes.fetch_payment_accounts.parameters))
//         listPaymentMethod.value = data.data
//     } catch (error) {
//         notify({
//             title: trans('Something went wrong'),
//             text: trans('Failed to fetch payment method list'),
//             type: 'error',
//         })
//     }
//     finally {
//         isLoadingFetch.value = false
//     }
// }

// const paymentData = ref({
//     payment_method: null as number | null,
//     payment_amount: 0 as number | null,
//     payment_reference: ''
// })
// const isOpenModalPayment = ref(false)
// const isLoadingPayment = ref(false)
// const errorPaymentMethod = ref<null | unknown>(null)
// const onSubmitPayment = () => {
//     try {
//         router[props.box_stats.information.routes.submit_payment.method || 'post'](
//             route(props.box_stats.information.routes.submit_payment.name, {
//                 ...props.box_stats.information.routes.submit_payment.parameters,
//                 paymentAccount: paymentData.value.payment_method
//             }),
//             {
//                 amount: paymentData.value.payment_amount,
//                 reference: paymentData.value.payment_reference,
//                 status: 'success',
//                 state: 'completed',
//             },
//             {
//                 onStart: () => isLoadingPayment.value = true,
//                 onFinish: () => {
//                     isLoadingPayment.value = false,
//                     isOpenModalPayment.value = false,
//                     notify({
//                         title: trans('Success'),
//                         text: 'Successfully add payment invoice',
//                         type: 'success',
//                     })
//                 },
//                 onSuccess: () => {
//                     paymentData.value.payment_method = null,
//                     paymentData.value.payment_amount = 0,
//                     paymentData.value.payment_reference = ''
//                 }
//             }
//         )

//     } catch (error: unknown) {
//         errorPaymentMethod.value = error
//     }
// }

// watch(paymentData, () => {
//     if (errorPaymentMethod.value) {
//         errorPaymentMethod.value = null
//     }
// })

// Section: Send Invoice
const isVisitWorkshopOutbox = ref(false)
const isModalSendInvoice = ref(false)




// ===
const isLoadingData = ref<false | 'addService' | 'addPGood'>(false)
const isLoadingButton = ref<false | 'addService' | 'addPGood'>(false)


// Tabs: Services
const formAddService = useForm({ service_id: '', quantity: 1, pallet_id: null, handle_date: null })
// const onOpenModalAddService = async () => {
//     isLoadingData.value = 'addService'
//     try {
//         const xxx = await axios.get(
//             route(props.service_list_route.name, props.service_list_route.parameters)
//         )
//         dataServiceList.value = xxx?.data?.data || []
//     } catch (error) {
//         notify({
//             title: 'Something went wrong.',
//             text: 'Failed to fetch Services list',
//             type: 'error',
//         })
//     }
//     isLoadingData.value = false
// }
const dataServiceList = ref([])
const dataPalletList = ref([])
const onSubmitAddService = (data: Action, closedPopover: Function) => {
    // console.log('dataservicelist', dataServiceList.value)
    // console.log('formAddService.service_id', formAddService.service_id)
    const selectedHistoricAssetId = dataServiceList.value.filter(service => service.id == formAddService.service_id)[0]?.historic_asset_id
    /*  console.log('hhh', handleTabUpdate) */
    // console.log('data.route?.name', data.route?.name )
    // console.log('selectedHistoricAssetId', selectedHistoricAssetId)
    // formAddService.historic_asset_id = selectedHistoricAssetId
    isLoadingButton.value = 'addService'

    formAddService.post(
        route(data.route?.name, {...data.route?.parameters, historicAsset: selectedHistoricAssetId}),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddService.reset()
                // handleTabUpdate('services')
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
const formAddPhysicalGood = useForm({ outer_id: '', quantity: 1 })
const dataPGoodList = ref([])
const onOpenModalAddPGood = async () => {
    isLoadingData.value = 'addPGood'
    try {
        const xxx = await axios.get(
            route(props.physical_good_list_route.name, props.physical_good_list_route.parameters)
        )
        dataPGoodList.value = xxx.data.data
    } catch (error) {
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
        route(data.route?.name, {...data.route?.parameters, historicAsset: selectedHistoricAssetId}),
        {
            preserveScroll: true,
            onSuccess: () => {
                closedPopover()
                formAddPhysicalGood.reset()
                // handleTabUpdate('physical_goods')
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
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">

        <!-- Button: PDF -->
        <!-- <template #otherBefore>
            <a v-if="exportPdfRoute?.name" :href="route(exportPdfRoute.name, exportPdfRoute.parameters)" target="_blank"
                class="mt-4 sm:mt-0 sm:flex-none text-base" v-tooltip="trans('Download in')">
                <Button label="PDF" icon="fas fa-file-pdf" type="tertiary" />
            </a>
        </template> -->

        
        <!-- Button: Add service -->
        <template #button-add-service="{ action }">
            <div class="relative" >
                <Popover>
                    <template #button="{open}">
                        <Button
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :key="`ActionButton${action.label}${action.style}`"
                            :tooltip="action.tooltip"
                        />
                    </template>
                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Services') }}: </span>
                            <div class="">
                                <!-- <PureMultiselect
                                    v-model="formAddService.service_id"
                                    autofocus
                                    caret
                                    required
                                    searchable
                                    placeholder="Select service"
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
                                </PureMultiselect> -->

                                <PureMultiselectInfiniteScroll
                                    v-model="formAddService.service_id"
                                    :fetchRoute="props.service_list_route"
                                    :placeholder="trans('Select Services')"
                                    valueProp="id"
                                    @optionsList="(options) => dataServiceList = options"
                                >
                                    <template #singlelabel="{ value }">
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ locale.currencyFormat(value.currency_code, value.price) }}/{{ value.unit }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">
                                            <FontAwesomeIcon v-if="option?.is_pallet_handling" v-tooltip="trans('Special service')" icon="fas fa-diamond" class="text-teal-500 text-sm" fixed-width aria-hidden="true" />
                                            {{ option.name }}
                                            <span class="text-sm text-gray-400">({{ locale.currencyFormat(option.currency_code, option.price) }}/{{ option.unit }})</span>
                                        </div>
                                    </template>
                                </PureMultiselectInfiniteScroll>

                                <!-- Pallet -->
                                <div v-if="dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling" class="mt-3">
                                    <span class="text-xs px-1 my-2">{{ trans('Pallet to attach') }}: </span>
                                    <PureMultiselectInfiniteScroll
                                        v-model="formAddService.pallet_id"
                                        :fetchRoute="props.pallet_list_route"
                                        :placeholder="trans('Select pallet')"
                                        required
                                        valueProp="id"
                                        @optionsList="(options) => dataPalletList = options"
                                    >
                                        <template #singlelabel="{ value }">
                                            <div class="w-full text-left pl-4">{{ value.reference }} <span class="text-sm text-gray-400">({{ value.type }})</span></div>
                                        </template>

                                        <template #option="{ option, isSelected, isPointed }">
                                            <div class="">{{ option.reference }} <span class="text-sm text-gray-400 capitalize">({{ option.type }})</span></div>
                                        </template>
                                    </PureMultiselectInfiniteScroll>

                                    <p v-if="get(formAddService, ['errors', 'pallet_id'])" class="mt-2 text-sm text-red-600">
                                        {{ formAddService.errors.pallet_id }}
                                    </p>
                                </div>

                                <!-- Date -->
                                <Popover v-if="dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling" position="" style="z-index: 20" class="mt-3 ">
                                    <template #button>
                                        <div class="text-left">
                                            <span class="text-xs px-1 my-2">{{ trans('Date') }}: </span>
                                            <div
                                                xxv-tooltip="'useDaysLeftFromToday(dataPalletDelivery.estimated_delivery_date)'"
                                                class="text-left border border-gray-300 py-2 text-sm rounded px-3 cursor-pointer"
                                                :class="formAddService.handle_date ? '' : 'text-gray-400 '"
                                            >
                                                <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-base" fixed-width aria-hidden="true" />
                                                {{ formAddService.handle_date ? useFormatTime(formAddService.handle_date, { formatTime: 'ddmy' }) : trans("Select date") }}
                                            </div>
                                        </div>
                                    </template>

                                    <template #content="{ close }">
                                        <DatePicker
                                            v-model="formAddService.handle_date"
                                            @update:modelValue="() => close()"
                                            inline
                                            auto-apply
                                            :enable-time-picker="false"
                                        />
                                    </template>
                                </Popover>

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
                                <p v-if="get(formAddService, ['errors', 'quantity'])" class="mt-2 text-sm text-red-600">
                                    {{ formAddService.errors.quantity }}
                                </p>
                            </div>
                            <div class="flex justify-end mt-3">
                                <Button
                                    @click="() => onSubmitAddService(action, closed)"
                                    :style="'save'"
                                    :loading="isLoadingButton == 'addService'"
                                    :disabled="
                                        !formAddService.service_id
                                        || !(formAddService.quantity > 0)
                                        || dataServiceList?.find(list => list.id === formAddService.service_id)?.is_pallet_handling ? (!formAddService.pallet_id || !formAddService.handle_date) : false
                                    "
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
        </template>

        <!-- Button: Add physical good -->
        <template #button-add-physical-good="{ action }">
            <div class="relative" >
                <Popover>
                    <template #button="{ open }">
                        <Button
                            @click="open ? false : onOpenModalAddPGood()"
                            :key="`ActionButton${action.label}${action.style}`"
                            :style="action.style"
                            :label="action.label"
                            :icon="action.icon"
                            :tooltip="action.tooltip"
                        />
                    </template>
                    <template #content="{ close: closed }">
                        <div class="w-[350px]">
                            <span class="text-xs px-1 my-2">{{ trans('Physical Goods') }}: </span>
                            <div>
                                <!-- <PureMultiselect
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
                                        <div class="w-full text-left pl-4">{{ value.name }} <span class="text-sm text-gray-400">({{ value.code }})</span></div>
                                    </template>

                                    <template #option="{ option, isSelected, isPointed }">
                                        <div class="">{{ option.name }} <span class="text-sm" :class="isSelected ? 'text-indigo-200' : 'text-gray-400'">({{ option.code }})</span></div>
                                    </template>
                                </PureMultiselect> -->

                                <PureMultiselectInfiniteScroll
                                    v-model="formAddPhysicalGood.outer_id"
                                    :fetchRoute="physical_good_list_route"
                                    :placeholder="trans('Select Physical Goods')"
                                    valueProp="id"
                                />

                                <p v-if="get(formAddPhysicalGood, ['errors', 'outer_id'])" class="mt-2 text-sm text-red-600">
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

                            <!-- Loading: fetching pgood list -->
                            <div v-if="isLoadingData === 'addPGood'" class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                                <FontAwesomeIcon icon='fad fa-spinner-third' class='animate-spin text-5xl' fixed-width aria-hidden='true' />
                            </div>
                        </div>
                    </template>
                </Popover>
            </div>
           
        </template>

        <!-- Button: delete Refund -->
        <template v-if="outbox.state === 'in_process'" #button-send-invoice="{ action }">
            <Button
                @click="() => isModalSendInvoice = true"
                :style="action.style"
                :label="action.label"
                :icon="action.icon"
                :loading="isVisitWorkshopOutbox"
                :iconRight="action.iconRight"
                :key="`ActionButton${action.label}${action.style}`"
                :tooltip="action.tooltip"
            />
        </template>
    </PageHeading>

    <div class="grid grid-cols-4 divide-x divide-gray-300 border-b border-gray-200">
        <!-- Box: Customer -->
        <BoxStatPallet class=" py-2 px-3" icon="fal fa-user">

            <!-- Field: Registration Number -->
            <Link as="a" v-if="box_stats?.customer.reference" :href="route(box_stats?.customer.route.name, box_stats?.customer.route.parameters)"
                class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Registration number</span>
                    <FontAwesomeIcon icon='fal fa-id-card-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">#{{ box_stats?.customer.reference }}</dd>
            </Link>

            <!-- Field: Contact name -->
            <div v-if="box_stats?.customer.contact_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Contact name'" class="flex-none">
                    <span class="sr-only">Contact name</span>
                    <FontAwesomeIcon icon='fal fa-user' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
            </div>

            <!-- Field: Company name -->
            <div v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Company name'" class="flex-none">
                    <span class="sr-only">Company name</span>
                    <FontAwesomeIcon icon='fal fa-building' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.company_name }}</dd>
            </div>

            <!-- Field: Tax number -->
            <!-- <div v-if="box_stats?.customer.tax_number"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Email'" class="flex-none">
                    <span class="sr-only">Tax Number</span>
                    <FontAwesomeIcon icon='fal fa-passport' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.tax_number }}</dd>
            </div> -->

            <!-- Field: Location -->
            <!-- <div v-if="box_stats?.customer.location"
                class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Location'" class="flex-none">
                    <span class="sr-only">Location</span>
                    <FontAwesomeIcon icon='fal fa-map-marked-alt' size="xs" class='text-gray-400' fixed-width aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">
                    <AddressLocation :data="box_stats?.customer.location" />
                </dd>
            </div> -->

            <!-- Field: Phone -->
            <div v-if="box_stats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
                <dt v-tooltip="'Phone'" class="flex-none">
                    <span class="sr-only">Phone</span>
                    <FontAwesomeIcon icon='fal fa-phone' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>
                <dd class="text-base text-gray-500">{{ box_stats?.customer.phone }}</dd>
            </div>

            <!-- Field: Address -->
            <div class="pl-1 flex items-start w-full gap-x-2">
                <dt v-tooltip="'Phone'" class="flex-none">
                    <span class="sr-only">Phone</span>
                    <FontAwesomeIcon icon='fal fa-map-marker-alt' size="xs" class='text-gray-400' fixed-width
                        aria-hidden='true' />
                </dt>

                <dd class="text-base text-gray-500 w-full">
                    <div v-if="invoice.address" class="relative bg-gray-50 border border-gray-300 rounded px-2 py-1">
                        <div v-html="invoice.address.formatted_address" />
                    </div>

                    <div v-else class="text-gray-400 italic">
                        No address
                    </div>
                </dd>
            </div>
        </BoxStatPallet>

        <!-- Section: Detail -->
        <BoxStatPallet class="py-2 px-3">
            <div class="mt-1">
                <div v-tooltip="'Recurring bill'"
                    class="w-fit flex items-center flex-none gap-x-2">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-receipt' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <component :is="box_stats.information.recurring_bill?.route?.name ? Link : 'div'"
                        as="dd"
                        :href="box_stats.information.recurring_bill?.route?.name ? route(box_stats.information.recurring_bill?.route?.name, box_stats.information.recurring_bill.route.parameters) : ''"
                        class="text-base text-gray-500"
                        :class="box_stats.information.recurring_bill?.route?.name ? 'cursor-pointer primaryLink' : ''">
                        {{ box_stats.information.recurring_bill?.reference || '-' }}
                    </component>
                </div>

                <div v-tooltip="'Invoice created'"
                    class="flex items-center w-full flex-none gap-x-2">
                    <dt class="flex-none">
                        <FontAwesomeIcon icon='fal fa-calendar-alt' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <dd class="text-base text-gray-500" :class='"ff"'>
                        {{ useFormatTime(props.invoice.date) }}
                    </dd>
                </div>

                <!-- <div class="relative flex items-start w-full flex-none gap-x-2">
                    <dt class="flex-none pt-1">
                        <FontAwesomeIcon icon='fal fa-dollar-sign' fixed-width aria-hidden='true' class="text-gray-500" />
                    </dt>
                    <NeedToPay
                        @click="() => Number(box_stats.information.pay_amount) > 0 ? (isOpenModalPayment = true, fetchPaymentMethod()) : false"
                        :totalAmount="Number(props.invoice.total_amount)"
                        :paidAmount="Number(box_stats.information.paid_amount)"
                        :payAmount="Number(box_stats.information.pay_amount)"
                        :currencyCode="invoice.currency_code"
                        :class="[Number(box_stats.information.pay_amount) ? 'hover:bg-gray-100 cursor-pointer' : '']"
                    />


                </div> -->
            </div>
        </BoxStatPallet>

        <!-- Section: Order Summary -->
        <BoxStatPallet class="col-start-3 col-span-2 py-2 px-3">
            <OrderSummary :order_summary :currency_code="invoice.currency_code" />
        </BoxStatPallet>

        <!-- Section: Invoice Information (looping) -->
        <!-- <BoxStatPallet class="col-start-4 py-2 px-3"')">
            <div class="pt-1 text-gray-500">
                <template v-for="invoiceGroup in boxInvoiceInformation">
                    <div class="space-y-1">
                        <div v-for="invoice in invoiceGroup" class="flex justify-between"
                            :class="invoice.label == 'Total' ? 'font-semibold' : ''"
                        >
                            <div>{{ invoice.label }} <span v-if="invoice.label == 'Tax'" class="text-sm text-gray-400">(VAT {{invoice.tax_percentage || 0}}%)</span></div>
                            <div>{{ locale.currencyFormat(box_stats?.currency, invoice.value || 0) }}</div>
                        </div>
                    </div>
                    <hr class="last:hidden my-1.5 border-gray-300">
                </template>
            </div>
        </BoxStatPallet> -->
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />

    <Modal :isOpen="isModalSendInvoice" @onClose="isModalSendInvoice = false" width="w-[600px]">
        <div>
            <EmptyState
                :data="{
                    title: trans('Outbox is still in process'),
                    description: trans('You can edit it in workshop')
                }"
                class="py-7"
            >
                <template #button-empty-state>
                    <Link :href="route(outbox.workshop_route.name, outbox.workshop_route.parameters)" @start="() => isVisitWorkshopOutbox = true" class="mt-4 block w-fit mx-auto">
                        <Button
                            label="workshop"
                            type="secondary"
                            icon="fal fa-drafting-compass"
                            :loading="isVisitWorkshopOutbox"
                        />
                    </Link>
                </template>
            </EmptyState>
        </div>
    </Modal>
</template>
