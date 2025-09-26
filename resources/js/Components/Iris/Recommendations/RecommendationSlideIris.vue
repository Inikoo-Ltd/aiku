<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ProductHit } from '@/types/Luigi/LuigiTypes'
import { faCircle } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import { SwiperSlide } from 'swiper/vue'
import { inject } from 'vue'

const props = defineProps<{
    product: ProductHit
    isProductLoading: (productId: string) => boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="flex w-full px-4 py-3 rounded border hover:border-black/40">
        <div class="w-full">
            <!-- Image -->
            <component :is="product.attributes.web_url?.[0] ? Link : 'div'"
                :href="product.attributes.web_url?.[0]"
                class="block rounded aspect-[5/4] w-full overflow-hidden">
                <img :src="product.attributes.image_link" :alt="product.attributes.title"
                    class="w-full h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal">
            </component>

            <!-- Title -->
            <component :is="product.attributes.web_url?.[0] ? Link : 'div'"
                :href="product.attributes.web_url?.[0]"
                class="font-bold text-sm leading-tight hover:!underline !cursor-pointer">
                {{ product.attributes.title }}
            </component>

            <!-- SKU and RRP -->
            <div class="flex justify-between text-xs text-gray-500 mb-1 capitalize">
                <span>{{ product.attributes.product_code?.[0] }}</span>
            </div>

            <!-- Rating and Stock -->
            <div class="flex justify-between items-center text-xs mb-2">
                <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')"
                    class="flex items-center gap-1"
                    :class="Number(product.attributes?.stock_qty?.[0]) > 0 ? 'text-green-600' : 'text-red-600'">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                    <span>{{ Number(product.attributes?.stock_qty?.[0]) > 0 ?
                        locale.number(Number(product.attributes?.stock_qty?.[0])) : 0 }} {{ trans('available') }}</span>
                </div>
            </div>

            <!-- Prices -->
            <div v-if="layout?.iris?.is_logged_in" class="mb-3">
                <div class="flex justify-between text-sm ">
                    <span>{{ trans('Price') }}: <span class="font-semibold"> {{ product.attributes.formatted_price }}</span>
                    </span>
                    <!-- <span><span v-tooltip="trans('Recommended retail price')" >{{trans('RRP')}}</span>:  <span class="font-semibold">{{ locale.currencyFormat(layout.iris.currency.code,product.rrp) }}</span></span> -->
                </div>
            </div>
        </div>
        
        <!-- Add to Basket Button -->
        <div v-if="layout.retina.type === 'b2b' && product.attributes.product_id?.[0]">
            <Button @click="() => false"
                xdisabled="isProductLoading(product.attributes.product_id[0])"
                disabled
                :label="isProductLoading(product.attributes.product_id[0]) ? trans('Adding...') :
                    trans('Add to Basket')"
                class="w-full justify-center"
                :loading="isProductLoading(product.attributes.product_id[0])"
            />
        </div>
    </div>
</template>