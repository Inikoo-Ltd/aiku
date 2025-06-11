<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref, watch } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TablePalletDeliveryPallets from '@/Components/Tables/Grp/Org/Fulfilment/TablePalletDeliveryPallets.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import Popover from '@/Components/Popover.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import { get, debounce } from 'lodash-es'
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { PalletDelivery, BoxStats, PDRNotes, UploadPallet } from '@/types/Pallet'
import { Table as TableTS } from '@/types/Table'
import { Tabs as TSTabs } from '@/types/Tabs'
import '@vuepic/vue-datepicker/dist/main.css'

import '@/Composables/Icon/PalletDeliveryStateEnum'

import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'

import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { Timeline as TSTimeline } from "@/types/Timeline"

import axios from 'axios'
import { Action } from '@/types/Action'
import TableFulfilmentTransactions from "@/Components/Tables/Grp/Org/Fulfilment/TableFulfilmentTransactions.vue"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { notify } from '@kyvg/vue3-notification'
import OrderProductTable from '@/Components/Dropshipping/Orders/OrderProductTable.vue'
import { Button as TSButton } from '@/types/Button'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import NeedToPay from '@/Components/Utils/NeedToPay.vue'
import BoxStatPallet from '@/Components/Pallet/BoxStatPallet.vue'

import OrderSummary from '@/Components/Summary/OrderSummary.vue'
import Modal from '@/Components/Utils/Modal.vue'
import CustomerAddressManagementModal from '@/Components/Utils/CustomerAddressManagementModal.vue'
import { Address, AddressManagement } from "@/types/PureComponent/Address"

import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject } from 'vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import AlertMessage from '@/Components/Utils/AlertMessage.vue'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from '@/Components/Upload/UploadAttachment.vue'

import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation } from '@fas'
import { faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, } from '@fal'
import { Currency } from '@/types/LayoutRules'
import TableInvoices from '@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue'
import ModalProductList from '@/Components/Utils/ModalProductList.vue'
import TableProductList from '@/Components/Tables/Grp/Helpers/TableProductList.vue'
import { faSpinnerThird } from '@far'
import ProductsSelector from '@/Components/Dropshipping/ProductsSelector.vue'
import CheckoutSummary from '@/Components/Retina/Ecom/CheckoutSummary.vue'
import DSCheckoutSummary from '@/Components/Retina/Dropshipping/DSCheckoutSummary.vue'
library.add(fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    data?: {
        data: {
            
        }
    }
    pageHead: PageHeadingTypes

    // upload_spreadsheet: UploadPallet

    box_stats: {
        customer: {
            reference: string
            contact_name: string
            company_name: string
            email: string
            phone: string
            addresses: {
                delivery: Address
                billing: Address
            }
        }
        products: {
            payment: {
                routes?: {
                    fetch_payment_accounts: routeType
                    submit_payment: routeType
                }
                total_amount: number
                paid_amount: number
                pay_amount: number
            }
            estimated_weight: number
        }
        order_summary: {

        }
    }
    
    routes?: {
        update_route: routeType
        submit_route: routeType
    }
    
    transactions: {}
    currency: Currency
    delivery_notes: {
        data: Array<any>
    }
    
    attachments?: {}
    invoices?: {}

    is_in_basket: boolean  // true if Order state is 'created'
    upload_spreadsheet: UploadPallet
}>()


const isModalUploadOpen = ref(false)
// const isModalProductListOpen = ref(false)
// const locale = inject('locale', aikuLocaleStructure)

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        transactions: OrderProductTable,
        delivery_notes: TableDeliveryNotes,
        attachments: TableAttachments,
        invoices: TableInvoices,
		products: TableProductList
    }

    return components[currentTab.value]
})


// const isLoadingButton = ref<string | boolean>(false)
// const isLoadingData = ref<string | boolean>(false)
const isModalAddress = ref<boolean>(false)

// Tabs: Products
// const formProducts = useForm({ historicAssetId: null, quantity_ordered: 1, })
// const onSubmitAddProducts = (data: Action, closedPopover: Function) => {
//     isLoadingButton.value = 'addProducts'

//     formProducts
//         .transform((data) => ({
//             quantity_ordered: data.quantity_ordered,
//         }))
//         .post(
//             route(data.route?.name || '#', { ...data.route?.parameters, historicAsset: formProducts.historicAssetId }),
//             {
//                 preserveScroll: true,
//                 onSuccess: () => {
//                     closedPopover()
//                     formProducts.reset()
//                 },
//                 onError: (errors) => {
//                     notify({
//                         title: trans('Something went wrong.'),
//                         text: trans('Failed to add service, please try again.'),
//                         type: 'error',
//                     })
//                 },
//                 onFinish: () => {
//                     isLoadingButton.value = false
//                 }
//             }
//         )
// }


// Section: Payment invoice
// const listPaymentMethod = ref([])
// const isLoadingFetch = ref(false)
// const fetchPaymentMethod = async () => {
//     try {
//         isLoadingFetch.value = true
//         const { data } = await axios.get(route(props.box_stats.products.payment.routes.fetch_payment_accounts.name, props.box_stats.products.payment.routes.fetch_payment_accounts.parameters))
//         listPaymentMethod.value = data.data
//     } catch (error) {
//         console.log('erropr', error)
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
const currentAction = ref(null);
// const isOpenModalPayment = ref(false)
// const isLoadingPayment = ref(false)
// const errorPaymentMethod = ref<null | unknown>(null)
// const onSubmitPayment = () => {
//     try {
//         router[props.box_stats.products.payment.routes.submit_payment.method || 'post'](
//             route(props.box_stats.products.payment.routes.submit_payment.name, {
//                 ...props.box_stats.products.payment.routes.submit_payment.parameters,
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
//                 onFinish: (response) => {
//                     isLoadingPayment.value = false,
//                         isOpenModalPayment.value = false,
//                         notify({
//                             title: trans('Success'),
//                             text: trans('Successfully add payment invoice'),
//                             type: 'success',
//                         })
//                 },
//                 onSuccess: (response) => {
//                     paymentData.value.payment_method = null,
//                         paymentData.value.payment_amount = 0,
//                         paymentData.value.payment_reference = ''
//                 }
//             }
//         )

//     } catch (error: unknown) {
//         errorPaymentMethod.value = error
//     }
// }


// Section: add notes (on popup pageheading)
const noteToSubmit = ref(props.data.data.public_notes)
const recentlySuccessNote = ref(false)
const recentlyErrorNote = ref(false)
const isLoadingNote = ref(false)
const onSubmitNote = async () => {
    try {
        isLoadingNote.value = true
        await axios.patch(route(props.routes.update_route.name, props.routes.update_route.parameters), {
            public_notes: noteToSubmit.value
        })

        // {
        //     headers: { "Content-Type": 'application/json' },
        //     onStart: () => isLoadingButton.value = 'submitNote',
        //     onError: (error) => errorNote.value = error,
        //     onFinish: () => isLoadingButton.value = false,
        //     onSuccess: () => {
        //         recentlySuccessNote.value = true
        //         setTimeout(() => {
        //             recentlySuccessNote.value = false
        //         }, 3000)
        //     },
        // })
        isLoadingNote.value = false
        recentlySuccessNote.value = true
        setTimeout(() => {
            recentlySuccessNote.value = false
        }, 3000)
    } catch  {
        recentlyErrorNote.value = true
        setTimeout(() => {
            recentlyErrorNote.value = false
        }, 3000)

        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to update the note, try again."),
            type: "error",
        })
    }
}
const debounceSubmitNote = debounce(onSubmitNote, 800)

// const openModal = (action :any) => {
// 	currentAction.value = action;
//     isModalProductListOpen.value = true;
// };



// Method: Submit the selected item
// const isLoadingSubmit = ref(false)
// const onAddProducts = async (products: number[]) => {
//     const productsMapped = products.map((item: any) => {
//         return {
//             id: item.id,
//             quantity: item.quantity_selected ?? 1
//         }
//     })

//     router.post(route('retina.models.order.transaction.store', { order: props?.data?.data?.id} ), {
//         products: productsMapped
//     }, {
//         onBefore: () => isLoadingSubmit.value = true,
//         onError: (error) => {
//             notify({
//                 title: "Something went wrong.",
//                 text: error.products || undefined,
//                 type: "error"
//             })
//         },
//         onSuccess: () => {
//             router.reload({only: ['data']})
//             notify({
//                 title: trans("Success!"),
//                 text: trans("Successfully added portfolios"),
//                 type: "success"
//             })
//             isModalProductListOpen.value = false
//         },
//         onFinish: () => isLoadingSubmit.value = false
//     })
// }

// const isModalUploadSpreadsheet = ref(false)

// const onNoStructureUpload = () => {
//     notify({
//         title: trans("Something went wrong"),
//         text: trans("Upload structure is not provided. Please contact support."),
//         type: "error",
//     })
// }


// Section: Modal confirmation order
const isModalConfirmationOrder = ref(false)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>

    <DSCheckoutSummary
        :summary="box_stats"
        :balance="24"
    />


    <!-- Section: Payment Tabs -->
    <!-- <div v-if="balance > total_amount" class="mx-10 border border-gray-300">
        <div v-if="props.paymentMethods?.length" class="max-w-lg">
            <div class="grid grid-cols-1 sm:hidden">
                <select aria-label="Select a tab" class="col-start-1 row-start-1 w-full appearance-none rounded-md bg-white py-2 pl-3 pr-8 text-base  outline outline-1 -outline-offset-1 outline-gray-300 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-600">
                    <option v-for="(tab, tabIdx) in paymentMethods" :key="tabIdx" :selected="currentTab === tabIdx">
                        <FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
                        {{ tab.label }}
                    </option>
                </select>
            </div>
            
            <div class="hidden sm:block">
                <nav class="isolate flex divide-x divide-gray-200 rounded-lg shadow" aria-label="Tabs">
                    <div
                        v-for="(tab, tabIdx) in props.paymentMethods"
                        @click="currentTab.index = tabIdx, currentTab.key = tab.key"
                        :key="tabIdx"
                        :class="[currentTab.index === tabIdx ? '' : 'text-gray-500 hover:text-gray-700', tabIdx === 0 ? 'rounded-l-lg' : '', tabIdx === props.paymentMethods?.length - 1 ? 'rounded-r-lg' : '']"
                        class="cursor-pointer group relative min-w-0 flex-1 overflow-hidden bg-white px-4 py-4 text-center text-sm font-medium hover:bg-gray-100 focus:z-10"
                    >
                        <FontAwesomeIcon v-if="tab.icon" :icon="tab.icon" class="mr-1" fixed-width aria-hidden="true" />
                        <span>{{ tab.label }}</span>
                        <span aria-hidden="true" :class="[currentTab.index === tabIdx ? 'bg-indigo-500' : 'bg-transparent', 'absolute inset-x-0 bottom-0 h-0.5']" />
                    </div>
                </nav>
            </div>
        </div>

        <KeepAlive>
            <component
                :is="component"
                :data="paymentMethods[currentTab.index]"
            />
        </KeepAlive>
    </div>

    <div v-else class="ml-10 mr-4 py-5 flex items-center flex-col gap-y-2 border border-gray-300 rounded">
        <div class="text-center text-gray-500">
            This is your final confirmation.
            <br> You can pay totally with your current balance.
        </div>
        <Button @click="'isModalConfirmationOrder = true'" label="Place Order" size="l" />
    </div> -->

    <div class="flex justify-end px-6">
        <div class="w-72">
            <PureTextarea
                v-model="noteToSubmit"
                @update:modelValue="() => debounceSubmitNote()"
                :placeholder="trans('Special instructions if needed')"
                xkeydown.enter="() => onSubmitNote(closed)"
                rows="4"
                :disabled="!is_in_basket"
                :loading="isLoadingNote"
                :isSuccess="recentlySuccessNote"
                :isError="recentlyErrorNote"
                class="mb-2"
            />
            
            <!-- <ButtonWithLink
                v-if="is_in_basket"
                iconRight="fas fa-arrow-right"
                :label="trans('Submit order')"
                :routeTarget="routes?.submit_route"
                disabled
                class="w-full"
                full
            /> -->

            <Button
                v-if="is_in_basket && 'products more than 0'"
                @click="() => isModalConfirmationOrder = true"
                iconRight="fas fa-arrow-right"
                :label="trans('Submit order')"
                class="w-full"
                full
            />
        </div>
    </div>


    <!-- Section: Address -->
    <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
        <CustomerAddressManagementModal
            :addresses="addresses"
            :updateRoute="address_update_route"
            keyPayloadEdit="delivery_address"
        />
    </Modal>

</template>
