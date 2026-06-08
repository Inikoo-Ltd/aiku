<script setup lang="ts">
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link } from '@inertiajs/vue3'
// import { notify } from '@kyvg/vue3-notification'
import axios from 'axios'
import { trans } from 'laravel-vue-i18n'
import { inject, onBeforeUnmount, onMounted, ref } from 'vue'

import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import Button from '@/Components/Elements/Buttons/Button.vue'
import { reactive } from 'vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import { RecommendationCollector } from '@/Composables/Unique/LuigiDataCollector'
import { debounce } from 'lodash-es'
// import { Carousel } from 'primevue'
library.add(faCircle)

interface ProductHits {
    attributes: {
        image_link: string
        price: string
        formatted_price: string
        department: string[]
        category: string[]
        product_code: string[]
        product_id: string[]
        stock_qty: string[]
        title: string
        web_url: string[]
    }
}

const props = withDefaults(defineProps<{
    listLoadingProducts?: Record<string, string>
    blacklistItems?: string[]
    basketItemIds?: string[]
}>(), {
    blacklistItems: () => [],
    basketItemIds: () => []
})

const emit = defineEmits<{
    'add-to-basket': [productId: string, productCode: string, product?: ProductHits]
}>()

const screenType: string = inject('screenType', 'desktop')
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)


const handleProductClick = (product: ProductHits) => {
    if (product.attributes.product_id?.[0]) {
        emit('add-to-basket', product.attributes.product_id[0], product.attributes.product_code[0], product)
    }
}

const isProductLoading = (productId: string) => {
    return props.listLoadingProducts?.[`recommender-${productId}`] === 'loading'
}

const pageSize = 7
const page = ref(0)
const listProducts = ref<ProductHits[] | null>()
const isLoadingFetch = ref(false)
const hitsMap = reactive(new Map<string, any>()) // untuk dedup cepat
let lastRequestId = 0

const fetchRecommenders = async (nextPage = false) => {
  try {
    if (nextPage) page.value += 1
    else {
      // reset untuk first load / context berubah
      page.value = 1
      listProducts.value = []
      hitsMap.clear()
    }

    isLoadingFetch.value = true
    const requestId = ++lastRequestId

    // Jika TIDAK ada offset di API, strategi "size bertambah"
    const size = page.value * pageSize

    const response = await axios.post(
      `https://live.luigisbox.tech/v1/recommend?tracker_id=${layout.iris.luigisbox_tracker_id}`,
      [
        {
          blacklisted_item_ids: props.blacklistItems,
          item_ids: props.basketItemIds,
          recommendation_type: 'basket',
          recommender_client_identifier: 'basket',
          size,
          user_id: layout.user?.customer_id?.toString(),
          recommendation_context: {},
        }
      ],
      { headers: { 'Content-Type': 'application/json;charset=utf-8' }, signal: undefined /* atau AbortController */ }
    )

    if (lastRequestId !== requestId) return // ada request yang lebih baru, abaikan respons ini
    if (response.status !== 200) {
      console.error('Error fetching recommenders:', response.statusText)
      return
    }

    const hits: any[] = response.data?.[0]?.hits ?? []

    // Ambil hanya "delta": item yang belum ada di hitsMap
    const delta = []
    for (const h of hits) {
      const key = h.id ?? h.item_id ?? h.uuid ?? h.url // pilih ID yang paling stabil
      if (!hitsMap.has(key)) {
        hitsMap.set(key, h)
        delta.push(h)
      }
    }

    // Send Analytics - only for new (delta) items
    if (layout.app.environment === 'production' && delta.length) {
        RecommendationCollector({ ...response.data[0], hits: delta })
    }

    // Kalau ini first load, delta = semua; kalau load berikutnya, delta = penambahan 7 (idealnya)
    listProducts.value = [...listProducts.value, ...delta]
  } catch (error: any) {
    console.error('Error on fetching recommendations:', error)
  } finally {
    isLoadingFetch.value = false
  }
}

const debFetchRecom = debounce((xxx?: boolean) => fetchRecommenders(xxx), 300) // untuk mencegah fetch berlebihan saat scroll cepat

const onReachEnd = () => {
    // minta halaman berikutnya: page bertambah 1 → size bertambah 7
    debFetchRecom(true)
}


onMounted(() => {
    debFetchRecom()
})


// Section: responsive Slides per view
const slidesPerView = ref(3.3)
const updateSlidesPerView = () => {
    const width = window.innerWidth
    if (width < 640) {
        slidesPerView.value = 2.3 // mobile
    }
    // else if (width < 768) {
    //     slidesPerView.value = 3.3 // small tablet
    // } else if (width < 1024) {
    //     slidesPerView.value = 3.5 // tablet
    // } else if (width < 1280) {
    //     slidesPerView.value = 4.5 // desktop
    // } else {
    //     slidesPerView.value = 4.5 // large desktop
    // }
}
onMounted(() => {
    // Set initial slides per view
    updateSlidesPerView()
    
    // Add resize listener
    window.addEventListener('resize', updateSlidesPerView)
})
// Cleanup resize listener
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateSlidesPerView)
})
</script>

<template>
    <div class="md:py-4" id="basket-recommendations" >
        <Swiper
            :slides-per-view="slidesPerView ? Math.min((listProducts?.length || (isLoadingFetch ? pageSize : 0)), slidesPerView || 0) : 4"
            :loop="false"
            :autoplay="false"
            :pagination="{ clickable: true }"
            :modules="[Autoplay]"
            class="w-full"
            spaceBetween="12"
            autoHeight
            @reachEnd="(e) => (e.slides?.length > 0 ? onReachEnd() : false)"
        >
            <template v-if="(!listProducts?.length && isLoadingFetch)">
                <SwiperSlide
                    v-for="n in pageSize"
                    class="w-full cursor-grab relative px-2 md:px-4 py-3 rounded !flex !flex-col !justify-between gap-y-4 min-h-full animate-pulse"
                >
                    <div class="flex flex-col md:flex-row gap-x-2">
                        <div class="h-fit mx-auto md:mx-0 w-full max-w-[50px] md:max-w-[120px] rounded aspect-[4/4] bg-gray-200"></div>
                        <div class="flex-1 space-y-3 mt-2 md:mt-0">
                            <div class="h-3 bg-gray-200 rounded w-full"></div>
                            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-2 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-4 bg-gray-200 rounded w-2/5"></div>
                            <div class="h-6 bg-gray-200 rounded w-full"></div>
                            <div class="h-9 bg-gray-200 rounded w-2/3"></div>
                        </div>
                    </div>
                </SwiperSlide>
            </template>

            <template v-else-if="listProducts?.length">
                <SwiperSlide v-for="(product, index) in listProducts"
                    :key="index"
                    class="w-full cursor-grab relative px-2 md:px-4 py-3 rounded !flex !flex-col !justify-between gap-y-4 min-h-full"
                    :class="Number(product.attributes?.stock_qty?.[0]) > 0 ? 'hover:bg-gray-500/10' : 'opacity-75'"
                >
                    <div class="flex flex-col md:flex-row gap-x-2">
                        <!-- Product Image - Always a link -->
                        <component :is="product.attributes.web_url?.[0] ? LinkIris : 'div'"
                            :href="product.attributes.web_url?.[0]"
                            class="mx-auto md:mx-0 w-full max-w-[50px] md:max-w-[120px] block rounded aspect-[4/4] overflow-hidden">
                            <img
                                :src="product.attributes.image_link"
                                :alt="product.attributes.title"
                                class="w-full xh-full object-contain"
                            />
                        </component>
                        
                        <div>
                            <!-- Title - Always a link -->
                            <component
                                :is="product.attributes.web_url?.[0] ? LinkIris : 'div'"
                                :href="product.attributes.web_url?.[0]"
                                class="xfont-bold text-xs md:text-sm !mt-2 md:mt-2 md:mb-1 text-justify line-clamp-3 overflow-hidden"
                                :class="product.attributes.web_url?.[0] ? 'hover:underline' : ''"
                            >
                                {{ product.attributes.title }}
                            </component>
                            
                            <!-- SKU and RRP -->
                            <div class="flex justify-between text-xxs md:text-xs opacity-70 mb-1">
                                <span>{{ product.attributes.product_code?.[0] }}</span>
                            </div>

                            <!-- Rating and Stock G -->
                            <!-- <div class="flex justify-between items-center text-xxs md:text-xs mb-2">
                                <div v-if="layout?.iris?.is_logged_in" v-tooltip="trans('Stock')"
                                    class="flex items-center gap-1"
                                    :class="Number(product.attributes?.stock_qty?.[0]) > 0 ? 'text-green-600' : 'text-red-600'">
                                    <FontAwesomeIcon :icon="faCircle" class="md:text-[8px]" />
                                    <span>{{ Number(product.attributes?.stock_qty?.[0]) > 0 ?
                                        locale.number(Number(product.attributes?.stock_qty?.[0])) : 0 }} {{ trans('available') }}</span>
                                </div>
                            </div> -->

                            <!-- Prices -->
                            <div class="xflex justify-between text-xs md:text-sm ">
                                <span class="font-bold">{{ locale.currencyFormat(layout.iris.currency.code, product.attributes.price) }}<span v-if="!((Number(product.attributes.units?.[0] ?? 1) !== 1) && product.attributes.unit?.[0])">/{{ product.attributes.unit?.[0] }}</span></span> 
                                <span v-if="(Number(product.attributes.units?.[0] ?? 1) !== 1) && product.attributes.unit?.[0]" class="ml-1">({{ locale.currencyFormat(layout.iris.currency.code, product.attributes.price/Number(product.attributes.units?.[0] ?? 0)) }}/{{ product.attributes.unit?.[0] }})</span>
                            </div>

                            <!-- Section: Add to Basket Button -->
                            <div v-if="product.attributes.product_id?.[0]" class="mt-2 w-full md:w-fit">
                                <Button v-if="Number(product.attributes?.stock_qty?.[0]) > 0" @click="handleProductClick(product)"
                                    :disabled="isProductLoading(product.attributes.product_id[0])"
                                    xclass="w-full justify-center"
                                    :loading="isProductLoading(product.attributes.product_id[0])"
                                    size="sm"
                                    full
                                    icon="fas fa-cart-plus"
                                >
                                    <template #label>
                                        <span class="text-xxs md:text-sm">{{ isProductLoading(product.attributes.product_id[0]) ? trans('Adding...') : trans('Add to Basket') }}</span>
                                    </template>
                                </Button>

                                <Button v-else
                                    disabled
                                    :label="trans('Out of Stock')"
                                    type="tertiary"
                                    :size="screenType === 'mobile' ? 'sm' : 'md'"
                                    class="w-full justify-center"
                                    :loading="isProductLoading(product.attributes.product_id[0])"
                                />
                            </div>
                        </div>
                    </div>
                </SwiperSlide>

                <SwiperSlide
                    v-if="isLoadingFetch"
                    v-for="xx in 4"
                    class="w-full cursor-grab relative px-4 py-3 rounded !flex !flex-col !justify-between gap-y-4 min-h-full skeleton"
                >
                </SwiperSlide>
            </template>

            <!-- <div v-else class="w-full h-full text-center text-gray-400 py-6">
                <span class="italic">{{ trans("No recommendations found. Explore more products to get personalized suggestions") }}</span> 🙂
            </div> -->
        </Swiper>
    </div>
</template>