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
</script>

<template>
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
                </div>
            </div>

            <!-- RIGHT: Product Info -->
            <div class="col-span-5 self-start">
                <div class="relative flex justify-between items-start mb-4">
                    <div class="w-full">
                        <div class="text-xl font-bold">
                            <span v-if="product.units > 1">{{ product.units }}x</span> {{ product.name }}
                        </div>


                        <!-- STOCK SECTION -->
                        <div v-if="layout?.iris?.is_logged_in" class="flex justify-between items-center mt-2">
                            <div class="flex items-center gap-2 text-sm">
                                <span :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{
                                        product.stock > 0
                                            ? `${trans("In stock")} (${customerData?.stock})`
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
                        <div v-else class="cursor-pointer text-2xl">
                            <FontAwesomeIcon v-if="customerData?.is_favourite" :icon="fasHeart" class="text-pink-500" />
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

                <!-- INFORMATION + PAYMENTS -->
                <div v-if="modelValue.setting?.information" class="mt-2">
                    <div class="flex flex-wrap items-center gap-6 py-2">
                        <img v-for="logo in modelValue.paymentData" :key="logo.code" :src="logo.image" :alt="logo.code"
                            class="h-4 px-1" />
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-6 py-2 border bg-gray-50 p-4">
                        <div class="font-bold text-xl">Product Specification</div>

                        <div class="w-full space-y-1">

                            <!-- Origin -->
                            <div v-if="product?.specifications?.origin" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('Origin') }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.origin }}</div>
                            </div>

                            <!-- Net Weight -->
                            <div v-if="product?.specifications?.marketing_weight" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('Net Weight') }}</div>
                                <div class="p-2 text-sm font-thin">
                                    {{ product.specifications.marketing_weight }} g/{{ product.specifications.unit }}
                                </div>
                            </div>

                            <!-- Shipping Weight -->
                            <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans("Shipping Weight") }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.gross_weight }} g</div>
                            </div>

                            <!-- Dimensions -->
                            <div v-if="product?.specifications?.dimensions" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans("Dimensions") }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.dimensions }}</div>
                            </div>

                            <!-- Ingredients -->
                            <div v-if="product?.specifications?.ingredients" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('Materials/Ingredients') }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.ingredients }}</div>
                            </div>

                            <!-- Barcode -->
                            <div v-if="product?.specifications?.barcode" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('Barcode') }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.barcode }}</div>
                            </div>

                            <!-- CPNP -->
                            <div v-if="product?.specifications?.cpnp" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('cpnp') }}</div>
                                <div class="p-2 text-sm font-thin">{{ product.specifications.cpnp }}</div>
                            </div>

                            <!-- Origin Country -->
                            <div v-if="product?.specifications?.country_of_origin?.code" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ trans('Origin Country') }}</div>

                                <div class="p-2 flex items-center gap-2 font-thin text-sm">
                                    <img :src="'/flags/' + product.specifications.country_of_origin.code.toLowerCase() + '.png'"
                                        :alt="product.specifications.country_of_origin.name"
                                        :title="product.specifications.country_of_origin.name" class="h-4 w-auto" />
                                    <span>{{ product.specifications.country_of_origin.name }}</span>
                                </div>
                            </div>

                            <!-- Attachments -->
                            <div v-for="(items, label) in groupedAttachments" :key="label" class="grid grid-cols-2">
                                <div class="p-2 text-sm font-thin">{{ label }}</div>

                                <div class="p-2 space-y-1">
                                    <div v-for="item in items" :key="item.caption"
                                        class="text-xs font-thin text-blue-600 underline cursor-pointer flex items-center">
                                        <a :href="item.url" target="_blank" class="flex items-center">
                                            <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" class="mr-1" />
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
        <div class="text-xs font-medium text-gray-800">
            <div v-html="product.description" />

            <div v-if="expanded" class="text-xs text-gray-700 my-1">
                <div class="prose prose-sm text-gray-700 max-w-none" v-html="product.description_extra" />
            </div>

            <button v-if="product.description_extra" @click="toggleExpanded" class="mt-1 text-xs underline">
                {{ expanded ? trans("Show Less") : trans("Read More") }}
            </button>
        </div>
    </div>
</template>
