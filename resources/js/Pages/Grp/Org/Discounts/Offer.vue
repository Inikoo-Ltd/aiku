<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import Coupon from '@/Components/Utils/Coupon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject } from 'vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    currency_code: string
    data: {
        trigger_data: {
        }
        data_allowance_signature: {
            percentage_off: number|null
            product_category: {} | null
        }
    }
}>()

const locale = inject('locale', aikuLocaleStructure)



</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <div class="p-5 border-b border-gray-300 mb-4 flex flex-col items-center">
        <div class="">
            Type: <span class="font-bold">{{ data.type }}</span>
        </div>
        
        <Coupon :offer="data" :currency_code="currency_code" />
    </div>

    
    
    <div class="flex justify-between gap-8 mx-8">
        <!-- Trigger -->
        <div class="max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-gray-100 font-bold border-b border-gray-200 text-gray-700 text-center mb-1 py-1">
                Details
            </div>

            <!-- Trigger: Item Quantity -->
            <div v-if="data.data_allowance_signature.product_category" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>Affected product category</span>
                        <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                    <!-- <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                        Minimum of quantity the item ordered
                    </span> -->
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ data.data_allowance_signature.product_category?.name }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Trigger -->
        <div class="max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-amber-100 font-bold border-b border-gray-200 text-amber-700 text-center mb-1 py-1">
                Trigger
            </div>
            <!-- Trigger: Item Quantity -->
            <div v-if="(typeof data.trigger_data.item_quantity !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>Item quantity</span>
                        <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                    <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                        Minimum of quantity the item ordered
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ data.trigger_data.item_quantity }}
                    </dd>
                </div>
            </div>

            <!-- Trigger: Item Quantity -->
            <div v-if="(typeof data.trigger_data.min_amount !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>Order amount</span>
                        <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                    <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                        Minimum of amount of the order
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ locale.currencyFormat(currency_code, data.trigger_data.min_amount) }}
                    </dd>
                </div>
            </div>

            <!-- Trigger: Item Quantity -->
            <div v-if="(typeof data.trigger_data.order_number !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>Minimum order</span>
                        <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                    <span v-tooltip="`The order count required to activate the discount (e.g., '7' is mean their 7th order)`" class="text-xs text-gray-400 truncate">
                        The order count required to activate the discount (e.g., 7 = 7th order)
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ data.trigger_data.order_number }}
                    </dd>
                </div>
            </div>
        </div>

        <!-- Discount -->
        <div class="ml-4 max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
            <div class="bg-green-100 font-bold border-b border-gray-200 text-green-700 text-center mb-1 py-1">
                Discounts
            </div>
            <div v-if="(typeof props.data.data_allowance_signature?.percentage_off !== 'undefined')" class="grid grid-cols-7 gap-x-4 items-center justify-between">
                <dt class="col-span-4 flex flex-col">
                    <div class="flex items-center leading-none">
                        <span>Discount percentage</span>
                        <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'fieldSummary.information_icon'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                    </div>
                    <span v-tooltip="'fieldSummary.information'" class="text-xs text-gray-400 truncate">
                        The discount of the product price
                    </span>
                </dt>
        
                <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                    <dd class="">
                        {{ props.data.data_allowance_signature?.percentage_off * 100 }}%
                    </dd>
                </div>
            </div>
        </div>
    </div>

        <!-- <pre>{{ data }}</pre> -->

</template>