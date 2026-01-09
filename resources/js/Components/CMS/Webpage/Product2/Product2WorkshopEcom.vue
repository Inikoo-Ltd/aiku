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
    <!-- ================= DESKTOP ================= -->
    <div
        v-if="screenType !== 'mobile'"
        class="mx-auto max-w-7xl px-6 py-8 text-gray-800"
        :style="getStyles(layout?.app?.webpage_layout?.container?.properties, screenType)"
    >
        <div class="grid grid-cols-12 gap-10">
            <!-- LEFT -->
            <div class="col-span-7">
                <Product2Image :images="validImages" :video="videoSetup?.url" />

                <div
                    class="mt-4 flex items-center gap-3 px-4 py-2 w-fit border rounded-lg bg-gray-50 hover:bg-gray-100"
                >
                    <FontAwesomeIcon :icon="faArrowToBottom" />
                    <span class="truncate max-w-[420px] font-medium">
                        {{ trans("Download Marketing Materials for") }} {{ product.name }}
                    </span>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="col-span-5">
                <div class="flex justify-between items-start">
                    <div>
                        <h1 class="text-xl font-bold">
                            <span v-if="product.units > 1">{{ product.units }}x</span>
                            {{ product.name }}
                        </h1>

                        <div
                            v-if="layout?.iris?.is_logged_in"
                            class="mt-1 text-sm"
                            :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'"
                        >
                            {{
                                product.stock > 0
                                    ? `${trans("In stock")} (23)`
                                    : trans("Out Of Stock")
                            }}
                        </div>
                    </div>

                    <div v-if="layout?.iris?.is_logged_in" class="text-2xl cursor-pointer">
                        <FontAwesomeIcon
                            v-if="product.is_favourite"
                            :icon="fasHeart"
                            class="text-pink-500"
                        />
                        <FontAwesomeIcon
                            v-else
                            :icon="faHeart"
                            class="text-pink-300"
                        />
                    </div>
                </div>

                <!-- PRICE -->
                <div class="mt-4 flex justify-between">
                    <div>
                        <div class="text-xl font-bold">
                            {{ locale.currencyFormat(currency.code, product.price || 0) }}
                        </div>
                        <div class="text-sm">
                            ({{ locale.currencyFormat(currency.code, product.price_per_unit || 0) }}/{{ product.unit }})
                        </div>
                    </div>
                </div>

                <!-- CART -->
                <div class="mt-4">
                    <EcomAddToBasketv2
                        v-if="layout?.iris?.is_logged_in && product.stock > 0"
                        v-model:product="product"
                        :product="product"
                        class="w-full"
                    />
                </div>

                <!-- APPOINTMENT -->
                <div
                    v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment"
                    class="mt-4 flex gap-2 p-3 border rounded-lg bg-gray-50"
                >
                    <FontAwesomeIcon :icon="faMapMarkerAlt" />
                    <div v-html="modelValue?.appointment_data?.text" />
                </div>

                <!-- DELIVERY -->
                <div class="mt-4 text-sm" v-html="modelValue?.delivery_info?.text" />

                <!-- PAYMENT -->
                <div
                    v-if="modelValue.setting?.payments_and_policy"
                    class="mt-4 flex gap-3"
                >
                    <img
                        v-for="logo in modelValue.paymentData"
                        :key="logo.code"
                        :src="logo.image"
                        class="h-4"
                    />
                </div>

                <!-- SPEC -->
                <div
                    v-if="modelValue?.setting?.product_specs"
                    class="mt-6 p-4 border rounded-lg bg-gray-50 text-sm"
                >
                    <div class="font-bold mb-2">Product Specification</div>

                    <div
                        v-for="(items, label) in groupedAttachments"
                        :key="label"
                        class="mt-2"
                    >
                        <div class="font-medium">{{ label }}</div>
                        <div
                            v-for="item in items"
                            :key="item.caption"
                            class="text-blue-600 underline"
                        >
                            <a :href="item.url" target="_blank">
                                <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" />
                                {{ item.caption }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DESCRIPTION -->
        <div class="mt-6 text-sm">
            <div v-html="product.description" />
            <div v-if="expanded" v-html="product.description_extra" />
            <button
                v-if="product.description_extra"
                class="underline text-xs mt-1"
                @click="toggleExpanded"
            >
                {{ expanded ? trans("Show Less") : trans("Read More") }}
            </button>
        </div>
    </div>

    <!-- ================= MOBILE ================= -->
    <div v-else class="px-4 py-4 bg-white">
        <Product2Image :images="validImages" :video="videoSetup?.url" />

        <h1 class="mt-4 text-xl font-bold">
            <span v-if="product.units > 1">{{ product.units }}x</span>
            {{ product.name }}
        </h1>

        <div class="flex justify-between items-center mt-1">
            <div class="font-semibold">
                {{ locale.currencyFormat(currency.code, product.price || 0) }}
            </div>

            <FontAwesomeIcon
                v-if="layout?.iris?.is_logged_in && product.is_favourite"
                :icon="fasHeart"
                class="text-pink-500 text-xl"
            />
        </div>

        <div
            v-if="layout?.iris?.is_logged_in"
            class="mt-1 text-xs"
            :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'"
        >
            {{
                product.stock > 0
                    ? `${trans("In stock")} (23)`
                    : trans("Out Of Stock")
            }}
        </div>

        <div class="mt-4">
            <EcomAddToBasketv2
                v-if="product.stock > 0"
                v-model:product="product"
                :product="product"
                class="w-full"
            />
        </div>

        <!-- DOWNLOAD -->
        <div
            class="mt-4 flex items-center gap-2 px-3 py-2 border rounded-lg bg-gray-50"
        >
            <FontAwesomeIcon :icon="faArrowToBottom" />
            <span class="truncate text-sm">
                {{ trans("Download Marketing Materials for") }} {{ product.name }}
            </span>
        </div>

        <!-- APPOINTMENT -->
        <div
            v-if="layout?.iris?.is_logged_in && modelValue?.setting?.appointment"
            class="mt-4 flex gap-2 p-3 border rounded-lg bg-gray-50"
        >
            <FontAwesomeIcon :icon="faMapMarkerAlt" />
            <div v-html="modelValue?.appointment_data?.text" />
        </div>

        <!-- DELIVERY -->
        <div class="mt-4 text-xs" v-html="modelValue?.delivery_info?.text" />

        <!-- PAYMENT -->
        <div
            v-if="modelValue.setting?.payments_and_policy"
            class="mt-4 flex gap-3"
        >
            <img
                v-for="logo in modelValue.paymentData"
                :key="logo.code"
                :src="logo.image"
                class="h-4"
            />
        </div>

        <!-- SPEC -->
        <div
            v-if="modelValue?.setting?.product_specs"
            class="mt-6 p-4 border rounded-lg bg-gray-50 text-xs"
        >
            <div class="font-bold mb-2">Product Specification</div>

            <div
                v-for="(items, label) in groupedAttachments"
                :key="label"
                class="mt-2"
            >
                <div class="font-medium">{{ label }}</div>
                <div
                    v-for="item in items"
                    :key="item.caption"
                    class="text-blue-600 underline"
                >
                    <a :href="item.url" target="_blank">
                        <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" />
                        {{ item.caption }}
                    </a>
                </div>
            </div>
        </div>

        <!-- DESCRIPTION -->
        <div class="mt-6 text-xs">
            <div v-html="product.description" />
            <div v-if="expanded" v-html="product.description_extra" />
            <button
                v-if="product.description_extra"
                class="underline mt-1"
                @click="toggleExpanded"
            >
                {{ expanded ? trans("Show Less") : trans("Read More") }}
            </button>
        </div>
    </div>
</template>