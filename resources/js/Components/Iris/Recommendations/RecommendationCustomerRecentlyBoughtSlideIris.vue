<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
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
}>()

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div id="recommendation-customer-recently-bought-slide-iris" class="flex w-full px-4 py-3 rounded border border-transparent hover:border-black/10">
        <div class="w-full flex flex-col">
            <!-- Image -->
            <component :is="product.canonical_url ? Link : 'div'"
                :href="product.canonical_url"
                class="group flex justify-center rounded aspect-[5/4] w-full overflow-hidden">
                <Image
                    :src="product.image"
                    :alt="product.name"
                    class="group-hover:scale-105 w-full h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal"
                    :style="{
                        margin: 'auto auto',
                    }"
                />
            </component>

            <div class="mt-3 text-center text-xxs md:text-xs text-gray-600 italic">
                {{ product.code }}
            </div>

            <!-- Title -->
            <component :is="product.canonical_url ? Link : 'div'"
                :href="product.canonical_url"
                class="block text-justify md:text-center xmt-2 font-bold text-xs md:text-sm leading-tight hover:!underline !cursor-pointer xtext-balance text-pretty"
            >
                {{ product.name }}
            </component>

            <div class="mt-auto">
                <div class="mt-3 text-center text-xxs md:text-base text-gray-700 italic">
                    <!-- <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${product.customer_country_code.toLowerCase()}.png`" /> -->
                    {{ product.customer_contact_name }}
                </div>
                <div class="text-center text-xxs md:text-sm text-gray-400 italic">
                    {{ useFormatTime(product.submitted_at) }}
                </div>
            </div>
        </div>
    </div>
</template>