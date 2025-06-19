<script setup lang="ts">
import { faCube, faLink, faHeart } from "@fal"
import { faBox, faPlus, faVial } from "@far"
import { faCircle, faStar, faDotCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ref, inject } from "vue"
import ImageProducts from "@/Components/Product/ImageProducts.vue"
import { useLocaleStore } from '@/Stores/locale'
import ProductContentsIris from "./ProductContentIris.vue"
import InformationSideProduct from "./InformationSideProduct.vue"
import Image from "@/Components/Image.vue"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

library.add(faCube, faLink)

type TemplateType = 'webpage' | 'template'

const props = withDefaults(defineProps<{
    fieldValue: any
    webpageData?: any
    blockData?: object
}>(), {
})
const layout = inject('layout', retinaLayoutStructure)
const currency = layout?.iris?.currency
const locale = useLocaleStore()
const isFavorite = ref(false)
const toggleFavorite = () => {
    isFavorite.value = !isFavorite.value
}


function formatNumber(value : Number) {
  return Number.parseFloat(value).toString();
}

</script>

<template>
    <div id="app" class="mx-auto max-w-7xl py-8 text-gray-800 overflow-hidden px-6 hidden sm:block">
        <div class="grid grid-cols-12 gap-x-10 mb-2">
            <div class="col-span-7">
                <div class="flex justify-between mb-4 items-start">
                    <div class="w-full">
                        <h1 class="text-2xl font-bold text-gray-900">{{ fieldValue.product.name }}</h1>
                        <div class="flex flex-wrap gap-x-10 text-sm font-medium text-gray-600 mt-1 mb-1">
                            <div>Product code: {{ fieldValue.product.code }}</div>
                            <div class="flex items-center gap-[1px]">
                              <!--   <FontAwesomeIcon :icon="faStar" class="text-[10px] text-yellow-400" v-for="n in 5"
                                    :key="n" />
                                <span class="ml-1 text-xs text-gray-500">41</span> -->
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-600 mb-4">
                            <FontAwesomeIcon :icon="faCircle" class="text-[10px]"
                                :class="fieldValue.product.stock > 0  ? 'text-green-600' : 'text-red-600'" />
                            <span>{{ fieldValue.product.stock > 0 ? `In Stock (${fieldValue.product.stock})` : 'Out Of Stock' }}</span>
                        </div>
                    </div>
                    <div class="h-full flex items-start">
                        <FontAwesomeIcon :icon="faHeart" class="text-2xl cursor-pointer"
                            :class="{ 'text-red-500': isFavorite }" @click="toggleFavorite" />
                    </div>
                </div>
                <div class="py-1 w-full">
                    <ImageProducts :images="fieldValue.product.images" />
                </div>
                <div class="flex gap-x-10 text-gray-400 mb-6 mt-4">
                    <div class="flex items-center gap-1 text-xs" v-for="(tag, index) in fieldValue.product.tags"
                        :key="index">
                        <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                        <div v-else class="aspect-square w-full h-[15px]">
                            <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`"
                                class="w-full h-full object-cover" />
                        </div>
                        <span>{{ tag.name }}</span>
                    </div>
                </div>
            </div>
            <div class="col-span-5 self-start">
                <div class="flex items-end border-b pb-3 mb-3">
                    <div class="text-gray-900 font-semibold text-5xl capitalize leading-none flex-grow min-w-0">
                        {{ locale.currencyFormat(currency?.code, fieldValue.product.price || 0) }}
                        <span class="text-sm text-gray-500 ml-2 whitespace-nowrap">({{ formatNumber(fieldValue.product.units) }}/{{
                            fieldValue.product.unit }})</span>
                    </div>
                    <div v-if="fieldValue.product.rrp" class="text-xs text-gray-400 font-semibold text-right whitespace-nowrap pl-4">
                        <span>RRP: {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp || 0) }}</span>
                        <span>/{{ fieldValue.product.unit }}</span>
                    </div>
                </div>
                <div class="flex gap-2 mb-6">
                    <button
                        class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                        <FontAwesomeIcon :icon="faPlus" class="text-base" />
                        Add to Portfolio
                    </button>
                    <button v-tooltip="'Buy sample'"
                        class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                        <FontAwesomeIcon :icon="faVial" class="text-sm" />
                    </button>
                </div>
                <div class="flex items-center text-sm text-medium text-gray-500 mb-6">
                    <FontAwesomeIcon :icon="faBox" class="mr-3 text-xl" />
                    <span>{{`Order ${formatNumber(fieldValue.product.units)} full carton`}}</span>
                </div>
                <div class="text-xs font-medium text-gray-800 py-3">
                    <div v-html="fieldValue.product.description"></div>
                </div>
                <div v-if="fieldValue.setting.information" class="mb-4 space-y-2">
                    <InformationSideProduct v-if="fieldValue?.information?.length > 0"
                        :informations="fieldValue?.information" />
                    <div v-if="fieldValue?.paymentData?.length > 0"
                        class="items-center gap-3  border-gray-400 font-bold text-gray-800 py-2">
                        Secure Payments:
                        <div class="flex flex-wrap items-center gap-6 border-gray-400 font-bold text-gray-800 py-2">
                            <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code"
                                :src="logo.image" :alt="logo.code" class="h-4 px-1" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ProductContentsIris :product="props.fieldValue.product" :setting="fieldValue.setting" />
    </div>

    <!-- Mobile Layout -->
    <div class="block sm:hidden px-4 py-6 text-gray-800">
        <h2 class="text-xl font-bold mb-2">{{ fieldValue.product.name }}</h2>
        <ImageProducts :images="fieldValue.product.images" />
        <div class="flex justify-between items-start gap-4 mt-4">
            <!-- Price + Unit Info -->
            <div>
                <div class="text-lg font-semibold">
                    {{ locale.currencyFormat(currency?.code, fieldValue.product.price || 0) }}
                    <span class="text-xs text-gray-500 ml-1">
                        ({{ formatNumber(fieldValue.product.units) }}/{{ fieldValue.product.unit }})
                    </span>
                </div>
                <div v-if="fieldValue.product.rrp" class="text-xs text-gray-400 font-semibold mt-1">
                    RRP: {{ locale.currencyFormat(currency?.code, fieldValue.product.rrp || 0) }}
                </div>
            </div>

            <!-- Favorite Icon -->
            <div class="mt-1">
                <FontAwesomeIcon :icon="faHeart" class="text-xl cursor-pointer transition-colors duration-300"
                    :class="{ 'text-red-500': isFavorite, 'text-gray-400 hover:text-red-500': !isFavorite }"
                    @click="toggleFavorite" />
            </div>
        </div>


        <div class="flex flex-wrap gap-2 mt-4">
            <div class="text-xs flex items-center gap-1 text-gray-500" v-for="(tag, index) in fieldValue.product.tags"
                :key="index">
                <FontAwesomeIcon v-if="!tag.image" :icon="faDotCircle" class="text-sm" />
                <div v-else class="aspect-square w-full h-[15px]">
                    <Image :src="tag?.image" :alt="`Thumbnail tag ${index}`"
                        class="w-full h-full object-cover" />
                </div>
                <span>{{ tag.name }}</span>
            </div>
        </div>
        <div class="mt-6 flex flex-col gap-2">
            <button
                class="flex items-center justify-center gap-2 bg-gray-800 text-white rounded px-4 py-2 text-sm font-semibold transition w-full">
                <FontAwesomeIcon :icon="faPlus" />
                Add to Portfolio
            </button>
            <button
                class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold transition w-full">
                <FontAwesomeIcon :icon="faVial" />
                Buy Sample
            </button>
        </div>
        <div class="text-xs font-medium py-3">
            <div v-html="fieldValue.product.description"></div>
        </div>
        <div class="mt-4">
            <div class="text-sm font-semibold mb-2">Secure Payments:</div>
            <div class="flex flex-wrap gap-4">
                <img v-for="logo in fieldValue?.paymentData" :key="logo.code" v-tooltip="logo.code" :src="logo.image"
                    :alt="logo.code" class="h-4 px-1" />
            </div>

        </div>

         <ProductContentsIris :product="props.fieldValue.product" :setting="fieldValue.setting" />
    </div>

</template>
