<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import { Head, Link, router, useForm } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref, inject } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Timeline from "@/Components/Utils/Timeline.vue"
import Popover from "@/Components/Popover.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading"
import { PalletDelivery, UploadPallet } from "@/types/Pallet"
import { Table as TableTS } from "@/types/Table"
import { Tabs as TSTabs } from "@/types/Tabs"
import "@vuepic/vue-datepicker/dist/main.css"
import "@/Composables/Icon/PalletDeliveryStateEnum"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { Timeline as TSTimeline } from "@/types/Timeline"
import axios from "axios"
import { Action } from "@/types/Action"
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { notify } from "@kyvg/vue3-notification"
import OrderProductTable from "@/Components/Dropshipping/Orders/OrderProductTable.vue"
import NeedToPay from "@/Components/Utils/NeedToPay.vue"
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import PureInputNumber from "@/Components/Pure/PureInputNumber.vue"
import AlertMessage from "@/Components/Utils/AlertMessage.vue"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faExclamationTriangle, faExclamation, faUndo } from "@fas"
import {
    faDollarSign,
    faIdCardAlt,
    faShippingFast,
    faIdCard,
    faEnvelope,
    faPhone,
    faWeight,
    faStickyNote,
    faTruck,
    faFilePdf,
    faPaperclip,
    faMapMarkerAlt,
    faPlus
} from "@fal"
import { Currency } from "@/types/LayoutRules"
import TableInvoices from "@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue"
import ModalProductList from "@/Components/Utils/ModalProductList.vue"
import TableProductList from "@/Components/Tables/Grp/Helpers/TableProductList.vue"
import { faSpinnerThird } from "@far"
import DeliveryAddressManagementModal from "@/Components/Utils/DeliveryAddressManagementModal.vue"
import UploadExcel from "@/Components/Upload/UploadExcel.vue"
import TablePayments from "@/Components/Tables/Grp/Org/Accounting/TablePayments.vue"
import { useConfirm } from "primevue/useconfirm";
import ConfirmDialog from 'primevue/confirmdialog';

import Icon from "@/Components/Icon.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { ToggleSwitch } from "primevue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"

library.add(fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faSpinnerThird, faMapMarkerAlt, faUndo, faPlus)

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

    products?: TableTS

    data?: {
        data: PalletDelivery
    }

    pageHead: PageHeadingTypes
    alert?: {
        status: string
        title?: string
        description?: string
    }
    notes: {
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

    upload_spreadsheet?: UploadPallet
    delivery_address_management: {
        can_open_address_management: boolean
        updateRoute: routeType
        addresses: AddressManagement
        address_update_route: routeType,
        address_modal_title: string
    }
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
            },
            address: {
                id: number
            }
        }
        customer_client?: {
            contact_name: string
            company_name: string
            email: string
            phone: string
            route: routeType
        }
        invoice: {
            reference: string
            route: routeType
        }
        products: {
            payment: {
                routes: {
                    fetch_payment_accounts: routeType
                    submit_payment: routeType
                }
                total_amount: number
                paid_amount: number
                pay_amount: number
            }
            excesses_payment?: {
                amount: number
                route_to_add_balance?: routeType
            }
            estimated_weight: number
        }
        order_summary: {}
        payments: {}[]
        delivery_notes?: {
            data: Array<any>
        },
        shipping_notes: string
    }
    pallet_limits?: {
        status: string
        message: string
    }

    routes: {
        updateOrderRoute: routeType
        products_list: routeType
        delivery_note: routeType
    }
    // nonProductItems: {}
    transactions: {}
    currency: Currency
    delivery_notes?: {
        data: Array<any>
    },
    delivery_note?: {
        reference: string
    }
    payments: {}
    readonly?: boolean
    attachments?: {}
    invoices?: {}
    attachmentRoutes?: {}
    address_update_route?: routeType
    addresses?: {}
    upload_excel: UploadSection
}>()


const isModalUploadOpen = ref(false)
const isModalProductListOpen = ref(false)
const locale = inject("locale", aikuLocaleStructure)
const confirm = useConfirm();
const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        transactions: OrderProductTable,
        delivery_notes: TableDeliveryNotes,
        attachments: TableAttachments,
        invoices: TableInvoices,
        products: TableProductList,
        payments: TablePayments
    }

    return components[currentTab.value]
})


const isLoadingButton = ref<string | boolean>(false)
const isModalAddress = ref<boolean>(false)

// Tabs: Products
const formProducts = useForm({historicAssetId: null, quantity_ordered: 1})
const onSubmitAddProducts = (data: Action, closedPopover: Function) => {
    isLoadingButton.value = "addProducts"

    formProducts
        .transform((data) => ({
            quantity_ordered: data.quantity_ordered
        }))
        .post(
            route(data.route?.name || "#", {...data.route?.parameters, historicAsset: formProducts.historicAssetId}),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closedPopover()
                    formProducts.reset()
                },
                onError: (errors) => {
                    notify({
                        title: trans("Something went wrong."),
                        text: trans("Failed to add service, please try again."),
                        type: "error"
                    })
                },
                onFinish: () => {
                    isLoadingButton.value = false
                }
            }
        )
}


// Section: Payment invoice
const listPaymentMethod = ref([])
const isLoadingFetch = ref(false)
const fetchPaymentMethod = async () => {
    try {
        isLoadingFetch.value = true
        const {data} = await axios.get(route(props.box_stats.products.payment.routes.fetch_payment_accounts.name, props.box_stats.products.payment.routes.fetch_payment_accounts.parameters))
        listPaymentMethod.value = data.data
    } catch (error) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to fetch payment method list"),
            type: "error"
        })
    } finally {
        isLoadingFetch.value = false
    }
}

const paymentData = ref({
    payment_method: null as number | null,
    payment_amount: 0 as number | null,
    payment_reference: ""
})
const currentAction = ref(null)
const isOpenModalPayment = ref(false)
const isLoadingPayment = ref(false)
const errorPaymentMethod = ref<null | unknown>(null)
const onSubmitPayment = (isRefund?: boolean) => {
    try {
        router[props.box_stats.products.payment.routes.submit_payment.method || "post"](
            route(props.box_stats.products.payment.routes.submit_payment.name, {
                ...props.box_stats.products.payment.routes.submit_payment.parameters,
                paymentAccount: paymentData.value.payment_method
            }),
            {
                amount: isRefund ? paymentData.value.payment_amount > 0 ? -(paymentData.value.payment_amount) : paymentData.value.payment_amount : paymentData.value.payment_amount,
                reference: paymentData.value.payment_reference,
                status: "success",
                state: "completed"
            },
            {
                onStart: () => isLoadingPayment.value = true,
                onFinish: (response) => {
                    isLoadingPayment.value = false,
                        isOpenModalPayment.value = false,
                        notify({
                            title: trans("Success"),
                            text: trans("Successfully add payment invoice"),
                            type: "success"
                        })
                },
                onSuccess: (response) => {
                    paymentData.value.payment_method = null,
                        paymentData.value.payment_amount = 0,
                        paymentData.value.payment_reference = ""
                }
            }
        )

    } catch (error: unknown) {
        errorPaymentMethod.value = error
    }
}


// Section: add notes (on popup pageHeading)
const errorNote = ref("")
const noteToSubmit = ref({
    selectedNote: "",
    value: ""
})
const onSubmitNote = async (closePopup: Function) => {

    try {
        router.patch(route(props.routes.updateOrderRoute.name, props.routes.updateOrderRoute.parameters), {
                [noteToSubmit.value.selectedNote]: noteToSubmit.value.value
            },
            {
                headers: {"Content-Type": "application/json"},
                onStart: () => isLoadingButton.value = "submitNote",
                onError: (error) => errorNote.value = error,
                onFinish: () => isLoadingButton.value = false,
                onSuccess: () => {
                    closePopup()
                    noteToSubmit.value.selectedNote = ""
                    noteToSubmit.value.value = ""
                }
            })
    } catch (error) {
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to update the note, try again."),
            type: "error"
        })
    }
}

const openModal = (action: any) => {
    currentAction.value = action
    isModalProductListOpen.value = true
}

function onClickPayInvoice() {
    // const pay = props.box_stats.products.payment.pay_amount
    // const state = props.data.data?.state

    // if (pay > 0 && state === "creating") {
    //     isOpenModalPayment.value = false
    // } else {
    //     isOpenModalPayment.value = true
    //     fetchPaymentMethod()
    // }

    isOpenModalPayment.value = true
    fetchPaymentMethod()
}

const isOpenModalRefund = ref(false)

function onClickPayRefund() {
    // const pay = props.box_stats.products.payment.pay_amount
    // const state = props.data.data?.state

    // if (pay < 0 && state === "creating") {
    //     isOpenModalRefund.value = false
    // } else {
    //     isOpenModalRefund.value = true
    //     fetchPaymentMethod()
    // }

    isOpenModalRefund.value = true
    fetchPaymentMethod()
}

const isModalUploadExcel = ref(false)

const last_payment = computed(() => {
    return Array.isArray(props.box_stats.payments) && props.box_stats.payments.length > 0 ? props.box_stats.payments[props.box_stats.payments.length - 1] : null
})

// console.log("props.box_stats.payments", router)
const generateRouteDeliveryNote = (slug: string) => {
    if (!slug) return ''

    return route(props.routes.delivery_note.deliveryNoteRoute.name, {
        ...props.routes.delivery_note.deliveryNoteRoute.parameters,
        deliveryNote: slug
    })
}

const cancelLoading = ref(false)
const confirm2 = (action) => {
    confirm.require({
        message: 'Do you want to cancel this order ?',
        header: 'Cancel Order',
        rejectLabel: 'Cancel',
        rejectProps: {
            label: 'No',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Yes',
            severity: 'danger'
        },
        accept: () => {
            router[action.route.method](
                route(action.route.name, action.route.parameters),
                {},
                {
                    onStart: () => {
                        cancelLoading.value = true
                    },
                    onFinish: () => {
                        cancelLoading.value = true
                    },
                    onSuccess: () => {
                        notify({
                            title: trans("Success"),
                            text: trans("Successfully cancel order"),
                            type: "success",
                        })
                    },
                    onError: () => {
                        notify({
                            title: trans("Error"),
                            text: trans("Failed to cancel order"),
                            type: "error",
                        })
                    }
                }
            )
        },

    });
};

//start: collection feature
const isCollection = ref<boolean>(props.delivery_address_management.addresses.collection_address_id ? true : false)
const collectionBy = ref<string>(props.box_stats?.shipping_notes ? 'thirdParty' : 'myself')
const textValue = ref<string | null>(props.box_stats?.shipping_notes)

const updateCollection = async (e: Event) => {
    const target = e.target as HTMLInputElement
    const payload = {
        collection_address_id: target.checked ? props.box_stats.customer.address.id : null
    }
    try {
        router.patch(route(props.routes.updateOrderRoute.name, props.routes.updateOrderRoute.parameters), {
            ...payload
        })
    } catch (error) {
        console.error(error)
        notify({
            title: trans("Something went wrong."),
            text: trans("Failed to update to collection"),
            type: "error",
        })
    }
}

const updateCollectionType = () => {
    const payload: Record<string, any> = {
        collection_by: collectionBy.value,
    }

    if (collectionBy.value === 'myself') {
        payload.shipping_notes = null
        textValue.value = null // also clear in frontend
    }

    router.patch(
        route(props.routes.updateOrderRoute.name, props.routes.updateOrderRoute.parameters),
        payload,
        {
            preserveScroll: true,
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Collection type updated successfully"),
                    type: "success",
                })
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to update collection type"),
                    type: "error",
                })
            },
        }
    )
}

const updateCollectionNotes = () => {
    router.patch(
        route(props.routes.updateOrderRoute.name, props.routes.updateOrderRoute.parameters),
        {shipping_notes: textValue.value},
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
// end: collection feature

const replacementLoading = ref<boolean>(false)
const onCreateReplacement = (action: any) => {
    router[action.route.method](
        route(action.route.name, action.route.parameters),
        {}, {
            preserveScroll: true,
            onStart: () => {
                replacementLoading.value = true
            },
            onFinish: () => {
                replacementLoading.value = false
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
</script>

<template>

    <Head :title="capitalize(title)"/>
    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-xl text-orange-500"/>
        </template>
    </ConfirmDialog>
    <PageHeading :data="pageHead">
        <template #button-add-products="{ action }">
            <div class="relative">
                <Button :style="action.style" :label="action.label" :icon="action.icon" @click="() => openModal(action)"
                        :key="`ActionButton${action.label}${action.style}`" :tooltip="action.tooltip"/>
            </div>
        </template>

        <template #button-cancel="{ action }">
            <div class="relative">
                <Button :style="action.style" :label="action.label" :icon="action.icon" :loading="cancelLoading"
                        @click="() => confirm2(action)" :key="`ActionButton${action.label}${action.style}`"
                        :tooltip="action.tooltip"/>
            </div>
        </template>

        <!-- Button: rollback -->
        <template #button-rollback="{ action }">
            <div class="relative">

                <ModalConfirmationDelete :routeDelete="action.route"
                                         :title="trans('Are you sure you want to rollback the Order??')"
                                         :description="trans('The state of the Order will go back to finalised state.')"
                                         isFullLoading
                                         :noLabel="trans('Yes, rollback')" noIcon="far fa-undo-alt">
                    <template #default="{ changeModel }">
                        <Button @click="changeModel" type="negative" :label="trans('Undispatch')" icon="fas fa-undo"
                                :tooltip="trans('Rollback the dispatch')"/>
                    </template>
                </ModalConfirmationDelete>


            </div>
        </template>

        <template #button-group-upload-add="{ action }">
            <div class="relative">
                <Button v-if="upload_excel" :style="action.button[0].style" :label="action.button[0].label"
                        :icon="action.button[0].icon" @click="() => isModalUploadExcel = true"
                        :key="`ActionButton${action.button[0].label}${action.button[0].style}`"
                        :tooltip="action.button[0].tooltip"/>
            </div>
        </template>

        <template #otherBefore v-if="!props.readonly">
            <!-- Section: Add notes -->
            <Popover v-if="!notes?.note_list?.some(item => !!(item?.note?.trim()))">
                <template #button="{ open }">
                    <Button icon="fal fa-sticky-note" type="tertiary" label="Add notes"/>
                </template>
                <template #content="{ close: closed }">
                    <div class="w-[350px]">
                        <span class="text-xs px-1 my-2">{{ trans("Select type note") }}: </span>
                        <div class="">
                            <PureMultiselect v-model="noteToSubmit.selectedNote"
                                             @update:modelValue="() => errorNote = ''"
                                             :placeholder="trans('Select type note')"
                                             required
                                             :options="[{ label: 'Public note', value: 'public_notes' }, { label: 'Private note', value: 'internal_notes' }]"
                                             valueProp="value"/>
                        </div>

                        <div class="mt-3">
                            <span class="text-xs px-1 my-2">{{ trans("Note") }}: </span>
                            <PureTextarea v-model="noteToSubmit.value" :placeholder="trans('Note')"
                                          @keydown.enter="() => onSubmitNote(closed)"/>
                        </div>

                        <p v-if="errorNote" class="mt-2 text-sm text-red-600">
                            *{{ errorNote }}
                        </p>

                        <div class="flex justify-end mt-3">
                            <Button @click="() => onSubmitNote(closed)" :style="'save'"
                                    :loading="isLoadingButton === 'submitNote'" :disabled="!noteToSubmit.value"
                                    label="Save"
                                    full/>
                        </div>

                        <!-- Loading: fetching service list -->
                        <div v-if="isLoadingButton === 'submitNote'"
                             class="bg-white/50 absolute inset-0 flex place-content-center items-center">
                            <FontAwesomeIcon icon="fad fa-spinner-third" class="animate-spin text-5xl" fixed-width
                                             aria-hidden="true"/>
                        </div>
                    </div>
                </template>
            </Popover>
        </template>

        <template #button-replacement="{ action }">
             <Button
                        @click="() =>onCreateReplacement(action)"
                        :label="trans('Replacement')"
                        xsize="xs"
                        type="secondary"
                        icon="fal fa-plus"
                        key="1"
                        :disabled="replacementLoading"
                        v-tooltip="trans('Create replacement if the user requests replacement of items')"
                    />
            <!-- <ModalConfirmation
                :routeYes="action.route"
                :title="trans('Create Replacement Order?')"
                :description="trans('This will create a replacement for the current Delivery Note (do this when the user requests replacement of items)')"
            >
                <template #default="{ changeModel }">
                    <Button
                        @click="() => changeModel()"
                        :label="trans('Replacement')"
                        xsize="xs"
                        type="secondary"
                        icon="fal fa-plus"
                        key="1"
                        v-tooltip="trans('Create replacement if the user requests replacement of items')"
                    />
                    
                </template>

                <template #btn-yes="{ isLoadingdelete, clickYes}">
                    <Button
                        :loading="isLoadingdelete"
                        @click="() => clickYes()"
                        :label="trans('Yes, Create Replacement')"
                    />
                </template>
            </ModalConfirmation> -->
        </template>

        <template #other>
            <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach"
                    icon="upload"/>
        </template>
    </PageHeading>

    <!-- Section: Pallet Warning -->
    <div v-if="alert?.status" class="p-2 pb-0">
        <AlertMessage :alert/>
    </div>

    <!-- Section: Box Note -->
    <div class="relative">
        <Transition name="headlessui">
            <div xv-if="notes?.note_list?.some(item => !!(item?.note?.trim()))"
                 class="p-2 grid grid-cols-2 sm:grid-cols-4 gap-y-2 gap-x-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
                <BoxNote v-for="(note, index) in notes.note_list" :key="index + note.label" :noteData="note"
                         :updateRoute="routes.updateOrderRoute"/>
            </div>
        </Transition>
    </div>

    <!-- Section: Timeline -->
    <div v-if="props.data?.data?.state != 'in_process' && currentTab != 'products'"
         class="mt-4 sm:mt-0 border-b border-gray-200 pb-2">
        <Timeline v-if="timelines" :options="timelines" :state="props.data?.data?.state" :slidesPerView="6"
                  formatTime="EEE, do MMM yy, HH:mm"/>
    </div>

    <div v-if="currentTab != 'products'"
         class="grid grid-cols-2 lg:grid-cols-3 divide-x divide-gray-300 border-b border-gray-200">
        <BoxStatPallet class=" py-2 px-3" icon="fal fa-user">
            <div class="text-xs md:text-sm">
                <div class="font-semibold xmb-2 text-base">
                    {{ trans("Order") }}
                </div>

                <div class="space-y-0.5 pl-1">
                    <!-- Field: Reference Number -->
                    <Link as="a" v-if="box_stats?.customer.reference"
                          :href="box_stats?.customer?.route?.name ? route(box_stats?.customer.route.name, box_stats?.customer.route.parameters) : '#'"
                          class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer primaryLink">
                        <div v-tooltip="trans('Customer')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-user" class="text-gray-400" fixed-width aria-hidden="true"/>
                        </div>
                        <dd class="text-sm text-gray-500">#{{ box_stats?.customer.reference }}</dd>
                    </Link>

                    <!-- Field: Customer -->
                    <Link as="a" v-if="!box_stats?.customer.reference"
                          :href="box_stats?.customer?.route?.name ? route(box_stats?.customer.route.name, box_stats?.customer.route.parameters) : '#'"
                          class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
                        <div v-tooltip="trans('Contact name')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-id-card-alt" class="text-gray-400" fixed-width
                                             aria-hidden="true"/>
                        </div>
                        <dd class="text-sm text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
                    </Link>

                    <!-- Field: Client -->
                    <Link as="a" v-if="box_stats?.customer_client"
                          :href="box_stats?.customer_client?.route?.name ? route(box_stats?.customer_client.route.name, box_stats?.customer_client.route.parameters) : '#'"
                          class="pl-1 flex items-center w-fit flex-none gap-x-2 cursor-pointer secondaryLink">
                        <div v-tooltip="trans('Customer client')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-users" class="text-gray-400" fixed-width aria-hidden="true"/>
                        </div>
                        <dd class="text-sm text-gray-500">{{ box_stats?.customer_client.contact_name }}</dd>
                    </Link>

                    <!-- Field: Contact name -->
                    <dl v-else-if="box_stats?.customer.contact_name"
                        class="pl-1 flex items-center w-fit flex-none gap-x-2">
                        <dt v-tooltip="trans('Contact name')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-id-card-alt" class="text-gray-400" fixed-width
                                             aria-hidden="true"/>
                        </dt>
                        <dd class="text-sm text-gray-500">{{ box_stats?.customer.contact_name }}</dd>
                    </dl>

                    <!-- Field: Company name -->
                    <dl v-if="box_stats?.customer.company_name" class="pl-1 flex items-center w-full flex-none gap-x-2">
                        <dt v-tooltip="trans('Company name')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width
                                             aria-hidden="true"/>
                        </dt>
                        <dd class="text-sm text-gray-500">{{ box_stats?.customer.company_name }}</dd>
                    </dl>

                    <!-- Field: Email -->
                    <dl v-if="box_stats?.customer.email" class="pl-1 flex items-center w-full flex-none gap-x-2">
                        <dt v-tooltip="trans('Email')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width
                                             aria-hidden="true"/>
                        </dt>
                        <a :href="`mailto:${box_stats?.customer.email}`" v-tooltip="'Click to send email'"
                           class="text-sm text-gray-500 hover:text-gray-700 truncate">{{
                                box_stats?.customer.email
                            }}</a>
                    </dl>

                    <!-- Field: Phone -->
                    <dl v-if="box_stats?.customer.phone" class="pl-1 flex items-center w-full flex-none gap-x-2">
                        <dt v-tooltip="trans('Phone')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true"/>
                        </dt>
                        <a :href="`tel:${box_stats?.customer.phone}`" v-tooltip="'Click to make a phone call'"
                           class="text-sm text-gray-500 hover:text-gray-700">{{ box_stats?.customer.phone }}</a>
                    </dl>

                    <!-- Field: Billing Address -->
                    <dl v-if="box_stats?.customer?.addresses?.billing?.formatted_address !== box_stats?.customer?.addresses?.delivery?.formatted_address"
                        class="pl-1 flex items w-full flex-none gap-x-2">
                        <dt v-tooltip="trans('Billing address')" class="flex-none">
                            <FontAwesomeIcon icon="fal fa-dollar-sign" class="text-gray-400" fixed-width
                                             aria-hidden="true"/>
                        </dt>
                        <dd class="flex text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded min-w-52"
                            v-html="box_stats?.customer.addresses.billing.formatted_address">
                        </dd>
                    </dl>

                    <!-- Collection Toggle -->
                    <div v-if="props.data?.data?.state != 'dispatched'" class="!mt-2 pl-1 flex items w-full flex-none gap-x-2 items-center">
                        <FontAwesomeIcon icon='fal fa-map-marker-alt' class='text-gray-400' fixed-width
                                         aria-hidden='true'/>
                        <ToggleSwitch v-model="isCollection" @change="updateCollection"/>
                        <span class="text-sm text-gray-500">Collection</span>
                    </div>

                    <div class="pl-2">
                        <div v-if="isCollection" class="w-full">
                            <span class="block mb-1">{{ trans("Collection by:") }}</span>
                            <div class="flex space-x-4">
                                <label class="inline-flex items-center">
                                    <input type="radio" value="myself" v-model="collectionBy"
                                           @change="updateCollectionType" class="form-radio"/>
                                    <span class="ml-2">{{ trans("My Self") }}</span>
                                </label>
                                <label class="inline-flex items-center">
                                    <input type="radio" value="thirdParty" v-model="collectionBy"
                                           @change="updateCollectionType" class="form-radio"/>
                                    <span class="ml-2">{{ trans("Third Party") }}</span>
                                </label>
                            </div>

                            <div v-if="collectionBy === 'thirdParty'" class="mt-3">
                                <textarea v-model="textValue" @blur="updateCollectionNotes" rows="5"
                                          class="w-full border border-gray-300 rounded-md p-2"
                                          placeholder="Type additional notes..."></textarea>
                            </div>
                        </div>

                        <!-- Field: Shipping Address -->
                        <dl v-if="box_stats?.customer?.addresses?.delivery?.formatted_address !== box_stats?.customer?.addresses?.billing?.formatted_address && !isCollection"
                            class="mt-2 pt-1 flex items w-full flex-none gap-x-2">
                            <dt v-tooltip="trans('Shipping address')" class="flex-none">
                                <FontAwesomeIcon icon="fal fa-shipping-fast" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class=" text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded min-w-52">
                                <span v-html="box_stats?.customer.addresses.delivery.formatted_address"></span>
                                <div v-if="!props.readonly && props.data?.data?.state !== 'dispatched'" @click="() => isModalAddress = true"
                                     class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                    <span>{{ trans("Edit") }}</span>
                                </div>
                            </dd>
                        </dl>

                        <!-- Field: Billing Address -->
                        <dl v-if="box_stats?.customer?.addresses?.delivery?.formatted_address === box_stats?.customer?.addresses?.billing?.formatted_address && !isCollection"
                            class="mt-2 flex items w-full flex-none gap-x-2">
                            <dt v-tooltip="trans('Shipping address and Billing address')" class="flex-none">
                                <FontAwesomeIcon icon="fal fa-shipping-fast" class="text-gray-400" fixed-width
                                                 aria-hidden="true"/>
                            </dt>
                            <dd class="flex text-gray-500 text-xs relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                                <span v-html="box_stats?.customer.addresses.delivery.formatted_address"></span>
                                <div v-if="!props.readonly && props.data?.data?.state !== 'dispatched'" @click="() => isModalAddress = true"
                                     class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                                    <span>{{ trans("Edit") }}</span>
                                </div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </BoxStatPallet>

        <!-- Box: Payment/Invoices/Delivery Notes  -->
        <BoxStatPallet class="py-4 px-3" icon="fal fa-user">
            <div class="text-xs md:text-sm">


                <div class="xspace-y-0.5 pl-1">
                    <!-- Field: Billing -->
                    <dl class="relative flex items-start w-full flex-none gap-x-1">
                        <dt class="flex-none pt-0.5 pl-1">
                            <FontAwesomeIcon icon="fal fa-dollar-sign" fixed-width aria-hidden="true"
                                             class="text-gray-500"/>
                        </dt>

                        <div>
                            <NeedToPay
                                :totalAmount="box_stats.products.payment.total_amount"
                                :paidAmount="box_stats.products.payment.paid_amount"
                                :payAmount="box_stats.products.payment.pay_amount"
                                xclass="[box_stats.products.payment.pay_amount ? 'hover:bg-gray-100 cursor-pointer' : '']"
                                :currencyCode="currency.code"
                            >
                                <template #default>
                                    <!-- Pay: Invoice -->
                                    <div
                                        v-if="box_stats.products.payment.pay_amount > 0 && !(props.data?.data?.state === 'creating' || props.data?.data?.state === 'cancelled'   ) "
                                        class="pt-1 border-t border-green-300 text-xxs">
                                        <Button
                                            @click.prevent="() => onClickPayInvoice()"
                                            :label="trans('Pay')"
                                            type="secondary"
                                            size="xxs"
                                        />
                                    </div>

                                    <!-- Pay: Refund -->
                                    <div
                                        v-if="box_stats.products.payment.pay_amount < 0 && !(props.data?.data?.state === 'creating' || props.data?.data?.state === 'cancelled'   )"
                                        class="pt-1 border-t border-green-300 text-xxs">
                                        <Button
                                            @click="() => onClickPayRefund()"
                                            :label="trans('Refund money')"
                                            type="secondary"
                                            size="xxs"
                                        />
                                    </div>

                                    <!-- Pay: excesses balance -->
                                    <div v-if="box_stats.products.excesses_payment?.amount > 0"
                                         class="pt-1 border-t border-green-300 text-xxs">
                                        <p class="text-gray-500 mb-1 mt-2">
                                            {{ trans("The order is overpaid") }}:
                                            <span class="text-gray-700">
                                                {{
                                                    locale.currencyFormat(currency.code,
                                                        Number(box_stats.products.excesses_payment?.amount))
                                                }}
                                            </span>
                                        </p>

                                        <ButtonWithLink
                                            v-if="box_stats.products.excesses_payment?.route_to_add_balance?.name"
                                            :routeTarget="box_stats.products.excesses_payment?.route_to_add_balance"
                                            icon="far fa-plus" label="Add to customer balance" size="xxs"/>
                                    </div>

                                </template>
                            </NeedToPay>

                            <div v-if="last_payment" class="text-xs text-gray-500">
                                {{ trans("Last payments:") }}
                                <Link :href="route('grp.org.accounting.payments.show', {
                                    organisation: route().params.organisation,
                                    payment: last_payment?.id
                                })" class="secondaryLink">{{ last_payment?.reference ?? last_payment?.id }}
                                </Link>
                            </div>
                        </div>
                    </dl>

                    <!-- Field: weight -->
                    <dl class="mt-1 flex items-center w-full flex-none gap-x-1.5">
                        <dt class="flex-none pl-1">
                            <FontAwesomeIcon icon="fal fa-weight" fixed-width aria-hidden="true"
                                             class="text-gray-500"/>
                        </dt>
                        <dd class="text-gray-500 sep" v-tooltip="trans('Estimated weight of all products')">
                            {{ box_stats?.products.estimated_weight || 0 }} kilograms
                        </dd>
                    </dl>


                    <div v-if="box_stats?.invoice" class="mt-1 flex items-center w-full flex-none justify-between">
                        <Link
                            :href="route(box_stats?.invoice?.routes?.show?.name, box_stats?.invoice?.routes?.show.parameters)"
                            class="flex items-center gap-3 gap-x-1.5 primaryLink cursor-pointer">
                            <div class="flex-none">
                                <FontAwesomeIcon icon="fal fa-file-invoice-dollar" fixed-width aria-hidden="true"
                                                 class="text-gray-500"/>
                            </div>
                            <div class="text-gray-500 " v-tooltip="trans('Invoice')">
                                {{ box_stats?.invoice?.reference }}
                            </div>
                        </Link>

                        <a v-if="box_stats?.invoice?.routes?.download?.name"
                           :href="route(box_stats?.invoice?.routes?.download?.name, box_stats?.invoice?.routes?.download.parameters)"
                           as="a" target="_blank" class="flex items-center text-gray-400 hover:text-orange-600">
                            <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true"/>
                        </a>
                    </div>


                    <div v-if="box_stats?.delivery_notes?.length"
                         class="mt-4 border rounded-lg p-4 pt-3 bg-white shadow-sm">
                        <!-- Section Title -->
                        <div class="flex items-center gap-2 border-b border-gray-200 pb-2 mb-3">
                            <FontAwesomeIcon :icon="faTruck" class="text-blue-500" fixed-width/>
                            <div class="text-sm font-semibold text-gray-800">
                                {{ trans('Delivery Notes') }}
                            </div>
                        </div>

                        <!-- Delivery Note Items -->
                        <div v-for="(note, index) in box_stats?.delivery_notes" :key="index"
                             class="mb-3 pb-3 border-b border-dashed last:border-0 last:mb-0 last:pb-0">

                            <div class="flex items-center gap-2 text-sm text-gray-700 mb-1">
                                <span class="font-medium">Ref:</span>
                                <Link :href="generateRouteDeliveryNote(note?.slug)" class="secondaryLink">{{
                                        note?.reference
                                    }}
                                </Link>
                                <span class="ml-auto text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded">
                                    <Icon :data="note?.state"/>
                                </span>
                            </div>

                            <!-- Shipments -->
                            <div v-if="note?.shipments?.length > 0" class="mt-1 text-xs text-gray-600">
                                <p class="text-gray-700 font-medium mb-1">{{ trans('Shipments') }}:</p>
                                <ul class="list-disc pl-4 space-y-1">
                                    <li v-for="(shipment, i) in note.shipments" :key="i">
                                        <template v-if="shipment?.formatted_tracking_urls?.length">
                                            {{ shipment.name }}
                                            <div v-for="trackingData in shipment.formatted_tracking_urls">

                                                <a :href="trackingData.url" target="_blank" rel="noopener noreferrer"
                                                   class="secondaryLink" v-tooltip="trans('Click to track shipment')">
                                                    {{ trackingData.tracking }}
                                                </a>
                                            </div>
                                        </template>

                                        <span v-else-if="shipment.name" class="">
                                            {{ shipment.name }}
                                        </span>
                                        <span v-else-if="shipment.name" class="text-gray-400 italic">
                                            {{ trans("No shipment information") }}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <div v-else class="mt-1 text-xs italic text-gray-400">
                                {{ trans('No shipments') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </BoxStatPallet>

        <!-- Box: Order summary -->
        <BoxStatPallet class="py-4 border-t lg:border-t-0 border-gray-300">
            <div class="text-xs md:text-sm">
                <div class="px-3 font-semibold xmb-2 text-base">
                    {{ trans("Summary") }}
                </div>

                <section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
                    <OrderSummary :order_summary="box_stats.order_summary" :currency_code="currency.code"/>
                </section>
            </div>
        </BoxStatPallet>
    </div>

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
          @update:tab="handleTabUpdate"/>
    <div class="pb-12">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"
                   :updateRoute="routes.updateOrderRoute" :state="data?.data?.state"
                   :detachRoute="attachmentRoutes.detachRoute" :fetchRoute="routes.products_list"
                   :modalOpen="isModalUploadOpen" :action="currentAction" :readonly="props.readonly"
                   @update:tab="handleTabUpdate"/>
    </div>

    <ModalProductList v-model="isModalProductListOpen" :fetchRoute="routes.products_list" :action="currentAction"
                      :current="currentTab" v-model:currentTab="currentTab" :typeModel="'order'"/>

    <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)" width="w-full max-w-5xl">
        <DeliveryAddressManagementModal :address_modal_title="delivery_address_management.address_modal_title"
                                        :addresses="delivery_address_management.addresses"
                                        :updateRoute="delivery_address_management.address_update_route"
                                        keyPayloadEdit="address"
                                        @onDone="() => (isModalAddress = false)"/>
    </Modal>


    <!-- Modal: payment Invoice -->
    <Modal :isOpen="isOpenModalPayment" @onClose="isOpenModalPayment = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">
                    {{ trans("Order Payment") }}</h2>
                <p class="text-xs leading-5 text-gray-400">
                    {{ trans("Information about payment from customer") }}
                </p>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label for="first-name" class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans("Select payment method") }}
                    </label>
                    <div class="mt-1">
                        <PureMultiselect v-model="paymentData.payment_method" :options="listPaymentMethod"
                                         :isLoading="isLoadingFetch" label="name" valueProp="id" required caret/>
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">
                        {{ trans("Payment amount") }}
                    </label>
                    <div class="mt-1">
                        <PureInputNumber v-model="paymentData.payment_amount"/>
                    </div>
                    <div class="space-x-1">
                        <span class="text-xxs text-gray-500">{{
                                trans("Need to pay")
                            }}: {{
                                locale.currencyFormat(box_stats.order_summary.currency.code, box_stats.products.payment.pay_amount)
                            }}</span>
                        <Button @click="() => paymentData.payment_amount = box_stats.products.payment.pay_amount"
                                :disabled="paymentData.payment_amount === box_stats.products.payment.pay_amount"
                                type="tertiary"
                                :label="trans('Pay all')" size="xxs"/>
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">{{ trans("Reference") }}</label>
                    <div class="mt-1">
                        <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                    </div>
                </div>

            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onSubmitPayment()" label="Submit" :disabled="!(!!paymentData.payment_method)"
                        :loading="isLoadingPayment" full/>
                <Transition name="spin-to-down">
                    <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">*{{
                            errorPaymentMethod
                        }}</p>
                </Transition>
            </div>
        </div>
    </Modal>

    <!-- Modal: Refund -->
    <Modal :isOpen="isOpenModalRefund" @onClose="isOpenModalRefund = false" width="w-[600px]">
        <div class="isolate bg-white px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <h2 class="text-lg font-bold tracking-tight sm:text-2xl">
                    {{ trans("Return Payment") }}
                </h2>
            </div>

            <div class="mt-7 grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div class="col-span-2">
                    <label for="first-name" class="block text-sm font-medium leading-6">
                        <span class="text-red-500">*</span> {{ trans("Select payment method") }}
                    </label>
                    <div class="mt-1">
                        <PureMultiselect v-model="paymentData.payment_method" :options="listPaymentMethod"
                                         :isLoading="isLoadingFetch" label="name" valueProp="id" required caret/>
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">
                        {{ trans('Refund amount') }}
                    </label>
                    <div class="mt-1">
                        <PureInputNumber v-model="paymentData.payment_amount"/>
                    </div>
                    <div class="space-x-1">
                        <span class="text-xxs text-gray-500">{{
                                trans("Need to refund")
                            }}: {{
                                locale.currencyFormat(box_stats.order_summary.currency.code, box_stats.products.payment.pay_amount)
                            }}</span>
                        <Button @click="() => paymentData.payment_amount = box_stats.products.payment.pay_amount"
                                :disabled="paymentData.payment_amount === box_stats.products.payment.pay_amount"
                                type="tertiary"
                                :label="trans('Refund all payment')" size="xxs"/>
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="last-name" class="block text-sm font-medium leading-6">{{ trans("Reference") }}</label>
                    <div class="mt-1">
                        <PureInput v-model="paymentData.payment_reference" placeholder="#000000"/>
                    </div>
                </div>

            </div>

            <div class="mt-6 mb-4 relative">
                <Button @click="() => onSubmitPayment(true)" label="Submit" :disabled="!(!!paymentData.payment_method)"
                        :loading="isLoadingPayment" full/>
                <Transition name="spin-to-down">
                    <p v-if="errorPaymentMethod" class="absolute text-red-500 italic text-sm mt-1">
                        *{{ errorPaymentMethod }}</p>
                </Transition>
            </div>
        </div>
    </Modal>

    <UploadExcel v-if="props.upload_excel" v-model="isModalUploadExcel" :title="upload_excel.title"
                 :progressDescription="upload_excel.progressDescription"
                 :upload_spreadsheet="upload_excel.upload_spreadsheet"
                 :preview_template="upload_excel.preview_template"
                 :propsRefreshAfterFinish="['transactions', 'box_stats']"
                 :xadditionalDataToSend="'interest.pallets_storage' ? ['stored_items'] : undefined"/>

    <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes"/>
</template>

<style scoped>
.p-toggleswitch {
    --p-toggleswitch-checked-background: #3b82f6;
}
</style>
