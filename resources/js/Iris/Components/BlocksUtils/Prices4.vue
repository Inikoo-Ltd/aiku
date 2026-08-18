<script lang="ts">
let openedStepsPopover: { hide: () => void } | null = null
</script>

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
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faCheck, faBadgePercent } from "@far"

library.add(faPlusCircle, faQuestionCircle)

const layout = inject("layout", retinaLayoutStructure)
const webpage_data = inject("webpage_data", null)
const locale = useLocaleStore()

interface ProductResource {
    id: number
    family_id?: number | null
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

    step_discount?: {
        label: string | null
        steps: {
            min_quantity: number
            percentage_off: number
            percentage_off_label: string
            price: number
            price_per_unit: number
            is_popular: boolean
        }[]
    } | null

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
    orderQuantity?: (quantity: number) => Promise<void>
}>()



const bestOffer = computed(() => {
    return getBestOfferfromComposable(props.product?.product_offers_data)
})

const hasOffer =
    props.product?.product_offers_data?.number_offers > 0 &&
    getBestOfferfromComposable(props.product?.product_offers_data)


const hasStepDiscount = computed(() => {
    return (props.product?.step_discount?.steps?.length ?? 0) > 0
})

const bestStep = computed(() => {
    const steps = props.product?.step_discount?.steps ?? []

    return steps[steps.length - 1] ?? null
})

const basketQuantity = computed(() => {
    return Number(props.hasInBasket?.quantity_ordered_new ?? props.hasInBasket?.quantity_ordered ?? 0)
})

const activeStep = computed(() => {
    const reachedSteps = (props.product?.step_discount?.steps ?? [])
        .filter(step => step.min_quantity <= basketQuantity.value)

    return reachedSteps[reachedSteps.length - 1] ?? null
})

const displayStep = computed(() => {
    return activeStep.value ?? bestStep.value
})

const canOrderStepDiscount = computed(() => {
    return !!props.orderQuantity
        && !!props.basketButton
        && props.product?.stock > 0
        && !props.product?.is_coming_soon
        && !props.product?.variant
})

const pendingStepQuantity = ref<number | null>(null)

const isStepOrderable = (minQuantity: number) => {
    return canOrderStepDiscount.value
        && minQuantity <= props.product.stock
        && minQuantity !== basketQuantity.value
}

const isStepOverStock = (minQuantity: number) => {
    return canOrderStepDiscount.value && minQuantity > props.product.stock
}

const isStepSelected = (minQuantity: number) => {
    return canOrderStepDiscount.value && minQuantity === basketQuantity.value
}

const onOrderStep = async (minQuantity: number) => {
    if (!isStepOrderable(minQuantity) || pendingStepQuantity.value === minQuantity) {
        return
    }

    pendingStepQuantity.value = minQuantity

    try {
        await props.orderQuantity?.(minQuantity)
    } finally {
        if (pendingStepQuantity.value === minQuantity) {
            pendingStepQuantity.value = null
        }
    }
}

const showIntervalOffer = computed(() => {
    if (hasStepDiscount.value) return false

    return getBestOfferfromComposable(props.product?.product_offers_data)?.type
        === 'Category Quantity Ordered Order Interval' && webpage_data?.sub_type != 'family'
})

// Total quantity ordered across the whole family this product belongs to
const familyQuantityOrdered = computed(() => {
    const familyId = props.product?.family_id
    if (familyId == null) return 0

    return Number(layout?.family_quantity_ordered?.[familyId] ?? 0)
})

// A golden product of the family sitting in the basket triggers the member price on its own,
// regardless of how many units of the family have been ordered
const familyHasGoldenProductInBasket = computed(() => {
    const familyId = props.product?.family_id
    if (familyId == null) return false

    return Boolean(layout?.family_has_golden_product?.[familyId])
})

const showMemberPrice = computed(() => {
    if (layout?.user?.gr_data?.amnesty) return true
    if (layout?.user?.gr_data?.customer_is_gr) return true
    if (familyHasGoldenProductInBasket.value) return true  // If a golden product of the family is in the basket, show member price for all products of the family

    return bestOffer.value?.category_qty_trigger <= familyQuantityOrdered.value
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

const isDiscountedPriceActive = computed(() => {
    if (displayStep.value) {
        return !!activeStep.value
    }

    return showMemberPrice.value
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

watch(basketQuantity, () => {
    pendingStepQuantity.value = null
})

const _popoverProfit = ref(null)
const _popoverSteps = ref(null)

const onToggleStepsPopover = (event: Event) => {
    if (openedStepsPopover && openedStepsPopover !== _popoverSteps.value) {
        openedStepsPopover.hide()
    }

    openedStepsPopover = _popoverSteps.value
    _popoverSteps.value?.toggle(event)
}

const onHideStepsPopover = () => {
    if (openedStepsPopover === _popoverSteps.value) {
        openedStepsPopover = null
    }
}
</script>

<template>
    <div
    class="step-discount-scope font-sans border-gray-200 mt-1 mb-[-2px] px-0 tabular-nums leading-none text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px] xl:text-[13px] 2xl:text-sm">

        <!-- HEADER -->
        <div style="margin-bottom: 0.25rem; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.25rem; font-size: 11px;">
            <div class="flex items-center gap-2">
                <span class="font-medium ">
                    {{ product?.code }}
                </span>

                <div v-if="layout?.iris?.is_logged_in && !product.is_coming_soon"
                    v-tooltip="trans('Available product stocks')" class="flex items-center">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]"
                        :class="product.stock > 0 ? 'text-green-500' : 'text-red-500'" />
                </div>

                <LabelComingSoon v-else-if="product.is_coming_soon" :product="product" />
            </div>

            <div v-if="(product?.rrp_per_unit ?? 0 > 0) && !product.is_coming_soon"
                style="margin-left: auto; display: flex; align-items: center; gap: 0.25rem; white-space: nowrap;">
                <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show"
                    @mouseleave="_popoverProfit?.hide"
                    class="cursor-pointer opacity-60 hover:opacity-100 flex items-center text-[8px] sm:text-[9px] md:text-[10px]">
                    <FontAwesomeIcon icon="fal fa-plus-circle" fixed-width />
                </span>

                <span class="text-[8px] sm:text-[9px] md:text-[10px] text-[#E87928] border-[#E87928] font-bold">
                    {{ trans('RRP') }}:
                    <span class="font-bold">
                        {{ locale.currencyFormatRrp(currency?.code, product?.rrp_per_unit) }}
                    </span>
                </span>
            </div>
        </div>

        <Popover ref="_popoverProfit" class="max-w-[90vw] md:max-w-none sm:min-w-[350px]">
            <ProfitCalculationList :product="product" />
        </Popover>


        <!-- PRICE -->
       <div class="flex flex-col gap-y-0 2xl:gap-y-0">

            <div class="relative grid items-center gap-x-1 w-full"
                :class="bestOffer?.type == 'Category Quantity Ordered Order Interval'
                    ? 'grid-cols-[1fr_minmax(0,70%)] lg:grid-cols-[1fr_minmax(0,75%)] 2xl:grid-cols-[1fr_minmax(0,75%)]'
                    : 'grid-cols-[1fr_minmax(0,70%)] lg:grid-cols-[1fr_minmax(0,75%)] 2xl:grid-cols-[1fr_minmax(0,75%)]'">

                <div class="font-semibold whitespace-nowrap">
                  <!--   <span>{{ trans("Price") }}</span> -->
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
                        <div class="flex justify-end items-center gap-1 min-w-0 ">
                            <span class="whitespace-nowrap text-[8px] sm:text-[9px] md:text-[10px]">
                                {{ locale.currencyFormat(currency?.code, product.price) }}
                            </span>
                            <span v-if="product.price_per_unit > 0"
                                class="truncate min-w-0"
                                :title="product.unit">
                                ({{ locale.currencyFormat(currency?.code, product.price_per_unit) }}/{{ product.unit }})
                            </span>

                        </div>
                    </template>

                    <div v-if="!isDiscountedPriceActive" class="absolute -right-3 sm:-right-4 top-1/2 -translate-y-1/2">
                        <div class="flex text-xs items-center justify-center rounded-full ">
                            <FontAwesomeIcon :icon="faCheck" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- GR PRICE -->
            <div v-if="product.discounted_price || displayStep" class="relative grid items-center gap-x-2 w-full mt-2"
                :class="bestOffer?.type == 'Category Quantity Ordered Order Interval'
                    ? 'grid-cols-[1fr_minmax(0,70%)] lg:grid-cols-[1fr_minmax(0,75%)] 2xl:grid-cols-[1fr_minmax(0,75%)]'
                    : 'grid-cols-[1fr_minmax(0,70%)] lg:grid-cols-[1fr_minmax(0,75%)] 2xl:grid-cols-[1fr_minmax(0,75%)]'">
                <button v-if="displayStep" type="button"
                    class="inline-flex items-center gap-1 rounded cursor-pointer transition-all duration-150"
                    v-tooltip="trans('Buy :qty+ :unit, save :off', {
                        qty: displayStep.min_quantity,
                        unit: product.unit,
                        off: displayStep.percentage_off_label,
                    })" aria-haspopup="true" @click.stop.prevent="onToggleStepsPopover">
                    <FontAwesomeIcon :icon="faBadgePercent" class="text-lg"
                        :class="activeStep ? 'step-discount-text' : 'step-discount-text-muted'" />

                    <div class="flex items-center gap-2 rounded px-1 md:py-[5px] py-[3px] xl:py-[3px] text-[8px] xl:text-[10px] 2xl:text-xs font-semibold leading-none whitespace-nowrap text-white transform transition-all duration-150"
                        :class="activeStep ? 'step-discount-bg' : 'step-discount-bg-muted border border-transparent'">
                        <span>
                            <span class="hidden xl:inline">{{ displayStep.min_quantity }}+ </span>
                            {{ displayStep.percentage_off_label }}
                        </span>
                    </div>
                </button>

                <div v-else-if="bestOffer?.type == 'Category Quantity Ordered Order Interval'">
                    <MemberPriceLabel :offer="bestOffer" :active="showMemberPrice" />
                </div>
                <div v-else class="offer">
                    <DiscountByType v-if="bestOffer?.type == 'Category Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Category Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'First Order Bonus'"
                        :offers_data="product?.product_offers_data" template="first-order" />
                    <DiscountByType v-if="bestOffer?.type == 'Category Amount Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Department Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Subdepartment Quantity Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Department Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <DiscountByType v-if="bestOffer?.type == 'Subdepartment Ordered'"
                        :offers_data="product?.product_offers_data" template="max_discount_3" :use_duration="false" />
                    <div v-else class="w-full"></div>
                </div>



                <div v-if="displayStep" class="font-medium text-right min-w-0 step-discount-text">
                    <div class="flex items-baseline justify-end gap-0.5 min-w-0">
                        <div class="min-w-0 flex-1 truncate step-discount-text step-discount-border">
                            <span class="font-bold" v-if="product.units == 1">{{ locale.currencyFormat(currency?.code, displayStep.price) }} /{{ product.unit }}</span>
                            <span v-else>
                                <span class=" text-[8px] sm:text-[9px] md:text-[10px] mr-1 font-bold">
                                    {{ locale.currencyFormat(currency?.code, displayStep.price) }}
                                </span>
                                <span class="font-bold">({{
                                        locale.currencyFormat(currency?.code, displayStep.price_per_unit) }}
                                    /{{ product.unit }})
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <div v-else-if="bestOffer" class="font-medium text-right min-w-0" :class="bestOfferClass">
                    <div class="flex items-baseline justify-end gap-0.5 min-w-0">
                        <div class="min-w-0 flex-1 truncate text-[#E87928] border-[#E87928]">
                            <span class="font-bold" v-if="product.units == 1">{{ locale.currencyFormat(currency?.code, product.discounted_price) }} /{{ product.unit }}</span>
                            <span v-else>
                                <span class=" text-[8px] sm:text-[9px] md:text-[10px] mr-1 font-bold">
                                    {{ locale.currencyFormat(currency?.code, product.discounted_price) }}
                                </span>
                                <span class="font-bold">({{
                                        locale.currencyFormat(currency?.code,
                                            product.discounted_price_per_unit) }}
                                    /{{ product.unit }})
                                </span>
                            </span>
                        </div>

                    </div>
                </div>

                <div v-if="isDiscountedPriceActive" class="absolute -right-3 sm:-right-4 top-1/2 -translate-y-1/2">
                    <div class="flex text-xs items-center justify-center rounded-full"
                        :class="displayStep ? 'step-discount-text' : 'text-[#E87928]'">
                        <FontAwesomeIcon :icon="faCheck" />
                    </div>
                </div>
            </div>


            <!-- MEMBER -->
            <div v-if="showIntervalOffer && !showMemberPrice"
                class="mt-1 flex flex-col items-start gap-0.5 text-[8px] sm:text-[9px] md:text-[10px] discount">
                <DiscountByType v-if="showDiscount" :offers_data="product?.product_offers_data"
                    template="products_triggers_label" />
            </div>

            <Popover v-if="displayStep" ref="_popoverSteps" class="max-w-[90vw] sm:max-w-[300px]"
                @hide="onHideStepsPopover">
                <div class="step-discount-scope p-2 text-xs tabular-nums">
                    <div v-if="product.step_discount.label" class="font-bold">
                        {{ product.step_discount.label }}
                    </div>

                    <div v-if="canOrderStepDiscount" class="mb-1 text-[10px] font-normal text-gray-500">
                        {{ trans('Select a quantity to order it straight away') }}
                    </div>

                    <button v-for="step in product.step_discount.steps" :key="step.min_quantity" type="button"
                        :disabled="!isStepOrderable(step.min_quantity) || pendingStepQuantity === step.min_quantity"
                        class="step-discount-row flex w-full items-center justify-between gap-3 border-t border-gray-100 px-1 py-1.5 text-left enabled:cursor-pointer disabled:cursor-default"
                        :class="[
                            step.is_popular && !isStepSelected(step.min_quantity) ? 'step-discount-surface-soft' : '',
                            isStepSelected(step.min_quantity) ? 'step-discount-surface step-discount-outline' : '',
                            isStepOverStock(step.min_quantity) ? 'step-discount-unavailable' : '',
                        ]"
                        @click.stop.prevent="onOrderStep(step.min_quantity)">
                        <span class="flex items-center gap-1.5 whitespace-nowrap font-medium">
                            <LoadingIcon v-if="pendingStepQuantity === step.min_quantity" class="step-discount-text" />

                            <span v-else-if="canOrderStepDiscount"
                                class="flex h-3 w-3 shrink-0 items-center justify-center rounded-full border"
                                :class="activeStep?.min_quantity === step.min_quantity ? 'step-discount-border' : 'step-discount-border-soft'">
                                <span v-if="activeStep?.min_quantity === step.min_quantity"
                                    class="h-1.5 w-1.5 rounded-full step-discount-bg" />
                            </span>

                            {{ step.min_quantity }}+ {{ product.unit }}
                            <span class="font-normal text-gray-500">−{{ step.percentage_off_label }}</span>
                        </span>

                        <span class="whitespace-nowrap text-right font-bold step-discount-text">
                            {{ locale.currencyFormat(currency?.code, step.price) }}
                            <span v-if="product.units != 1" class="font-normal text-gray-500">
                                ({{ locale.currencyFormat(currency?.code, step.price_per_unit) }}/{{ product.unit }})
                            </span>
                        </span>
                    </button>
                </div>
            </Popover>
        </div>
    </div>


</template>


<style scoped>
.step-discount-scope {
    --step-discount-color: var(--theme-color-4, #8E44AD);
    --step-discount-surface: color-mix(in srgb, var(--step-discount-color) 12%, white);
    --step-discount-surface-soft: color-mix(in srgb, var(--step-discount-color) 6%, white);
    --step-discount-border-soft: color-mix(in srgb, var(--step-discount-color) 40%, #9ca3af);
    --step-discount-muted: color-mix(in srgb, var(--step-discount-color) 25%, #b3b3b3);
}

.step-discount-text {
    color: var(--step-discount-color);
}

.step-discount-bg {
    background-color: var(--step-discount-color);
}

.step-discount-border {
    border-color: var(--step-discount-color);
}

.step-discount-outline {
    box-shadow: inset 0 0 0 1px var(--step-discount-color);
}

.step-discount-surface {
    background-color: var(--step-discount-surface);
}

.step-discount-surface-soft {
    background-color: var(--step-discount-surface-soft);
}

.step-discount-border-soft {
    border-color: var(--step-discount-border-soft);
}

.step-discount-text-muted {
    color: var(--step-discount-muted);
}

.step-discount-bg-muted {
    background-color: var(--step-discount-muted);
}

.step-discount-row:enabled:hover {
    background-color: var(--step-discount-surface);
}

.step-discount-unavailable {
    @apply opacity-40 grayscale;
}

.zoom-75 {
    transform-origin: 0 0;
    transform: scale(.9);
    display: block;
    width: calc(100% / .9);
    height: calc(100% / .9);
    box-sizing: border-box;
}

/* tablet ke atas */
@media (min-width: 640px) {
    .zoom-75 {
        transform: scale(.75);
        width: calc(100% / .75);
        height: calc(100% / .75);
    }
}

/* 2xl */
@media (min-width: 1536px) {
    .zoom-75 {
        transform: scale(1);
        width: 100%;
        height: auto;
    }
}

.discount :deep(.offer-trigger-label) {
    @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs md:text-xs text-[#E87928] border-[#E87928];    
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