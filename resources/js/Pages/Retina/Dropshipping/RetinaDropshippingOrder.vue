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


    routes?: {
        update_route: routeType
        submit_route: routeType
    }
    timelines: {

    }
    fffff: {}


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

</script>

<template>

    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
    </PageHeading>

    <div class="mt-4 sm:mt-0 border-b border-gray-200 pb-2 max-w-5xl">
        <Timeline v-if="timelines" :options="timelines" :state="props.data?.data?.state" :slidesPerView="6" />
    </div>

    <!-- Section: Alert if unpaid -->
    <Message v-if="!data?.data?.is_fully_paid" severity="error" class="mx-4 mt-4 ">
        <template #icon>
            <FontAwesomeIcon :icon="fadExclamationTriangle" class="text-xl" fixed-width aria-hidden="true" />
        </template>

        <div class="ml-2 font-normal flex justify-between w-full">
            <div class="flex items-center gap-x-2">
                {{ trans("You have unpaid amount of the order") }}: <span class="font-bold">{{ locale.currencyFormat(locale.currencyInertia?.code, data?.data.unpaid_amount) }}</span>
            </div>

            <ButtonWithLink
                v-if="data?.data.route_to_pay_unpaid"
                :routeTarget="data?.data.route_to_pay_unpaid"
                :label="trans('Click to pay')"
                type="positive"
                class="bg-green-100"
            />
        </div>
    </Message>

    <DSCheckoutSummary :summary="box_stats" />

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
        @update:tab="handleTabUpdate" />

    <div class="mb-12 mx-4 mt-4 rounded-md border border-gray-200">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"
            :updateRoute="routes?.updateOrderRoute" :state="data?.data?.state" :modalOpen="isModalUploadOpen"
            @update:tab="handleTabUpdate" />
    </div>


</template>

<style scoped lang="scss">

:deep(.p-message-text) {
    width: 100%;
}
</style>