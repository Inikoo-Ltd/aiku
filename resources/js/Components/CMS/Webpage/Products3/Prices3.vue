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
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabelDesain2.vue"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import { getBestOffer as getBestOfferfromComposable } from "@/Composables/useOffers"

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


const bestOffer = computed(() => {
    return getBestOfferfromComposable(props.product?.product_offers_data)
})

const hasOffer =
    props.product?.product_offers_data?.number_offers > 0 &&
    getBestOfferfromComposable(props.product?.product_offers_data)


const showIntervalOffer = computed(() => {
    return getBestOfferfromComposable(props.product?.product_offers_data)?.type
        === 'Category Quantity Ordered Order Interval'
})

const showMemberPrice = computed(() => {
    return layout?.user?.gr_data?.amnesty || layout?.user?.gr_data?.customer_is_gr
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

const _popoverQuestionCircle = ref(null)
const _popoverProfit = ref(null)
</script>

<template>
    <div class="font-sans border-gray-200 mt-2 mb-1 px-0 tabular-nums leading-none
            text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px] xl:text-[13px] 2xl:text-sm">

        <!-- HEADER -->
        <div class="border-b pb-2 mb-2 flex items-center justify-between gap-1 whitespace-nowrap text-[9px] sm:text-[10px] md:text-[11px]"
            v-if="product?.rrp_per_unit ?? 0 > 0"
        >

            <div class="flex items-baseline gap-1 leading-none" >
                <span class="text-xs">
                    {{ trans("RRP") }}:
                </span>
                <span class="text-xs font-medium relative top-[1px]">
                    {{ locale.currencyFormat(currency?.code, product?.rrp_per_unit) }}
                </span>
            </div>

            <div class="flex items-center gap-1 justify-end whitespace-nowrap">
                <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show"
                    @mouseleave="_popoverProfit?.hide"
                    class="cursor-pointer opacity-60 hover:opacity-100 text-[8px] sm:text-[9px] md:text-[10px]">
                    <FontAwesomeIcon icon="fal fa-plus-circle" fixed-width />
                </span>

                <span class="text-[8px] sm:text-[9px] md:text-[10px]">
                    {{ trans("Profit") }}:
                </span>

                <span class="font-bold text-green-700">
                    (
                    {{
                        (layout?.user?.gr_data?.customer_is_gr || layout?.user?.gr_data?.amnesty)
                            ? product?.discounted_margin
                            : product?.margin
                    }}
                    )
                </span>

            </div>

            <Popover ref="_popoverProfit" class="max-w-[90vw] md:max-w-none sm:min-w-[350px]">
                <ProfitCalculationList :product="product" />
            </Popover>

        </div>


        <!-- PRICE -->
        <div class="flex flex-col gap-y-2 2xl:gap-y-0 ">

            <div class="grid grid-cols-[auto_1fr] items-center gap-x-2">

                <div class="font-semibold whitespace-nowrap">
                    <span>{{ trans("Price") }}</span>
                    <span class="text-[8px] sm:text-[9px] font-light">
                        ({{ trans("Excl. Vat") }})
                    </span>
                </div>

                <div class="font-bold text-right min-w-0">

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

                </div>

            </div>


            <!-- GR PRICE -->
            <div v-if="product.discounted_price" class="grid grid-cols-[auto_1fr] items-center gap-x-2">

                <div class="whitespace-nowrap
            text-[9px]
            sm:text-[10px]
            md:text-[11px]
            lg:text-[12px]
            xl:text-[13px]
            2xl:text-[14px]">

                    <span v-if="showMemberPrice" class="text-primary">
                        {{ trans("GR Active") || "GR Active" }}
                    </span>

                    <span v-else>
                        {{ trans("GR Inactive") || "GR Inactive" }}
                    </span>

                    <span class="question-trigger" @click="_popoverQuestionCircle?.toggle($event)"
                        @mouseenter="_popoverQuestionCircle?.show($event)" @mouseleave="_popoverQuestionCircle?.hide()"
                        @blur="_popoverQuestionCircle?.hide()">
                        <FontAwesomeIcon icon="fal fa-question-circle" fixed-width aria-hidden="true" />
                    </span>

                    <Popover ref="_popoverQuestionCircle" class="member-popover">
                        <div class="popover-content">
                            <p class="popover-title">{{ trans("VOLUME DISCOUNT") }}</p>

                            <p class="popover-paragraph">
                                {{ trans("You don't need Gold Reward status to access the lower price") }}.
                            </p>

                            <p class="popover-paragraph">
                                {{ trans("Order the listed volume and the member price applies automatically at checkout") }}.
                                {{ trans("The volume can be made up from the whole product family, not just the same item") }}.
                            </p>
                        </div>
                    </Popover>

                </div>

                <div class="text-primary font-bold text-right min-w-0">

                    <template v-if="product.units == 1">

                        <div class="flex justify-end items-center gap-1 min-w-0">

                            <span class="whitespace-nowrap">
                                {{ locale.currencyFormat(currency?.code, product.discounted_price) }}/
                            </span>

                            <span class="truncate min-w-0" :title="product.unit">
                                {{ product.unit }}
                            </span>

                        </div>

                    </template>

                    <template v-else>

                        <div class="flex justify-end items-center gap-1 min-w-0">

                            <span class="whitespace-nowrap">
                                {{ locale.currencyFormat(currency?.code, product.discounted_price) }}
                            </span>
                            <span class="truncate min-w-0 font-normal text-[8px] sm:text-[9px] md:text-[10px]" :title="product.unit">
                                ({{ locale.currencyFormat(currency?.code, product.discounted_price_per_unit) }}/{{ product.unit }})
                            </span>

                        </div>

                    </template>

                </div>

            </div>

        </div>


        <!-- MEMBER -->
        <div v-if="showIntervalOffer" class="mt-1 flex flex-col items-start gap-0.5 text-[8px] sm:text-[9px] md:text-[10px]">
            <MemberPriceLabel v-if="showMemberPrice" :offer="bestOffer" />

            <DiscountByType v-if="showDiscount" :offers_data="product?.product_offers_data"
                template="products_triggers_label" />
        </div>

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
</style>