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
import { routeType } from '@/types/route'
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import '@/Composables/Icon/PalletDeliveryStateEnum'
import { Address, AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation, faShieldAlt, faBoxHeart } from '@fas'
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
    faTimes,
    faInfoCircle,
    faStar
} from '@fal'
import { Currency } from '@/types/LayoutRules'
import { faSpinnerThird } from '@far'
import DropshippingSummaryOrder from '@/Components/Retina/Dropshipping/DropshippingSummaryOrder.vue'
import RetinaTableOrderReviewableReview from "../../../Components/Tables/Retina/RetinaTableOrderReviewableReview.vue"
import OverallReview from "@/Components/OverallReview.vue"
library.add(fadExclamationTriangle,faStar, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faShieldAlt, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    pageHead: PageHeadingTypes
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
        order_summary: {}
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
    address_management: AddressManagement
    transactions: {}
    invoices?: {}
    delivery_notes: {
        data: Array<any>
    }
    attachments?: {}
    review_settings : object
    order:any
    overall_review : any
    family_reviews : any
    product_reviews : any
}>()

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {
    const components: Component = {
        overall_review: OverallReview,
        family_reviews: RetinaTableOrderReviewableReview,
        product_reviews: RetinaTableOrderReviewableReview
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <DropshippingSummaryOrder :address_management :summary="box_stats" :order />
    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <div class="mb-12 mx-4 mt-4 overflow-x-auto">
      <component
            :is="component"
            :data="props[currentTab as keyof typeof props]"
            :tab="currentTab"
            @update:tab="handleTabUpdate"
            :review_settings
        />
    </div>
</template>
