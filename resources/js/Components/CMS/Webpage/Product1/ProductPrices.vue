<script setup lang="ts">
import { faCube, faLink,  } from "@fal"
import { faHeart as  faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ref, inject, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { Popover, PopoverButton, PopoverPanel } from "@headlessui/vue"

library.add(faCube, faLink, faFilePdf, faFileDownload)

const props = withDefaults(defineProps<{
    fieldValue: any
}>(), {})

const layout = inject("layout", {})
const currency = layout?.iris?.currency
const locale = useLocaleStore()


const profitMargin = computed(() => {
    const price = props.fieldValue?.product?.price
    const rrp = props.fieldValue?.product?.rrp
    if (!price || !rrp || rrp === 0) return 0
    return Math.round(((rrp - price) / rrp) * 100)
})


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
    <Popover v-slot="{ open, close }">
        <PopoverButton style="width: 100%;">
            <div v-if="layout?.iris?.is_logged_in" class="border-y border-gray-200 p-1 mb-2 text-gray-800 tabular-nums">
                <div class="grid grid-cols-2 gap-4 items-start" @mouseover="(e) => hoverPopover(e, open)"
                    @mouseleave="closePopover(close)">
                    <!-- Retail -->
                    <div class="flex flex-col text-left">
                        <span class="text-sm font-medium text-gray-600 mb-1">{{ trans('Retail') }}   <span class="text-xs ml-1 font-medium text-gray-400">({{ trans('excluding tax') }})</span></span>
                        <div class="flex flex-wrap items-baseline gap-1">
                            <span class="text-base font-semibold">
                                {{ locale.currencyFormat(currency?.code, fieldValue.product?.rrp_per_unit || 0) }}
                            </span>
                            <span class="text-sm text-gray-500">/ {{ fieldValue.product.unit }}</span>

                        </div>
                    </div>

                    <!-- Profit -->
                    <div class="flex flex-col items-end text-right">
                        <div>
                            <span class="text-sm font-medium text-gray-600 mb-1 flex justify-start">
                                <span v-tooltip="trans('Profit margin')" class="mr-3 text-sm font-medium text-gray-600">
                                   {{trans('Margin') }}: ({{ profitMargin }}%)
                                </span> {{trans('Profit') }}</span>
                            <div class="flex flex-wrap items-baseline justify-end gap-1">
                                <span class="text-base font-semibold text-gray-700">
                                    {{ locale.currencyFormat(currency?.code, fieldValue.product?.profit_per_unit ||
                                        0) }}  <span class="text-sm text-gray-500">/ {{ fieldValue.product.unit }}</span>
                                </span>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </PopoverButton>

        <PopoverPanel class="absolute z-10 bg-white border border-gray-200 rounded-lg p-4 shadow-lgv md:w-[30rem]">
            <!-- Title -->
            <div class="text-sm font-semibold text-gray-900 border-gray-300 pb-2">
                {{ trans('Profit Margin Breakdown') }}
            </div>

            <div class="p-5 bg-gray-50 rounded-md shadow-sm border border-gray-200 text-gray-800 space-y-2">

                <!-- Retail Price -->
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-700">{{ trans('Retail Price') }}</span>
                    <div class="flex items-center gap-4 text-right">
                        <span class="font-semibold text-gray-900 min-w-[90px] text-end">
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
                        <span class="font-semibold text-gray-900 min-w-[90px] text-end">
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
                {{ trans('All prices exclude tax.') }}
            </div>
        </PopoverPanel>
    </Popover>
    <div v-if="layout?.iris?.is_logged_in" class="p-1 px-0 mb-3 flex flex-col gap-1 text-gray-800 tabular-nums">
        <div v-if="fieldValue.product.units === 1" class="flex justify-between">
            <div>
                {{ trans("Price") }}:
                <span class="font-semibold">
                    {{ locale.currencyFormat(currency?.code, fieldValue.product.price) }}
                    <span class="text-xs text-gray-600"> / {{ fieldValue.product.unit }}</span>
                </span>
            </div>
        </div>
        <div v-else>
            <div class="flex justify-between">
                <div>
                    {{ trans("Price") }}:
                    <span class="font-semibold">{{ locale.currencyFormat(currency?.code,
                        fieldValue.product.price) }}</span>
                </div>
                <div>
                    <span class="text-xs price_per_unit">
                        (<span>
                            {{ locale.currencyFormat(currency?.code, Number((fieldValue.product.price
                                / fieldValue.product.units).toFixed(2) || 0).toFixed(2)) }}
                            <span class="text-gray-600"> / {{ fieldValue.product.unit }}</span>
                        </span>)
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>