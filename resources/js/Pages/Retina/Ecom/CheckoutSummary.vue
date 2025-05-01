<script setup lang="ts">
    
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faClipboard, faDollarSign, faPencil } from "@fal"
import OrderSummary from "@/Components/Summary/OrderSummary.vue"

const props = defineProps<{
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
}>()
console.log('ppp', props.summary)
</script>

<template>
    <div class="py-4 grid grid-cols-3 px-4">
        <div>
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faClipboard" class="" fixed-width aria-hidden="true" />
                Delivery Address
                <FontAwesomeIcon :icon="faPencil" class="text-gray-400 hover:text-gray-600 text-sm" fixed-width aria-hidden="true" />
            </div>
            <div class="pl-6 pr-3" v-html="summary?.customer?.addresses?.delivery?.formatted_address">
                
            </div>
        </div>

        <div>
            <div class="font-semibold">
                <FontAwesomeIcon :icon="faDollarSign" class="" fixed-width aria-hidden="true" />
                Invoice Address
                <FontAwesomeIcon :icon="faPencil" class="text-gray-400 hover:text-gray-600 text-sm" fixed-width aria-hidden="true" />
            </div>
            <div class="pl-6 pr-3" v-html="summary?.customer?.addresses?.billing?.formatted_address">
                
            </div>
        </div>

        <div class="border border-gray-200 p-2 rounded">
            <OrderSummary
                :order_summary="summary.order_summary"
            />
        </div>

        <!-- <div class="text-right">
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Item gross</div>
                <div>{{ summary.gross_amount }}</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Discounts</div>
                <div>-31.83</div>
            </div>
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Item Net</div>
                <div>{{ summary.net_amount }}</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Charges</div>
                <div>{{ summary.charges_amount }}</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>Shipping</div>
                <div>{{ summary.services_amount }}</div>
            </div>
            <div class="py-1 border-t border-gray-400 grid grid-cols-2 ">
                <div>Net</div>
                <div>79.28</div>
            </div>
            <div class="py-1 border-t border-gray-300 grid grid-cols-2 ">
                <div>
                    <div>Tax</div>
                    <div class="text-xs text-gray-400">GB-SR VAT 20%</div>
                </div>
                <div>15.86</div>
            </div>
            <div class="font-semibold py-1 border-t border-b border-gray-400 grid grid-cols-2 ">
                <div class="">Total</div>
                <div>79.28</div>
            </div>
        </div> -->
    </div>
</template>