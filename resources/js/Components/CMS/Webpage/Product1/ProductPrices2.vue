<script setup lang="ts">
import { faCube, faLink,  } from "@fal"
import { faHeart as  faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"
import Discount from "@/Components/Utils/Label/Discount.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faCube, faLink, faFilePdf, faFileDownload)

const props = withDefaults(defineProps<{
    fieldValue: any
    offers_data?: {}
    offer_net_amount_per_quantity?: number
    offer_price_per_unit?: number
}>(), {})

const layout = inject("layout", {})
const currency = layout?.iris?.currency
const locale = useLocaleStore()


const popoverHover = ref(false)
const popoverTimeout = ref()

const hoverPopover = (e: any, open: boolean): void => {
    popoverHover.value = true
    if (!open) {
        e.target.parentNode.click()
    }
}

const closePopover = (close: any): void => {
    popoverHover.value = false
    if (popoverTimeout.value) clearTimeout(popoverTimeout.value)
    popoverTimeout.value = setTimeout(() => {
        if (!popoverHover.value) {
            close()
        }
    }, 100)
}


</script>

<template>

    <Popover v-if="false" v-slot="{ open, close }">
        <PopoverButton style="width: 100%;">
            <div v-if="layout?.iris?.is_logged_in" class="border-y border-gray-200 p-1 mb-2 text-gray-800 tabular-nums">
                <div class="grid grid-cols-6 gap-4 items-start" @mouseover="(e) => hoverPopover(e, open)"
                    @mouseleave="closePopover(close)">
                    <!-- Retail -->
                    <div class="flex flex-col text-left col-span-4">
                        <span class="text-sm font-medium text-gray-600 xmb-1">{{ trans('Retail Price') }} </span>
                        <div class="flex flex-wrap items-baseline gap-1">
                            <span class="text-base font-semibold">
                                {{ locale.currencyFormat(currency?.code, fieldValue.product?.rrp_per_unit || 0) }}
                            </span>
                            <span class="text-sm text-gray-500">/ {{ fieldValue.product.unit }}</span>

                        </div>
                    </div>

                    <!-- Profit -->
                    <div class="flex flex-col items-end text-right col-span-1 col-span-2 justify-end">
                        <div class="">
                            <span class="text-sm font-medium text-gray-600 xmb-1 flex justify-end">
                                <span v-tooltip="trans('Profit margin')" class="mr-3 text-xs ml-1 font-medium text-gray-400">
                                </span> {{trans('Profit') }} ({{ fieldValue.product?.margin }})
                            </span>
                            <div class="flex flex-wrap items-baseline justify-end gap-1">
                                <span class="text-base font-semibold text-gray-700">
                                    {{ locale.currencyFormat(currency?.code, fieldValue.product?.profit_per_unit || 0) }}
                                </span>
                                <span class="text-sm text-gray-500 ">/ {{ fieldValue.product.unit }}</span>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </PopoverButton>

        <PopoverPanel class="absolute z-10 bg-white border border-gray-200 rounded-lg p-4 shadow-lgv md:w-[30rem]">
            <!-- Title -->
            <div class="text-sm font-semibold border-gray-300 pb-2">
                {{ trans('Profit Margin Breakdown') }}
            </div>

            <div class="p-5 bg-gray-50 rounded-md shadow-sm border border-gray-200 text-gray-800 space-y-2">

                <!-- Retail Price -->
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-700">{{ trans('Retail Price') }}</span>
                    <div class="flex items-center gap-4 text-right">
                        <span class="font-semibold min-w-[90px] text-end">
                            {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp) }} / <span v-if="fieldValue.product.units != 1">{{trans('Outer') }}</span><span v-else>{{ fieldValue.product.unit }}</span>
                        </span>
                        <span v-if="fieldValue.product.units != 1"
                            class="text-xs text-gray-500 border-gray-300 pl-3 min-w-[90px] text-start leading-none">
                            {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp_per_unit.toFixed(2)) }} / {{
                                fieldValue.product.unit }}
                        </span>
                    </div>
                </div>

                <!-- Cost Price -->
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-700">{{ trans('Cost Price') }}</span>
                    <div class="flex items-center gap-4 text-right">
                        <span class="font-semibold min-w-[90px] text-end">
                            {{ locale.currencyFormat(currency?.code, fieldValue.product.price) }} / <span v-if="fieldValue.product.units != 1">{{trans('Outer') }}</span><span v-else>{{ fieldValue.product.unit }}</span>
                        </span>
                        <span v-if="fieldValue.product.units != 1"
                            class="text-xs text-gray-500 border-gray-300 pl-3 min-w-[90px] text-start leading-none">
                            {{ locale.currencyFormat(currency?.code, (fieldValue.product.price /
                                fieldValue.product.units).toFixed(2)) }} / {{ fieldValue.product.unit }}
                        </span>
                    </div>
                </div>

                <!-- Profit -->
                <div class="flex justify-between items-center text-sm border-t border-gray-300 pt-2 mt-2">
                    <span class="font-semibold text-gray-800">{{ trans('Profit') }}</span>
                    <div class="flex items-center gap-4 text-right">
                        <span class="font-bold  min-w-[90px] text-end">
                            {{ locale.currencyFormat(currency?.code, fieldValue.product.profit) }} / <span v-if="fieldValue.product.units != 1">{{trans('Outer') }}</span><span v-else>{{ fieldValue.product.unit }}</span>
                        </span>
                        <span v-if="fieldValue.product.units != 1"
                            class="text-xs  border-gray-300 pl-3 min-w-[90px] text-start leading-none">
                            {{ locale.currencyFormat(currency?.code, (fieldValue.product.rrp_per_unit - (fieldValue.product.price / fieldValue.product.units)).toFixed(2)) }} / {{fieldValue.product.unit }}
                        </span>
                    </div>
                </div>
            </div>


            <!-- Notes -->
            <div class="text-xs text-gray-500 border-dashed border-gray-300 pt-2 mt-1 italic">
                {{ trans('All our prices exclude tax, all RRP (Retail) include tax.') }}
            </div>
        </PopoverPanel>
    </Popover>

    <!-- New Section -->
    <div class="">
        <div class="grid grid-cols-5">
            <div class="col-span-3">
                <div class="border-b border-gray-300">Price (Excl. Tax)</div>
                <!-- Section: Price -->
                <div v-if="fieldValue.product.units < 2">
                    <span class="font-bold">{{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}</span>/{{ fieldValue.product.unit }}
                </div>
                <div v-else>
                    <span class="font-bold">{{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}</span> ({{ locale.currencyFormat(currency?.code, Number((fieldValue.product.price / fieldValue.product.units).toFixed(2) || 0).toFixed(2)) }}/{{ fieldValue.product.unit }})
                </div>

                <!-- Section: Discounted Price -->
                <div v-if="fieldValue.product.units < 2" class="text-orange-500">
                    <span class="font-bold">{{ locale.currencyFormat(currency?.code, fieldValue.product.discounted_price) }}</span>/{{ fieldValue.product.unit }}
                </div>
                <div v-else class="text-orange-500">
                    <span class="font-bold">{{ locale.currencyFormat(currency?.code, fieldValue.product.discounted_price) }}</span> ({{ locale.currencyFormat(currency?.code, fieldValue.product.discounted_price_per_unit) }}/{{ fieldValue.product.unit }})
                </div>
            </div>

            <div class="col-span-2 text-right">
                <div class="border-b border-gray-300">
                    RRP
                    <span class="bg-gray-300 border border-gray-400 px-1 py-0.5 rounded-full text-xxs align-middle">
                        <FontAwesomeIcon icon="fas fa-circle" class="text-white text-xs" fixed-width aria-hidden="true" />
                        Excl. Tax
                    </span>
                </div>
                <div>
                     <span class="">
                        {{ locale.currencyFormat(currency?.code, fieldValue.product?.rrp_per_unit || 0) }}
                    </span>
                    <span class="">/{{ fieldValue.product.unit }}</span>
                </div>
            </div>
        </div>
    </div>


    <!-- <div v-if="layout?.iris?.is_logged_in" class="p-1 px-0 mb-3 flex flex-col gap-1 text-gray-800 tabular-nums">

        <Discount v-if="offers_data && Object.keys(offers_data).length" :offers_data="offers_data" class="my-3" />

        <div v-if="fieldValue.product.units == 1" class="flex justify-between">
            <div>
                {{ trans("Price") }}:
                <span v-if="offer_price_per_unit &&  Object.keys(offers_data).length" class="font-semibold text-green-600 text-xl">
                    <span class="line-through opacity-60 mr-1 text-gray-600 text-xs">{{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}</span> 
                    <span class="">{{ locale.currencyFormat(currency?.code, offer_price_per_unit || 0) }}</span> 
                    <span class="text-xs text-gray-600"> / {{ fieldValue.product.unit }}</span>
                </span>

                <span v-else class="font-semibold text-green-600 text-xl">
                    {{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}
                    <span class="text-xs text-gray-600"> / {{ fieldValue.product.unit }}</span>
                </span>
            </div>
        </div>

        <div v-else>
            <div class="flex justify-between flex-wrap">
                <div v-tooltip="trans('Wholesale Price')" class="flex items-center gap-1">
                    {{ trans("Price") }}:
                    <span v-if="offer_net_amount_per_quantity && Object.keys(offers_data).length">
                        <span class="opacity-60 line-through mr-2 text-xs">
                            {{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}
                        </span>
                        <span class="font-semibold text-green-600 text-xl">
                            {{ locale.currencyFormat(currency?.code, offer_net_amount_per_quantity) }}
                        </span>
                    </span>
                    <span v-else class="font-semibold text-green-600 text-xl">
                        {{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}
                    </span>
                    <span class="text-sm text-gray-500">/ {{trans('Outer') }}</span>
                    
                </div>
                <div>
                    <span class="text-xs price_per_unit">
                        <span v-if="offer_price_per_unit && Object.keys(offers_data).length">
                            <span class="line-through opacity-60 mr-2 text-xs">
                                {{ locale.currencyFormat(currency?.code, Number((fieldValue.product.price / fieldValue.product.units).toFixed(2) || 0).toFixed(2)) }}
                            </span>
                            <span class="text-green-600 text-xl font-semibold">
                                {{ locale.currencyFormat(currency?.code, offer_price_per_unit || 0) }}
                            </span>
                        </span>
                        <span v-else class="text-green-600 text-xl font-semibold">
                            {{ locale.currencyFormat(currency?.code, Number((fieldValue.product.price / fieldValue.product.units).toFixed(2) || 0).toFixed(2)) }}
                        </span>
                        <span class="text-gray-600 text-sm"> / {{ fieldValue.product.unit }}</span>
                    </span>
                </div>
            </div>
        </div>
    </div> -->
</template>
