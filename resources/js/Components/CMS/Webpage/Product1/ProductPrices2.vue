<script setup lang="ts">
import { faCube, faLink,  } from "@fal"
import { faHeart as  faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { inject, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { getBestOffer } from "@/Composables/useOffers"

library.add(faCube, faLink, faFilePdf, faFileDownload)

const props = defineProps<{
    product: {
        
    }
}>()

const layout = inject("layout", {})
const currency = layout?.iris?.currency
const locale = useLocaleStore()
const bestOffer = computed(() => {
  return getBestOffer(props.product?.offers_data)
})

</script>

<template>

    <div class="grid grid-cols-5">
        <div class="col-span-3 space-y-1.5 flex items-end w-full">
            <div class="border-b border-gray-300 pb-1 w-full">{{ trans("Price") }} ({{ trans("Excl. Tax") }})</div>

        </div>

        <div class="col-span-2 text-right space-y-1.5">
            <div class="border-b border-gray-300 pb-1">
                <span v-tooltip="trans('Recommended Retail Price')" class="inline-block">{{ trans("RRP") }}

                   <!--  <span class="whitespace-nowrap ml-1 bg-gray-300 border border-gray-400 px-1 py-0.5 rounded-full text-xxs align-middle inline">
                        <FontAwesomeIcon icon="fas fa-circle" class="text-white text-xs" fixed-width aria-hidden="true" />
                        {{ trans("Excl. Tax") }}
                    </span> -->
                </span>
            </div>
        </div>
        
        <div class="col-span-3 space-y-1.5">
            <!-- Section: Price -->
            <div v-if="product?.units < 2">
                <span :class="layout?.retina?.organisation == 'aroma' && bestOffer ? 'line-through' : ''"><span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.price) }}</span>/{{ product?.unit }}</span>
            </div>
            <div v-else>
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.price) }}</span> ({{ locale.currencyFormat(currency?.code, Number((product?.price / product?.units).toFixed(2) || 0).toFixed(2)) }}/{{ product?.unit }})
            </div>

            <!-- Section: Discounted Price -->
            <div v-if="product?.units < 2" :class="bestOffer?.type == 'Category Ordered' ?  'text-by-offer' : 'text-primary'">
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.discounted_price) }}</span>/{{ product?.unit }}
            </div>
            <div v-else :class="bestOffer?.type == 'Category Ordered' ?  'text-by-offer' : 'text-primary'">
                <span class="font-bold">{{ locale.currencyFormat(currency?.code, product?.discounted_price) }}</span> ({{ locale.currencyFormat(currency?.code, product?.discounted_price_per_unit) }}/{{ product?.unit }})
            </div>
        </div>

        <!-- Section: RRP (Excl. Tax) -->
        <div class="col-span-2 text-right space-y-1.5">
            <div class="">
                <span class="">
                    {{ locale.currencyFormat(currency?.code, product.rrp_per_unit || 0) }}
                </span>
                <span class="">/{{ product?.unit }}</span>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.text-primary {
  color: var(--theme-color-4) !important;
}

.text-by-offer {
  @apply text-red-600
}
</style>

