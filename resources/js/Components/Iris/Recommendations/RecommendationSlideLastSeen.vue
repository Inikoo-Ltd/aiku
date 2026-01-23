<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
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
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
const currency = layout?.iris?.currency
const isLoadingVisit = ref(false)
</script>

<template>
    <div class="relative h-full w-full rounded border border-gray-200 bg-white px-3 py-2 sm:px-4 sm:py-3">

        <!-- Card content -->
        <div class="flex h-full flex-col">

            <!-- Image -->
            <component :is="product.url ? LinkIris : 'div'" :href="product.url"
                class="mb-2 block w-full overflow-hidden rounded bg-white" @success="() => SelectItemCollector(product)"
                @start="() => isLoadingVisit = true" @finish="() => isLoadingVisit = false">
                <Image :src="product?.web_images?.main?.original" :alt="product.name"
                    class="w-full h-[160px] sm:h-[200px] object-cover" />
            </component>


            <!-- Body -->
            <div class="flex flex-1 flex-col">

                <!-- Title -->
                <h3 class="mb-1">
                    <component :is="product.url ? LinkIris : 'div'" :href="product.url"
                        class="line-clamp-2 cursor-pointer text-sm font-semibold leading-snug hover:underline"
                        @success="() => SelectItemCollector(product)" @start="() => isLoadingVisit = true"
                        @finish="() => isLoadingVisit = false">
                        {{ product.name }}
                    </component>
                </h3>

                <!-- Meta -->
                <div class="mb-1 flex flex-col gap-1 text-xs text-gray-500 sm:flex-row sm:justify-between">
                    <div class="truncate">{{ product.code }}</div>

                    <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')" class="flex items-center gap-1"
                        :class="Number(product.stock) > 0 ? 'text-green-600' : 'text-red-600'">
                        <FontAwesomeIcon :icon="faCircle" class="text-[6px]" />
                        <span>
                            {{ Number(product.stock) > 0 ? locale.number(Number(product.stock)) : 0 }}
                            {{ trans('available') }}
                        </span>
                    </div>
                </div>

                <!-- Prices -->
                <div v-if="layout?.iris?.is_logged_in" class="mt-1 sm:mt-auto">
                    <Prices2 v-if="layout.retina?.type === 'b2b'" :product="product" :currency="currency"
                        :basketButton="false" class="mb-1 sm:mb-3" />
                    <Prices v-else :product="product" :currency="currency" :basketButton="false" />
                </div>

            </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="isLoadingVisit" class="absolute inset-0 grid place-items-center bg-black/50 text-white">
            <LoadingIcon class="h-8 w-8" />
        </div>

    </div>
</template>
