<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
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
        <div v-for="item in product.attachments" :key="item.label"
            class="grid grid-cols-2 border-b border-gray-300 bg-gray-50">
            <div class="p-2 font-medium text-sm">{{ item.label }}</div>
            <div class="p-2 text-xs text-blue-600 underline cursor-pointer">
                <a :href="route(item.download_route.name, item.download_route.parameters)" target="_blank">
                    <FontAwesomeIcon :icon="getIcon(extractFileType(item.mime_type))" class="mr-1" />
                    {{ item.caption }} {{ `.${extractFileType(item.mime_type)}` }}
                </a>
            </div>
        </div>
    </div>
</template>
