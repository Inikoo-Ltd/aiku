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
import { AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation } from '@fas'
import { faStar,faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, } from '@fal'
import { Currency } from '@/types/LayoutRules'
import { faSpinnerThird } from '@far'
import EcomReviewSummary from '@/Components/Retina/Ecom/EcomReviewSummary.vue'
import RetinaTableOrderReview from '@/Components/Tables/Retina/RetinaTableOrderReview.vue'
import OveralReview from '@/Components/OveralReview.vue'


library.add(faStar,fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    pageHead: PageHeadingTypes
    summary: {
        order_summary: {
            net_amount: string
            gross_amount: string
            tax_amount: string
            goods_amount: string
            services_amount: string
            charges_amount: string
        }
        order_properties: {
            weight: number
            customer_order_number: number
            customer_order_ordinal: string
            customer_order_ordinal_tooltip: string
        }
    }
    address_management: AddressManagement
    currency: Currency
    data?: {
        data: {
            slug: string
            is_fully_paid: boolean
            unpaid_amount: number
            route_to_pay_unpaid?: routeType
            state: string
            state_label: string
            state_icon: string

        }
    }
    overall_review: {}
    family_reviews?: {}
    product_reviews?: {}
    transactions: {}
    invoices?: {}
    delivery_notes: {
        data: Array<any>
    }
    attachments?: {}
}>()


const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        overall_review: OveralReview,
        family_reviews: RetinaTableOrderReview,
        product_reviews: RetinaTableOrderReview,
    }

    return components[currentTab.value]
})

</script>

<template>
    <Head :title="capitalize(title)" />

    <PageHeading :data="pageHead">
    </PageHeading>

    <EcomReviewSummary
        :summary
        :order="data?.data"
    />

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
        @update:tab="handleTabUpdate" />

    <div class="mb-12 mx-4 mt-4 overflow-x-auto">
        <component 
            :is="component" 
            :data="props[currentTab as keyof typeof props]" 
            :tab="currentTab"
            @update:tab="handleTabUpdate" 
        />
    </div>

</template>
