<script setup lang="ts">
import { ref, inject, computed, watch, onMounted, nextTick } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCube, faLink, faHeart, faEnvelope } from "@fal"
import { faCircle, faHeart as fasHeart, faDotCircle, faPlus, faMinus, faChevronCircleLeft, faChevronCircleRight } from "@fas"
import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"

import ImageProducts from "@/Components/Product/ImageProducts.vue"
import ProductContentsIris from "@/Components/CMS/Webpage/Product1/ProductContentIris.vue"
import InformationSideProduct from "@/Components/CMS/Webpage/Product1/InformationSideProduct.vue"
import ProductPrices from "@/Components/CMS/Webpage/Product1/ProductPrices.vue"

import Image from "@/Components/Image.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"

import { trans } from "laravel-vue-i18n"
import { urlLoginWithRedirect } from "@/Composables/urlLoginWithRedirect"
import { getStyles } from "@/Composables/styles"
import { ulid } from "ulid"
import LabelComingSoon from "@/Components/Iris/Products/LabelComingSoon.vue"

import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import { faImage } from "@far"
import NonMemberPriceLabel from "@/Components/Utils/Iris/Family/NonMemberPriceLabel.vue"
import ProductPrices2 from "../ProductPrices2.vue"
import { Popover } from "primevue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import MemberPriceLabel from "@/Components/Utils/Iris/Family/MemberPriceLabel.vue"
import ProfitCalculationList from "@/Components/Utils/Iris/ProfitCalculationList.vue"

import { Navigation, Thumbs } from 'swiper/modules'
import AvailableVolOfferLabel from "@/Components/Utils/Iris/AvailableVolOfferLabel.vue"
import DiscountByType from "@/Components/Utils/Label/DiscountByType.vue"



// Register icons
library.add(faCube, faLink, faPlus, faMinus)

interface ProductResource {
    id: number
    name: string
    code: string
    image?: { source: any }
    currency_code: string
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
}

const props = withDefaults(
    defineProps<{
        fieldValue: {
            product: {   // WebBlockProductResource
                discounted_price: number
                discounted_price_per_unit: number
                discounted_profit: number
                discounted_profit_per_unit: number
                discounted_margin: number

                offers_data: {
                    number_offers: 1
                    offers: {
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
                    }[]
                    best_percentage_off: {
                        percentage_off: string
                        offer_id: number
                    }
                }
            }
        }
        webpageData?: any
        blockData?: object
        screenType: "mobile" | "tablet" | "desktop"
        validImages: object
        customerData: {  // \Json\GetIrisProductEcomOrdering  (no cache)

        }
        product: ProductResource  // Catalogue\GetProductDetail (no cache)
        isLoadingRemindBackInStock: boolean
        isLoadingFavourite: boolean
        videoSetup: { url: string }
        listProducts: ProductResource[]
    }>(),
    {}
)

const locale = inject('locale', aikuLocaleStructure)

const emits = defineEmits<{
    (e: "setFavorite", value: any[]): void
    (e: "unsetFavorite", value: any[]): void
    (e: "setBackInStock", value: any[]): void
    (e: "unsetBackInStock", value: any[]): void
    (e: "selectProduct", value: any[]): void
}>()

const product = ref(props.product)
const layout = inject("layout", {})
const expanded = ref(false)
const keyCustomer = ref(ulid())

const toggleExpanded = () => (expanded.value = !expanded.value)

const onAddFavourite = (p: ProductResource) => emits("setFavorite", p)
const onUnselectFavourite = (p: ProductResource) => emits("unsetFavorite", p)
const onAddBackInStock = (p: ProductResource) => emits("setBackInStock", p)
const onUnselectBackInStock = (p: ProductResource) => emits("unsetBackInStock", p)
const onSelectProduct = (p: ProductResource) => emits("selectProduct", p)


watch(
    () => props.product,
    (newProduct) => {
       product.value = newProduct
       console.log('product',product.value)
    },
    { deep: true }
)


const _popoverProfit = ref(null)

// console.log('fdsfds', props.fieldValue.product)
const getBestOffer = (offerId: string) => {
    if (!offerId) {
        return
    }

    return product.value?.offers_data?.offers?.[offerId] 
}



const variantPrevEl = ref<HTMLElement | null>(null)
const variantNextEl = ref<HTMLElement | null>(null)

const varinatNavigation = ref({
  prevEl: null as HTMLElement | null,
  nextEl: null as HTMLElement | null,
})

watch([variantPrevEl, variantNextEl], () => {
  if (variantPrevEl.value && variantNextEl.value) {
    varinatNavigation.value = {
      prevEl: variantPrevEl.value,
      nextEl: variantNextEl.value,
    }
  }
})

onMounted(async () => {
  await nextTick()
  varinatNavigation.value.prevEl = variantPrevEl.value
  varinatNavigation.value.nextEl = variantNextEl.value
})



</script>


<template>
    <!-- DESKTOP -->
    <div v-if="screenType !== 'mobile'" id="product-iris-1-ecom"
        class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block mt-4" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            marginLeft: 'auto',
            marginRight: 'auto'
        }">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <!-- LEFT: Images -->
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :key="product.code" :images="validImages" :video="videoSetup?.url" />
                </div>

                <!-- TAGS -->
                <div class="flex gap-x-10 text-gray-400 text-xs mb-6 mt-4">
                    <div v-for="(tag, index) in product.tags" :key="index" class="flex items-center gap-1">
                        <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                        <Image v-else :src="tag.image" :alt="`Thumbnail tag ${index}`"
                            class="w-[15px] h-[15px] object-cover" />
                        <span>{{ tag.name }}</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Product Info -->
            <div class="col-span-5 self-start">
                
                
                <div class="relative flex justify-between items-start mb-4 gap-x-3">
                    <div class="w-full">
                        <h1 class="text-3xl font-bold text-justify">
                            <span v-if="product.units > 1">{{ product.units }}x</span>
                            {{ product.name }}
                        </h1>

                        <div class="text-sm font-medium text-gray-600 mt-1 mb-1">
                            {{ trans("Product code") }}: {{ product.code }}
                        </div>

                        <!-- STOCK SECTION -->
                        <div v-if="layout?.iris?.is_logged_in" class="flex justify-between items-center">
                            <!-- Stock info -->
                            <LabelComingSoon v-if="product.status === 'coming-soon'" :product="product" />
                            <div v-else class="flex items-center gap-2 text-sm">
                                <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                    :class="product.stock ? 'text-green-600' : 'text-red-600'" />
                                <span>
                                    {{ product?.stock >= 250
                                        ? trans("Unlimited quantity available")
                                        : (product.stock > 0 ? trans("In stock") + ` (${product.stock} ` +
                                            trans("available") + `)` : trans("Out Of Stock"))
                                    }}
                                </span>
                            </div>

                            <!-- REMIND ME -->
                            <button v-if="!product.stock && layout?.outboxes?.oos_notification?.state == 'active'"
                                v-tooltip="customerData?.back_in_stock ? trans('You will be notify via email when the product back in stock') : trans('Click to be notified via email when the product back in stock')"
                                @click="() => customerData?.back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                                class="absolute right-0 bottom-0 inline-flex items-center gap-2 rounded-full border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-200 hover:border-gray-400">
                                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                                <FontAwesomeIcon v-else
                                    :icon="customerData?.back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                                    :class="[customerData?.back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                                <span>{{ customerData?.back_in_stock ? trans("Notified") : trans("Remind me") }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- FAVOURITE -->
                    <div v-if="layout?.iris?.is_logged_in">
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

                <!-- PRICE -->
                <!-- <ProductPrices
                    :field-value="fieldValue"
                    :key="product.code"
                    :offers_data="customerData?.offers_data"
                    :offer_net_amount_per_quantity="customerData?.offer_net_amount_per_quantity"
                    :offer_price_per_unit="customerData?.offer_price_per_unit"
                /> -->

                <ProductPrices2
                    v-if="layout?.iris?.is_logged_in"
                    :field-value="fieldValue"
                    :product="product"
                    :key="product.code"
                />

                <!-- Section: Member/Non Member label, Profit -->
                <div class="flex justify-between mt-1" v-if="layout?.iris?.is_logged_in">
                    <template v-if="product.offers_data?.number_offers > 0">
                        <div class="flex flex-col w-fit offer">
                            <template v-if="getBestOffer(product.offers_data?.best_percentage_off?.offer_id)?.type === 'Category Quantity Ordered Order Interval'">
                                <MemberPriceLabel v-if="layout?.user?.gr_data?.customer_is_gr" :offer="getBestOffer(product.offers_data?.best_percentage_off?.offer_id)" />
                                <NonMemberPriceLabel v-else :product />
                            </template>
            
                            <!-- <AvailableVolOfferLabel
                                v-if="
                                    (product.stock && !product.is_coming_soon)  // same as button add to basket conditions
                                    && !layout?.user?.gr_data?.customer_is_gr"
                                :offer="getBestOffer(product.offers_data?.best_percentage_off?.offer_id)"
                            /> -->
                             <DiscountByType v-if="(product.stock  && !product.is_coming_soon)" :offers_data="product?.offers_data" />
                        </div>
                        <div />
                    </template>
                    <div v-else />

                    
                    <!-- Section: Profit and the popover -->
                    <div class="flex justify-between items-end">
                        <span @click="_popoverProfit?.toggle">{{ trans("Profit") }}</span>:
                        <span class="text-green-500 ml-1 font-bold">
                            {{ fieldValue.product?.discounted_margin ?? fieldValue.product?.margin }}
                        </span>
                        <span @click="_popoverProfit?.toggle" @mouseenter="_popoverProfit?.show" @mouseleave="_popoverProfit?.hide"
                            class="ml-1 cursor-pointer opacity-60 hover:opacity-100"
                        >
                            <FontAwesomeIcon icon="fal fa-plus-circle" class="" fixed-width aria-hidden="true" />
                        </span>
                        
                        <!-- Popover: Question circle GR member -->
                        <Popover ref="_popoverProfit" :style="{width: '550px'}" class="py-1 px-2">
                            <ProfitCalculationList :product="fieldValue.product" />
                        </Popover>
                    </div>

                </div>

                
                <!-- Section: ADD TO CART -->
                <div class="mt-4 flex gap-2 mb-6">
                    <div v-if="layout?.iris?.is_logged_in && product.status !== 'coming-soon'" class="w-full">
                        <EcomAddToBasketv2 
                            v-if="product.stock"  
                            v-model:product="product"  
                            :customerData="customerData"
                            :key="keyCustomer" 
                            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" 
                        />
                        <div v-else>
                            <Button :label="product.status_label ?? trans('Out of stock')" type="tertiary" disabled full />
                        </div>
                    </div>

                    <LinkIris v-else :href="urlLoginWithRedirect()"
                        class="w-full block text-center border text-sm px-3 py-2 rounded text-gray-600"
                        :style="getStyles(fieldValue?.buttonLogin?.properties, screenType)">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </LinkIris>
                </div>

                
                <div v-if="listProducts && listProducts.length > 0" class="bg-white shadow-sm p-0.5 rounded-md mb-4">
                    <Swiper :modules="[Navigation]" :navigation="varinatNavigation" :space-between="6"
                        :slides-per-view="3.2" :grab-cursor="true" :breakpoints="{
                            640: { slidesPerView: 4.5 },
                            1024: { slidesPerView: 4 }
                        }">

                        <div class="absolute inset-0 pointer-events-none z-50">
                            <div ref="variantPrevEl"
                                class="absolute left-2 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto">
                                <FontAwesomeIcon :icon="faChevronCircleLeft" />
                            </div>

                            <div ref="variantNextEl"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto">
                                <FontAwesomeIcon :icon="faChevronCircleRight" />
                            </div>
                        </div>

                        <SwiperSlide v-for="item in listProducts" :key="item.id">
                            <button @click="onSelectProduct(item)" :disabled="item.code === product.code" :class="[
                                'relative w-full rounded-lg border transition overflow-hidden flex flex-col',
                                item.code === product.code
                                    ? 'ring-1 primary'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                                <!-- IMAGE FULL AREA -->
                                <div class="relative w-full aspect-square bg-gray-50">
                                    <Image v-if="item?.web_images?.main?.original" :src="item.web_images.main.original"
                                        :alt="item.code" class="absolute inset-0 w-full h-full object-contain" />
                                    <FontAwesomeIcon v-else :icon="faImage"
                                        class="absolute inset-0 m-auto text-gray-300 text-xl" />
                                </div>

                                <!-- VARIANT LABEL -->
                                <div class="p-1">
                                    <span :class="[
                                        'block text-[11px] font-medium px-2 py-0.5 rounded text-center truncate bg-gray-100 text-gray-700'
                                    ]">
                                        {{ item.variant_label }}
                                    </span>
                                </div>
                            </button>
                        </SwiperSlide>
                    </Swiper>
                </div>

                <!-- DESCRIPTION -->
                <div class="text-xs font-medium text-gray-800">
                    <div v-html="product.description" />

                    <div v-if="expanded" class="text-xs text-gray-700 my-1">
                        <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra" />
                    </div>

                    <button v-if="product.description_extra" @click="toggleExpanded" class="mt-1 text-xs underline">
                        {{ expanded ? trans("Show Less") : trans("Read More") }}
                    </button>
                </div>

                <!-- PRODUCT CONTENTS -->
                <ProductContentsIris class="mt-6" :product="product" :setting="fieldValue.setting"
                    :styleData="fieldValue?.information_style" fullWidth />

                <!-- INFORMATION + PAYMENTS -->
                <div v-if="fieldValue.setting?.information" class="mt-2">
                    <InformationSideProduct v-if="fieldValue?.information?.length"
                        :informations="fieldValue.information" :styleData="fieldValue?.information_style" />

                    <h2 v-if="fieldValue?.paymentData?.length" class="text-base font-semibold text-gray-800">
                        {{ trans("Secure Payments") }}:
                    </h2>

                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in fieldValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MOBILE -->
    <div v-else class="block sm:hidden px-4 py-6 text-gray-800">
        <h1 class="text-xl font-bold mb-2">
            <span v-if="product.units > 1">{{ product.units }}x</span>
            {{ product.name }}
        </h1>

        <ImageProducts :images="validImages" :video="videoSetup?.url" />

        <!-- Section: Stock info, coming soon label -->
        <div v-if="layout?.iris?.is_logged_in" class="flex items-center justify-between mt-4">
            <!-- Stock info -->
            <LabelComingSoon v-if="product.status === 'coming-soon'" :product="product" class="w-full text-center" />
            <div v-else class="flex items-center gap-2 text-sm">
                <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                    :class="product?.stock ? 'text-green-600' : 'text-red-600'" />
                <span>
                    {{ product?.stock
                        ? trans("Unlimited quantity available")
                        : (product.stock > 0 ? trans("In stock") + ` (${product.stock} ` + trans("available") + `)` :
                            trans("Out Of Stock"))
                    }}
                </span>
            </div>

            <FontAwesomeIcon v-if="layout?.iris?.is_logged_in && layout?.retina?.type !== 'dropshipping'"
                :icon="product?.is_favourite ? fasHeart : faHeart"
                class="text-xl cursor-pointer transition-colors duration-300"
                :class="product?.is_favourite ? 'text-red-500' : 'text-gray-400 hover:text-red-500'" @click="
                    product?.is_favourite
                        ? onUnselectFavourite(product)
                        : onAddFavourite(product)
                    " />
        </div>

        <!-- Section: Price, unit info, favourite icon -->
        <div class="md:flex md:justify-between items-start gap-4 mt-2">
            <ProductPrices v-if="layout?.iris?.is_logged_in" :field-value="fieldValue" />
        </div>

        <!-- Section: Mobile Tags -->
        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <Image v-else :src="tag.image" :alt="`Thumbnail tag ${index}`" class="w-[15px] h-[15px] object-cover" />
                <span>{{ tag.name }}</span>
            </div>
        </div>

        <button v-if="!product.stock && layout?.outboxes?.oos_notification?.state == 'active'"
            v-tooltip="customerData.is_back_in_stock ? trans('You will be notify via email when the product back in stock') : trans('Click to be notified via email when the product back in stock')"
            @click="() => customerData.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
            class="inline-flex items-center gap-2 rounded-full border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-200 hover:border-gray-400">
            <LoadingIcon v-if="isLoadingRemindBackInStock" />
            <FontAwesomeIcon v-else :icon="customerData.back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                :class="[customerData.back_in_stock ? 'text-green-600' : 'text-gray-600']" />
            <span>{{ customerData.back_in_stock ? trans("Notified") : trans("Remind me") }}</span>
        </button>

        <!-- ADD TO CART -->
        <div class="mt-6 flex flex-col gap-2">
            <EcomAddToBasketv2 
                v-if="layout?.iris?.is_logged_in && product.stock && product.status !== 'coming-soon'"
                   v-model:product="product"  :customerData="customerData"
                :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" />

            <Button v-else-if="layout?.iris?.is_logged_in" :label="product.status_label ?? trans('Out of stock')"
                type="tertiary" disabled full />


            <LinkIris v-else :href="urlLoginWithRedirect()"
                :style="getStyles(fieldValue?.button?.properties, screenType)"
                class="block text-center border text-sm px-3 py-2 rounded text-gray-600 w-full">
                {{ trans("Login or Register for Wholesale Prices") }}
            </LinkIris>
        </div>


          <div v-if="listProducts && listProducts.length > 0" class="bg-white shadow-sm p-0.5 rounded-md my-4">
                    <Swiper  :modules="[Navigation]" :navigation="varinatNavigation" :space-between="6" :slides-per-view="3.2" :grab-cursor="true" :breakpoints="{
                        640: { slidesPerView: 4.5 },
                        1024: { slidesPerView: 4 }
                    }">

                     <div class="absolute inset-0 pointer-events-none z-50">
                            <div ref="variantPrevEl"
                                class="absolute left-2 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto">
                                <FontAwesomeIcon :icon="faChevronCircleLeft" />
                            </div>

                            <div ref="variantNextEl"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-3xl cursor-pointer opacity-60 hover:opacity-100 pointer-events-auto">
                                <FontAwesomeIcon :icon="faChevronCircleRight" />
                            </div>
                        </div>

                        <SwiperSlide v-for="item in listProducts" :key="item.id">
                            <button @click="onSelectProduct(item)" :disabled="item.code === product.code" :class="[
                                'relative w-full rounded-lg border transition overflow-hidden flex flex-col',
                                item.code === product.code
                                    ? 'ring-1 primary'
                                    : 'border-gray-200 hover:border-gray-300'
                            ]">
                                <!-- IMAGE FULL AREA -->
                                <div class="relative w-full aspect-square bg-gray-50">
                                    <Image v-if="item?.web_images?.main?.original" :src="item.web_images.main.original"
                                        :alt="item.code" class="absolute inset-0 w-full h-full object-contain" />
                                    <FontAwesomeIcon v-else :icon="faImage"
                                        class="absolute inset-0 m-auto text-gray-300 text-xl" />
                                </div>

                                <!-- VARIANT LABEL -->
                                <div class="p-1">
                                    <span :class="[
                                        'block text-[11px] font-medium px-2 py-0.5 rounded text-center truncate bg-gray-100 text-gray-700'
                                    ]">
                                        {{ item.variant_label }}
                                    </span>
                                </div>
                            </button>
                        </SwiperSlide>
                    </Swiper>
                </div>

        <!-- DESCRIPTION -->
        <div class="mt-4 text-xs font-medium py-3">
            <div v-html="product.description" />
            <div class="text-xs text-gray-700 my-1">
                <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra" />
            </div>
        </div>

        <!-- CONTENTS -->
        <div class="mt-4">
            <ProductContentsIris :product="product" :setting="fieldValue.setting" :styleData="fieldValue?.information_style" />
            <div v-if="fieldValue.setting?.information" class="mt-2">
                <InformationSideProduct v-if="fieldValue?.information?.length" :informations="fieldValue.information" :styleData="fieldValue?.information_style" />
            </div>
            <h2 class="text-base font-semibold mb-2">{{ trans("Secure Payments") }}:</h2>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in fieldValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                    class="h-4 px-1" />
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.primary {
  color: var(--theme-color-4) !important;
  border: 2px solid color-mix(in srgb, var(--theme-color-4) 80%, black);
}
</style>
