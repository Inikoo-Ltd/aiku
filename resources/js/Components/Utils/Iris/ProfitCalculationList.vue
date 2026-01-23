<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { inject } from 'vue'

const props = defineProps<{
    product: {

    }
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="">                            
        <!-- Rows -->
        <div class="space-y-2 bg-gray-100 pr-4 border-b border-gray-500 pb-2">
            <!-- Section: Title Profit Breakdown -->
            <div class="flex items-center justify-between mb-2">
                <div class="font-semibold text-[13px] text-slate-800">Profit Breakdown:</div>
            </div>

            <!-- Retail -->
            <div class="flex items-center justify-between pl-4 pr-24">
                <div class="text-slate-700">Retail:</div>
                <div class="font-semibold text-slate-900">
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp) }}
                    <span class="font-normal text-slate-500">Outer</span>
                    <template v-if="product.units > 1">
                        <span class="ml-3">{{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp_per_unit) }}</span>
                        <span class="font-normal text-slate-500">/{{ product.unit }}</span>
                    </template>
                </div>
            </div>

            <!-- Cost -->
            <div class="flex items-center justify-between pl-4 pr-24">
                <div class="text-slate-700">Cost Price:</div>
                <div class="font-semibold text-slate-900">
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, product.price) }}
                    <span class="font-normal text-slate-500">Outer</span>
                    <template v-if="product.units > 1">
                        <span class="ml-3">{{ locale.currencyFormat(layout?.iris?.currency?.code, Number((product.price / product.units).toFixed(2) || 0).toFixed(2)) }}</span>
                        <span class="font-normal text-slate-500">/{{ product.unit }}</span>
                    </template>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-300 my-1 mr-24"></div>

            <!-- Profit 50% -->
            <div class="flex items-center justify-between pl-4">
                <div class="text-slate-700">
                    Profit <span class="text-emerald-600 font-semibold">({{ product.margin }})</span>:
                </div>
                <div class="flex font-semibold text-emerald-600">
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp - product.price) }}
                    <span class="font-normal text-slate-500 ml-1">Outer</span>
                    <template v-if="product.units > 1">
                        <span class="ml-3">{{ locale.currencyFormat(layout?.iris?.currency?.code, product.rrp_per_unit - product.price_per_unit) }}</span>
                        <span class="font-normal text-slate-500">/{{ product.unit }}</span>
                    </template>

                    <div class="w-24">
                        <div
                            class="w-fit ml-auto text-xs px-2 py-[2px] rounded-full bg-gray-200 border border-slate-300 text-slate-600 hover:bg-slate-50"
                        >
                            Excl. Vat
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit -->
        <div class="flex items-center justify-between pl-4 mt-2">
            <div class="text-slate-700">
                Profit <span class="text-orange-500 font-semibold">({{ product.discounted_margin }})</span>:
            </div>

            <div class="flex items-center gap-2x">
                <div class="font-semibold text-orange-500 mr-4">
                    {{ locale.currencyFormat(layout?.iris?.currency?.code, product.discounted_price) }}
                    <span class="font-normal text-slate-500">Outer</span>
                    <template v-if="product.units > 1">
                        <span class="ml-3">{{ locale.currencyFormat(layout?.iris?.currency?.code, product.discounted_price_per_unit) }}</span>
                        <span class="font-normal text-slate-500">/{{ product.unit }}</span>
                    </template>
                </div>

                <div class="w-24 flex gap-x-2">
                    <img src="/assets/promo/gr.png" alt="Gold Reward Logo" class="h-7" />
                    <span class="text-xs text-orange-500 flex items-center gap-1">
                        Members <br />& Volume
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>