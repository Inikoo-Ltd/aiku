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
    product: {
        
    }
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
    <div class="grid grid-cols-5">
        <div class="col-span-3 space-y-1.5">
            <div class="border-b border-gray-300 pb-1">{{ trans("Price") }} ({{ trans("Excl. Tax") }})</div>

            <!-- Section: Price -->
            <div v-if="product?.units < 2">
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.price) }}</span>/{{ product?.unit }}
            </div>
            <div v-else>
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.price) }}</span> ({{ locale.currencyFormat(currency?.code, Number((product?.price / product?.units).toFixed(2) || 0).toFixed(2)) }}/{{ product?.unit }})
            </div>

            <!-- Section: Discounted Price -->
            <div v-if="product?.units < 2" class="text-orange-500">
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.discounted_price) }}</span>/{{ product?.unit }}
            </div>
            <div v-else class="text-orange-500">
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.discounted_price) }}</span> ({{ locale.currencyFormat(currency?.code, product?.discounted_price_per_unit) }}/{{ product?.unit }})
            </div>
        </div>

        <!-- Section: RRP (Excl. Tax) -->
        <div class="col-span-2 text-right space-y-1.5">
            <div class="border-b border-gray-300 pb-1">
                <span v-tooltip="trans('Recommended Retail Price')" class="inline-block">{{ trans("RRP") }}</span>
                <span class="ml-1 bg-gray-300 border border-gray-400 px-1 py-0.5 rounded-full text-xxs align-middle">
                    <FontAwesomeIcon icon="fas fa-circle" class="text-white text-xs" fixed-width aria-hidden="true" />
                    {{ trans("Excl. Tax") }}
                </span>
            </div>

            <div>
                
                <span class="">
                    {{ locale.currencyFormat(currency?.code, product.rrp_per_unit || 0) }}
                </span>
                <span class="">/{{ product?.unit }}</span>
            </div>
        </div>
    </div>
</template>
