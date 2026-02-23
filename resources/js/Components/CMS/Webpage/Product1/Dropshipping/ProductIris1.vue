<script setup lang="ts">
import { faCube, faLink, faEnvelope} from "@fal"
import { faCircle, faFilePdf, faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import ProductContentsIris from "@/Components/CMS/Webpage/Product1/ProductContentIris.vue"
import InformationSideProduct from "@/Components/CMS/Webpage/Product1/InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import { trans } from "laravel-vue-i18n"
import { Image as ImageTS } from "@/types/Image"
import { getStyles } from "@/Composables/styles"
import ProductPrices from "@/Components/CMS/Webpage/Product1/ProductPrices.vue"
import { Swiper, SwiperSlide } from "swiper/vue"
import "swiper/css"
import { faImage } from "@far"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"



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
    validImages: object
    videoSetup: {
        url: string
    }
    product: ProductResource
    productExistenceInChannels: Number[]
    listProducts?: object
    isLoadingRemindBackInStock? : boolean
}>(), {})

const emits = defineEmits<{
    (e: "selectProduct", value: any[]): void
    (e: "setBackInStock", value: any[]): void
    (e: "unsetBackInStock", value: any[]): void
}>()


const layout = inject("layout", {})
const screenType = inject("screenType", ref('desktop'))
const expanded = ref(false)

const onSelectProduct = (p: ProductResource) => emits("selectProduct", p)
const onAddBackInStock = (p: ProductResource) => emits("setBackInStock", p)
const onUnselectBackInStock = (p: ProductResource) => emits("unsetBackInStock", p)

const toggleExpanded = () => {
    expanded.value = !expanded.value
}

</script>

<template>
    <div v-if="screenType != 'mobile'" :id="fieldValue?.id ? fieldValue?.id  : 'product-ds-1'"  component="product-ds-1" :style="{
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
                    <div class="flex items-center gap-1 text-xs" v-for="(tag, index) in product.tags" :key="index">
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

                        <div class="flex items-end justify-between gap-4">
                            <!-- LEFT CONTENT -->
                            <div>
                                <div
                                    class="flex flex-wrap justify-between gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                                    <div>Product code: {{ product.code }}</div>
                                </div>

                                <div v-if="layout?.iris?.is_logged_in"
                                    class="flex items-center gap-2 text-sm text-gray-600">
                                    <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                        :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'" />
                                    <span>
                                        {{
                                            product?.stock >= 250
                                                ? trans("Unlimited quantity")
                                                : product.stock > 0
                                                    ? trans("In stock") + ` (${product.stock} ` + trans("available") + `)`
                                        : trans("Out Of Stock")
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- RIGHT BUTTON -->
                            <button v-if="!product.stock && layout?.outboxes?.oos_notification?.state === 'active'"
                                v-tooltip="product?.back_in_stock
                                    ? trans('You will be notify via email when the product back in stock')
                                    : trans('Click to be notified via email when the product back in stock')" 
                                    @click="product?.back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                                class="inline-flex shrink-0 items-center gap-2 rounded-full border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-200 hover:border-gray-400">
                                <LoadingIcon v-if="isLoadingRemindBackInStock" />
                                <FontAwesomeIcon v-else
                                    :icon="product?.back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                                    :class="product?.back_in_stock ? 'text-green-600' : 'text-gray-600'" />
                                <span>{{ product?.back_in_stock ? trans("Notified") : trans("Remind me") }}</span>
                            </button>
                        </div>

                    </div>
                </div>

                <div v-if="listProducts && listProducts.length > 0" class="bg-white shadow-sm p-0.5 rounded-md mb-4">
                    <Swiper :space-between="6" :slides-per-view="3.2" :grab-cursor="true" :breakpoints="{
                        640: { slidesPerView: 4.5 },
                        1024: { slidesPerView: 4 }
                    }">

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

                <!-- Price + Profit popover -->
                <ProductPrices :field-value="fieldValue" />


                <!-- Button existence on all channels -->
                <div class="relative flex gap-2 mb-6">
                    <ButtonAddPortfolio :product="product"
                        :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)"
                        :productHasPortfolio="productExistenceInChannels"
                        :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType)" />
                    <!-- <div v-if="isLoadingFetchExistenceChannels" class="absolute h-full w-full z-10">
                        <div class="h-full w-full skeleton rounded" />
                    </div> -->
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
            <ButtonAddPortfolio :buttonStyleLogin="getStyles(fieldValue?.buttonLogin?.properties, screenType)"
                :product="product" :productHasPortfolio="productExistenceInChannels"
                :buttonStyle="getStyles(fieldValue?.button?.properties, screenType)" />
        </div>


        <div v-if="listProducts && listProducts.length > 0" class="bg-white shadow-sm p-0.5 rounded-md my-4">
            <Swiper :space-between="6" :slides-per-view="3.2" :grab-cursor="true" :breakpoints="{
                640: { slidesPerView: 4.5 },
                1024: { slidesPerView: 4 }
            }">

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


<style lang="scss" scoped>
.primary {
    color: var(--theme-color-4) !important;
    border: 2px solid color-mix(in srgb, var(--theme-color-4) 80%, black);
}
</style>
