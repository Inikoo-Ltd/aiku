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

const props = defineProps<{
    product: ProductHit
    isProductLoading: (productId: string) => boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const isLoadingVisit = ref(false)
</script>

<template>
    <div class="flex flex-col justify-between w-full px-4 py-3 rounded relative">
        <div class="w-full">
            <!-- Image -->
            <component :is="product.attributes.web_url?.[0] ? LinkIris : 'div'"
                :href="product.attributes.web_url?.[0]"
                class="block rounded aspect-[5/4] w-full overflow-hidden"
                @success="() => SelectItemCollector(product)"
                @start="() => isLoadingVisit = true"
                @finish="() => isLoadingVisit = false"
            >
                <img :src="product.attributes.image_link" :alt="product.attributes.title"
                    class="w-full h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal">
            </component>

            <!-- Title -->
            <component :is="product.attributes.web_url?.[0] ? LinkIris : 'div'"
                :href="product.attributes.web_url?.[0]"
                class="font-bold text-sm leading-tight hover:!underline !cursor-pointer"
                @success="() => SelectItemCollector(product)"
                @start="() => isLoadingVisit = true"
                @finish="() => isLoadingVisit = false"
            >
                {{ product.attributes.title }}
            </component>

           
            <div class="flex justify-between text-xs text-gray-500 mb-1 capitalize">
                 <!-- code -->
                <div>{{ product.attributes.product_code?.[0] }}</div>
                   <!-- Rating and Stock E-->
                    <div class="flex justify-between items-center text-xs mb-2">
                        <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')"
                            class="flex items-center gap-1"
                            :class="Number(product.attributes?.stock_qty?.[0]) > 0 ? 'text-green-600' : 'text-red-600'">
                            <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                            <span>{{ Number(product.attributes?.stock_qty?.[0]) > 0 ?
                                locale.number(Number(product.attributes?.stock_qty?.[0])) : 0 }} {{ trans('available') }}</span>
                        </div>
                    </div>
                </div>
            </div>

          <!-- Prices -->
            <div v-if="layout?.iris?.is_logged_in" class="mb-3">
                <div class="flex justify-between text-sm ">
                    <span>{{ trans('Price') }}: <span class="font-semibold"> {{ product.attributes.formatted_price }}</span></span>
                </div>
            </div>

            
        <div v-if="isLoadingVisit" class="absolute inset-0 grid justify-center items-center bg-black/50 text-white text-5xl">
            <LoadingIcon />
        </div>
        
        <!-- Button: Add to Basket -->
        <!-- <div v-if="false && layout.retina.type === 'b2b' && product.attributes.product_id?.[0]">
            <Button @click="() => false"
                xdisabled="isProductLoading(product.attributes.product_id[0])"
                disabled
                :label="isProductLoading(product.attributes.product_id[0]) ? trans('Adding...') :
                    trans('Add to Basket')"
                class="w-full justify-center"
                :loading="isProductLoading(product.attributes.product_id[0])"
            />
        </div> -->
    </div>
</template>