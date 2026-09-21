<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { computed, inject } from 'vue'
import { faFileCheck, faFileWord } from "@fal"
import { faFilePdf } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
const props = defineProps<{
    product: {
        slug?: string
        specifications: {
            gross_weight?: number
            marketing_weight?: number
            barcode?: number
            origin?: string
            unit?: string
            cpnp?: string
            dimensions?: [number, number]
            ingredients?: Array<string>
            countries_of_origin?: Array<{ code: string; name: string }>
        }
        attachments?: Array<{ label: string; caption: string; url: string; mime_type: string }>
    }
}>()


const extractFileType = (mime: string) => {
    if (!mime) return ''
    const parts = mime.split('/')
    return parts[1]?.split('+')[0]?.toLowerCase() || ''
}

const isPdf = (mime: string) => extractFileType(mime) === 'pdf'

const pdfButtonClass = "inline-flex items-center gap-1.5 rounded border border-gray-300 px-1.5 py-0.5 text-xs font-semibold no-underline transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"

const fileLinkClass = "inline-flex items-center gap-1 text-xs text-blue-600 underline"

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

const resolveRoute = inject<((name: string, params?: object) => string) | null>("route", null)

const ingredientsLabelUrl = computed(() => {
    if (!props.product?.slug || !resolveRoute) {
        return null
    }

    try {
        return resolveRoute("iris.catalogue.product.ingredients_label", { product: props.product.slug })
    } catch {
        return null
    }
})

const countriesOfOrigin = computed(() =>
    (props.product?.specifications?.countries_of_origin || []).filter(country => country?.code)
)

const groupedAttachments = computed(() => {
    const allFiles = [
        ...(props.product.attachments || []),
    ]

    // Group by label (scope)
    const grouped: Record<string, typeof allFiles> = {}
    allFiles.forEach(file => {
        if (!grouped[file.label]) grouped[file.label] = []
        grouped[file.label].push(file)
    })

    return grouped
})



</script>

<template>
    <div class="w-full  border border-gray-300">
        <div v-if="product?.specifications?.origin" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('Origin') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.origin }}</div>
        </div>

        <div v-if="product?.specifications?.marketing_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('Net Weight') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.marketing_weight }} g/{{ product.specifications.unit }}</div>
        </div>

        <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans("Shipping Weight") }}</div>
            <div class="p-2 text-sm">{{ product.specifications.gross_weight }} g</div>
        </div>

        <div v-if="product?.specifications?.dimensions" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans("Dimensions") }}</div>
            <div class="p-2 text-sm">{{ product?.specifications?.dimensions }}</div>
        </div>


        <div v-if="product?.specifications?.ingredients" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('Materials/Ingredients') }}</div>
            <div class="p-2 text-sm">
                {{ product.specifications.ingredients }}
                <a v-if="ingredientsLabelUrl" :href="ingredientsLabelUrl" target="_blank" rel="nofollow"
                    class="mt-2" :class="pdfButtonClass">
                    <FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true" class="text-red-600" />
                    {{ ctrans('Download') }} PDF
                </a>
            </div>
        </div>

        <div v-if="product?.specifications?.barcode" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('Barcode') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.barcode }}</div>
        </div>

        <div v-if="product?.specifications?.cpnp" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('cpnp') }}</div>
            <div class="p-2 text-sm">{{ product?.specifications?.cpnp }}</div>
        </div>

        <!-- Section: countries_of_origin -->
        <div v-if="countriesOfOrigin.length" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ ctrans('Origin Country') }}</div>
            <div class="p-2 flex flex-col gap-1">
                <div v-for="country in countriesOfOrigin" :key="country.code"
                    class="flex items-center gap-2">
                    <img :src="'/flags/' + country.code.toLowerCase() + '.png'" :alt="country.name" :title="country.name"
                        class="h-4 w-auto inline-block" loading="lazy" decoding="async" />
                    <span class="text-sm">
                        {{ country.name }}
                    </span>
                </div>
            </div>
        </div>


        <!-- Downloadable Items -->
        <div v-for="(items, label) in groupedAttachments" :key="label"
            class="grid grid-cols-2 border-b border-gray-300 bg-gray-50">
            <!-- Label column -->
            <div class="p-2 font-medium text-sm border-gray-200 flex items-center">
                {{ label }}
            </div>

            <!-- Files column (up to 2 files per scope) -->
            <div>
                <div v-for="item in items" :key="item.caption" class="p-2 flex items-center">
                    <a :href="item.url" target="_blank"
                        :class="isPdf(item.mime_type) ? pdfButtonClass : fileLinkClass">
                        <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" fixed-width
                            aria-hidden="true" :class="isPdf(item.mime_type) ? 'text-red-600' : ''" />
                        {{ item.caption }}{{ `.${extractFileType(item.mime_type)}` }}
                    </a>
                </div>
            </div>
        </div>

    </div>
</template>
