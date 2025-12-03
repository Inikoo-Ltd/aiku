<script setup lang="ts">
import { faCube, faLink } from "@fal"
import { faCircle, faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject, onMounted, computed, watch } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import ProductContentsIris from "@/Components/CMS/Webpage/Product1/ProductContentIris.vue"
import InformationSideProduct from "@/Components/CMS/Webpage/Product1/InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import { trans } from "laravel-vue-i18n"
import { Image as ImageTS } from "@/types/Image"
import { isArray } from "lodash-es"
import { getStyles } from "@/Composables/styles"
import axios from "axios"
import ProductPrices from "@/Components/CMS/Webpage/Product1/ProductPrices.vue"

library.add(faCube, faLink, faFilePdf, faFileDownload)

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS
    }
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

const props = withDefaults(defineProps<{
    fieldValue: any
    webpageData?: any
    blockData?: object
    validImages : object
    videoSetup : {
        url : string
    }
    product : ProductResource
    productExistenceInChannels : Number[]
}>(), {})

const layout = inject("layout", {})
const screenType = inject("screenType", ref('desktop'))
const contentRef = ref(null)
const expanded = ref(false)


const toggleExpanded = () => {
    expanded.value = !expanded.value
}

</script>

<template>
    <div v-if="screenType != 'mobile'" id="product-1" :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        marginLeft: 'auto',
        marginRight: 'auto'
    }" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 block">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <ImageProducts :images="validImages" :video="videoSetup?.url ?? videoSetup?.video_url" />
                </div>

                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4" v-if="product?.tags?.length">
                    <div class="flex items-center gap-1 text-xs" v-for="(tag, index) in product.tags"
                        :key="index">
                        <FontAwesomeIcon v-if="!tag.image" :icon="['fas', 'dot-circle']" class="text-sm" />
                        <div v-else class="aspect-square w-full h-[15px]">
                            <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`"
                                class="w-full h-full object-cover" />
                        </div>
                        <span>{{ tag.name }}</span>
                    </div>
                </div>
            </div>

            <div class="col-span-5 self-start">
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-gray-900">
                            <span class="">{{ product?.units }}x</span>
                            {{ product.name }}
                        </h1>

                        <div
                            class="flex flex-wrap justify-between gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>Product code: {{ product.code }}</div>
                            <div class="flex items-center gap-[1px]"></div>
                        </div>

                        <div v-if="layout?.iris?.is_logged_in"
                            class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                            <span>
                                {{
                                product.stock > 0
                                ? trans("In stock") + ` (${product.stock} ` + trans("available") + `)`
                                : trans("Out Of Stock")
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Price + Profit popover -->
                <ProductPrices :field-value="fieldValue" />


                <!-- Button existence on all channels -->
                <div class="relative flex gap-2 mb-6">
                    <ButtonAddPortfolio :product="product" :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)"
                        :productHasPortfolio="productExistenceInChannels" :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType)"
                        />
                    <div v-if="isLoadingFetchExistenceChannels" class="absolute h-full w-full z-10">
                        <div class="h-full w-full skeleton rounded" />
                    </div>
                </div>

                <div class="text-xs font-medium text-gray-800"
                    :style="getStyles(fieldValue?.description?.description_content, screenType)">
                    <div v-html="product.description"></div>

                    <div class="text-xs font-normal text-gray-700 my-1" v-if="expanded"
                        :style="getStyles(fieldValue?.description?.description_extra, screenType)">
                        <div ref="contentRef"
                            class="prose prose-sm text-gray-700 max-w-none transition-all duration-300 overflow-hidden"
                            v-html="product.description_extra"></div>
                    </div>

                    <button v-if="product.description_extra" @click="toggleExpanded"
                        class="mt-1 text-gray-900 text-xs underline focus:outline-none">
                        {{ expanded ? trans("Show Less") : trans("Read More") }}
                    </button>
                </div>

                <div v-if="fieldValue.setting?.information" class="my-4 space-y-2">
                    <ProductContentsIris :product="product" :setting="fieldValue.setting"
                        :styleData="fieldValue?.information_style" />
                    <InformationSideProduct v-if="fieldValue?.information?.length > 0"
                        :informations="fieldValue?.information" :styleData="fieldValue?.information_style" />
                    <div v-if="fieldValue?.paymentData?.length > 0"
                        class="items-center gap-3 border-gray-400 font-bold text-gray-800 xpy-2"
                        :style="getStyles(fieldValue?.information_style?.title)">
                        <h2 class="!text-base font-bold">{{ trans("Secure Payments") }}:</h2>
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code"
                                :src="logo.image" :alt="logo.code" class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Layout -->
    <div v-else class="block px-4 py-6 text-gray-800">
        <h1 class="text-xl font-bold mb-2">{{ product.name }}</h1>
        <ImageProducts :images="validImages" :video="videoSetup?.url ?? videoSetup?.video_url" />

        <div class="items-start gap-4 mt-4">
            <ProductPrices :field-value="fieldValue" />
        </div>

        <div class="flex flex-wrap gap-2 mt-4" v-if="product?.tags?.length">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="['fas', 'dot-circle']" class="text-sm" />
                <div v-else class="aspect-square w-full h-[15px]">
                    <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`" class="w-full h-full object-cover" />
                </div>
                <span>{{ tag.name }}</span>
            </div>
        </div>

        <div class="mt-6 flex flex-col gap-2">
            <ButtonAddPortfolio 
            :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType)" 
            :product="product"
            :productHasPortfolio="productExistenceInChannels" 
            :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" />
        </div>

        <div class="text-xs font-medium py-3">
            <div v-html="product.description"></div>
             <div class="text-xs font-normal text-gray-700 my-1">
            <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra"></div>
        </div>
        </div>

        <div class="mt-4">
            <ProductContentsIris :product="product" :setting="fieldValue.setting"
                :styleData="fieldValue?.information_style" />
            <InformationSideProduct v-if="fieldValue?.information?.length > 0" :informations="fieldValue?.information"
                :styleData="fieldValue?.information_style" />
            <h2 class="!text-sm !font-semibold mb-2">{{ trans("Secure Payments") }}:</h2>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>
        </div>
    </div>
</template>