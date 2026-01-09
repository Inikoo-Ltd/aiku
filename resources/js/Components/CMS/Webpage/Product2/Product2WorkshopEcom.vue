<script setup lang="ts">
import { faCube, faLink, faHeart } from "@fal"
import { faFileDownload } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject, useAttrs, onMounted, computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { getStyles } from "@/Composables/styles"
import EcomAddToBasketv2 from "@/Components/Iris/Products/EcomAddToBasketv2.vue"
import { faFileCheck, faFilePdf, faFileWord } from "@fal"
import { useLocaleStore } from "@/Stores/locale"
import Product2Image from "./Product2Image.vue"
import { faHeart as fasHeart } from "@fas"
import { faEnvelopeCircleCheck } from "@fortawesome/free-solid-svg-icons"
import { faArrowToBottom, faDownload, faMapMarkerAlt } from "@fas"

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
const locale = useLocaleStore()
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

const extractFileType = (mime: string) => {
    if (!mime) return ''
    const parts = mime.split('/')
    return parts[1]?.split('+')[0]?.toLowerCase() || ''
}

const getIcon = (type: string) => {
    switch (type) {
        case "pdf":
            return faFilePdf
        case "doc":
        case "docx":
        case "msword":
        case "vnd.openxmlformats-officedocument.wordprocessingml.document":
            return faFileWord
        default:
            return faFileCheck
    }
}

const groupedAttachments = computed(() => {
    const allFiles = [
        ...(product.value.attachments || []),
    ]

    // Group by label (scope)
    const grouped = {}
    allFiles.forEach(file => {
        if (!grouped[file.label]) grouped[file.label] = []
        grouped[file.label].push(file)
    })

    return grouped
})


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

console.log(props.modelValue)
</script>

<template>
      <!-- DESKTOP -->
    <div v-if="screenType !== 'mobile'" id="product-1"
        class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block" :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            marginLeft: 'auto',
            marginRight: 'auto'
        }">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <!-- LEFT: Images -->
            <div class="col-span-7">
                <div class="py-1 w-full">
                    <Product2Image :images="validImages" :video="videoSetup?.url" />

                    <div class="
                        group
                        flex items-center gap-3
                        py-2 px-4 mt-4 w-fit
                        border rounded-lg bg-gray-50
                        transition
                        hover:bg-gray-100 hover:border-gray-300
                    ">
                        <FontAwesomeIcon :icon="faArrowToBottom"
                            class="text-gray-600 transition group-hover:text-gray-800 shrink-0" />

                        <span class="
                            font-medium text-xl text-gray-800
                            truncate max-w-[420px]
                        " :title="`${trans('Download Marketing Materials for')} ${product.name}`">
                            {{ trans('Download Marketing Materials for') }} {{ product.name }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Product Info -->
            <div class="col-span-5 self-start">
                <div class="relative flex justify-between items-start mb-4">
                    <div class="w-full">
                        <div class="text-xl font-bold w-[80%]">
                            <span v-if="product.units > 1">{{ product.units }}x</span> {{ product.name }}
                        </div>


                        <!-- STOCK SECTION -->
                        <div v-if="layout?.iris?.is_logged_in" class="flex justify-between items-center mt-2">
                            <div class="flex items-center gap-2 text-sm">
                                <span :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{
                                        product.stock > 0
                                            ? `${trans("In stock")} (23)`
                                            : trans("Out Of Stock")
                                    }}
                                </span>
                            </div>

                            <!-- REMIND ME -->
                            <button v-if="product.stock <= 0 "
                                class="absolute right-0 bottom-2 flex items-center gap-2 px-3 py-1.5 text-sm rounded-full border bg-gray-100 hover:bg-gray-200">
                                <FontAwesomeIcon
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
                    <div v-if="layout?.iris?.is_logged_in">
                        <div  class="cursor-pointer text-2xl">
                            <FontAwesomeIcon v-if="product?.is_favourite" :icon="fasHeart" class="text-pink-500" />
                            <FontAwesomeIcon v-else :icon="faHeart" class="text-pink-300 hover:text-pink-400" />
                        </div>
                    </div>
                </div>


                <div class='flex justify-between'>
                    <div>
                        <div class="text-xl font-bold">
                            {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                        </div>
                        <div class="text-sm font-medium">
                            ({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/{{ product.unit
                            }})
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium border-b-2 border-gray-900 p-1.5 text-right ">
                            <p>Retail Price:</p>
                            <p>{{ locale.currencyFormat(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                            </p>
                        </div>
                        <div class="p-1.5 text-right">
                            <span class="text-base font-medium">Profit:</span>

                            <div class="flex items-center justify-end text-xs font-bold">
                                <span>{{ locale.currencyFormat(currency?.code, product.profit || 0) }} &nbsp;</span>
                                <span class="font-normal">({{ product.margin }})</span>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- ADD TO CART -->
                <div class="flex gap-2 mb-6">
                    <div v-if="layout?.iris?.is_logged_in" class="w-full">
                        <EcomAddToBasketv2 v-if="product.stock > 0" v-model:product="product"
                            :product="product" :key="keyCustomer"
                            :buttonStyle="getStyles(modelValue?.button?.properties, screenType)" />
                        <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />
                    </div>

                    <div v-else
                        class="w-full block text-center border text-sm px-3 py-2 rounded text-gray-600"
                        :style="getStyles(modelValue?.buttonLogin?.properties, screenType)">
                        {{ trans("Login or Register for Wholesale Prices") }}
                    </div>
                </div>



                <div v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment" class="
                        group
                        flex items-center gap-3
                        py-2 px-4 mt-4 w-full
                        border rounded-lg bg-gray-50
                        transition
                        hover:bg-gray-100 hover:border-gray-300
                        my-2
                    ">
                    <FontAwesomeIcon :icon="faMapMarkerAlt"
                        class="text-gray-600 transition group-hover:text-gray-800 shrink-0" />

                    <span class="
                             font-medium text-sm underline text-gray-800
                            truncate max-w-[420px]
                        " :title="`${trans('Download Marketing Materials for')} ${product.name}`">
                         <div v-html="modelValue?.appointment_data?.text"></div>
                    </span>
                </div>



                <div class="text-sm font-medium my-2">
                      <div v-html="modelValue?.delivery_info?.text"></div>
                </div>

                <div v-if="modelValue.setting?.payments_and_policy && modelValue.paymentData" class="my-5">
                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>

                    <div v-if="modelValue?.setting?.product_specs" >
                        <div class="flex flex-wrap items-center gap-6 py-2 border bg-gray-50 p-4">
                            <div class="font-bold text-xl">Product Specification</div>

                            <div class="w-full space-y-1">

                                <!-- Origin -->
                                <div v-if="product?.specifications?.origin" class="grid grid-cols-2">
                                    <div class="p-2 text-sm fomedium">{{ trans('Origin') }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.origin }}</div>
                                </div>

                                <!-- Net Weight -->
                                <div v-if="product?.specifications?.marketing_weight" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans('Net Weight') }}</div>
                                    <div class="p-2 text-sm font-normal">
                                        {{ product.specifications.marketing_weight }} g/{{ product.specifications.unit }}
                                    </div>
                                </div>

                                <!-- Shipping Weight -->
                                <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans("Shipping Weight") }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.gross_weight }} g</div>
                                </div>

                                <!-- Dimensions -->
                                <div v-if="product?.specifications?.dimensions" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans("Dimensions") }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.dimensions }}</div>
                                </div>

                                <!-- Ingredients -->
                                <div v-if="product?.specifications?.ingredients" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans('Materials/Ingredients') }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.ingredients }}</div>
                                </div>

                                <!-- Barcode -->
                                <div v-if="product?.specifications?.barcode" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans('Barcode') }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.barcode }}</div>
                                </div>

                                <!-- CPNP -->
                                <div v-if="product?.specifications?.cpnp" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans('cpnp') }}</div>
                                    <div class="p-2 text-sm font-normal">{{ product.specifications.cpnp }}</div>
                                </div>

                                <!-- Origin Country -->
                                <div v-if="product?.specifications?.country_of_origin?.code" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ trans('Origin Country') }}</div>

                                    <div class="p-2 flex items-center gap-2 font-normal text-sm">
                                        <img :src="'/flags/' + product.specifications.country_of_origin.code.toLowerCase() + '.png'"
                                            :alt="product.specifications.country_of_origin.name"
                                            :title="product.specifications.country_of_origin.name" class="h-4 w-auto" />
                                        <span>{{ product.specifications.country_of_origin.name }}</span>
                                    </div>
                                </div>

                                <!-- Attachments -->
                                <div v-for="(items, label) in groupedAttachments" :key="label" class="grid grid-cols-2">
                                    <div class="p-2 text-sm font-normal">{{ label }}</div>

                                    <div class="p-2 space-y-1">
                                        <div v-for="item in items" :key="item.caption"
                                            class="text-xs font-normal text-blue-600 underline cursor-pointer flex items-center">
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

        <!-- DESCRIPTION -->
        <div class="text-xs font-normal text-gray-800">
            <div v-html="product.description" />

            <div v-if="expanded" class="text-xs text-gray-700 my-1">
                <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra" />
            </div>

            <button v-if="product.description_extra" @click="toggleExpanded" class="mt-1 text-xs underline">
                {{ expanded ? trans("Show Less") : trans("Read More") }}
            </button>
        </div>
    </div>


    <!-- MOBILE -->
      <div v-if="screenType === 'mobile'" class="bg-white">

        <!-- IMAGES -->
        <Product2Image :images="validImages" :video="videoSetup?.url" />

        <div class="px-4 py-4 space-y-5">

            <!-- TITLE -->
            <h1 class="!text-xl font-bold leading-tight">
                <span v-if="product.units > 1">{{ product.units }}x</span>
                {{ product.name }}
            </h1>

            <div v-if="layout?.iris?.is_logged_in" class="flex items-center justify-between mt-1">
                <!-- STOCK -->
                <span class="text-sm font-medium" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                    {{
                        product.stock > 0
                            ? `${trans('In stock')} (${customerData?.stock})`
                            : trans('Out Of Stock')
                    }}
                </span>

                <!-- FAVOURITE -->
                <div v-if="layout?.retina?.type !== 'dropshipping'">
                    <LoadingIcon v-if="isLoadingFavourite" class="text-gray-400" />
                    <FontAwesomeIcon v-else :icon="customerData?.is_favourite ? fasHeart : faHeart"
                        class="text-xl cursor-pointer transition" :class="customerData?.is_favourite
                            ? 'text-pink-500'
                            : 'text-pink-300 hover:text-pink-400'" />
                </div>
            </div>

            <!-- PRICE -->
            <div class='flex justify-between'>
                <div>
                    <div class="text-xl font-bold">
                        {{ locale.currencyFormat(currency?.code, product.price || 0) }}
                    </div>
                    <div class="text-sm font-medium">
                        ({{ locale.currencyFormat(currency?.code, product.price_per_unit || 0) }}/{{ product.unit  }})
                    </div>
                </div>

                <div>
                    <div class="text-xs font-medium border-b-2 border-gray-900 p-1.5 text-right ">
                        <p>Retail Price:</p>
                        <p>{{ locale.currencyFormat(currency?.code, product.rrp_per_unit || 0) }}/{{ product.unit }}
                        </p>
                    </div>
                    <div class="p-1.5 text-right">
                        <span class="text-base font-medium">Profit:</span>

                        <div class="flex items-center justify-end text-xs font-bold">
                            <span>{{ locale.currencyFormat(currency?.code, product.profit || 0) }} &nbsp;</span>
                            <span class="font-normal">({{ product.margin }})</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STOCK + FAVOURITE -->



            <!-- REMIND ME -->
            <button v-if="product.stock <= 0 && layout?.outboxes?.oos_notification?.state === 'active'"
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

            <!-- ADD TO CART -->
            <EcomAddToBasketv2 v-if="product.stock > 0" v-model:product="product" :customerData="customerData"
                :key="keyCustomer" class="w-full" />
            <Button v-else :label="trans('Out of stock')" type="tertiary" disabled full />

            <!-- DOWNLOAD -->
            <a :href="`${baseUrl}app/catalogue/feeds/feeds/product/${product.slug}/download?type=products_images`"
                class="flex items-center gap-3 px-4 py-2 rounded-lg border bg-gray-50">
                <FontAwesomeIcon :icon="faArrowToBottom" />
                <span class="text-sm font-medium truncate">
                    {{ trans('Download Marketing Materials for') }} {{ product.name }}
                </span>
            </a>

            <!-- VARIANTS -->
            <Swiper v-if="listProducts?.length" :slides-per-view="2.4" :space-between="12">
                <SwiperSlide v-for="item in listProducts" :key="item.id">
                    <button :disabled="item.code === product.code"
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

            <!-- APPOINTMENT -->
            <div v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment"
                :href="modelValue?.appointment_data?.link?.href" :type="modelValue?.appointment_data?.link?.type"
                class="flex gap-3 items-center px-4 py-2 border rounded-lg bg-gray-50">
                <FontAwesomeIcon :icon="faMapMarkerAlt" />
                <div v-html="modelValue?.appointment_data?.text" class="text-sm underline" />
            </div>

            <!-- DELIVERY -->
            <div v-if="layout?.iris?.is_logged_in && modelValue?.delivery_info?.text"
                v-html="modelValue.delivery_info.text" class="text-sm" />

            <!-- PAYMENTS -->
            <div v-if="modelValue?.setting?.payments_and_policy && modelValue.paymentData">
                <div class="flex flex-wrap gap-4">
                    <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" class="h-4" />
                </div>
            </div>

            <!-- PRODUCT SPECS -->
            <div v-if="modelValue?.setting?.product_specs" class="border rounded-lg p-4 bg-gray-50">
                <div class="font-bold mb-2">Product Specification</div>
                <div class="space-y-1 text-sm">
                    <div v-if="product.specifications.origin">
                        Origin: {{ product.specifications.origin }}
                    </div>
                    <div v-if="product.specifications.barcode">
                        Barcode: {{ product.specifications.barcode }}
                    </div>
                </div>
            </div>

            <!-- DESCRIPTION -->
            <div class="text-xs">
                <div v-html="product.description" />
                <div v-if="expanded" v-html="product.description_extra" class="mt-2" />
                <button v-if="product.description_extra" @click="toggleExpanded" class="underline text-xs mt-2">
                    {{ expanded ? trans('Show Less') : trans('Read More') }}
                </button>
            </div>
        </div>
    </div>
</template>
