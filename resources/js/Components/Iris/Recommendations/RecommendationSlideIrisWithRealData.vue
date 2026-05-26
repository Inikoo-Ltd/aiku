<script setup lang="ts">
import { SelectItemCollector } from '@/Composables/Unique/LuigiDataCollector'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ProductHit } from '@/types/Luigi/LuigiTypes'
import { faCircle } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject, ref } from 'vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Prices3 from '@/Iris/Components/BlocksUtils/Prices3.vue'
import Image from "@common/Components/Image.vue"
import Prices from "@/Iris/Components/BlocksUtils/Prices.vue"
import NewAddToCartButton from '@/Components/CMS/Webpage/Products/NewAddToCartButton.vue'
import ProductRenderEcom from "@/Iris/Components/IrisBlocks/Products/Ecom/ProductCard/ProductCardEcom3.vue"

const props = defineProps<{
    product: ProductHit
    isLoadingProductRealData: boolean
    isProductLoading?: any
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
const currency = layout?.iris?.currency
const isLoadingVisit = ref(false)


</script>

<template>
    <div class="relative flex flex-col h-full  rounded bg-white">
        <!-- Section: Image -->
        <div class="mb-3 flex justify-center relative">
            <component :is="product.attributes.web_url[0] ? LinkIris : 'div'" :href="product.attributes.web_url[0]"


                class="w-full max-w-[220px] aspect-square flex items-center justify-center"
                @success="() => SelectItemCollector(product)" @start="() => isLoadingVisit = true"

                @finish="() => isLoadingVisit = false">
                <Image v-if="product.attributes.image_link || product.iris_attributes?.web_images?.main?.gallery"
                    :src="product.iris_attributes?.web_images?.main?.gallery" :alt="product.name"
                    class="object-contain w-full h-full" />
                <FontAwesomeIcon v-else icon="fal fa-image" class="opacity-40 text-2xl md:text-5xl" fixed-width
                    aria-hidden="true" />
            </component>

           <!--  <div v-if="layout?.iris?.is_logged_in" class="absolute right-2 bottom-2">
                <NewAddToCartButton v-if="product.iris_attributes?.stock && layout.retina?.type === 'b2b'"

                    :hasInBasket="layout?.family_page?.productInBasket?.list?.[product.iris_attributes.id]"
                    :product="product.iris_attributes" :key="product.iris_attributes.id"
                    :addToBasketRoute="{ name: 'iris.models.transaction.store' }"
                    :updateBasketQuantityRoute="{ name: 'iris.models.transaction.update' }"

                    :buttonStyleHover="layout?.buttonBasket?.buttonStyleHover"
                    :buttonStyle="layout?.buttonBasket?.buttonStyle" />
            </div> -->
        </div>

        <!-- <div class="mb-3 flex items-center gap-3">
            <div class="text-xs text-gray-400">
                {{ product.attributes.product_code[0] }}
            </div>

            <div v-if="isLoadingProductRealData">
                <div class="skeleton h-4 w-20" />
            </div>

            <div v-else-if="layout?.iris?.is_logged_in" class="flex items-center gap-1 text-xs" :class="Number(product.iris_attributes?.stock) > 0
                    ? 'text-green-600'
                    : 'text-red-600'
                ">
                <FontAwesomeIcon :icon="faCircle" />
            </div>
        </div> -->

        <!-- Section: Title -->
        <span class="mb-1 !text-sm font-semibold leading-snug line-clamp-2 min-h-[3em]"
            :title="product.attributes.title">
            <component :is="product.attributes.web_url[0] ? LinkIris : 'div'" :href="product.iris_attributes?.url"
                class="hover:underline" @success="() => SelectItemCollector(product)"
                @start="() => isLoadingVisit = true" @finish="() => isLoadingVisit = false">
                {{ product.attributes.title }}
            </component>
        </span>

        <div class="flex justify-between">
            <div class="text-xs text-gray-400 mb-2">
               {{ product.attributes.product_code[0] }}
            </div>
            <div class="font-semibold underline text-xs">
                {{ ctrans('See Details') }}
            </div>
        </div>

        <!-- LOADING -->
        <div v-if="isLoadingVisit"
            class="absolute inset-0 z-10 grid place-items-center bg-black/50 text-white text-4xl">
            <LoadingIcon />
        </div>
    </div>

    <!-- Section: Prices -->
   <!--  <template v-if="layout?.iris?.is_logged_in">
        <div v-if="isLoadingProductRealData">
            <div class="h-40 md:h-32 w-full skeleton">
            </div>
        </div>
        <div v-else>
            <template v-if="product.iris_attributes">
                <Prices3 v-if="layout.retina?.type === 'b2b'" :product="product.iris_attributes" :currency="currency" :basketButton="true" />
                <Prices v-else :product="product.iris_attributes" :currency="currency" :basketButton="true" />
            </template>
        </div>
    </template> -->
</template>