<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, inject, ref } from 'vue'
import type { Component } from 'vue'
import { useTabChange } from "@/Composables/tab-change"
import { debounce } from 'lodash-es'
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { UploadPallet } from '@/types/Pallet'
import { Tabs as TSTabs } from '@/types/Tabs'
import '@vuepic/vue-datepicker/dist/main.css'

import '@/Composables/Icon/PalletDeliveryStateEnum'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'

import axios from 'axios'
import TableDeliveryNotes from "@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue"
import { notify } from '@kyvg/vue3-notification'
import OrderProductTable from '@/Components/Dropshipping/Orders/OrderProductTable.vue'
import { Address, AddressManagement } from "@/types/PureComponent/Address"

import { library } from "@fortawesome/fontawesome-svg-core"
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue"

import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation } from '@fas'
import { faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, } from '@fal'
import { Currency } from '@/types/LayoutRules'
import TableInvoices from '@/Components/Tables/Grp/Org/Accounting/TableInvoices.vue'
import TableProductList from '@/Components/Tables/Grp/Helpers/TableProductList.vue'
import { faSpinnerThird } from '@far'
import DSCheckoutSummary from '@/Components/Retina/Dropshipping/DSCheckoutSummary.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import { Message } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Button from '@/Components/Elements/Buttons/Button.vue'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
library.add(fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    pageHead: PageHeadingTypes
    order: {}


    routes: {
        update_route: routeType
        submit_route: routeType
        route_to_pay_unpaid: routeType
        updateOrderRoute: routeType
    }
    timelines: {

    }
    is_notes_editable: boolean

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
        customer_channel: {
            status: boolean
            platform: {
                name: string
                image: string
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
    currency: Currency
    data?: {
        data: {
            is_fully_paid: boolean
            unpaid_amount: number
            route_to_pay_unpaid?: routeType
            state: string
            state_label: string
            state_icon: string
            public_notes?: string
            customer_notes?: string
            shipping_notes?: string
        }
    }



    transactions: {}
    invoices?: {}
    delivery_notes: {
        data: Array<any>
    }
    attachments?: {}

    // upload_spreadsheet: UploadPallet
    // balance: string
    // total_to_pay: number
    // address_management: AddressManagement
}>()


const isModalUploadOpen = ref(false)

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

const locale = inject('locale', aikuLocaleStructure)
console.log('DS Orders', props)



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
const debounceSubmitNote = debounce(() => onSubmitNote('customer_notes', noteToSubmit.value), 800)
const debounceDeliveryInstructions = debounce(() => onSubmitNote('shipping_notes', deliveryInstructions.value), 800)

</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
    </PageHeading>

    <div class="mt-4 sm:mt-0 border-b border-gray-200 pb-2 max-w-5xl">
        <Timeline v-if="timelines" :options="timelines" :state="props.data?.data?.state" :slidesPerView="6" />
    </div>

    <!-- Section: Alert if unpaid -->
<!--    <Message v-if="!data?.data?.is_fully_paid" severity="error" class="mx-4 mt-4 ">-->
    <Message v-if="false" severity="error" class="mx-4 mt-4 ">
        <template #icon>
            <FontAwesomeIcon :icon="fadExclamationTriangle" class="text-xl" fixed-width aria-hidden="true" />
        </template>

        <div class="ml-2 font-normal flex justify-between w-full">
            <div class="flex items-center gap-x-2">
                {{ trans("You have unpaid amount of the order") }}: <span class="font-bold">{{ locale.currencyFormat(locale.currencyInertia?.code, data?.data.unpaid_amount) }}</span>
            </div>
            <ButtonWithLink
                :routeTarget="routes.route_to_pay_unpaid"
                :label="trans('Click to pay')"
                type="positive"
                class="bg-green-100"
            />
        </div>
    </Message>

    <DSCheckoutSummary :summary="box_stats" />

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
        @update:tab="handleTabUpdate" />

    <div class="mb-12 mx-4 mt-4 rounded-md border border-gray-200 overflow-x-auto">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"
            :updateRoute="routes?.updateOrderRoute" :state="data?.data?.state" :modalOpen="isModalUploadOpen"
            @update:tab="handleTabUpdate" />
    </div>

    <div class="flex justify-end px-6 gap-x-4">
        <div class="grid grid-cols-3 gap-x-4 w-full">
            <div class="">
                <div class="mb-2 text-sm text-gray-500">
                    <FontAwesomeIcon style="color: rgb(148, 219, 132)" icon="fal fa-sticky-note" class="xopacity-70" fixed-width aria-hidden="true" />
                    {{ trans("Notes from staff") }}
                    :
                </div>
                <PureTextarea
                    :modelValue="props.data?.data?.public_notes || ''"
                    @update:modelValue="() => debounceDeliveryInstructions()"
                    :placeholder="trans('No notes from staff')"
                    rows="4"
                    disabled
                    xloading="isLoadingNote.includes('shipping_notes')"
                    xisSuccess="recentlySuccessNote.includes('shipping_notes')"
                    xisError="recentlyErrorNote"
                />
            </div>

            <!-- Input text: Delivery instructions -->
            <div class="">
                <div class="mb-2 text-sm text-gray-500">
                    <FontAwesomeIcon icon="fal fa-truck" class="text-[#38bdf8]" fixed-width aria-hidden="true" />
                    {{ trans("Delivery instructions") }}
                    <FontAwesomeIcon v-tooltip="trans('To be printed in shipping label')" icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
                    :
                </div>
                <PureTextarea
                    v-model="deliveryInstructions"
                    @update:modelValue="() => debounceDeliveryInstructions()"
                    :placeholder="is_notes_editable ? trans('Add if needed') : 'No delivery instructions'"
                    rows="4"
                    :disabled="!is_notes_editable"
                    :loading="isLoadingNote.includes('shipping_notes')"
                    :isSuccess="recentlySuccessNote.includes('shipping_notes')"
                    :isError="recentlyErrorNote"
                />
            </div>
        
            <!-- Input text: Other instructions -->
            <div class="">
                <div class="mb-2 text-sm text-gray-500">
                    <FontAwesomeIcon icon="fal fa-sticky-note" style="color: rgb(255, 125, 189)" fixed-width aria-hidden="true" />
                    {{ trans("Other instructions") }}:
                </div>
                <PureTextarea
                    v-model="noteToSubmit"
                    @update:modelValue="() => debounceSubmitNote()"
                    :placeholder="is_notes_editable ? trans('Add if needed') : 'No instructions'"
                    rows="4"
                    :disabled="!is_notes_editable"
                    :loading="isLoadingNote.includes('customer_notes')"
                    :isSuccess="recentlySuccessNote.includes('customer_notes')"
                    :isError="recentlyErrorNote"
                />
            </div>
        </div>
    </div>

</template>
