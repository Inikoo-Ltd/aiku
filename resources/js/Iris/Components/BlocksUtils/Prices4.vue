<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed, watch } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Image as ImageTS } from "@/types/Image"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlusCircle, faQuestionCircle } from "@fal"
import { faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Popover } from "primevue"
import MemberPriceLabel from "@/Iris/Components/Offer/MemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import { getBestOffer as getBestOfferfromComposable } from "@/Composables/useOffers"
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue'
import { faCheck } from "@far"
import { toInteger } from "lodash"

library.add(faPlusCircle, faQuestionCircle)

const layout = inject("layout", retinaLayoutStructure)
const webpage_data = inject("webpage_data", null)
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
    hasInBasket?: {
        transaction_id: number,
        quantity_ordered: number,
        quantity_ordered_new: number
    }
    currency?: {
        code: string
        name: string
    }
    basketButton?: boolean
}>()



const bestOffer = computed(() => {
    return getBestOfferfromComposable(props.product?.product_offers_data)
})

const hasOffer =
    props.product?.product_offers_data?.number_offers > 0 &&
    getBestOfferfromComposable(props.product?.product_offers_data)


const showIntervalOffer = computed(() => {
    return getBestOfferfromComposable(props.product?.product_offers_data)?.type
        === 'Category Quantity Ordered Order Interval' && webpage_data.sub_type != 'family'
})

const quantityOrdered = computed(() =>
    toInteger(props.hasInBasket?.quantity_ordered)
)

const showMemberPrice = computed(() => {
    if (layout?.user?.gr_data?.amnesty) return true
    if (layout?.user?.gr_data?.customer_is_gr) return true

    return bestOffer.value?.category_qty_trigger <= quantityOrdered.value
})

// console.log("showMemberPrice", showMemberPrice.value)

const showDiscount = computed(() => {
    if (props.product?.is_coming_soon) return false
    if (layout?.user?.gr_data?.amnesty) return false
    return !layout?.user?.gr_data?.customer_is_gr
})

// console.log("product_discounted_price", props.product?.discounted_price)

const showLeftBlock = computed(() => {
    return showMemberPrice.value || showDiscount.value
})

const bestOfferClass = computed(() => {
    const type = bestOffer?.value?.type

    if (type === 'Category Ordered' || type === 'Category Amount Ordered' || type == 'Department Ordered' || type == 'Subdepartment Ordered' || type == 'Department Quantity Ordered' || type == 'Subdepartment Quantity Ordered') {
        return 'text-red-700'
    }

    if (type === 'First Order') {
        return 'text-[#2a919e]'
    }

    return 'text-primary'
})

watch(
    () => props.hasInBasket?.quantity_ordered,
    (newValue) => {
        console.log('quantity changed', newValue)
    },
    { immediate: true }
)

const _popoverProfit = ref(null)
</script>

<template>
    <div
        class="font-sans border-gray-200 mt-2 mb-1 px-0 tabular-nums leading-none text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px] xl:text-[13px] 2xl:text-sm">

        <!-- HEADER -->
        <div v-if="product?.rrp_per_unit ?? 0 > 0"
            class="mb-2 flex items-center justify-between border-b border-gray-200 pb-2 text-[11px]">
            <div class="flex items-center gap-2">
                <span class="font-medium text-[#333]">
                    {{ product?.code }}
                </span>

                <div v-if="layout?.iris?.is_logged_in && !product.is_coming_soon"
                    v-tooltip="trans('Available product stocks')" class="flex items-center">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]"
                        :class="product.stock > 0 ? 'text-green-500' : 'text-red-500'" />
                </div>

                <LabelComingSoon v-else-if="product.is_coming_soon" :product="product" />
            </div>

            <div class="flex items-center gap-1 whitespace-nowrap">
                <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show"
                    @mouseleave="_popoverProfit?.hide"
                    class="cursor-pointer opacity-60 hover:opacity-100 flex items-center text-[8px] sm:text-[9px] md:text-[10px]">
                    <FontAwesomeIcon icon="fal fa-plus-circle" fixed-width />
                </span>

                <span class="text-[8px] sm:text-[9px] md:text-[10px]">
                    {{ trans('RRP') }}:
                    <span class="font-medium">
                        {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit) }}
                    </span>
                </span>
            </div>

            <Popover ref="_popoverProfit" class="max-w-[90vw] md:max-w-none sm:min-w-[350px]">
                <ProfitCalculationList :product="product" />
            </Popover>
        </div>


        <!-- PRICE -->
        <div class="flex flex-col gap-y-[0.2rem] 2xl:gap-y-0 ">

            <div class="relative grid items-center gap-x-2 w-full"
                :class="bestOffer?.type == 'Category Quantity Ordered Order Interval'
                    ? 'grid-cols-[1fr_minmax(0,78px)] lg:grid-cols-[1fr_minmax(0,43%)] 2xl:grid-cols-[1fr_minmax(0,43%)]'
                    : 'grid-cols-[1fr_minmax(0,78px)] lg:grid-cols-[1fr_minmax(0,43%)] 2xl:grid-cols-[1fr_minmax(0,43%)]'">

                <div class="font-semibold whitespace-nowrap">
                    <span>{{ trans("Price") }}</span>
                    <span class="text-[8px] sm:text-[9px] font-light">
                        ({{ trans("Excl. Vat") }})
                    </span>
                </div>

                <div class="font-bold text-right min-w-0 relative">

                    <!-- SINGLE -->
                    <template v-if="product.units == 1">
                        <div class="flex justify-end items-center gap-1 min-w-0">

                            <span class="whitespace-nowrap">
                                {{ locale.currencyFormat(currency?.code, product.price) }}/
                            </span>

                            <span class="truncate min-w-0" :title="product.unit">
                                {{ product.unit }}
                            </span>

                        </div>
                    </template>

                    <!-- MULTIPLE -->
                    <template v-else>
                        <div class="flex justify-end items-center gap-1 min-w-0">
                            <span class="whitespace-nowrap">
                                {{ locale.currencyFormat(currency?.code, product.price) }}
                            </span>
                            <span v-if="product.price_per_unit > 0"
                                class="font-normal truncate min-w-0 text-[8px] sm:text-[9px] md:text-[10px]"
                                :title="product.unit">
                                ({{ locale.currencyFormat(currency?.code, product.price_per_unit) }}/{{ product.unit }})
                            </span>

                        </div>
                    </template>

                    <div v-if="!showMemberPrice" class="absolute -right-2 sm:-right-4 top-1/2 -translate-y-1/2">
                        <div class="flex text-xs items-center justify-center rounded-full ">
                            <FontAwesomeIcon :icon="faCheck" />
                        </div>
                    </div>
                </div>
            </div>


            <!-- GR PRICE -->
            <div v-if="product.discounted_price" class="relative grid items-center gap-x-2 w-full"
                :class="bestOffer?.type == 'Category Quantity Ordered Order Interval'
                    ? 'grid-cols-[1fr_minmax(0,78px)] lg:grid-cols-[1fr_minmax(0,43%)] 2xl:grid-cols-[1fr_minmax(0,43%)]'
                    : 'grid-cols-[1fr_minmax(0,78px)] lg:grid-cols-[1fr_minmax(0,43%)] 2xl:grid-cols-[1fr_minmax(0,43%)]'">
                <div v-if="bestOffer?.type == 'Category Quantity Ordered Order Interval'">
                    <MemberPriceLabel :offer="bestOffer" :active="showMemberPrice" />
                </div>
                <div v-else class="offer">
                    <DiscountByType v-if="bestOffer?.type == 'Category Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Category Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'First Order Bonus'"
                        :offers_data="product?.product_offers_data" template="first-order" />
                    <DiscountByType v-if="bestOffer?.type == 'Category Amount Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Department Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Subdepartment Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Department Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Subdepartment Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount" :use_duration="false" />
                    <div v-else class="w-full"></div>
                </div>



                <div v-if="bestOffer" class="font-medium text-right  min-w-0" :class="bestOfferClass">
                    <div class="flex items-baseline justify-end gap-1 min-w-0">
                        <div class="min-w-0 flex-1 truncate">
                            <span v-if="product.units == 1">{{ locale.currencyFormat(currency?.code,
                                product.discounted_price) }}
                                /{{ product.unit }}</span>
                            <span v-else>{{ locale.currencyFormat(currency?.code, product.discounted_price) }}<span
                                    class="text-[8px] sm:text-[9px] md:text-[10px]">({{
                                        locale.currencyFormat(currency?.code,
                                            product.discounted_price_per_unit) }}
                                    /{{ product.unit }})</span></span>
                        </div>

                    </div>
                </div>

                <div v-if="showMemberPrice" class="absolute -right-2 sm:-right-4 top-1/2 -translate-y-1/2">
                    <div class="flex text-xs items-center justify-center rounded-full text-primary">
                        <FontAwesomeIcon :icon="faCheck" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MEMBER -->
    <div v-if="showIntervalOffer && !showMemberPrice"
        class="mt-1 flex flex-col items-start gap-0.5 text-[8px] sm:text-[9px] md:text-[10px] discount">
        <DiscountByType v-if="showDiscount" :offers_data="product?.product_offers_data"
            template="products_triggers_label" />
    </div>
</template>


<style scoped>
.discount :deep(.offer-trigger-label) {
    @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs md:text-xs;
    border-color: var(--theme-color-4);
    color: var(--theme-color-4);
}

.break-safe {
    overflow-wrap: anywhere;
    word-break: break-word;
}

.question-trigger {
    @apply cursor-pointer opacity-60 hover:opacity-100 ml-1 text-xs;
}

.member-popover {
    @apply max-w-[260px];
}

.popover-content {
    width: 300px;
    @apply text-xs;
}

.popover-title {
    @apply font-bold mb-3;
}

.popover-paragraph {
    @apply mb-2 text-justify;
}

.offer :deep(.offer-max-discount) {
    @apply bg-[#A80000] border border-red-900 text-gray-100 flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1 min-w-0 max-w-[6rem] 2xl:max-w-[12rem];
}

.offer :deep(.offer-label) {
    @apply flex items-center gap-1 min-w-0 max-w-full;
}

.offer :deep(.label-text) {
    @apply leading-none truncate min-w-0;
}
</style>