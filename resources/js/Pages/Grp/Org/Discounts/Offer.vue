<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import Coupon from '@/Components/Utils/Coupon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject } from 'vue'
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'
import FamilyOfferLabelDiscount from '@/Components/Utils/Label/DiscountTemplate/CategoryQuantityOrderedOrderInterval/FamilyOfferLabelDiscount.vue'
import BasicDiscount from '@/Components/Utils/Label/DiscountTemplate/BasicDiscount.vue'
import { OfferResource } from '@/types/Catalogue/Offers'
import { useFormatTime } from '@/Composables/useFormatTime'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currency_code: string
    data: {
        offer: OfferResource
    }
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

const getOfferCampaignLink = (offerCampaign: {}) => {
    if (offerCampaign) {
        return route('grp.org.shops.show.discounts.campaigns.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            offerCampaign: offerCampaign.slug,
        })
    }
    return '#'
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <div class="whitespace-nowrap">
                <Link v-if="url_master?.name" :href="route(url_master.name,url_master.parameters)" v-tooltip="trans('Go to Master Family section Offer GR/Vol')" class="mr-1 opacity-70 hover:opacity-100">
                    <FontAwesomeIcon
                        icon="fab fa-octopus-deploy"
                        color="#4B0082"
                        fixed-width
                    />
                </Link>
            </div>
        </template>
    </PageHeading>
    <!-- <pre>{{ data.offer }}</pre> -->

    <!-- Section: Preview label -->
    <div class="p-5 border-b border-gray-300 mb-4 flex flex-col items-center offer">
        <div class="mb-2">
            {{ ctrans("Type") }}: <span class="font-bold">{{ data.offer.type }}</span>
        </div>
        <!-- {{ data.offer.type }} -->
        <FamilyOfferLabelDiscount v-if="data.offer.type == 'Category Quantity Ordered Order Interval'" :offer="data.offer" />
        <BasicDiscount v-else-if="data.offer.type == 'GR Amnesty'"
            :offers_data="{
                o: {
                    p: (data.offer.max_percentage_discount || 0 )*100 + '%',
                    l: data.offer.label,
                    st: 'a'
                }
            }"
            class="!text-xl"
        />
        <BasicDiscount v-else-if="data.offer.type == 'Category Ordered'"
            :offers_data="{
                o: {
                    p: (data.offer.max_percentage_discount || 0 )*100 + '%',
                    l: data.offer.label
                }
            }"
            class="!text-3xl"
        />
        <Coupon v-else :offer="data.offer" :currency_code="currency_code" />
    </div>
    
    <div class="flex justify-between gap-8 mx-8">
        <!-- Section: Details -->
        <div class="max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-gray-100 font-bold border-b border-gray-200 text-gray-700 text-center mb-1 py-1 px-2">
                {{ ctrans("Details") }}
            </div>

            <!-- Detail: Created -->
            <!-- <div v-if="data.offer.created_at" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Created at") }}</span>
                    </div>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden text-right">
                    {{ useFormatTime(data.offer.created_at, { formatTime: 'hm' }) }}
                </div>
            </div> -->

            <!-- Detail: Campaign -->
            <div v-if="data.offer.offer_campaign" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Campaign") }}</span>
                    </div>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden text-right">
                    <Link :href="getOfferCampaignLink(data.offer.offer_campaign)" class="secondaryLink">
                        {{ data.offer.offer_campaign.name }}
                    </Link>
                </div>
            </div>

            <!-- Detail: Duration -->
            <div v-if="data.offer.start_at || data.offer.end_at" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Duration") }}</span>
                    </div>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden text-right">
                    {{ useFormatTime(data.offer.start_at, { formatTime: 'hm' }) }} - {{ data.offer.end_at ? useFormatTime(data.offer.end_at, { formatTime: 'hm' }) : ctrans('Permanent') }}
                </div>
            </div>

            <!-- Detail: Product category -->
            <div v-if="data.offer.data_allowance_signature?.product_category" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Product category") }}</span>
                    </div>
                    <!-- <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                        Minimum of quantity the item ordered
                    </span> -->
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden text-right">
                    <Link :href="getCategoryLink(data.offer.data_allowance_signature.product_category)" class="secondaryLink">
                        {{ data.offer.data_allowance_signature.product_category?.name }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Section: Trigger -->
        <div v-if="
            (typeof data.offer.trigger_data?.item_quantity !== 'undefined')
            || (typeof data.offer.trigger_data?.min_amount !== 'undefined')
            || (typeof data.offer.trigger_data?.order_number !== 'undefined')
            || (typeof data.offer.trigger_data?.item_amount !== 'undefined')
        "
            class="max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-amber-100 font-bold border-b border-gray-200 text-amber-700 text-center mb-1 py-1 px-2">
                {{ ctrans("Trigger") }}
            </div>
            
            <!-- Trigger: Item Quantity -->
            <div v-if="(typeof data.offer.trigger_data?.item_quantity !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Item quantity") }}</span>
                    </div>
                    <span v-tooltip="ctrans('Minimum of quantity the item ordered')" class="text-xs text-gray-400 truncate">
                        {{ ctrans("Minimum of quantity the item ordered") }}
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ data.offer.trigger_data.item_quantity }}
                    </dd>
                </div>
            </div>
            
            <!-- Trigger: item amount -->
            <div v-if="(typeof data.offer.trigger_data?.item_amount !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Item amount") }}</span>
                    </div>
                    <span v-tooltip="ctrans('Minimum of amount the item ordered')" class="text-xs text-gray-400 truncate">
                        {{ ctrans("Minimum of amount the item ordered") }}
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ locale.currencyFormat(currency_code, data.offer.trigger_data.item_amount)}}
                    </dd>
                </div>
            </div>
            
            <!-- Trigger: Min amount -->
            <div v-if="(typeof data.offer.trigger_data?.min_amount !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Minimum amount") }}</span>
                    </div>
                    <span v-tooltip="ctrans('Minimum of amount the item ordered')" class="text-xs text-gray-400 truncate">
                        {{ ctrans("Minimum of amount the item ordered") }}
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ locale.currencyFormat(currency_code, data.offer.trigger_data.min_amount) }}
                    </dd>
                </div>
            </div>

            <!-- Trigger: Order number -->
            <div v-if="(typeof data.offer.trigger_data?.order_number !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Minimum order") }}</span>
                    </div>
                    <span v-tooltip="ctrans('The order count required to activate the discount (e.g., 7 = 7th order)')" class="text-xs text-gray-400 truncate">
                        {{ ctrans("The order count required to activate the discount (e.g., 7 = 7th order)") }}
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ data.offer.trigger_data.order_number }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Section: Discount -->
        <div class="ml-4 max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-green-100 font-bold border-b border-gray-200 text-green-700 text-center mb-1 py-1 px-2">
                {{ ctrans("Discounts") }}
            </div>
            <div v-if="(typeof props.data.offer.data_allowance_signature?.percentage_off !== 'undefined')" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>{{ ctrans("Discount percentage") }}</span>
                    </div>
                    <span v-tooltip="ctrans('The discount of the product price')" class="text-xs text-gray-400 truncate">
                        {{ ctrans("The discount of the product price") }}
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ props.data.offer.data_allowance_signature?.percentage_off * 100 }}%
                    </dd>
                </div>
            </div>
        </div>
    </div>

</template>


<style scoped>
.offer :deep(.background-primary) {
    background-color: #ff862f;
}

.offer :deep(.text-primary) {
    color:#ff862f;
}
</style>
