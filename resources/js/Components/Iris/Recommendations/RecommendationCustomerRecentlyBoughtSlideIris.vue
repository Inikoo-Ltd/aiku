<script setup lang="ts">
import Image from '@/Components/Image.vue'
//import { useFormatTime, useRangeFromNow } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { LastOrderedProduct } from '@/types/Resource/LastOrderedProductsResource'
import { faCircle } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { Link } from '@inertiajs/vue3'
//import { formatDistance } from 'date-fns'
import { trans } from 'laravel-vue-i18n'
import { SwiperSlide } from 'swiper/vue'
import { inject } from 'vue'

const props = defineProps<{
    product: LastOrderedProduct
    isProductLoading: (productId: string) => boolean
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="flex w-full px-4 py-3 rounded border border-transparent hover:border-black/40">
        <div class="w-full">
            <!-- Image -->
            <component :is="product.web_url?.[0] ? Link : 'div'"
                :href="product.web_url?.[0]"
                class="flex justify-center rounded aspect-[5/4] w-full overflow-hidden">
                <Image
                    :src="product.image"
                    :alt="product.name"
                    class="w-full h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal"
                    :style="{
                        margin: 'auto auto',
                    }"
                />
            </component>

            <div class="mt-3 text-center text-xs text-gray-600 italic">
                {{ product.code }}
            </div>

            <!-- Title -->
            <component :is="product.web_url?.[0] ? Link : 'div'"
                :href="product.web_url?.[0]"
                class="text-center xmt-2 font-bold text-sm leading-tight hover:!underline !cursor-pointer text-balance"
            >
                {{ product.name }}
            </component>

            <div class="mt-3 text-center text-sm text-gray-600 italic">
                <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${product.customer_country_code.toLowerCase()}.png`" />
                {{ product.customer_contact_name }}
            </div>

            <div class="text-center text-sm text-gray-400 italic">
                {{product.submitted_at}}

<!--                {{ formatDistance(new Date(product.submitted_at), new Date(), { addSuffix: true }) }}-->
            </div>
        </div>
        
        <!-- Add to Basket Button -->
        <!-- <div v-if="layout.retina.type === 'b2b' && product.id">
            <Button @click="() => false"
                disabled
                :label="isProductLoading(product.id) ? trans('Adding...') :
                    trans('Add to Basket')"
                class="w-full justify-center"
                :loading="isProductLoading(product.id)"
            />
        </div> -->
    </div>
</template>