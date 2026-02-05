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

const firstName = props.product?.customer_contact_name?.split(" ")?.[0];

</script>

<template>
    <div id="recommendation-customer-recently-bought-slide-iris" class="flex w-full md:px-4 md:py-3 rounded xborder border-transparent hover:border-black/10">
        <div class="w-full flex flex-col">
            <!-- Section: Image -->
            <component :is="product.canonical_url ? Link : 'div'"
                :href="product.canonical_url"
                class="group max-w-[220px] flex justify-center mx-auto rounded aspect-square w-full overflow-hidden">
                <Image
                    :src="product.image"
                    :alt="product.name"
                    class="w-full h-full object-contain text-center text-xxs text-gray-400/70 italic font-normal"
                    :style="{
                        margin: 'auto auto',
                    }"
                />
            </component>


            <!-- Section: Title -->
            <component :is="product.canonical_url ? Link : 'div'"
                :href="product.canonical_url"
                class="text-[13px] md:text-[16px] text-justify font-semibold leading-snug line-clamp-2 min-h-[3em] block hover:!underline !cursor-pointer text-pretty"
            >
                {{ product.name }}
            </component>

            <!-- Section: code -->
            <div class="text-xs text-gray-400 mb-2">
                {{ product.code }}
            </div>

            <!-- Section: first name and date -->
            <div v-if="layout?.iris?.is_logged_in" class="mt-auto">
                <div class="mt-3 text-center text-xxs md:text-base text-gray-700 italic">
                    <!-- <img class="inline pr-1 pl-1 h-[1em]" :src="`/flags/${product.customer_country_code.toLowerCase()}.png`" /> -->
                    {{ product.customer_first_name ?? firstName }}
                </div>
                <div class="text-center text-xxs md:text-sm text-gray-400 italic">
                    {{ useFormatTime(product.submitted_at) }}
                </div>
            </div>
        </div>
    </div>
</template>