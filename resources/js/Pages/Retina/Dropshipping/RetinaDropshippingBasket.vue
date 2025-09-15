<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import {Head, router} from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import {capitalize} from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import {computed, ref} from 'vue'
import type {Component} from 'vue'
import {useTabChange} from "@/Composables/tab-change"
import Button from '@/Components/Elements/Buttons/Button.vue'
import {debounce} from 'lodash-es'
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import {trans} from "laravel-vue-i18n"
import {routeType} from '@/types/route'
import {PageHeading as PageHeadingTypes} from '@/types/PageHeading'
import {UploadPallet} from '@/types/Pallet'
import {Tabs as TSTabs} from '@/types/Tabs'
import '@vuepic/vue-datepicker/dist/main.css'
import '@/Composables/Icon/PalletDeliveryStateEnum'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import axios from 'axios'
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import {notify} from '@kyvg/vue3-notification'
import OrderProductTable from '@/Components/Dropshipping/Orders/OrderProductTable.vue'
import Modal from '@/Components/Utils/Modal.vue'
import {Address, AddressManagement} from "@/types/PureComponent/Address"
import {library} from "@fortawesome/fontawesome-svg-core"
import {FontAwesomeIcon} from '@fortawesome/vue-fontawesome'
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import {faExclamationTriangle as fadExclamationTriangle} from '@fad'
import {faExclamationTriangle, faExclamation} from '@fas'
import { faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, } from '@fal'
import {Currency} from '@/types/LayoutRules'
import TableInvoices from '@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue'
import TableProductList from '@/Components/Tables/Grp/Helpers/TableProductList.vue'
import {faSpinnerThird, faCheck} from '@far'
import ProductsSelectorAutoSelect from '@/Components/Dropshipping/ProductsSelectorAutoSelect.vue'
import DropshippingSummaryBasket from '@/Components/Retina/Dropshipping/DropshippingSummaryBasket.vue'
import { inject } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ToggleSwitch } from 'primevue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

library.add(fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird, faCheck)

const props = defineProps<{
    title: string
    tabs: TSTabs
    data: {
        data: {
            is_premium_dispatch: boolean
            has_extra_packing: boolean
            id: number
            slug: string
            reference: string
            state: string
            public_notes?: string
            shipping_notes?: string
            customer_notes?: string
            created_at: string
            updated_at: string
            is_collection: boolean
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
        order_properties: {
            weight: string
        }
        order_summary: {}
    }

    routes?: {
        select_products: routeType
        update_route: routeType
        submit_route: routeType
        pay_with_balance: routeType
    }

    transactions: {}
    currency: Currency


    upload_spreadsheet: UploadPallet
    balance: string
    total_to_pay: number
    address_management: AddressManagement
    total_products: number
    charges: {
        premium_dispatch?: {
            label: string
            name: string
            description: string
            state: string
            amount: number
            currency_code: string
        }
        extra_packing?: {
            label: string
            name: string
            description: string
            state: string
            amount: number
            currency_code: string
        }
    }
    is_unable_dispatch: boolean
}>()
const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

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



const noteToSubmit = ref(props?.data?.data?.customer_notes || '')
const deliveryInstructions = ref(props?.data?.data?.shipping_notes || '')
const recentlySuccessNote = ref<string[]>([])
const recentlyErrorNote = ref(false)
const isLoadingNote = ref<string[]>([])
const onSubmitNote = async (key_in_db: string, value: string) => {
    try {
        isLoadingNote.value.push(key_in_db)
        await axios.patch(route(props.routes.update_route.name, props.routes.update_route.parameters), {
            [key_in_db ?? 'customer_notes']: value
        })


        isLoadingNote.value = isLoadingNote.value.filter(item => item !== key_in_db)
        recentlySuccessNote.value.push(key_in_db)
        setTimeout(() => {
            recentlySuccessNote.value = recentlySuccessNote.value.filter(item => item !== key_in_db)
        }, 3000)
    } catch {
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
const debounceSubmitNote = debounce(() => onSubmitNote('customer_notes', noteToSubmit.value), 800)
const debounceDeliveryInstructions = debounce(() => onSubmitNote('shipping_notes', deliveryInstructions.value), 800)


const isLoadingSubmit = ref(false)


const listLoadingProducts = ref({})
const onAddProducts = async (product: { historic_asset_id: number }) => {

    const routePost = product?.transaction_id ?
        {
            route_post: route('retina.models.transaction.update', {transaction: product.transaction_id}),
            method: 'patch',
            body: {
                quantity_ordered: product.quantity_selected ?? 1,
            }
        } : {
            route_post: route('retina.models.order.transaction.store', {order: props?.data?.data?.id}),
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
            only: ['transactions', 'box_stats', 'total_products', 'balance', 'total_to_pay'],
            onStart: () => {
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'loading'
            },
            onBefore: () => 'isLoadingSubmit.value = true',
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error.products || undefined,
                    type: "error"
                })
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'error'
            },
            onSuccess: () => {
                // Luigi: event add to cart
                if (!product?.transaction_id) {
                    window?.dataLayer?.push({
                        event: "add_to_cart",
                        ecommerce: {
                            currency: layout?.iris?.currency?.code,
                            value: product.price,
                            items: [
                                {
                                    item_id: product?.luigi_identity,
                                }
                            ]
                        }
                    })
                }
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'success'
            },
            onFinish: () => {
                isLoadingSubmit.value = false
                setTimeout(() => {
                    listLoadingProducts.value[`id-${product.historic_asset_id}`] = null
                }, 3000)
            }
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

const isLoadingPriorityDispatch = ref(false)
const onChangePriorityDispatch = async (val: boolean) => {
    router.patch(
        route('retina.models.order.update_premium_dispatch', props.data.data?.id),
        {
            is_premium_dispatch: val
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingPriorityDispatch.value = true
            },
            onSuccess: () => {
                if (val) {
                    notify({
                        title: trans("Success"),
                        text: trans("The order is changed to priority dispatch!"),
                        type: "success"
                    })
                } else {
                    notify({
                        title: trans("Success"),
                        text: trans("The order is no longer on priority dispatch."),
                        type: "success"
                    })
                }
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to update priority dispatch, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingPriorityDispatch.value = false
            },
        }
    )
}

// Section: Extra Packing
const isLoadingExtraPacking = ref(false)
const onChangeExtraPacking = async (val: boolean) => {
    router.patch(
        route('retina.models.order.update_extra_packing', props.data.data?.id),
        {
            has_extra_packing: val
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingExtraPacking.value = true
            },
            onSuccess: () => {
                if (val) {
                    notify({
                        title: trans("Success"),
                        text: trans("The order is changed to extra packing!"),
                        type: "success"
                    })
                } else {
                    notify({
                        title: trans("Success"),
                        text: trans("The order is no longer on extra packing."),
                        type: "success"
                    })
                }
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to update extra packing, try again."),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingExtraPacking.value = false
            },
        }
    )
}

console.log('ewew', props.address_management)
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead">
        <template #button-group-upload-add="{ action }">
            <div class="flex items-center border border-gray-300 rounded-md divide-x divide-gray-300">
                <Button
                    v-if="upload_spreadsheet"
                    @click="() => upload_spreadsheet ? isModalUploadSpreadsheet = true : onNoStructureUpload()"
                    :label="trans('Upload products')"
                    icon="upload"
                    type="tertiary"
                    class="rounded-none border-0"
                />
                <Button
                    @click="() => isModalProductListOpen = true"
                    :label="trans('Add products')"
                    type="tertiary"
                    icon="fas fa-plus"
                    class="rounded-none border-none"
                />
            </div>

        </template>
    </PageHeading>

    <DropshippingSummaryBasket
        :isCollection="data.data.is_collection"
        :summary="box_stats"
        :balance="balance"
        :address_management
        :updateOrderRoute="routes?.update_route"
        :is_unable_dispatch
    />

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
          @update:tab="handleTabUpdate"/>

    <div class="mb-4 mx-4 mt-4 rounded-md border border-gray-200">
        <component :is="component"
                   :data="props[currentTab as keyof typeof props]" :tab="currentTab"
                   :updateRoute="routes?.updateOrderRoute" :state="data?.data?.state"
                   detachRoute="attachmentRoutes?.detachRoute"
                   :fetchRoute="routes?.products_list"
                   :modalOpen="isModalUploadOpen"
                   @update:tab="handleTabUpdate"/>

        <!-- Section: Priority Dispatch -->
        <div v-if="charges.premium_dispatch" class="flex gap-4 my-4 justify-end pr-6">
            <div class="px-2 flex justify-end items-center gap-x-1 relative" xclass="data?.data?.is_premium_dispatch ? 'text-green-500' : ''">
                <InformationIcon :information="charges.premium_dispatch?.description" />
                {{ charges.premium_dispatch?.label ?? charges.premium_dispatch?.name }}
                <span class="text-gray-400">({{ locale.currencyFormat(charges.premium_dispatch?.currency_code, charges.premium_dispatch?.amount) }})</span>
            </div>

            <div class="px-2 flex justify-end relative" xstyle="width: 200px;">
                <ToggleSwitch
                    :modelValue="data?.data?.is_premium_dispatch"
                    @update:modelValue="(e) => (onChangePriorityDispatch(e))"
                    xdisabled="isLoadingPriorityDispatch"
                >
                    <template #handle="{ checked }">
                        <LoadingIcon v-if="isLoadingPriorityDispatch" xclass="text-sm text-gray-500" />
                        <template v-else>
                            <FontAwesomeIcon v-if="checked" icon="far fa-check" class="text-sm text-green-500" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fal fa-times" class="text-sm text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </template>
                </ToggleSwitch>
            </div>
        </div>

        <!-- Section: Extra Packing -->
        <div v-if="charges.extra_packing" class="flex gap-4 my-4 justify-end pr-6">
            <div class="px-2 flex justify-end items-center gap-x-1 relative" xclass="data?.data?.has_extra_packing ? 'text-green-500' : ''">
                <InformationIcon :information="charges.extra_packing?.description" />
                {{ charges.extra_packing?.label ?? charges.extra_packing?.name }}
                <span class="text-gray-400">({{ locale.currencyFormat(charges.extra_packing?.currency_code, charges.extra_packing?.amount) }})</span>
            </div>

            <div class="px-2 flex justify-end relative" xstyle="width: 200px;">
                <ToggleSwitch
                    :modelValue="data?.data?.has_extra_packing"
                    @update:modelValue="(e) => (onChangeExtraPacking(e))"
                    xdisabled="isLoadingExtraPacking"
                >
                    <template #handle="{ checked }">
                        <LoadingIcon v-if="isLoadingExtraPacking" xclass="text-sm text-gray-500" />
                        <template v-else>
                            <FontAwesomeIcon v-if="checked" icon="far fa-check" class="text-sm text-green-500" fixed-width aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fal fa-times" class="text-sm text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </template>
                </ToggleSwitch>
            </div>
        </div>
    </div>

    <div v-if="total_products > 0" class="flex justify-end px-6 gap-x-4">
        <div class="grid grid-cols-3 gap-x-4 w-full">
            <div></div>
            <!-- Input text: notes from staff  -->
            <!-- <div class="">
                <div class="text-sm text-gray-500">
                    <FontAwesomeIcon style="color: rgb(148, 219, 132)" icon="fal fa-sticky-note" class="xopacity-70" fixed-width aria-hidden="true" />
                    {{ trans("Notes from staff") }}
                    :
                </div>
                <PureTextarea
                    :modelValue="props.data.data.public_notes || ''"
                    @update:modelValue="() => debounceDeliveryInstructions()"
                    :placeholder="trans('No notes from staff')"
                    rows="4"
                    disabled
                    xloading="isLoadingNote.includes('shipping_notes')"
                    xisSuccess="recentlySuccessNote.includes('shipping_notes')"
                    xisError="recentlyErrorNote"
                />
            </div> -->

            <!-- Input text: Delivery instructions -->
            <div class="">
                <div class="text-sm text-gray-500">
                    <FontAwesomeIcon icon="fal fa-truck" class="text-[#38bdf8]" fixed-width aria-hidden="true"/>
                    {{ trans("Delivery instructions") }}
                    <FontAwesomeIcon v-tooltip="trans('To be printed in shipping label')" icon="fal fa-info-circle"
                                     class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true"/>
                    :
                </div>
                <PureTextarea
                    v-model="deliveryInstructions"
                    @update:modelValue="() => debounceDeliveryInstructions()"
                    :placeholder="trans('Add if needed')"
                    rows="4"
                    :loading="isLoadingNote.includes('shipping_notes')"
                    :isSuccess="recentlySuccessNote.includes('shipping_notes')"
                    :isError="recentlyErrorNote"
                />
            </div>

            <!-- Input text: Other instructions -->
            <div class="">
                <div class="text-sm text-gray-500">
                    <FontAwesomeIcon icon="fal fa-sticky-note" style="color: rgb(255, 125, 189)" fixed-width
                                     aria-hidden="true"/>
                    {{ trans("Other instructions") }}:
                </div>
                <PureTextarea
                    v-model="noteToSubmit"
                    @update:modelValue="() => debounceSubmitNote()"
                    :placeholder="trans('Add if needed')"
                    rows="4"
                    :loading="isLoadingNote.includes('customer_notes')"
                    :isSuccess="recentlySuccessNote.includes('customer_notes')"
                    :isError="recentlyErrorNote"
                />
            </div>
        </div>


        <!-- Button: Continue to checkout, Place Order -->
        <div v-if="!is_unable_dispatch || data.data.is_collection" class="w-72 pt-5">
            <!-- Place Order -->
            <template v-if="total_to_pay == 0 && balance > 0">
                <ButtonWithLink
                    iconRight="fas fa-arrow-right"
                    :label="trans('Place order')"
                    :routeTarget="routes?.pay_with_balance"
                    class="w-full"
                    full
                >
                </ButtonWithLink>

                <div class="text-xs text-gray-500 mt-2 italic flex items-start gap-x-1">
                    <FontAwesomeIcon icon="fal fa-info-circle" class="mt-[4px]" fixed-width aria-hidden="true"/>
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
        <div v-else class="w-72 pt-5 text-sm">
            <div class="text-red-500">*{{ trans("We cannot deliver to :country. Please update the address or contact support.", { country: box_stats?.customer?.addresses?.delivery?.country?.name}) }}</div>
        </div>
    </div>


    <!-- Modal: add products to Order -->
    <Modal :isOpen="isModalProductListOpen" @onClose="isModalProductListOpen = false" width="w-full max-w-6xl">
        <ProductsSelectorAutoSelect
            :headLabel="trans('Add products to Order') + ' #' + props?.data?.data?.reference"
            :routeFetch="props.routes.select_products"
            :isLoadingSubmit
            @submit="(products: {}) => onAddProducts(products)"
            :listLoadingProducts
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
