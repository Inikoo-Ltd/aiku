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

const toggleFavorite = () => {
    isFavorite.value = !isFavorite.value
}

const onDescriptionUpdate = (key: string, val: string) => {
    emits('onDescriptionUpdate', key, val)
}


function formatNumber(value: Number) {
    return Number.parseFloat(value).toString();
}

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
                <ProductPrices :field-value="modelValue" />


                <!-- Button existence on all channels -->
                <div class="relative flex gap-2 mb-6">
                    <ButtonAddPortfolio :product="product" :buttonStyle="getStyles(modelValue?.button?.properties, screenType)"
                        :productHasPortfolio="productExistenceInChannels" :buttonStyleLogin="getStyles(modelValue?.buttonLogin?.properties, screenType)"
                        />
                    <div v-if="isLoadingFetchExistenceChannels" class="absolute h-full w-full z-10">
                        <div class="h-full w-full skeleton rounded" />
                    </div>
                </div>

                <div class="text-xs font-medium text-gray-800"
                    :style="getStyles(modelValue?.description?.description_content, screenType)">
                    <div v-html="product.description"></div>

                    <div class="text-xs font-normal text-gray-700 my-1" v-if="expanded"
                        :style="getStyles(modelValue?.description?.description_extra, screenType)">
                        <div ref="contentRef"
                            class="prose prose-sm text-gray-700 max-w-none transition-all duration-300 overflow-hidden"
                            v-html="product.description_extra"></div>
                    </div>

                    <button v-if="product.description_extra" @click="toggleExpanded"
                        class="mt-1 text-gray-900 text-xs underline focus:outline-none">
                        {{ expanded ? trans("Show Less") : trans("Read More") }}
                    </button>
                </div>

                <div v-if="modelValue.setting?.information" class="my-4 space-y-2">
                    <ProductContents :product="product" :setting="modelValue.setting"
                        :styleData="modelValue?.information_style" />
                    <InformationSideProduct v-if="modelValue?.information?.length > 0"
                        :informations="modelValue?.information" :styleData="modelValue?.information_style" />
                    <div v-if="modelValue?.paymentData?.length > 0"
                        class="items-center gap-3 border-gray-400 font-bold text-gray-800 xpy-2"
                        :style="getStyles(modelValue?.information_style?.title)">
                        <h2 class="!text-base font-bold">{{ trans("Secure Payments") }}:</h2>
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in modelValue?.paymentData" :key="logo.code" v-tooltip="logo.code"
                                :src="logo.image" :alt="logo.code" class="h-4 px-1" />
                        </div>
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
         <h1 class="text-xl font-bold mb-2">{{ product.name }}</h1>
        <ImageProducts :images="validImages" :video="videoSetup?.url ?? videoSetup?.video_url" />

        <div class="items-start gap-4 mt-4">
            <ProductPrices :field-value="modelValue" />
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
            :buttonStyleLogin="getStyles(modelValue?.buttonLogin?.properties, screenType)" 
            :product="product"
            :productHasPortfolio="productExistenceInChannels" 
            :buttonStyle="getStyles(modelValue?.button?.properties, screenType)" />
        </div>

        <div class="text-xs font-medium py-3">
            <div v-html="product.description"></div>
             <div class="text-xs font-normal text-gray-700 my-1">
            <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra"></div>
        </div>
        </div>

        <div class="mt-4">
            <ProductContents :product="product" :setting="modelValue.setting"
                :styleData="modelValue?.information_style" />
            <InformationSideProduct v-if="modelValue?.information?.length > 0" :informations="modelValue?.information"
                :styleData="modelValue?.information_style" />
            <h2 class="!text-sm !font-semibold mb-2">{{ trans("Secure Payments") }}:</h2>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in modelValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>
        </div>
    </div>
</template>
