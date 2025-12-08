<script setup lang="ts">
import { faCube, faLink, faHeart } from "@fal"
import { faBox } from "@far"
import { faCircle, faDotCircle, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject, useAttrs, onMounted, computed } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import EditorV2 from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { trans } from "laravel-vue-i18n"
import ProductContents from "@/Components/CMS/Webpage/Product1/ProductContents.vue"
import InformationSideProduct from "@/Components/CMS/Webpage/Product1/InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import { getStyles } from "@/Composables/styles"
import ProductPrices from "@/Components/CMS/Webpage/Product1/ProductPrices.vue"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"
import Product from "@/Pages/Grp/Org/Catalogue/Product.vue"

library.add(faCube, faLink, faFileDownload)

type TemplateType = 'webpage' | 'template'

const props = withDefaults(defineProps<{
    modelValue: any
    webpageData?: any
    blockData?: object
    templateEdit?: TemplateType
    indexBlock?: number
    screenType: "mobile" | "tablet" | "desktop"
    currency: {
        code: string
        name: string
    }
    videoSetup: {
        url: string
    }
    validImages: object
}>(), {
    templateEdit: 'webpage'
})

const emits = defineEmits<{
    (e: 'onDescriptionUpdate', key: string, val: string): void
}>()

const product = ref(props.modelValue.product)
const layout = inject('layout', {})
const isFavorite = ref(false)
const contentRef = ref(null)
const expanded = ref(false)
const showButton = ref(false)


const attrs = useAttrs()

// ✅ helper for responsive classes
function resolveResponsiveClass(
    screenType: "mobile" | "tablet" | "desktop",
    options: Record<string, string>
) {
    return options[screenType] || ""
}

onMounted(() => {
    requestAnimationFrame(() => {
        if (contentRef?.value?.scrollHeight > 100) {
            showButton.value = true
        }
    })

    if (props.templateEdit != 'webpage') {
        layout.iris = {
            is_logged_in: true
        }
    }
})

const toggleExpanded = () => {
    expanded.value = !expanded.value
}

defineOptions({
    inheritAttrs: false,
})
</script>

<template>
    <!-- Desktop / Tablet Layout -->
    <div id="product-1" v-bind="attrs" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        marginLeft: 'auto',
        marginRight: 'auto'
    }" :class="[
        'mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 pointer-events-none',
        resolveResponsiveClass(screenType, {
            mobile: 'hidden',
            tablet: 'block',
            desktop: 'block'
        })
    ]">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <!-- LEFT: Images -->
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :images="validImages" :video="videoSetup?.url" />
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
                <div class="relative flex justify-between items-start mb-4">
                    <div class="w-full">
                        <h1 class="text-3xl font-bold">
                            <span v-if="product.units > 1">{{ product.units }}x</span>
                            {{ product.name }}
                        </h1>

                        <div class="text-sm font-medium text-gray-600 mt-1 mb-1">
                            {{ trans("Product code") }}: {{ product.code }}
                        </div>

                        <!-- STOCK SECTION -->
                        <div v-if="layout?.iris?.is_logged_in" class="flex justify-between items-center">
                            <div class="flex items-center gap-2 text-sm">
                                <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                    :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                                <span>
                                    {{
                                        product.stock > 0
                                            ? `${trans("In stock")} (${customerData?.stock} ${trans("available")})`
                                            : trans("Out Of Stock")
                                    }}
                                </span>
                            </div>

                            <!-- REMIND ME -->
                            <button v-if="product.stock <= 0 && layout?.app?.environment === 'local'" @click="
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

                    <!-- FAVOURITE -->
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

                <!-- PRICE -->
                <ProductPrices :field-value="modelValue" />

                <!-- ADD TO CART -->
                <div class="flex gap-2 mb-6">
                    <div v-if="layout?.iris?.is_logged_in" class="w-full">
                        <EcomAddToBasketv2 v-if="product.stock > 0" :product="product" :customerData="customerData"
                            :key="keyCustomer" :buttonStyle="getStyles(modelValue?.button?.properties, screenType)" />
                        <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />
                    </div>

                    <div v-else
                        class="w-full block text-center border text-sm px-3 py-2 rounded text-gray-600"
                        :style="getStyles(modelValue?.buttonLogin?.properties, screenType)">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </div>
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
                <ProductContents class="mt-6" :product="product" :setting="modelValue?.setting"
                    :styleData="modelValue?.information_style" fullWidth />

                <!-- INFORMATION + PAYMENTS -->
                <div v-if="modelValue?.setting?.information" class="mt-2">
                    <InformationSideProduct v-if="modelValue?.information?.length"
                        :informations="modelValue.information" :styleData="modelValue?.information_style" />

                    <h2 v-if="modelValue?.paymentData?.length" class="text-base font-semibold text-gray-800">
                        {{ trans("Secure Payments") }}:
                    </h2>

                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Layout -->
    <div :class="[
        'px-4 py-6 text-gray-800 pointer-events-none',
        resolveResponsiveClass(screenType, {
            mobile: 'block',
            tablet: 'hidden',
            desktop: 'hidden'
        })
    ]">
        <h1 class="text-xl font-bold mb-2">
            <span v-if="product.units > 1">{{ product.units }}x</span>
            {{ product.name }}
        </h1>

        <ImageProducts :images="validImages" :video="videoSetup?.url" />

        <div class="flex justify-between items-start gap-4 mt-4">
            <ProductPrices v-if="layout?.iris?.is_logged_in" :field-value="modelValue" />

            <FontAwesomeIcon v-if="layout?.iris?.is_logged_in && layout?.retina?.type !== 'dropshipping'"
                :icon="customerData?.is_favourite ? fasHeart : faHeart"
                class="text-xl cursor-pointer transition-colors duration-300"
                :class="customerData?.is_favourite ? 'text-red-500' : 'text-gray-400 hover:text-red-500'" @click="
                    customerData?.is_favourite
                        ? onUnselectFavourite(product)
                        : onAddFavourite(product)
                    " />
        </div>

        <!-- TAGS -->
        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <Image v-else :src="tag.image" :alt="`Thumbnail tag ${index}`" class="w-[15px] h-[15px] object-cover" />
                <span>{{ tag.name }}</span>
            </div>
        </div>

        <!-- ADD TO CART -->
        <div class="mt-6 flex flex-col gap-2">
            <EcomAddToBasketv2 v-if="layout?.iris?.is_logged_in && product.stock > 0" :product="product"
                :customerData="customerData" :buttonStyle="getStyles(modelValue?.button?.properties, screenType)" />

            <Button v-else-if="layout?.iris?.is_logged_in" :label="trans('Out of stock')" type="tertiary" disabled
                full />

            <div v-else 
                :style="getStyles(modelValue?.button?.properties, screenType)"
                class="block text-center border text-sm px-3 py-2 rounded text-gray-600 w-full">
                {{ trans("Login or Register for Wholesale Prices") }}
            </div>
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
            <ProductContents :product="product" :setting="modelValue?.setting"
                :styleData="modelValue?.information_style" />
            <InformationSideProduct v-if="modelValue?.information?.length" :informations="modelValue.information"
                :styleData="modelValue?.information_style" />

            <h2 class="text-base font-semibold mb-2">{{ trans("Secure Payments") }}:</h2>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                    class="h-4 px-1" />
            </div>
        </div>
    </div>
</template>
