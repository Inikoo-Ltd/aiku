<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { computed } from 'vue'
import { faUnlink, faInfoCircle, faFile, faStarChristmas, faFileCheck, faFilePdf, faFileWord } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
const props = defineProps<{
    product: {
        specifications: {
            gross_weight?: number
            net_weight?: number
            barcode?: number
            origin?: string
            dimensions?: [number, number]
            ingredients?: Array<string>
        }
    }
}>()


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
        ...(props.product.attachments || []),
    ]

    // Group by label (scope)
    const grouped = {}
    allFiles.forEach(file => {
        if (!grouped[file.label]) grouped[file.label] = []
        grouped[file.label].push(file)
    })

    return grouped
})



</script>

<template>
    <div class="w-full sm:w-7/12 border border-gray-300">
        <div v-if="product?.specifications?.origin" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('Origin') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.origin }}</div>
        </div>

        <div v-if="product?.specifications?.net_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('Net Weight') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.net_weight }} g/{{ product.specifications.unit }}</div>
        </div>

        <div v-if="product?.specifications?.gross_weight" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans("Shipping Weight") }}</div>
            <div class="p-2 text-sm">{{ product.specifications.gross_weight }} g</div>
        </div>

        <div v-if="product?.specifications?.dimensions" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans("Dimensions") }}</div>
            <div class="p-2 text-sm">{{ product?.specifications?.dimensions }}</div>
        </div>


        <div v-if="product?.specifications?.ingredients" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('Materials/Ingredients') }}</div>
            <div class="p-2 text-sm"> {{ product.specifications.ingredients.join(', ') }}</div>
        </div>

        <div v-if="product?.specifications?.barcode" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('Barcode') }}</div>
            <div class="p-2 text-sm">{{ product.specifications.barcode }}</div>
        </div>

        <div v-if="product?.specifications?.cpnp" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('cpnp') }}</div>
            <div class="p-2 text-sm">{{ product?.specifications?.cpnp }}</div>
        </div>

        <div v-if="product?.specifications?.country_of_origin?.code" class="grid grid-cols-2 border-b border-gray-300">
            <div class="p-2 font-medium text-sm bg-gray-50">{{ trans('Origin Country') }}</div>
            <div class="p-2 flex items-center gap-2">
                <img :src="'/flags/' + product.specifications.country_of_origin.code.toLowerCase() + '.png'"
                    :alt="product.specifications.country_of_origin.name"
                    :title="product.specifications.country_of_origin.name" class="h-4 w-auto inline-block" />
                <span class="text-sm">
                    {{ product.specifications.country_of_origin.name }}
                </span>
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
                <div v-for="item in items" :key="item.caption"
                    class="p-2 text-xs text-blue-600 underline cursor-pointer flex items-center">
                    <div>
                        <a :href="route(item.download_route.name, item.download_route.parameters)" target="_blank"
                            class="flex items-center">
                            <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" class="mr-1" />
                            {{ item.caption }}{{ `.${extractFileType(item.mime_type)}` }}
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
