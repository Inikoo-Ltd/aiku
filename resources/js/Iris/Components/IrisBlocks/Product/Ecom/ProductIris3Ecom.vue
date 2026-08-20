<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, nextTick } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

import {
    faCube,
    faLink,
    faHeart,
    faEnvelope,
    faFileCheck,
    faFilePdf,
    faFileWord,
    faChevronLeft,
    faChevronRight,
    faPlusCircle,
    faPencil,
    faCheckCircle
} from "@fal"

import {
    faHeart as fasHeart,
    faPlus,
    faMinus,
    faArrowToBottom,
    faMapMarkerAlt,
    faCircle
} from "@fas"

import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"
import { faImage } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import "swiper/css/navigation"
import { Navigation } from "swiper/modules"

import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"
import Product2Image from "@/Components/CMS/Webpage/Product2/Product2Image.vue"
import GoldenProductBadge from "@/Components/CMS/Webpage/Products/GoldenProductBadge.vue"
import Image from "@common/Components/Image.vue"

import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { ctrans } from "@/Composables/useTrans"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { getStyles } from "@/Composables/styles"
import { ulid } from "ulid"
import Discount from "@/Components/Utils/Label/Discount.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabel.vue"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import GRAmnestyPriceLabel from "@/Components/Utils/Iris/Family/GRAmnestyPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import StepDiscountOffer from "@/Components/CMS/Webpage/Product1/StepDiscountOffer.vue"
import { getBestOffer } from "@/Composables/useOffers"
import { Popover } from "primevue"

library.add(
    faCube,
    faLink,
    faPlus,
    faMinus,
    faCircle,
    faPencil,
    faCheckCircle,
    faFileCheck,
    faFilePdf,
    faFileWord,
    faArrowToBottom,
    faMapMarkerAlt,
    faImage,
    faChevronLeft,
    faChevronRight,
    faPlusCircle
)

interface ProductTag {
    id: number
    slug: string
    name: string
    label?: string
    image?: any
}

interface ProductAttachment {
    label: string
    caption: string
    url: string
    mime_type: string
}

interface ProductResource {
    id: number
    name: string
    code: string
    slug?: string
    image?: { source: any }

    price: number
    price_per_unit?: number
    rrp_per_unit?: number
    profit?: number
    margin?: string

    discounted_price?: number
    discounted_price_per_unit?: number
    discounted_profit?: number
    discounted_profit_per_unit?: number
    discounted_margin?: string
    offers_data?: any
    step_discount?: {
        label?: string
        steps: any[]
        unit?: string
        units?: number
    }
    is_coming_soon?: boolean

    currency_code: string
    unit: string
    units: number
    stock: number

    rating?: number
    url?: string | null

    bestseller?: boolean
    is_favourite?: boolean
    is_back_in_stock?: boolean
    is_golden_product?: boolean

    description?: string
    description_extra?: string

    web_images?: any
    variant_label?: string

    tags?: ProductTag[]
    attachments?: ProductAttachment[]
    specifications?: any
}

const props = withDefaults(
    defineProps<{
        fieldValue: any
        webpageData?: any
        blockData?: object
        screenType: "mobile" | "tablet" | "desktop"
        validImages: object
        customerData: any
        product: ProductResource
        isLoadingRemindBackInStock: boolean
        isLoadingFavourite: boolean
        videoSetup: { url: string }
        listProducts: ProductResource[]
        indexBlock?: number
    }>(),
    {
        indexBlock: 0
    }
)

const emit = defineEmits<{
    (e: "setFavorite", value: ProductResource): void
    (e: "unsetFavorite", value: ProductResource): void
    (e: "setBackInStock", value: ProductResource): void
    (e: "unsetBackInStock", value: ProductResource): void
    (e: "selectProduct", value: ProductResource): void
}>()

const layout = inject<any>("layout", {})
const locale = useLocaleStore()
const keyCustomer = ref(ulid())

const expanded = ref(false)
const product = ref<ProductResource>(props.product)

watch(
    () => props.product,
    value => (product.value = value),
    { deep: true }
)

const currency = computed(() => layout?.iris?.currency)

const resolveRoute = inject<((name: string, params?: object) => string) | null>("route", null)



const productTags = computed<ProductTag[]>(() =>
    (product.value?.tags || []).filter(tag => tag?.name)
)

const documentations = computed<ProductAttachment[]>(() => product.value?.attachments || [])

const countriesOfOrigin = computed(() =>
    (product.value?.specifications?.countries_of_origin || []).filter((country: any) => country?.code)
)

const specificationRows = computed(() => {
    const specifications = product.value?.specifications || {}

    return [
        {
            key: "marketing_weight",
            label: trans("Net Weight"),
            value: specifications.marketing_weight
                ? `${specifications.marketing_weight} g/${specifications.unit ?? product.value?.unit ?? ""}`.trim()
                : null
        },
        {
            key: "gross_weight",
            label: trans("Shipping Weight"),
            value: specifications.gross_weight ? `${specifications.gross_weight} g` : null
        },
        {
            key: "dimensions",
            label: trans("Dimensions"),
            value: specifications.dimensions || null
        },
        {
            key: "ingredients",
            label: trans("Materials/Ingredients"),
            value: specifications.ingredients || null
        },
        {
            key: "barcode",
            label: trans("Barcode"),
            value: specifications.barcode || null
        },
        {
            key: "cpnp",
            label: trans("cpnp"),
            value: specifications.cpnp || null
        },
        {
            key: "origin",
            label: trans("Origin"),
            value: specifications.origin || null
        }
    ].filter(row => row.value)
})

const hasSpecifications = computed(() =>
    Boolean(specificationRows.value.length || countriesOfOrigin.value.length)
)

const offersData = computed(() => product.value?.offers_data || props.customerData?.offers_data || null)

const bestOffer = computed(() => getBestOffer(offersData.value))

const isGoldRewardCustomer = computed(() =>
    Boolean(layout?.user?.gr_data?.customer_is_gr || layout?.user?.gr_data?.amnesty)
)

const isPurchasable = computed(() => Boolean(product.value?.stock && !product.value?.is_coming_soon))

const showDiscount = computed(() =>
    isPurchasable.value
    && !isGoldRewardCustomer.value
    && bestOffer.value?.type === "Category Quantity Ordered Order Interval"
)

const displayedProfit = computed(() =>
    isGoldRewardCustomer.value && props.fieldValue?.product?.discounted_profit != null
        ? props.fieldValue.product.discounted_profit
        : product.value?.profit
)

const displayedMargin = computed(() =>
    isGoldRewardCustomer.value && props.fieldValue?.product?.discounted_margin != null
        ? props.fieldValue.product.discounted_margin
        : product.value?.margin
)

const displayedPrice = computed(() => (bestOffer.value ? product.value?.discounted_price : product.value?.price) || 0)

const displayedPricePerUnit = computed(() =>
    (bestOffer.value ? product.value?.discounted_price_per_unit : product.value?.price_per_unit) || 0
)

const orderedQuantity = computed<number>(() =>
    Number(props.customerData?.quantity_ordered_new ?? props.customerData?.quantity_ordered ?? 0)
)

const variantAxisLabel = computed(() =>
    (props.fieldValue?.variant?.data?.variants || [])
        .map((variant: any) => variant?.label)
        .filter(Boolean)
        .join(" / ")
)

const selectedVariantLabel = computed(() =>
    props.listProducts?.find(item => item.code === product.value.code)?.variant_label
    || product.value.variant_label
    || ""
)

const bespokeData = computed(() => props.fieldValue?.bespoke_data || {})

const showBespoke = computed(() =>
    Boolean(props.fieldValue?.setting?.bespoke && (bespokeData.value?.title || bespokeData.value?.text))
)

const toggleExpanded = () => {
    expanded.value = !expanded.value
}

const onAddFavourite = (value: ProductResource) => {
    emit("setFavorite", value)
}

const onUnselectFavourite = (value: ProductResource) => {
    emit("unsetFavorite", value)
}

const onAddBackInStock = (value: ProductResource) => {
    emit("setBackInStock", value)
}

const onUnselectBackInStock = (value: ProductResource) => {
    emit("unsetBackInStock", value)
}

const onSelectProduct = (value: ProductResource) => {
    emit("selectProduct", value)
}

const extractFileType = (mime = "") =>
    mime.split("/")[1]?.split("+")[0]?.toLowerCase() || ""

const getIcon = (type: string) => {
    if (type === "pdf") return faFilePdf
    if (["doc", "docx", "msword", "vnd.openxmlformats-officedocument.wordprocessingml.document"].includes(type)) return faFileWord
    return faFileCheck
}

const _popoverProfit = ref(null)
const _popoverProfitMobile = ref(null)

const _desktopAddToBasket = ref<InstanceType<typeof EcomAddToBasketv2> | null>(null)
const _mobileAddToBasket = ref<InstanceType<typeof EcomAddToBasketv2> | null>(null)

const isDesktopStepSyncing = ref(false)
const isMobileStepSyncing = ref(false)

const onSelectStepQuantityDesktop = async (quantity: number) => {
    if (isDesktopStepSyncing.value) {
        return
    }

    isDesktopStepSyncing.value = true
    try {
        await _desktopAddToBasket.value?.setQuantity(quantity)
    } finally {
        isDesktopStepSyncing.value = false
    }
}

const onSelectStepQuantityMobile = async (quantity: number) => {
    if (isMobileStepSyncing.value) {
        return
    }

    isMobileStepSyncing.value = true
    try {
        await _mobileAddToBasket.value?.setQuantity(quantity)
    } finally {
        isMobileStepSyncing.value = false
    }
}

const variantPrevEl = ref<HTMLElement | null>(null)
const variantNextEl = ref<HTMLElement | null>(null)
const variantNavigation = ref<{ prevEl: HTMLElement | null; nextEl: HTMLElement | null }>({
    prevEl: null,
    nextEl: null
})

onMounted(async () => {
    await nextTick()
    variantNavigation.value.prevEl = variantPrevEl.value
    variantNavigation.value.nextEl = variantNextEl.value
})
</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id : 'product-iris-3-ecom' + indexBlock" component="product-iris-3-ecom"
        class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            marginLeft: 'auto',
            marginRight: 'auto'
        }">
        <div class="grid grid-cols-12 gap-x-10">
            <div class="col-span-7">
                <Product2Image :images="validImages" :video="videoSetup?.url" />

                <div v-if="productTags.length" class="mt-6 flex flex-wrap items-center gap-x-8 gap-y-4">
                    <div v-for="tag in productTags" :key="tag.id" class="flex items-center gap-2 text-gray-400">
                        <Image v-if="tag.image" :src="tag.image" :alt="tag.name" class="h-4 w-4 object-contain grayscale opacity-70" />
                        <FontAwesomeIcon v-else :icon="faCheckCircle" class="text-sm" />
                        <span class="text-xs font-medium">{{ tag.label || tag.name }}</span>
                    </div>
                </div>

                <component :is="bespokeData?.link?.href ? LinkIris : 'div'"
                    v-if="showBespoke"
                    :href="bespokeData?.link?.href"
                    :type="bespokeData?.link?.type"
                    class="mt-6 block">
                    <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-[#F4F4F4] px-4 py-3">
                        <FontAwesomeIcon :icon="faPencil" class="text-lg text-gray-500 shrink-0" />
                        <div class="text-sm">
                            <div v-if="bespokeData?.title" class="font-semibold text-gray-800">{{ bespokeData.title }}</div>
                            <div v-if="bespokeData?.text" class="text-gray-600" v-html="bespokeData.text" />
                        </div>
                    </div>
                </component>

            </div>

            <div class="col-span-5 self-start">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="w-full">
                        <GoldenProductBadge v-if="product.is_golden_product" class="mb-2" />

                        <h1 class="product-title !text-xl font-bold leading-snug">
                            <span v-if="product.units > 1">{{ product.units }}x</span> {{ product.name }}
                        </h1>

                        <div class="mt-1 text-xs text-gray-500">
                            {{ trans("Product code") }}: <span class="font-medium text-gray-700">{{ product.code }}</span>
                        </div>

                        <div v-if="layout?.iris?.is_logged_in" class="mt-2 flex items-center gap-2 text-xs">
                            <FontAwesomeIcon :icon="faCircle" class="text-[6px]"
                                :class="product.stock > 0 ? 'text-green-500' : 'text-red-500'" />
                            <span :class="product.stock > 0 ? 'text-gray-700' : 'text-red-600'">
                                {{
                                    product.stock > 0
                                        ? `${trans("In stock")} (${customerData?.stock})`
                                        : trans("Out Of Stock")
                                }}
                            </span>
                        </div>

                        <button v-if="layout?.iris?.is_logged_in && product.stock <= 0 && layout?.outboxes?.oos_notification?.state == 'active'"
                            @click="product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                            class="mt-2 flex items-center gap-2 rounded-full border bg-gray-100 px-3 py-1.5 text-sm hover:bg-gray-200">
                            <LoadingIcon v-if="isLoadingRemindBackInStock" />
                            <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                                :class="product.is_back_in_stock ? 'text-green-600' : 'text-gray-600'" />
                            <span>
                                {{
                                    product.is_back_in_stock
                                        ? trans("will be notified when in Stock")
                                        : trans("Remind me")
                                }}
                            </span>
                        </button>
                    </div>

                    <div v-if="layout?.retina?.type !== 'dropshipping' && layout?.iris?.is_logged_in">
                        <LoadingIcon v-if="isLoadingFavourite" class="text-2xl text-gray-500" />
                        <div v-else class="cursor-pointer text-2xl"
                            @click="customerData?.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)">
                            <FontAwesomeIcon v-if="customerData?.is_favourite" :icon="fasHeart" class="text-pink-500" />
                            <FontAwesomeIcon v-else :icon="faHeart" class="text-pink-300 hover:text-pink-400" />
                        </div>
                    </div>
                </div>

                <div v-if="Object.keys(customerData?.offers_data || {})?.length" class="my-3 w-full">
                    <Discount :offers_data="customerData?.offers_data" class="justify-center" template="agnes_and_cat" />
                </div>

                <hr class="my-4 border-gray-200" />

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">
                            {{ trans("Price (Excl. Tax)") }}
                        </div>
                        <div class="mt-1 flex items-baseline gap-2">
                            <span v-if="bestOffer" class="text-sm font-medium text-gray-400 line-through">
                                {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                            </span>
                            <span class="text-lg font-bold text-black">
                                {{ locale.currencyFormat(currency?.code, displayedPrice) }}
                            </span>
                            <span class="text-xs text-gray-500">
                                ({{ locale.currencyFormat(currency?.code, displayedPricePerUnit) }}/{{ product.unit }})
                            </span>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-[11px] uppercase tracking-wide text-gray-400">{{ trans("RRP") }}</div>
                        <div class="mt-1 text-sm text-gray-600">
                            {{ locale.currencyFormatRrp(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                        </div>
                    </div>
                </div>

                <div v-if="layout?.iris?.is_logged_in" class="mt-2 flex items-baseline gap-1 text-xs text-rose-500">
                    <span>{{ trans("Profit") }}:</span>
                    <span class="font-semibold">{{ locale.currencyFormat(currency?.code, displayedProfit || 0) }}</span>
                    <span>({{ displayedMargin }})</span>

                    <span class="cursor-pointer opacity-60 hover:opacity-100" @click="_popoverProfit?.toggle"
                        @mouseenter="_popoverProfit?.show" @mouseleave="_popoverProfit?.hide">
                        <FontAwesomeIcon :icon="faPlusCircle" fixed-width aria-hidden="true" />
                    </span>

                    <Popover ref="_popoverProfit" class="max-w-[90vw] md:max-w-none sm:min-w-[350px]">
                        <ProfitCalculationList :product="fieldValue.product" />
                    </Popover>
                </div>

                <div v-if="layout?.iris?.is_logged_in && offersData?.number_offers > 0"
                    class="offers mt-3 flex flex-col items-start gap-1 ">
                    <template v-if="bestOffer?.type === 'Category Quantity Ordered Order Interval'">
                        <GRAmnestyPriceLabel v-if="layout?.user?.gr_data?.amnesty" :offer="bestOffer" />
                        <MemberPriceLabel v-else-if="isGoldRewardCustomer" :offer="bestOffer" />
                        <NonMemberPriceLabel v-else :product="product" />
                    </template>

                    <DiscountByType v-if="showDiscount" template="products_triggers_label" :offers_data="offersData" />

                    <DiscountByType
                        v-if="isPurchasable && bestOffer?.type !== 'Category Quantity Ordered Order Interval'"
                        template="max_discount" :offers_data="offersData" />
                </div>

                <StepDiscountOffer
                    class="mt-5"
                    v-if="layout?.iris?.is_logged_in && isPurchasable && product.step_discount?.steps?.length"
                    :stepDiscount="product.step_discount" :currencyCode="product.currency_code ?? currency?.code"
                    :originalPrice="product.price" :unit="product.unit" :units="product.units"
                    :quantity="orderedQuantity" :isSubmitting="isDesktopStepSyncing"
                    @selectQuantity="onSelectStepQuantityDesktop" />

                <div class="mt-5">
                    <div v-if="layout?.iris?.is_logged_in" class="w-full">
                        <EcomAddToBasketv2 v-if="product.stock > 0" ref="_desktopAddToBasket" v-model:product="product"
                            :customerData="customerData" :key="keyCustomer"
                            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" class="button-basket" />
                        <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />
                    </div>

                    <LinkIris v-else :href="urlLoginWithRedirect()"
                        class="block w-full rounded border px-3 py-2 text-center text-sm text-gray-600"
                        :style="getStyles(fieldValue?.buttonLogin?.properties, screenType)">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </LinkIris>
                </div>

                <div v-if="listProducts && listProducts.length > 0" class="mt-5">
                    <div v-if="selectedVariantLabel" class="mb-1 text-sm">
                        <span v-if="variantAxisLabel" class="font-semibold">{{ variantAxisLabel }}:</span>
                        <span class="ml-1">{{ selectedVariantLabel }}</span>
                    </div>

                    <div class="relative px-5">
                        <button ref="variantPrevEl" type="button"
                            class="absolute left-0 top-1/2 z-10 -translate-y-1/2 text-gray-500 hover:text-gray-800">
                            <FontAwesomeIcon :icon="faChevronLeft" class="text-sm" />
                        </button>

                        <button ref="variantNextEl" type="button"
                            class="absolute right-0 top-1/2 z-10 -translate-y-1/2 text-gray-500 hover:text-gray-800">
                            <FontAwesomeIcon :icon="faChevronRight" class="text-sm" />
                        </button>

                        <Swiper :modules="[Navigation]" :navigation="variantNavigation" :space-between="8"
                            :slides-per-view="4" :grab-cursor="true">
                            <SwiperSlide v-for="item in listProducts" :key="item.id">
                                <button @click="onSelectProduct(item)" :disabled="item.code === product.code"
                                    class="group relative flex w-full flex-col overflow-hidden rounded-lg border bg-[#F4F4F4] transition"
                                    :class="item.code === product.code ? 'ring-1 primary' : 'border-gray-200 hover:border-gray-300'">
                                    <div class="relative aspect-square w-full overflow-hidden bg-gray-50">
                                        <Image v-if="item?.web_images?.main?.original" :src="item.web_images.main.original"
                                            :alt="item.code" loading="lazy"
                                            class="absolute inset-0 h-full w-full object-contain transition-transform duration-300 ease-out group-hover:scale-110" />

                                        <FontAwesomeIcon v-else :icon="faImage"
                                            class="absolute inset-0 m-auto text-xl text-gray-300" />

                                        <div class="pointer-events-none absolute bottom-1 left-1 right-1 translate-y-1 opacity-0 transition-all duration-200 group-hover:translate-y-0 group-hover:opacity-100">
                                            <span class="block truncate rounded bg-gray-900/80 px-2 py-0.5 text-center text-[11px] font-medium text-white backdrop-blur">
                                                {{ item.variant_label }}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>

                <div v-if="fieldValue.setting?.payments_and_policy && fieldValue.paymentData" class="mt-5">
                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in fieldValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>

                <div v-if="layout?.iris?.is_logged_in && fieldValue?.delivery_info?.text" class="mt-4 text-sm font-medium">
                    <div v-html="fieldValue?.delivery_info?.text" />
                </div>

                <div v-if="fieldValue?.setting?.product_specs && hasSpecifications" class="spec-card mt-6">
                    <div class="spec-card-header">{{ ctrans("Product Specification") }}</div>

                    <div class="spec-card-body">
                        <div v-for="row in specificationRows" :key="row.key" class="spec-row">
                            <div class="spec-label">{{ row.label }}</div>
                            <div class="spec-value">{{ row.value }}</div>
                        </div>

                        <div v-if="countriesOfOrigin.length" class="spec-row">
                            <div class="spec-label">{{ trans('Origin Country') }}</div>
                            <div class="spec-value flex flex-col gap-1 justify-end align-end">
                                <div v-for="country in countriesOfOrigin" :key="country.code" class="flex items-center gap-2">
                                    <img :src="'/flags/' + country.code.toLowerCase() + '.png'" :alt="country.name"
                                        :title="country.name" class="h-4 w-auto" />
                                    <span>{{ country.name }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="fieldValue?.setting?.product_specs && documentations.length" class="spec-card mt-4">
                    <div class="spec-card-header">{{ ctrans("Product Documentations") }}</div>

                    <div class="spec-card-body">
                        <div v-for="item in documentations" :key="`${item.label}-${item.caption}`" class="spec-row">
                            <div class="spec-label">{{ item.label }}</div>
                            <div class="spec-value flex items-center justify-end gap-2">
                                <a :href="item.url" target="_blank" class="text-xs text-gray-500 underline hover:text-gray-700">
                                    {{ trans("Link") }}
                                </a>
                                <a :href="item.url" target="_blank" download class="doc-download">
                                    {{ trans("Download File") }}
                                    <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <LinkIris v-if="layout?.iris?.is_logged_in && fieldValue?.setting?.appointment && fieldValue?.appointment_data?.link?.href"
                    :href="fieldValue?.appointment_data?.link?.href" :type="fieldValue?.appointment_data?.link?.type">
                    <div class="group my-4 flex w-full items-center gap-3 rounded-lg border bg-[#F4F4F4] px-4 py-2 transition hover:border-gray-300 hover:bg-gray-100">
                        <FontAwesomeIcon :icon="faMapMarkerAlt" class="shrink-0 text-gray-600 transition group-hover:text-gray-800" />
                        <span class="max-w-[420px] truncate text-sm font-medium text-gray-800 underline">
                            <div v-html="fieldValue?.appointment_data?.text" />
                        </span>
                    </div>
                </LinkIris>
            </div>
        </div>

    </div>

    <div class="bg-white sm:hidden">
        <Product2Image :images="validImages" :video="videoSetup?.url" />

        <div class="space-y-5 px-4 py-4">
            <div>
                <GoldenProductBadge v-if="product.is_golden_product" class="mb-2" />

                <h1 class="product-title !text-xl font-bold leading-snug">
                    <span v-if="product.units > 1">{{ product.units }}x</span> {{ product.name }}
                </h1>

                <div class="mt-1 text-xs text-gray-500">
                    {{ trans("Product code") }}: <span class="font-medium text-gray-700">{{ product.code }}</span>
                </div>

                <div class="mt-2 flex items-center justify-between">
                    <div v-if="layout?.iris?.is_logged_in" class="flex items-center gap-2 text-xs">
                        <FontAwesomeIcon :icon="faCircle" class="text-[6px]"
                            :class="product.stock > 0 ? 'text-green-500' : 'text-red-500'" />
                        <span :class="product.stock > 0 ? 'text-gray-700' : 'text-red-600'">
                            {{
                                product.stock > 0
                                    ? `${trans('In stock')} (${customerData?.stock})`
                                    : trans('Out Of Stock')
                            }}
                        </span>
                    </div>

                    <div v-if="layout?.retina?.type !== 'dropshipping' && layout?.iris?.is_logged_in">
                        <LoadingIcon v-if="isLoadingFavourite" class="text-gray-400" />
                        <FontAwesomeIcon v-else :icon="customerData?.is_favourite ? fasHeart : faHeart"
                            class="cursor-pointer text-xl transition"
                            :class="customerData?.is_favourite ? 'text-pink-500' : 'text-pink-300 hover:text-pink-400'"
                            @click="customerData?.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)" />
                    </div>
                </div>
            </div>

            <div v-if="Object.keys(customerData?.offers_data || {})?.length" class="w-full">
                <Discount :offers_data="customerData?.offers_data" class="justify-center" template="agnes_and_cat" />
            </div>

            <hr class="border-gray-200" />

            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">{{ trans("Price (Excl. Tax)") }}</div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span v-if="bestOffer" class="text-sm font-medium text-gray-400 line-through">
                            {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                        </span>
                        <span class="text-lg font-bold text-black">
                            {{ locale.currencyFormat(currency?.code, displayedPrice) }}
                        </span>
                        <span class="text-xs text-gray-500">
                            ({{ locale.currencyFormat(currency?.code, displayedPricePerUnit) }}/{{ product.unit }})
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">{{ trans("RRP") }}</div>
                    <div class="mt-1 text-sm text-gray-600">
                        {{ locale.currencyFormatRrp(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                    </div>
                </div>
            </div>

            <div v-if="layout?.iris?.is_logged_in" class="flex items-baseline gap-1 text-xs text-rose-500">
                <span>{{ trans("Profit") }}:</span>
                <span class="font-semibold">{{ locale.currencyFormat(currency?.code, displayedProfit || 0) }}</span>
                <span>({{ displayedMargin }})</span>

                <span class="cursor-pointer opacity-60 hover:opacity-100" @click="_popoverProfitMobile?.toggle">
                    <FontAwesomeIcon :icon="faPlusCircle" fixed-width aria-hidden="true" />
                </span>

                <Popover ref="_popoverProfitMobile" class="max-w-[90vw]">
                    <ProfitCalculationList :product="fieldValue.product" />
                </Popover>
            </div>

            <div v-if="layout?.iris?.is_logged_in && offersData?.number_offers > 0"
                class="offers flex flex-col items-start gap-1">
                <template v-if="bestOffer?.type === 'Category Quantity Ordered Order Interval'">
                    <GRAmnestyPriceLabel v-if="layout?.user?.gr_data?.amnesty" :offer="bestOffer" />
                    <MemberPriceLabel v-else-if="isGoldRewardCustomer" :offer="bestOffer" />
                    <NonMemberPriceLabel v-else :product="product" />
                </template>

                <DiscountByType v-if="showDiscount" template="products_triggers_label" :offers_data="offersData" />

                <DiscountByType v-if="isPurchasable && bestOffer?.type !== 'Category Quantity Ordered Order Interval'"
                    template="max_discount" :offers_data="offersData" />
            </div>

            <hr class="border-gray-200" />

            <StepDiscountOffer
                v-if="layout?.iris?.is_logged_in && isPurchasable && product.step_discount?.steps?.length"
                :stepDiscount="product.step_discount" :currencyCode="product.currency_code ?? currency?.code"
                :originalPrice="product.price" :unit="product.unit" :units="product.units"
                :quantity="orderedQuantity" :isSubmitting="isMobileStepSyncing"
                @selectQuantity="onSelectStepQuantityMobile" />

            <button v-if="layout?.iris?.is_logged_in && product.stock <= 0 && layout?.outboxes?.oos_notification?.state === 'active'"
                @click="product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                class="flex items-center gap-2 rounded-full border bg-gray-100 px-3 py-2 text-sm">
                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope" />
                <span>
                    {{
                        product.is_back_in_stock
                            ? trans('will be notified when in Stock')
                            : trans('Remind me')
                    }}
                </span>
            </button>

            <template v-if="layout?.iris?.is_logged_in">
                <EcomAddToBasketv2 v-if="product.stock > 0" ref="_mobileAddToBasket" v-model:product="product"
                    :customerData="customerData" :key="keyCustomer" class="button-basket w-full" />
                <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />
            </template>

            <LinkIris v-else :href="urlLoginWithRedirect()"
                class="block w-full rounded border px-3 py-2 text-center text-sm text-gray-600">
                {{ trans("Login or Register for Wholesale Prices") }}
            </LinkIris>

            <div v-if="productTags.length" class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div v-for="tag in productTags" :key="tag.id" class="flex items-center gap-2 text-gray-400">
                    <Image v-if="tag.image" :src="tag.image" :alt="tag.name" class="h-4 w-4 object-contain grayscale opacity-70" />
                    <FontAwesomeIcon v-else :icon="faCheckCircle" class="text-sm" />
                    <span class="text-xs font-medium">{{ tag.label || tag.name }}</span>
                </div>
            </div>

            <component :is="bespokeData?.link?.href ? LinkIris : 'div'"
                v-if="showBespoke"
                :href="bespokeData?.link?.href"
                :type="bespokeData?.link?.type"
                class="block">
                <div class="flex items-center gap-4 rounded-lg border border-gray-200 bg-[#F4F4F4] px-4 py-3">
                    <FontAwesomeIcon :icon="faPencil" class="shrink-0 text-lg text-gray-500" />
                    <div class="text-sm">
                        <div v-if="bespokeData?.title" class="font-semibold text-gray-800">{{ bespokeData.title }}</div>
                        <div v-if="bespokeData?.text" class="text-gray-600" v-html="bespokeData.text" />
                    </div>
                </div>
            </component>

  
            <div v-if="listProducts?.length && selectedVariantLabel" class="text-sm">
                <span v-if="variantAxisLabel" class="font-semibold">{{ variantAxisLabel }}:</span>
                <span class="ml-1">{{ selectedVariantLabel }}</span>
            </div>

            <Swiper v-if="listProducts?.length" :slides-per-view="2.4" :space-between="12">
                <SwiperSlide v-for="item in listProducts" :key="item.id">
                    <button @click="onSelectProduct(item)" :disabled="item.code === product.code"
                        class="w-full overflow-hidden rounded-xl border">
                        <div class="relative aspect-square bg-gray-50">
                            <Image v-if="item?.web_images?.main?.original" :src="item.web_images.main.original"
                                class="absolute inset-0 h-full w-full object-contain" />
                        </div>
                        <div class="truncate p-1 text-center text-xs">
                            {{ item.variant_label }}
                        </div>
                    </button>
                </SwiperSlide>
            </Swiper>

            <LinkIris v-if="layout?.iris?.is_logged_in && fieldValue?.setting?.appointment && fieldValue?.appointment_data?.link?.href"
                :href="fieldValue?.appointment_data?.link?.href" :type="fieldValue?.appointment_data?.link?.type">
                <div class="flex items-center gap-3 rounded-lg border bg-[#F4F4F4] px-4 py-2">
                    <FontAwesomeIcon :icon="faMapMarkerAlt" />
                    <div v-html="fieldValue?.appointment_data?.text" class="text-sm underline" />
                </div>
            </LinkIris>

            <div v-if="layout?.iris?.is_logged_in && fieldValue?.delivery_info?.text"
                v-html="fieldValue.delivery_info.text" class="text-sm" />

            <div v-if="fieldValue?.setting?.payments_and_policy && fieldValue.paymentData">
                <div class="flex flex-wrap gap-4">
                    <img v-for="logo in fieldValue.paymentData" :key="logo.code" :src="logo.image" class="h-4" />
                </div>
            </div>

            <div v-if="fieldValue?.setting?.product_specs && hasSpecifications" class="spec-card">
                <div class="spec-card-header">{{ ctrans("Product Specification") }}</div>

                <div class="spec-card-body">
                    <div v-for="row in specificationRows" :key="row.key" class="spec-row">
                        <div class="spec-label">{{ row.label }}</div>
                        <div class="spec-value">{{ row.value }}</div>
                    </div>

                    <div v-if="countriesOfOrigin.length" class="spec-row">
                        <div class="spec-label">{{ trans('Origin Country') }}</div>
                        <div class="spec-value flex flex-col gap-1 justify-end align-end">
                            <div v-for="country in countriesOfOrigin" :key="country.code" class="flex items-center gap-2 ">
                                <img :src="'/flags/' + country.code.toLowerCase() + '.png'" :alt="country.name"
                                    :title="country.name" class="h-4 w-auto" />
                                <span>{{ country.name }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="fieldValue?.setting?.product_specs && documentations.length" class="spec-card">
                <div class="spec-card-header">{{ ctrans("Product Documentations") }}</div>

                <div class="spec-card-body">
                    <div v-for="item in documentations" :key="`${item.label}-${item.caption}`" class="spec-row">
                        <div class="spec-label">{{ item.label }}</div>
                        <div class="spec-value flex flex-wrap items-center justify-end gap-2">
                            <a :href="item.url" target="_blank" class="text-xs text-gray-500 underline hover:text-gray-700">
                                {{ trans("Link") }}
                            </a>
                            <a :href="item.url" target="_blank" download class="doc-download">
                                {{ trans("Download File") }}
                                <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
.product-title {
  color: var(--theme-color-4, #1e3a5f);
}

.button-basket :deep(.qty-price-new) {
  @apply font-semibold text-black text-base sm:text-lg md:text-xl;
}

.spec-card {
  @apply rounded-lg border border-gray-200 bg-[#F4F4F4] overflow-hidden;
}

.spec-card-header {
  @apply px-4 py-3 text-sm font-semibold text-black  border-gray-200;
}

.spec-card-body {
  @apply px-4 py-2;
}

.spec-row {
  @apply grid grid-cols-[40%_60%] items-center gap-2  border-gray-200 py-2 ;
}

.spec-label {
  @apply text-xs font-medium text-gray-700;
}

.spec-value {
  @apply text-right text-xs font-light leading-snug text-gray-500;
}

.doc-download {
  @apply inline-flex items-center gap-1.5 rounded-md bg-gray-800 px-2.5 py-1 text-[11px] font-medium text-white transition hover:bg-black;
}

:deep(.swiper-button-disabled) {
  @apply opacity-30 cursor-default;
}

.offers :deep(.offer-max-discount) {
  @apply bg-[#A80000] border border-red-900 text-gray-100 w-fit flex items-center
    rounded-sm px-1 py-0.5 text-sm
    sm:px-1.5 sm:py-1 sm:text-sm
    md:px-2 md:py-1;
}

.offers :deep(.offer-trigger-label) {
  @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs md:text-xs;
  border-color: var(--theme-color-4);
  color: var(--theme-color-4);
}

.offers :deep(.member-badge) {
  @apply bg-gray-400 rounded px-2 py-0.5 text-xs md:text-sm text-white w-fit;
}
</style>
