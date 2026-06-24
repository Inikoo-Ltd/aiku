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
import { trans } from "laravel-vue-i18n"
import { routeType } from '@/types/route'
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import '@vuepic/vue-datepicker/dist/main.css'
import '@/Composables/Icon/PalletDeliveryStateEnum'
import EcomTableOrderTransactions from "@/Components/Retina/Ecom/EcomTableOrderTransactions.vue"
import { AddressManagement } from "@/types/PureComponent/Address"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationTriangle as fadExclamationTriangle } from '@fad'
import { faExclamationTriangle, faExclamation, faBoxHeart, faShieldAlt } from '@fas'
import { faStar,faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, } from '@fal'
import { Currency } from '@/types/LayoutRules'
import { faSpinnerThird } from '@far'
import Timeline from '@/Components/Utils/Timeline.vue'
import { Message } from 'primevue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { debounce } from 'lodash-es'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import EcomReviewSummary from '@/Components/Retina/Ecom/EcomReviewSummary.vue'
import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import RetinaTableOrderReview from '@/Components/Tables/Retina/RetinaTableOrderReview.vue'


library.add(faStar,fadExclamationTriangle, faExclamationTriangle, faDollarSign, faIdCardAlt, faShippingFast, faIdCard, faEnvelope, faPhone, faWeight, faStickyNote, faExclamation, faTruck, faFilePdf, faPaperclip, faTimes, faInfoCircle, faSpinnerThird)


const props = defineProps<{
    title: string
    tabs: TSTabs
    pageHead: PageHeadingTypes
    routes: {
        update_route: routeType
        submit_route: routeType
        route_to_pay_unpaid: routeType
        updateOrderRoute: routeType
    }
    timelines: {
    }
    is_notes_editable: boolean
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
    balance: string
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
    order_reviews?: {}
    family_reviews?: {}
    product_reviews?: {}


    attachments?: {}

}>()

const isModalUploadOpen = ref(false)

const currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {

        order_reviews: RetinaTableOrderReview,
        family_reviews: RetinaTableOrderReview,
        product_reviews: RetinaTableOrderReview,

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

    <div v-if="data?.data?.has_insurance || data?.data?.is_premium_dispatch || data?.data?.has_extra_packing" class="absolute top-0 left-1/2 -translate-x-1/2 bg-yellow-500 rounded-b px-4 py-0.5 text-sm space-x-1">
        <FontAwesomeIcon v-if="data?.data?.is_premium_dispatch" v-tooltip="trans('Premium dispatch')" :icon="faStar" class="text-white animate-pulse" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-if="data?.data?.has_extra_packing" v-tooltip="trans('Extra packing')" :icon="faBoxHeart" class="text-white animate-pulse" fixed-width aria-hidden="true" />
        <FontAwesomeIcon v-if="data?.data?.has_insurance" v-tooltip="trans('Insurance')" :icon="faShieldAlt" class="text-white animate-pulse" fixed-width aria-hidden="true" />
    </div>

    <!-- Section: Timelines -->
    <div v-if="timelines"  class="mt-4 py-3 sm:mt-0 border-b border-gray-200 w-full">
        <div class="max-w-5xl mx-auto">
            <Timeline :options="timelines" :state="props.data?.data?.state" :slidesPerView="6" />
        </div>
    </div>

    <!-- Section: Alert if unpaid -->
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

    <EcomReviewSummary
        :summary
        :order="data?.data"
    />

    <Tabs v-if="currentTab != 'products'" :current="currentTab" :navigation="tabs?.navigation"
        @update:tab="handleTabUpdate" />

    <div class="mb-12 mx-4 mt-4 rounded-md border border-gray-200 overflow-x-auto">
        <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab"
            :updateRoute="routes?.updateOrderRoute" :state="data?.data?.state" :modalOpen="isModalUploadOpen"
            @update:tab="handleTabUpdate" />
    </div>



</template>
