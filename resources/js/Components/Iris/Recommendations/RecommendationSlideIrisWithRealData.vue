<script setup lang="ts">
import { SelectItemCollector } from '@/Composables/Unique/LuigiDataCollector'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ProductHit } from '@/types/Luigi/LuigiTypes'
import { faCircle } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Prices2 from '@/Components/CMS/Webpage/Products1/Prices2.vue'
import Image from '@/Components/Image.vue'
import Prices from '@/Components/CMS/Webpage/Products1/Prices.vue'

const props = defineProps<{
    product: ProductHit
    isProductLoading: (productId: string) => boolean
    isLoadingProductRealData: boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
const currency = layout?.iris?.currency
const isLoadingVisit = ref(false)


</script>

<template>
    <div class="relative flex flex-col h-full p-3 rounded bg-white">
<!-- <pre>{{ product }}</pre> -->
        <!-- IMAGE -->
        <div class="mb-3 flex justify-center">
            <component :is="product.attributes.web_url[0] ? LinkIris : 'div'" :href="product.attributes.web_url[0]"
                class="w-full max-w-[220px] aspect-square flex items-center justify-center"
                @success="() => SelectItemCollector(product)" @start="() => isLoadingVisit = true"
                @finish="() => isLoadingVisit = false">
                <img :src="product.attributes.image_link" :alt="product.attributes.title" class="object-contain w-full h-full" />
            </component>
        </div>

        <!-- TITLE -->
        <span class="mb-1 text-[11px] md:text-[16px] text-justify font-semibold leading-snug line-clamp-2 min-h-[3em]" :title="product.attributes.title">
            <component :is="product.attributes.web_url[0] ? LinkIris : 'div'" :href="product.iris_attributes?.url" class="hover:underline"
                @success="() => SelectItemCollector(product)" @start="() => isLoadingVisit = true"
                @finish="() => isLoadingVisit = false">
                {{ product.attributes.title }}
            </component>
        </span>

        <!-- CODE -->
        <div class="text-xs text-gray-400 mb-2">
            {{ product.attributes.product_code[0] }}
        </div>

        <!-- STOCK -->
        <div v-if="isLoadingProductRealData" class="mb-3">
            <div class="h-4 w-20 skeleton" />
        </div>
        <div v-else-if="layout?.iris?.is_logged_in" class="flex items-center gap-1 text-xs mb-3"
            :class="Number(product.iris_attributes?.stock) > 0 ? 'text-green-600' : 'text-red-600'">
            <FontAwesomeIcon :icon="faCircle" class="text-[7px]" />
            <span>
                {{ locale.number(Number(product.iris_attributes?.stock)) }} {{ trans('available') }}
            </span>
        </div>

        <!-- LOADING -->
        <div v-if="isLoadingVisit"
            class="absolute inset-0 z-10 grid place-items-center bg-black/50 text-white text-4xl">
            <LoadingIcon />
        </div>

    </div>

    <!-- PRICES (KEEP COMPONENTS) -->
    <div v-if="isLoadingProductRealData">
        <div class="h-40 md:h-24 w-full skeleton">

        </div>
    </div>
    <div v-else-if="layout?.iris?.is_logged_in">
        <Prices2 v-if="layout.retina?.type === 'b2b'" :product="product.iris_attributes" :currency="currency" :basketButton="false" />
        <Prices v-else :product="product.iris_attributes" :currency="currency" :basketButton="false" />
    </div>
</template>
