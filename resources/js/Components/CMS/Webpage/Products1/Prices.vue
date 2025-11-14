<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, computed } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"

const layout = inject("layout", retinaLayoutStructure)
const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS,
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
    is_exist_in_all_channel: boolean
    top_seller: number | null
    web_images: {
        main: {
            original: ImageTS,
            gallery: ImageTS
        }
    }
}

const props = defineProps<{
    product: ProductResource
    currency?: {
        code: string
        name: string
    }
}>()



</script>

<template>
    <!-- Price Card -->
    <div v-if="layout?.iris?.is_logged_in"
        class="border-t border-b border-gray-200 p-1 px-0 mb-1 flex flex-col gap-1 text-gray-800 tabular-nums">
        <div class="flex items-center justify-between">
            <span class="font-medium text-xs">
                {{ trans("Retail") }} : {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }} /
                <span class=""> {{ product.unit }}</span>
                <!-- <span class="text-xs ml-2 font-medium">
                    {{ trans("(excl. tax)") }}
                </span> -->
            </span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-xs flex items-center text-gray-500 font-medium">
                {{ trans("Profit") }} : {{ locale.currencyFormat(currency?.code, product?.profit_per_unit || 0) }}
                <span v-tooltip="trans('Profit margin')" class="ml-1">
                    ({{ product?.margin }})
                </span>
            </span>
        </div>
    </div>

    <div v-if="layout?.iris?.is_logged_in" class="p-1 px-0 mb-3 flex flex-col gap-1 text-gray-800 tabular-nums">
        <div v-if="product.units == 1" class="flex justify-between">
            <div>
                {{ trans("Price") }}:
                <span class="font-semibold">
                    {{ locale.currencyFormat(currency?.code, product.price_per_unit | product.price  ) }}
                    <span class="text-xs text-gray-600"> / {{ product.unit }}</span>
                </span>
            </div>
        </div>
        <div v-else>
            <div class="flex justify-between">
                <div>
                   {{ trans("Price") }}:
                    <span class="font-semibold">{{ locale.currencyFormat(currency?.code, product.price_per_unit | product.price  ) }}/{{trans('outer')}}</span>
                </div>
                <div>
                    <span class="text-xs price_per_unit">(<span> {{ locale.currencyFormat(currency?.code, (product.price / product.units).toFixed(2)) }}
                    <span class=" text-gray-600"> / {{ product.unit }}</span></span>)</span>
                </div>
            </div>

        </div>


        <!-- old Prices -->
        <!-- <div v-if="layout?.iris?.is_logged_in"
                class="text-sm flex flex-wrap items-center justify-between gap-x-2 mb-3 tabular-nums">
                <div class="">
                    <div>{{ trans('Price') }}: <span class="font-semibold">{{ locale.currencyFormat(currency?.code,
                        product.price || 0) }}</span></div>
                    <div>
                        <span class="text-sm text-gray-400  font-normal">
                            ({{ locale.currencyFormat(currency?.code, (product.price / product.units).toFixed(2))
                            }}/{{
                                product.unit }})
                        </span>
                    </div>
                </div>

                <div v-if="product?.rrp" class="text-xs mt-1 text-right">
                    <div>
                        RRP: {{ locale.currencyFormat(currency?.code, Number(product.rrp).toFixed(2)) }}
                        <span
                            v-tooltip="trans('Profit margin')" class="text-green-600 font-medium">( {{ product?.margin }} )</span>
                        <div v-if="product?.rrp_per_unit" class="text-gray-400 text-sm font-normal">
                            ({{ locale.currencyFormat(currency?.code, Number(product.rrp_per_unit).toFixed(2)) }} / {{
                                product.unit }})
                        </div>
                    </div>
                </div>
            </div> -->
    </div>
</template>


<style scoped></style>