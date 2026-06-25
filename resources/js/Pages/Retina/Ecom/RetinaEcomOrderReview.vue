<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Created on: 26-08-2024, Bali, Indonesia
    -  Github: https://github.com/aqordeon
    -  Copyright: 2024
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import { routeType } from "@/types/route"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle as fadExclamationTriangle } from "@fad"
import { faExclamationTriangle, faExclamation } from "@fas"
import { faStars, faGalaxy, faStar, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle } from "@fal"
import { Currency } from "@/types/LayoutRules"
import { faSpinnerThird } from "@far"
import EcomReviewSummary from "@/Components/Retina/Ecom/EcomReviewSummary.vue"
import RetinaTableOrderReviewableReview from "../../../Components/Tables/Retina/RetinaTableOrderReviewableReview.vue"
import OverallReview from "../../../Components/OverallReview.vue"


library.add(faStars, faGalaxy, faStar, fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    pageHead: PageHeadingTypes
    review_settings : object
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
    overall_review: {}
    family_reviews?: {}
    product_reviews?: {}
    transactions: {}
    invoices?: {}
    delivery_notes: {
        data: Array<any>
    }
    review_summary?: {
        family_review: number
        total_family_review: number
        total_product_review: number
        overall_review: number
        average_review: number
    }
  
    attachments?: {}
}>()

console.log('sdsdsd',props.review_settings)
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

    <PageHeading :data="pageHead">
    </PageHeading>

    <EcomReviewSummary
        :summary
        :order="data?.data"
        :review_summary
    />

    <Tabs 
        v-if="currentTab != 'products'" 
        :current="currentTab" 
        :navigation="tabs?.navigation"
        @update:tab="handleTabUpdate" 
    />

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
