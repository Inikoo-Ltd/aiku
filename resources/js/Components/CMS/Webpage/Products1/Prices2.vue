<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlusCircle, faQuestionCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Popover } from "primevue"
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabel.vue"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"

library.add(faPlusCircle, faQuestionCircle)

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

    discounted_price: number
    discounted_price_per_unit: number
    discounted_profit: number
    discounted_profit_per_unit: number
    discounted_margin: number

    product_offers_data: {
        number_offers: 1
        offers: {
            [key: string]: {
                state: string
                type: string
                label: string
                allowances: {
                    class: string
                    type: string
                    label: string
                    percentage_off: string
                }[]
                triggers_labels: string[]
                max_percentage_discount: string
            }
        }
        best_percentage_off: {
            percentage_off: string
            offer_id: number
        }
    }
}

const props = defineProps<{
    product: ProductResource
    currency?: {
        code: string
        name: string
    }
    basketButton?: boolean
}>()

const getBestOffer = (offerId: string) => {
    if (!offerId) {
        return
    }

    return Object.values(props.product?.product_offers_data?.offers || []).find(e => e.id == offerId)
}

const hasOffer =
    props.product?.product_offers_data?.number_offers > 0 &&
    getBestOffer(props.product?.product_offers_data?.best_percentage_off?.offer_id)


const showIntervalOffer = computed(() => {
    return getBestOffer(props.product?.product_offers_data?.best_percentage_off?.offer_id)?.type
        === 'Category Quantity Ordered Order Interval'
})

const showMemberPrice = computed(() => {
    return layout?.user?.gr_data?.customer_is_gr
})

const showDiscount = computed(() => {
    return props.basketButton
        && !props.product.is_coming_soon
        && !layout?.user?.gr_data?.customer_is_gr
})

const showLeftBlock = computed(() => {
    return showMemberPrice.value || showDiscount.value
})


const _popoverProfit = ref(null)
</script>

<template>
    <div class="font-sans border-t xborder-b border-gray-200 mt-1 p-1 px-0 mb-1 gap-1 tabular-nums text-sm break-safe">
        <div>
            <div class="flex justify-between">
                <div>
                    <div class="text-xs mb-1">
                        {{ trans("Price") }}
                        <span class="text-gray-500 text-xxs">({{ trans("Excl. Vat") }})</span>
                    </div>

                    <div v-if="product.units == 1"
                        class="font-bold text-sm leading-4 inline-flex flex-wrap items-baseline">
                        <span class="whitespace-nowrap">
                            {{ locale.currencyFormat(currency?.code, product.price) }}/
                        </span>

                        <span class="font-normal whitespace-nowrap">
                            {{ product.unit }}
                        </span>
                    </div>


                    <div v-else class="font-bold text-sm leading-4 break-words">
                        {{ locale.currencyFormat(currency?.code, product.price) }}

                        <span v-if="product.price_per_unit > 0" class="text-xs">
                            <!--  ({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }} -->
                            ({{ product.price_per_unit }}/
                            <span class="font-normal">{{ product.unit }}</span>)
                        </span>
                    </div>
                </div>

                <!-- <div v-if="product?.rrp_per_unit > 0"
                    v-tooltip="trans('Recommended retail price') + ' (' + trans('Excl. Vat') + ')'"
                    class="flex flex-col text-right break-safe">
                    <div class="text-xs">{{ trans("RRP") }}:</div>
                    <div class="font-bold text-xs break-safe">
                        {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }}
                        <span class="font-normal">{{ product.unit}}</span>
                    </div>
                </div> -->
            </div>

            <!-- Price: Gold Member -->
            <div v-if="product.discounted_price" class="text-primary font-bold text-sm break-safe mt-2">
                <span v-if="product.units == 1">
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }}/
                    <span class="font-normal">{{ product.unit }}</span>
                </span>
                <span v-else>
                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }}
                    <span class="text-xs">
                        <!-- ({{ locale.currencyFormat(currency?.code, product.discounted_price_per_unit) }} -->
                        ({{ product.discounted_price_per_unit }}/
                        <span class="font-normal">{{ product.unit }}</span>)
                    </span>
                </span>
            </div>

            <div v-else class="h-5"></div>

            <!-- Section: Profit + Offer -->

            <div class="mt-2" :class="hasOffer ? 'grid grid-cols-2 gap-x-2' : 'flex flex-col'">
                <!-- LEFT: only if offer exists -->

                <div :class="showLeftBlock ? 'col-span-2' : 'col-span-1'">
                    <NonMemberPriceLabel :product
                        v-if="!layout?.user?.gr_data?.customer_is_gr && product.product_offers_data?.number_offers > 0" />
                </div>


                <div v-if="showLeftBlock && showIntervalOffer" class="flex flex-col w-fit break-safe discount">
                    <MemberPriceLabel v-if="showMemberPrice" :offer="bestOffer" />

                    <DiscountByType v-if="showDiscount" :offers_data="product?.product_offers_data"
                        template="products_triggers_label" />
                </div>


                <!-- RIGHT -->
                <div :class="[
                    hasOffer
                        ? 'flex flex-col justify-end text-right text-xs'
                        : 'text-xs'
                ]">

                    <div v-if="product?.rrp_per_unit > 0"
                        v-tooltip="trans('Recommended retail price') + ' (' + trans('Excl. Vat') + ')'"
                        class="flex flex-col break-safe mb-2">
                        <div class="text-xs">{{ trans("RRP") }}:</div>
                        <div class="font-bold text-xs break-safe">
                            {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit || 0) }}
                        </div>
                    </div>

                    <div class="whitespace-nowrap break-safe">
                        <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show"
                            @mouseleave="_popoverProfit?.hide" class="ml-1 cursor-pointer opacity-60 hover:opacity-100">
                            <FontAwesomeIcon icon="fal fa-plus-circle" fixed-width />
                        </span>
                        {{ trans("Profit") }}:
                    </div>

                    <div class="font-bold text-green-700 text-xs break-safe">
                        ({{ layout?.user?.gr_data?.customer_is_gr ? product?.discounted_margin : product?.margin }})
                    </div>

                    <Popover ref="_popoverProfit" :style="{ width: '385px' }" class="py-1 px-2 text-xxs">
                        <ProfitCalculationList :product="product" />
                    </Popover>
                </div>
            </div>
        </div>
    </div>
</template>


<style scoped>
.text-primary {
    color: var(--theme-color-4) !important;
}

.discount :deep(.offer-trigger-label) {
    @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs md:text-xs;
    border-color: var(--theme-color-4) !important;
    color: var(--theme-color-4) !important;
}


.break-safe {
    overflow-wrap: anywhere;
    word-break: break-word;
}
</style>