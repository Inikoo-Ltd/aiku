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
    <div class="flex flex-col w-full px-4 py-3 rounded relative h-full">

        <div class="flex flex-col h-full">

            <!-- Image -->
          <component
                :is="product.url ? LinkIris : 'div'"
                :href="product.url"
                class="block rounded aspect-[5/4] mb-2"
                @success="() => SelectItemCollector(product)"
                @start="() => isLoadingVisit = true"
                @finish="() => isLoadingVisit = false"
            >
                <Image
                    :src="product?.web_images?.main?.original"
                    :alt="product.name"
                    class="max-w-full max-h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal"
                />
            </component>


            <!-- Content -->
            <div class="flex-1 flex flex-col">

                <!-- Title -->
                <h3>
                    <component :is="product.url ? LinkIris : 'div'"
                        :href="product.url"
                        class="!font-bold !text-sm !leading-tight hover:!underline !cursor-pointer !mb-2 inline-block text-justify"
                        @success="() => SelectItemCollector(product)" @start="() => isLoadingVisit = true"
                        @finish="() => isLoadingVisit = false">
                        {{ product.name }}
                    </component>
                </h3>

                <div class="flex justify-between text-xs text-gray-500 mb-1 capitalize flex-wrap">
                    <div>{{ product.code }}</div>

                    <div class="flex items-center text-xs mb-2">
                        <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')"
                            class="flex items-center gap-1"
                            :class="Number(product.stock) > 0 ? 'text-green-600' : 'text-red-600'">
                            <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                            <span>
                                {{ Number(product.stock) > 0 ? locale.number(Number(product.stock)) : 0 }}
                                {{ trans('available') }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Prices -->
                <div v-if="layout?.iris?.is_logged_in">
                    <Prices2  v-if="layout.retina?.type == 'b2b'" :product="product" :currency="currency" :basketButton="false"  class="mt-2 md:mt-auto mb-2 md:mb-3"/>
                    <Prices  v-else :product="product" :currency="currency" :basketButton="false" />
                </div>

            </div>
        </div>

        <!-- Loading Overlay -->
        <div v-if="isLoadingVisit"
            class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
            <LoadingIcon />
        </div>

    </div>

</template>