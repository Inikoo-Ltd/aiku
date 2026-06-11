<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { useTabChange } from '@/Composables/tab-change'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import Coupon from '@/Components/Utils/Coupon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject, computed, ref } from 'vue'
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import FamilyOfferLabelDiscount from '@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelDiscount.vue'
import BasicDiscount from '@/Components/Utils/Label/DiscountTemplate/BasicDiscount.vue'
import { OfferResource, OfferAllowanceResource } from '@/types/Catalogue/Offers'
import { useFormatTime } from '@/Composables/useFormatTime'
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlagCheckered } from "@fortawesome/free-solid-svg-icons"
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import DiscountByType from '@/Components/Utils/Label/DiscountByType.vue'
import PreviewVoucher from '@/Components/Offers/PreviewOffer/PreviewVoucher.vue'

library.add(faFlagCheckered)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currency_code: string
    data: {
        offer: OfferResource
        offer_allowances: OfferAllowanceResource[]
    }
    tabs: {
        current: string
        navigation: Record<string, { title: string; icon?: string; type?: string; align?: string }>
    }
    customers?: object
    orders?: object
    url_master?: routeType
}>()

const locale = inject('locale', aikuLocaleStructure)

type ProductCategoryLink = {
    name: string
    slug: string
    type: 'department' | 'sub_department' | 'family'
}

const getCategoryLink = (productCategory?: ProductCategoryLink | null) => {
    if (!productCategory) return '#'

    if (productCategory.type === 'department') {
        return route('grp.org.shops.show.catalogue.departments.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            department: productCategory.slug,
        })
    }

    if (productCategory.type === 'sub_department') {
        return route('grp.org.shops.show.catalogue.sub_departments.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            subDepartment: productCategory.slug,
        })
    }

    return route('grp.org.shops.show.catalogue.families.show', {
        organisation: route().params.organisation,
        shop: route().params.shop,
        family: productCategory.slug,
    })
}

const getOfferCampaignLink = (offerCampaign: { slug: string }) => {
    if (offerCampaign) {
        return route('grp.org.shops.show.discounts.campaigns.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            offerCampaign: offerCampaign.slug,
        })
    }
    return '#'
}

const isExpired = computed(() => {
    const now = new Date()
    const end = props.data.offer.end_at ? new Date(props.data.offer.end_at) : null

    return end && now > end
})

const stateClass = computed(() => {
    if (isExpired.value) return 'bg-red-200 text-red-800 border'

    switch (state) {
        case 'active':
            return 'bg-green-50 text-green-700 border border-green-300'
        case 'in_process':
            return 'bg-grey-100 text-gray-600 border border-gray-300'
        case 'suspended':
            return 'bg-yellow-50 text-yellow-700 border border-yellow-300'
        case 'finished':
            return 'bg-red-200 text-red-800 border border'
        default:
            return 'bg-blue-50 text-blue-700 border border-blue-300'
    }
})

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const tabComponent = computed(() => {
    const components: Record<string, unknown> = {
        customers: TableCustomers,
        orders: TableOrders,
    }
    return components[currentTab.value]
})

const hasTrigger = computed(() => {
    const t = props.data.offer.trigger_data
    return (
        t?.item_quantity !== undefined ||
        t?.min_amount !== undefined ||
        t?.order_number !== undefined ||
        t?.item_amount !== undefined
    )
})

const state = props.data.offer_allowances[0]?.state
const percentage_off = props.data.offer_allowances[0]?.data?.percentage_off

const irisOffersData = computed(() => {
    const bestPercentageOff = props.data.offer_allowances?.reduce((best, oa) => {
        const po = oa.data?.percentage_off ?? 0
        return po > best ? po : best
    }, 0) ?? 0

    return {
        number_offers: props.data.offer_allowances?.length ? 1 : 0,
        offers: [{ ...props.data.offer, id: props.data.offer.id ?? 0 }],
        best_percentage_off: props.data.offer.id ? {
            offer_id: props.data.offer.id,
            percentage_off: (bestPercentageOff * 100).toFixed(1) + '%'
        } : undefined
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <div class="whitespace-nowrap">
                <Link v-if="url_master?.name" :href="route(url_master.name, url_master.parameters)" v-tooltip="trans('Go to Master Family section Offer GR/Vol')" class="mr-1 opacity-70 hover:opacity-100">
                    <FontAwesomeIcon icon="fab fa-octopus-deploy" color="#4B0082" fixed-width />
                </Link>
            </div>
        </template>
    </PageHeading>

    <!-- Section: Preview label -->
    <div class="p-5 border-b border-gray-300 offer">
        <div class="grid grid-cols-1 md:grid-cols-3 items-center gap-4">
            <!-- Left: Duration & State -->
            <div class="flex flex-col gap-3 ml-4">
                <div v-if="data.offer.start_at || data.offer.end_at" class="flex flex-col gap-1 text-lg text-gray-600">
                    <div class="flex items-center gap-2">
                        <span class="w-16 text-xs text-gray-400 uppercase tracking-wide">{{ ctrans("Start") }}</span>
                        <span class="font-medium">{{ useFormatTime(data.offer.start_at ?? undefined, { formatTime: 'hm' }) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-16 text-xs text-gray-400 uppercase tracking-wide">{{ ctrans("End") }}</span>
                        <span class="font-medium">{{ data.offer.end_at ? useFormatTime(data.offer.end_at, { formatTime: 'hm' }) : ctrans('Permanent') }}</span>
                    </div>
                </div>

                <div v-if="state" class="inline-flex items-center text-sm capitalize rounded-full px-3 py-0.5 font-medium w-fit" :class="stateClass">
                    {{ (data.offer.end_at && new Date() > new Date(data.offer.end_at)) ? ctrans('finished') : state?.replace('_', ' ') }}
                </div>
            </div>

            <!-- Center: Type & Preview -->
            <div class="flex flex-col items-center gap-2">
                <div class="text-sm text-gray-600 gap-2">
                    {{ ctrans("Type") }}: <span class="font-bold">{{ data.offer.type }}</span>
                </div>
                
                <FamilyOfferLabelDiscount v-if="data.offer.type == 'Category Quantity Ordered Order Interval'" :offer="data.offer" :offer_allowances="data.offer_allowances" />
                <BasicDiscount v-else-if="data.offer.type == 'GR Amnesty'"
                    :offers_data="{
                        o: {
                            p: (percentage_off || 0) * 100 + '%',
                            l: data.offer.label ?? '',
                            st: 'a'
                        }
                    }"
                    class="!text-xl"
                />
                <BasicDiscount v-else-if="data.offer.type == 'Category Ordered'"
                :offers_data="{
                    o: {
                        p: (percentage_off || 0) * 100 + '%',
                        l: data.offer.label ?? ''
                    }
                }"
                class="!text-3xl"
                />
                <!-- This should use component for GRP, not use Iris -->
                <DiscountByType
                    v-else-if="data.offer.type == 'Department Quantity Ordered'"
                    :offers_data="irisOffersData"
                    template="max_discount"
                    class="scale-[200%] mt-6"
                />
                <PreviewVoucher
                    v-else-if="data.offer.type == 'Voucher Amount Ordered'"
                    :offer="data.offer"
                    class="scale-[120%] mt-3"
                />
                <Coupon v-else :offer="data.offer" :currency_code="currency_code" />    
            </div>

            <!-- RIGHT -->
            <div :class="[
                'grid gap-6',
                hasTrigger ? 'grid-cols-1 md:grid-cols-2' : 'grid-cols-1'
            ]">

                <div class="flex flex-col gap-3">
                    <div class="bg-gray-100 font-semibold text-gray-700 text-center py-1 px-2 rounded">
                        {{ ctrans("Details") }}
                    </div>

                    <div class="flex flex-col gap-2 text-sm">

                        <!-- Campaign -->
                        <div v-if="data.offer.offer_campaign" class="flex justify-between gap-4">
                            <dt class="text-gray-500">
                                {{ ctrans("Campaign") }}
                            </dt>
                            <dd class="font-medium text-right break-words max-w-[60%]">
                                <Link
                                    :href="getOfferCampaignLink(data.offer.offer_campaign)"
                                    class="secondaryLink capitalize"
                                >
                                    {{ data.offer.offer_campaign.name }}
                                </Link>
                            </dd>
                        </div>

                        <!-- Product Category -->
                        <div v-if="data.offer.data_allowance_signature?.product_category" class="flex justify-between gap-4">
                            <dt class="text-gray-500">
                                {{ ctrans("Product category") }}
                            </dt>
                            <dd class="font-medium text-right break-words max-w-[60%]">
                                <Link
                                    :href="getCategoryLink(data.offer.data_allowance_signature.product_category)"
                                    class="secondaryLink"
                                >
                                    {{ data.offer.data_allowance_signature.product_category?.name }}
                                </Link>
                            </dd>
                        </div>

                        <div v-if="data.offer.settings?.can_customer_reuse !== undefined" class="flex justify-between gap-4">
                            <dt class="text-gray-500">
                                {{ ctrans("Customer can reuse") }}
                            </dt>
                            <dd class="font-medium text-right break-words max-w-[60%]">
                                <span v-if="data.offer.settings?.can_customer_reuse" v-tooltip="ctrans('Voucher are allowed to reuse')">
                                    <FontAwesomeIcon icon='fas fa-check-circle' class='text-green-500' fixed-width aria-hidden='true' />
                                </span>
                                <span v-else v-tooltip="ctrans('Voucher not allowed to reuse')">
                                    <FontAwesomeIcon icon='fas fa-times-circle' class='text-red-500' fixed-width aria-hidden='true' />
                                </span>
                            </dd>
                        </div>

                    </div>
                </div>

                <div
                    v-if="hasTrigger"
                    class="flex flex-col gap-3"
                >
                    <div class="bg-amber-100 font-semibold text-amber-700 text-center py-1 px-2 rounded">
                        {{ ctrans("Trigger") }}
                    </div>

                    <div class="flex flex-col gap-2 text-sm">

                        <!-- Item Quantity -->
                        <div v-if="data.offer.trigger_data?.item_quantity !== undefined" class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ ctrans("Item quantity") }}</dt>
                            <dd class="font-medium text-right">
                                {{ data.offer.trigger_data.item_quantity }}
                            </dd>
                        </div>

                        <!-- Item Amount -->
                        <div v-if="data.offer.trigger_data?.item_amount !== undefined" class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ ctrans("Item amount") }}</dt>
                            <dd class="font-medium text-right">
                                {{ locale.currencyFormat(currency_code, data.offer.trigger_data.item_amount) }}
                            </dd>
                        </div>

                        <!-- Minimum Amount -->
                        <div v-if="data.offer.trigger_data?.min_amount !== undefined" class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ ctrans("Minimum amount") }}</dt>
                            <dd class="font-medium text-right">
                                {{ locale.currencyFormat(currency_code, data.offer.trigger_data.min_amount) }}
                            </dd>
                        </div>

                        <!-- Order Number -->
                        <div v-if="data.offer.trigger_data?.order_number !== undefined" class="flex justify-between gap-4">
                            <dt class="text-gray-500">{{ ctrans("Minimum order") }}</dt>
                            <dd class="font-medium text-right">
                                {{ data.offer.trigger_data.order_number }}
                            </dd>
                        </div>

                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Tabs: Customers / Orders -->
    <div class="">
        <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
        <component :is="tabComponent" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
    </div>
</template>


<style scoped>
.offer :deep(.background-primary) {
    background-color: #ff862f;
}

.offer :deep(.text-primary) {
    color: #ff862f;
}
</style>
