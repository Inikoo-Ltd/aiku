<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, nextTick, useAttrs } from "vue"
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
    faPlusCircle
} from "@fal"

import {
    faHeart as fasHeart,
    faPlus,
    faMinus,
    faArrowToBottom,
    faMapMarkerAlt,
    faFileDownload
} from "@fas"

import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"
import { faImage } from "@far"

import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import "swiper/css/navigation"
import { Navigation } from "swiper/modules"

import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"
import Product2Image from "./Product2Image.vue"
import Image from "@common/Components/Image.vue"

import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { getStyles } from "@/Composables/styles"
import { ulid } from "ulid"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"
import { Popover } from "primevue"

library.add(
    faCube,
    faLink,
    faPlus,
    faMinus,
    faFileCheck,
    faFilePdf,
    faFileWord,
    faFileDownload,
    faArrowToBottom,
    faMapMarkerAlt,
    faImage,
    faChevronLeft,
    faChevronRight,
    faPlusCircle
)

type TemplateType = 'webpage' | 'template'

interface ProductResource {
    id: number
    name: string
    code: string
    image?: { source: any }

    price: number
    price_per_unit?: number
    rrp_per_unit?: number
    profit?: number
    margin?: string

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

    description?: string
    description_extra?: string

    web_images?: any
    variant_label?: string

    attachments?: any[]
    specifications?: any
}

const props = withDefaults(defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: object
    templateEdit?: TemplateType
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
    currency?: {
        code: string
        name: string
    }
    videoSetup: {
        url: string
    }
    validImages: object
    product?: ProductResource
    customerData?: any
    listProducts?: ProductResource[]
    isLoadingFavourite?: boolean
    isLoadingRemindBackInStock?: boolean
}>(), {
    templateEdit: 'webpage'
})

const emits = defineEmits<{
    (e: 'onDescriptionUpdate', key: string, val: string): void
    (e: "setFavorite", value: ProductResource): void
    (e: "unsetFavorite", value: ProductResource): void
    (e: "setBackInStock", value: ProductResource): void
    (e: "unsetBackInStock", value: ProductResource): void
    (e: "selectProduct", value: ProductResource): void
}>()

const layout = inject<any>("layout", {})
const locale = useLocaleStore()
const attrs = useAttrs()
const keyCustomer = ref(ulid())

const expanded = ref(false)
const product = ref<ProductResource>(props.product ?? props.modelValue.product)

watch(
    () => props.product ?? props.modelValue.product,
    value => (product.value = value),
    { deep: true }
)

const currency = computed(() => layout?.iris?.currency ?? props.currency)

const groupedAttachments = computed(() => {
    if (!product.value?.attachments?.length) return {}
    return product.value.attachments.reduce((acc: any, file: any) => {
        acc[file.label] ??= []
        acc[file.label].push(file)
        return acc
    }, {})
})

const countriesOfOrigin = computed(() =>
    (product.value?.specifications?.countries_of_origin || []).filter((country: any) => country?.code)
)

const availableStock = computed(() => props.customerData?.stock ?? product.value?.stock)

const variantAxisLabel = computed(() =>
    (props.modelValue?.variant?.data?.variants || [])
        .map((variant: any) => variant?.label)
        .filter(Boolean)
        .join(" / ")
)

const selectedVariantLabel = computed(() =>
    props.listProducts?.find(item => item.code === product.value?.code)?.variant_label
    || product.value?.variant_label
    || ""
)

const toggleExpanded = () => {
    expanded.value = !expanded.value
}

const onAddFavourite = (p: ProductResource) => emits("setFavorite", p)
const onUnselectFavourite = (p: ProductResource) => emits("unsetFavorite", p)
const onAddBackInStock = (p: ProductResource) => emits("setBackInStock", p)
const onUnselectBackInStock = (p: ProductResource) => emits("unsetBackInStock", p)
const onSelectProduct = (p: ProductResource) => emits("selectProduct", p)

const extractFileType = (mime = "") =>
    mime.split("/")[1]?.split("+")[0]?.toLowerCase() || ""

const getIcon = (type: string) => {
    if (type === "pdf") return faFilePdf
    if (["doc", "docx", "msword"].includes(type)) return faFileWord
    return faFileCheck
}

const _popoverProfit = ref(null)
const _popoverProfitMobile = ref(null)

const variantPrevEl = ref<HTMLElement | null>(null)
const variantNextEl = ref<HTMLElement | null>(null)
const variantNavigation = ref<{ prevEl: HTMLElement | null; nextEl: HTMLElement | null }>({
    prevEl: null,
    nextEl: null
})

onMounted(async () => {
    if (props.templateEdit != 'webpage') {
        layout.iris = {
            is_logged_in: true
        }
    }

    await nextTick()
    variantNavigation.value.prevEl = variantPrevEl.value
    variantNavigation.value.nextEl = variantNextEl.value
})

defineOptions({
    inheritAttrs: false,
})
</script>

<template>
    <div v-if="screenType !== 'mobile'" :id="modelValue?.id ? modelValue?.id : 'product-iris-2-ecom' + indexBlock"
        v-bind="attrs" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 pointer-events-none" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            marginLeft: 'auto',
            marginRight: 'auto'
        }">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <Product2Image :images="validImages" :video="videoSetup?.url" />

                    <div class="
                        group
                        flex items-center gap-3
                        py-2 px-4 mt-4 w-fit
                        border rounded-lg bg-[#f9f8f5]
                        transition
                        hover:bg-gray-100 hover:border-gray-300
                    ">
                        <FontAwesomeIcon :icon="faArrowToBottom"
                            class="text-gray-600 transition group-hover:text-gray-800 shrink-0" />

                        <span class="
                            font-medium text-sm text-gray-800
                            truncate max-w-[420px]
                        " :title="`${trans('Download Marketing Materials for')} ${product.name}`">
                            {{ trans('Download Marketing Materials for') }} {{ product.name }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-span-5 self-start">
                <div class="relative flex justify-between items-start mb-4">
                    <div class="w-full">
                        <div class="text-xl text-black font-bold w-[80%]">
                            <span v-if="product.units > 1">{{ product.units }}x</span> {{ product.name }}
                        </div>

                        <div v-if="layout?.iris?.is_logged_in" class="flex justify-between items-center mt-2">
                            <div class="flex items-center gap-2 text-sm">
                                <span :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{
                                        product.stock > 0
                                            ? `${trans("In stock")} (${availableStock})`
                                            : trans("Out Of Stock")
                                    }}
                                </span>
                            </div>

                            <button v-if="product.stock <= 0 && layout?.outboxes?.oos_notification?.state == 'active'"
                                @click="
                                    product.is_back_in_stock
                                        ? onUnselectBackInStock(product)
                                        : onAddBackInStock(product)
                                    "
                                class="absolute right-0 bottom-2 flex items-center gap-2 px-3 py-1.5 text-sm rounded-full border bg-gray-100 hover:bg-gray-200">
                                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                                <FontAwesomeIcon v-else
                                    :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
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
                    </div>

                    <div v-if="layout?.retina?.type !== 'dropshipping' && layout?.iris?.is_logged_in">
                        <LoadingIcon v-if="isLoadingFavourite" class="text-gray-500 text-2xl" />
                        <div v-else class="cursor-pointer text-2xl" @click="
                            customerData?.is_favourite
                                ? onUnselectFavourite(product)
                                : onAddFavourite(product)
                            ">
                            <FontAwesomeIcon v-if="customerData?.is_favourite" :icon="fasHeart" class="text-pink-500" />
                            <FontAwesomeIcon v-else :icon="faHeart" class="text-pink-300 hover:text-pink-400" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-start gap-4">
                    <div>
                        <div class="text-2xl font-bold leading-tight text-black">
                            {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                        </div>

                        <div class="text-sm text-black">
                            ({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/{{ product.unit }})
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-xs text-black leading-tight">{{ trans("Retail Price") }}:</p>
                        <p class="text-xs text-black leading-tight line-through">
                            {{ locale.currencyFormatRrp(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                        </p>

                        <p class="mt-2 text-xs text-black leading-tight">{{ trans("Profit") }}:</p>
                        <div class="flex items-baseline justify-end gap-1 text-black">
                            <span class="text-base font-bold">
                                {{ locale.currencyFormat(currency?.code, product.profit || 0) }}
                            </span>
                            <span class="text-sm">({{ product.margin }})</span>

                            <span v-if="layout?.iris?.is_logged_in" class="cursor-pointer opacity-60 hover:opacity-100"
                                @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show"
                                @mouseleave="_popoverProfit?.hide">
                                <FontAwesomeIcon :icon="faPlusCircle" fixed-width aria-hidden="true" />
                            </span>

                            <Popover ref="_popoverProfit" class="max-w-[90vw] md:max-w-none sm:min-w-[350px]">
                                <ProfitCalculationList :product="modelValue.product" />
                            </Popover>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 mt-4 mb-4">
                    <div v-if="layout?.iris?.is_logged_in" class="w-full">
                        <EcomAddToBasketv2 v-if="product.stock > 0" v-model:product="product"
                            :customerData="customerData" :key="keyCustomer"
                            :buttonStyle="getStyles(modelValue?.button?.properties, screenType)" class="button-basket" />
                        <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />
                    </div>

                    <div v-else class="w-full block text-center border text-sm px-3 py-2 rounded text-gray-600"
                        :style="getStyles(modelValue?.buttonLogin?.properties, screenType)">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </div>
                </div>

                <div v-if="listProducts && listProducts.length > 0" class="mb-4">
                    <div v-if="selectedVariantLabel" class="text-sm mb-1">
                        <span v-if="variantAxisLabel" class="font-semibold">{{ variantAxisLabel }}:</span>
                        <span class="ml-1">{{ selectedVariantLabel }}</span>
                    </div>

                    <div class="relative px-5">
                        <button ref="variantPrevEl" type="button"
                            class="absolute left-0 top-1/2 -translate-y-1/2 z-10 text-gray-500 hover:text-gray-800">
                            <FontAwesomeIcon :icon="faChevronLeft" class="text-sm" />
                        </button>

                        <button ref="variantNextEl" type="button"
                            class="absolute right-0 top-1/2 -translate-y-1/2 z-10 text-gray-500 hover:text-gray-800">
                            <FontAwesomeIcon :icon="faChevronRight" class="text-sm" />
                        </button>

                        <Swiper :modules="[Navigation]" :navigation="variantNavigation" :space-between="8"
                            :slides-per-view="4" :grab-cursor="true" :breakpoints="{
                                640: { slidesPerView: 4 },
                                768: { slidesPerView: 4 },
                                1024: { slidesPerView: 4 }
                            }">
                            <SwiperSlide v-for="item in listProducts" :key="item.id">
                                <button @click="onSelectProduct(item)" :disabled="item.code === product.code"
                                    class="group relative w-full rounded-lg border bg-white overflow-hidden transition flex flex-col"
                                    :class="item.code === product.code
                                        ? 'ring-1 primary'
                                        : 'border-gray-200 hover:border-gray-300'">
                                    <div class="relative w-full aspect-square bg-gray-50 overflow-hidden">
                                        <Image v-if="item?.web_images?.main?.original"
                                            :src="item.web_images.main.original" :alt="item.code" loading="lazy"
                                            class="absolute inset-0 w-full h-full object-contain transition-transform duration-300 ease-out group-hover:scale-110" />

                                        <FontAwesomeIcon v-else :icon="faImage"
                                            class="absolute inset-0 m-auto text-gray-300 text-xl" />

                                        <div
                                            class="pointer-events-none absolute bottom-1 left-1 right-1 opacity-0 translate-y-1 transition-all duration-200 group-hover:opacity-100 group-hover:translate-y-0">
                                            <span
                                                class="block text-[11px] font-medium px-2 py-0.5 rounded text-center truncate bg-gray-900/80 text-white backdrop-blur">
                                                {{ item.variant_label }}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </SwiperSlide>
                        </Swiper>
                    </div>
                </div>

                <div v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment && modelValue?.appointment_data?.text && modelValue?.appointment_data?.link?.href"
                    class="group flex items-center gap-3 py-2 px-4 mt-4 w-full border rounded-lg bg-[#f9f8f5] transition hover:bg-gray-100 hover:border-gray-300 my-2">
                    <FontAwesomeIcon :icon="faMapMarkerAlt"
                        class="text-black transition shrink-0" />

                    <span class="font-medium text-sm underline text-black truncate max-w-[420px]">
                        <div v-html="modelValue?.appointment_data?.text"></div>
                    </span>
                </div>

                <div v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment" class="text-sm font-medium">
                    <div v-html="modelValue?.delivery_info?.text"></div>
                </div>

                <div v-if="modelValue.setting?.payments_and_policy && modelValue.paymentData" class="my-2">
                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>

                <div v-if="modelValue?.setting?.product_specs" class="my-2">
                    <div class="border rounded-lg bg-[#f9f8f5] p-4">
                        <div class="font-semibold text-base mb-2 text-black">{{ ctrans("Product Specification") }}</div>

                        <div class="w-full">
                            <div v-if="product?.specifications?.origin" class="spec-row">
                                <div class="spec-cell">{{ trans('Origin') }}</div>
                                <div class="spec-cell">{{ product.specifications.origin }}</div>
                            </div>

                            <div v-if="product?.specifications?.marketing_weight" class="spec-row">
                                <div class="spec-cell">{{ trans('Net Weight') }}</div>
                                <div class="spec-cell">
                                    {{ product.specifications.marketing_weight }} g/{{ product.specifications.unit }}
                                </div>
                            </div>

                            <div v-if="product?.specifications?.gross_weight" class="spec-row">
                                <div class="spec-cell">{{ trans("Shipping Weight") }}</div>
                                <div class="spec-cell">{{ product.specifications.gross_weight }} g</div>
                            </div>

                            <div v-if="product?.specifications?.dimensions" class="spec-row">
                                <div class="spec-cell">{{ trans("Dimensions") }}</div>
                                <div class="spec-cell">{{ product.specifications.dimensions }}</div>
                            </div>

                            <div v-if="product?.specifications?.ingredients" class="spec-row">
                                <div class="spec-cell">{{ trans('Materials/Ingredients') }}</div>
                                <div class="spec-cell">{{ product.specifications.ingredients }}</div>
                            </div>

                            <div v-if="product?.specifications?.barcode" class="spec-row">
                                <div class="spec-cell">{{ trans('Barcode') }}</div>
                                <div class="spec-cell">{{ product.specifications.barcode }}</div>
                            </div>

                            <div v-if="product?.specifications?.cpnp" class="spec-row">
                                <div class="spec-cell">{{ trans('cpnp') }}</div>
                                <div class="spec-cell">{{ product.specifications.cpnp }}</div>
                            </div>

                            <div v-if="countriesOfOrigin.length" class="spec-row">
                                <div class="spec-cell">{{ trans('Origin Country') }}</div>

                                <div class="spec-cell flex flex-col gap-1">
                                    <div v-for="country in countriesOfOrigin" :key="country.code"
                                        class="flex items-center gap-2">
                                        <img :src="'/flags/' + country.code.toLowerCase() + '.png'" :alt="country.name"
                                            :title="country.name" class="h-4 w-auto" />
                                        <span>{{ country.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <div v-for="(items, label) in groupedAttachments" :key="label" class="spec-row">
                                <div class="spec-cell">{{ label }}</div>

                                <div class="spec-cell space-y-1">
                                    <div v-for="item in items" :key="item.caption"
                                        class="text-xs font-thin text-black underline cursor-pointer flex items-center">
                                        <a :href="item.url" target="_blank" class="flex items-center">
                                            <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))"
                                                class="mr-1" />
                                            {{ item.caption }}.{{ extractFileType(item.mime_type) }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 text-sm text-gray-800">
            <div v-html="product.description" />

            <div v-if="expanded" class="text-sm text-gray-700 my-1">
                <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra" />
            </div>

            <button v-if="product.description_extra" @click="toggleExpanded" class="mt-2 text-sm underline">
                {{ expanded ? trans("Show Less") : trans("Read More") }}
            </button>
        </div>
    </div>

    <div v-if="screenType === 'mobile'" v-bind="attrs"
        :id="modelValue?.id ? modelValue?.id : 'product-iris-2-ecom' + indexBlock"
        class="bg-white pointer-events-none">

        <Product2Image :images="validImages" :video="videoSetup?.url" />

        <div class="px-4 py-4 space-y-5 text-gray-800">

            <h1 class="!text-xl text-black font-bold leading-tight">
                <span v-if="product.units > 1">{{ product.units }}x</span>
                {{ product.name }}
            </h1>

            <div v-if="layout?.iris?.is_logged_in" class="flex items-center justify-between mt-1">
                <span class="text-sm font-medium" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                    {{
                        product.stock > 0
                            ? `${trans('In stock')} (${availableStock})`
                            : trans('Out Of Stock')
                    }}
                </span>

                <div v-if="layout?.retina?.type !== 'dropshipping'">
                    <LoadingIcon v-if="isLoadingFavourite" class="text-gray-400" />
                    <FontAwesomeIcon v-else :icon="customerData?.is_favourite ? fasHeart : faHeart"
                        class="text-xl cursor-pointer transition" :class="customerData?.is_favourite
                            ? 'text-pink-500'
                            : 'text-pink-300 hover:text-pink-400'" @click="
                                customerData?.is_favourite
                                    ? onUnselectFavourite(product)
                                    : onAddFavourite(product)
                                " />
                </div>
            </div>

            <div class="flex justify-between items-start gap-4">
                <div>
                    <div class="text-2xl font-bold leading-tight text-black">
                        {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                    </div>

                    <div class="text-sm text-black">
                        ({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/{{ product.unit }})
                    </div>
                </div>

                <div class="text-right">
                    <p class="text-xs text-black leading-tight">{{ trans("Retail Price") }}:</p>
                    <p class="text-xs text-black leading-tight line-through">
                        {{ locale.currencyFormatRrp(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                    </p>

                    <p class="mt-2 text-xs text-black leading-tight">{{ trans("Profit") }}:</p>
                    <div class="flex items-baseline justify-end gap-1 text-black">
                        <span class="text-base font-bold">
                            {{ locale.currencyFormat(currency?.code, product.profit || 0) }}
                        </span>
                        <span class="text-sm">({{ product.margin }})</span>

                        <span v-if="layout?.iris?.is_logged_in" class="cursor-pointer opacity-60 hover:opacity-100"
                            @click="_popoverProfitMobile?.toggle">
                            <FontAwesomeIcon :icon="faPlusCircle" fixed-width aria-hidden="true" />
                        </span>

                        <Popover ref="_popoverProfitMobile" class="max-w-[90vw]">
                            <ProfitCalculationList :product="modelValue.product" />
                        </Popover>
                    </div>
                </div>
            </div>

            <button v-if="product.stock <= 0 && layout?.outboxes?.oos_notification?.state === 'active'" @click="
                product.is_back_in_stock
                    ? onUnselectBackInStock(product)
                    : onAddBackInStock(product)
                " class="flex items-center gap-2 px-3 py-2 rounded-full border bg-gray-100 text-sm">
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

            <EcomAddToBasketv2 v-if="product.stock > 0" v-model:product="product" :customerData="customerData"
                :key="keyCustomer" class="w-full button-basket" />
            <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />

            <div class="flex items-center gap-3 px-4 py-2 rounded-lg border bg-[#f9f8f5]">
                <FontAwesomeIcon :icon="faArrowToBottom" />
                <span class="text-sm font-medium truncate">
                    {{ trans('Download Marketing Materials for') }} {{ product.name }}
                </span>
            </div>

            <div v-if="listProducts?.length && selectedVariantLabel" class="text-sm">
                <span v-if="variantAxisLabel" class="font-semibold">{{ variantAxisLabel }}:</span>
                <span class="ml-1">{{ selectedVariantLabel }}</span>
            </div>

            <Swiper v-if="listProducts?.length" :slides-per-view="2.4" :space-between="12">
                <SwiperSlide v-for="item in listProducts" :key="item.id">
                    <button @click="onSelectProduct(item)" :disabled="item.code === product.code"
                        class="rounded-xl border overflow-hidden w-full">
                        <div class="aspect-square bg-gray-50 relative">
                            <Image v-if="item?.web_images?.main?.original" :src="item.web_images.main.original"
                                class="absolute inset-0 w-full h-full object-contain" />
                        </div>
                        <div class="p-1 text-xs truncate text-center">
                            {{ item.variant_label }}
                        </div>
                    </button>
                </SwiperSlide>
            </Swiper>

            <div v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment && modelValue?.appointment_data?.text && modelValue?.appointment_data?.link?.href"
                class="flex gap-3 items-center px-4 py-2 border rounded-lg bg-[#f9f8f5] text-black">
                <FontAwesomeIcon :icon="faMapMarkerAlt" />
                <div v-html="modelValue?.appointment_data?.text" class="text-sm underline text-black" />
            </div>

            <div v-if="layout?.iris?.is_logged_in && modelValue?.delivery_info?.text"
                v-html="modelValue.delivery_info.text" class="text-sm" />

            <div v-if="modelValue?.setting?.payments_and_policy && modelValue.paymentData">
                <div class="flex flex-wrap gap-4">
                    <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" class="h-4" />
                </div>
            </div>

            <div v-if="modelValue?.setting?.product_specs">
                <div class="border rounded-lg bg-[#f9f8f5] p-4">
                    <div class="font-semibold text-base mb-2 text-black">{{ ctrans("Product Specification") }}</div>

                    <div class="w-full">
                        <div v-if="product?.specifications?.origin" class="spec-row">
                            <div class="spec-cell">{{ trans('Origin') }}</div>
                            <div class="spec-cell">{{ product.specifications.origin }}</div>
                        </div>

                        <div v-if="product?.specifications?.marketing_weight" class="spec-row">
                            <div class="spec-cell">{{ trans('Net Weight') }}</div>
                            <div class="spec-cell">
                                {{ product.specifications.marketing_weight }} g/{{ product.specifications.unit }}
                            </div>
                        </div>

                        <div v-if="product?.specifications?.gross_weight" class="spec-row">
                            <div class="spec-cell">{{ trans("Shipping Weight") }}</div>
                            <div class="spec-cell">{{ product.specifications.gross_weight }} g</div>
                        </div>

                        <div v-if="product?.specifications?.dimensions" class="spec-row">
                            <div class="spec-cell">{{ trans("Dimensions") }}</div>
                            <div class="spec-cell">{{ product.specifications.dimensions }}</div>
                        </div>

                        <div v-if="product?.specifications?.ingredients" class="spec-row">
                            <div class="spec-cell">{{ trans('Materials/Ingredients') }}</div>
                            <div class="spec-cell">{{ product.specifications.ingredients }}</div>
                        </div>

                        <div v-if="product?.specifications?.barcode" class="spec-row">
                            <div class="spec-cell">{{ trans('Barcode') }}</div>
                            <div class="spec-cell">{{ product.specifications.barcode }}</div>
                        </div>

                        <div v-if="product?.specifications?.cpnp" class="spec-row">
                            <div class="spec-cell">{{ trans('cpnp') }}</div>
                            <div class="spec-cell">{{ product.specifications.cpnp }}</div>
                        </div>

                        <div v-if="countriesOfOrigin.length" class="spec-row">
                            <div class="spec-cell">{{ trans('Origin Country') }}</div>

                            <div class="spec-cell flex flex-col gap-1">
                                <div v-for="country in countriesOfOrigin" :key="country.code"
                                    class="flex items-center gap-2">
                                    <img :src="'/flags/' + country.code.toLowerCase() + '.png'" :alt="country.name"
                                        :title="country.name" class="h-4 w-auto" />
                                    <span>{{ country.name }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-for="(items, label) in groupedAttachments" :key="label" class="spec-row">
                            <div class="spec-cell">{{ label }}</div>

                            <div class="spec-cell space-y-1">
                                <div v-for="item in items" :key="item.caption"
                                    class="text-xs font-thin text-black underline cursor-pointer flex items-center">
                                    <a :href="item.url" target="_blank" class="flex items-center">
                                        <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))"
                                            class="mr-1" />
                                        {{ item.caption }}.{{ extractFileType(item.mime_type) }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-sm text-gray-800">
                <div v-html="product.description" />
                <div v-if="expanded" v-html="product.description_extra" class="mt-2" />
                <button v-if="product.description_extra" @click="toggleExpanded" class="underline text-sm mt-2">
                    {{ expanded ? trans('Show Less') : trans('Read More') }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.button-basket :deep(.qty-price-new) {
    @apply font-semibold text-black text-base sm:text-lg md:text-xl;
}

.spec-row {
    @apply grid grid-cols-[42%_58%] items-start;
}

.spec-cell {
    @apply px-2 py-1 text-xs font-light leading-snug text-black;
}

.spec-row > .spec-cell:first-child {
    @apply text-black;
}

:deep(.swiper-button-disabled) {
    @apply opacity-30 cursor-default;
}
</style>
