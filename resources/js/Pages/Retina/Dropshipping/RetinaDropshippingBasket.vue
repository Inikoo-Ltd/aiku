<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from "@/Composables/tab-change"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { debounce } from 'lodash'
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { UploadPallet } from '@/types/Pallet'
import { Tabs as TSTabs } from '@/types/Tabs'
import '@vuepic/vue-datepicker/dist/main.css'
import '@/Composables/Icon/PalletDeliveryStateEnum'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import axios from 'axios'
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { notify } from '@kyvg/vue3-notification'
import OrderProductTable from '@/Components/Dropshipping/Orders/OrderProductTable.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation } from '@fas'
import { faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, } from '@fal'
import { Currency } from '@/types/LayoutRules'
import TableInvoices from '@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue'
import TableProductList from '@/Components/Tables/Grp/Helpers/TableProductList.vue'
import { faSpinnerThird } from '@far'
import ProductsSelectorAutoSelect from '@/Components/Dropshipping/ProductsSelectorAutoSelect.vue'
import DSCheckoutSummary from '@/Components/Retina/Dropshipping/DSCheckoutSummary.vue'
library.add(fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    data?: {
        data: {
            
        }
    }
    pageHead: PageHeadingTypes
    order: {}
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
        select_products: routeType
        update_route: routeType
        submit_route: routeType
        pay_with_balance: routeType
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
    balance: string 
    total_to_pay: number
    address_management: AddressManagement
    total_products: number
}>()


const isModalUploadOpen = ref(false)
const isModalProductListOpen = ref(false)

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


const currentAction = ref(null);



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





// Method: Submit the selected item
const isLoadingSubmit = ref(false)
// const onAddProducts = async (products: number[]) => {
//     // console.log('products', products)
//     // return 

//     const productsMapped = products.map((product: any) => {
//         return {
//             id: product.item_id,
//             quantity: product.quantity_selected ?? 1
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

const onAddProducts = async (product: {}) => {
    console.log('products zzzz', product.transaction_id)
    // return 


    const routePost = product?.transaction_id ? 
        {
            route_post: route('retina.models.transaction.update', {transaction: product.transaction_id }),
            method: 'patch',
            body: {
                quantity: product.quantity_selected ?? 1,
            }
        } : {
            route_post: route('retina.models.order.transaction.store', { order: props?.data?.data?.id }),
            method: 'post',
            body: {
                quantity: product.quantity_selected ?? 1,
                historic_asset_id: product.historic_asset_id,
            }
        }

    // return

    router[routePost.method](
        routePost.route_post,
        routePost.body,
        {
            only: ['transactions', 'box_stats'],
            onBefore: () => 'isLoadingSubmit.value = true',
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error.products || undefined,
                    type: "error"
                })
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Successfully added portfolios"),
                    type: "success"
                })
            },
            onFinish: () => isLoadingSubmit.value = false
        })
}

const isModalUploadSpreadsheet = ref(false)

const onNoStructureUpload = () => {
    notify({
        title: trans("Something went wrong"),
        text: trans("Upload structure is not provided. Please contact support."),
        type: "error",
    })
}

console.log('basket ds', props)

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #button-group-upload-add>
            <Button
                @click="() => upload_spreadsheet ? isModalUploadSpreadsheet = true : onNoStructureUpload()"
                :label="trans('Upload products')"
                icon="upload"
                type="tertiary"
            />
        </template>

        <template #other>
            <Button
                v-if="is_in_basket"
                @click="() => isModalProductListOpen = true"
                :label="trans('Add products')"
                icon="plus"
                type="secondary"
            />
        </template>
    </PageHeading>

    <DSCheckoutSummary
        :summary="box_stats"
        :balance="balance"
        :address_management
    />

    <Tabs  v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />

    <div class="mb-12 mx-4 mt-4 rounded-md border border-gray-200">
        <component :is="component"
            :data="props[currentTab as keyof typeof props]" :tab="currentTab"
            :updateRoute="routes?.updateOrderRoute" :state="data?.data?.state"
            detachRoute="attachmentRoutes?.detachRoute"
            :fetchRoute="routes?.products_list"
			:modalOpen="isModalUploadOpen"
			:action="currentAction"
			@update:tab="handleTabUpdate"/>
    </div>

    <div v-if="total_products > 0" class="flex justify-end px-6">
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
            
            <!-- Place Order -->
            <template v-if="total_to_pay == 0 && balance > 0">
                <ButtonWithLink
                    iconRight="fas fa-arrow-right"
                    :label="trans('Place order')"
                    :routeTarget="routes.pay_with_balance"
                    class="w-full"
                    full
                >
                </ButtonWithLink>
                <div class="text-sm text-gray-500 mt-2 italic flex items-start gap-x-1">
                    <FontAwesomeIcon icon="fal fa-info-circle" class="mt-[1px]" fixed-width aria-hidden="true" />
                    <div class="leading-5">
                        {{ trans("This is your final confirmation. You can pay totally with your current balance.") }}
                    </div>
                </div>
            </template>

            <!-- Checkout -->
            <ButtonWithLink
                v-else
                iconRight="fas fa-arrow-right"
                :label="trans('Continue to Checkout')"
                :routeTarget="{
                    name: 'retina.dropshipping.checkout.show',
                    parameters: {
                        order: props?.data?.data?.slug
                    }
                }"
                class="w-full"
                full
            />


        </div>
    </div>


    <!-- Modal: add products to Order -->
    <Modal :isOpen="isModalProductListOpen" @onClose="isModalProductListOpen = false" width="w-full max-w-6xl">
        <ProductsSelectorAutoSelect
            :headLabel="trans('Add products to Order') + ' #' + props?.data?.data?.reference"
            :routeFetch="props.routes.select_products"
            :isLoadingSubmit
            @submit="(products: {}) => onAddProducts(products)"
            withQuantity
        >
        </ProductsSelectorAutoSelect>
    </Modal>


    <UploadExcel
        v-if="upload_spreadsheet"
        v-model="isModalUploadSpreadsheet"
        :title="upload_spreadsheet.title"
        :progressDescription="upload_spreadsheet.progressDescription"
        :preview_template="upload_spreadsheet.preview_template"
        :upload_spreadsheet="upload_spreadsheet.upload_spreadsheet"
        xxxadditionalDataToSend="interest.pallets_storage ? ['stored_items'] : undefined"
    />

</template>
