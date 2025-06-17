<script setup lang="ts">
import { faHeart } from '@far'
import { faCircle, faStar, faHeart as fasHeart } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { faPlus, faVial } from '@fal'
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()

defineProps<{
    product: {
        is_favourite: boolean
    }
}>()

const isLoading = ref(false)
const onClickAddToPortfolio = (productId: number) => {
    // Emit an event or call a method to handle adding the product to the portfolio
    console.log(`Adding product with ID ${productId} to portfolio`)
    // Section: Submit
    // router.post(
    //     'app/models/customer-sales-channel/manual/products',
    //     {
    //         items: [productId]
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         onStart: () => { 
    //             isLoading.value = true
    //         },
    //         onSuccess: () => {
    //             notify({
    //                 title: trans("Success"),
    //                 text: trans("Added to portfolio"),
    //                 type: "success"
    //             })
    //         },
    //         onError: errors => {
    //             notify({
    //                 title: trans("Something went wrong"),
    //                 text: trans("Failed to add to portfolio"),
    //                 type: "error"
    //             })
    //         },
    //         onFinish: () => {
    //             isLoading.value = false
    //         },
    //     }
    // )
}
</script>

<template>
    <div class="relative flex flex-col justify-between h-full bg-white">

        <!-- Top Section -->
        <div>
            <!-- {{ product.currency_code }} -->
            <!-- Bestseller Badge -->
            <div v-if="product.bestseller"
                class="absolute top-2 left-2 bg-white border border-black text-black text-xs font-bold px-2 py-0.5 rounded">
                BESTSELLER
            </div>

            <!-- Favorite Icon -->
            <div @click="() => product.is_favourite = !product.is_favourite" class="absolute top-2 right-2 ">
                <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" class="cursor-pointer text-pink-500 text-xl" />
                <FontAwesomeIcon v-else :icon="faHeart" class="cursor-pointer text-gray-400 hover:text-pink-400 text-xl" />
            </div>

            <!-- Product Image -->
            <div class="w-full h-64 mb-3 rounded">
                <Image :src="product.image?.source" alt="product image" :imageCover="true"
                    :style="{ objectFit: 'contain' }" />
            </div>

            <!-- Title -->
            <div class="font-medium text-sm mb-1">{{ product.name }}</div>

            <!-- SKU and RRP -->
            <div class="flex justify-between text-xs text-gray-600 mb-1 capitalize">
                <span>{{ product?.code }}</span>
                <span>
                    RRP: {{ locale.currencyFormat(product?.currency_code, (product.rpp || 0)) }}/ {{ product.unit }}
                </span>
            </div>

            <!-- Rating and Stock -->
            <div class="flex justify-between items-center text-xs mb-2">
                <div class="flex items-center gap-1" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                    <span>({{ product.stock > 0 ? product.stock : 0 }})</span>
                </div>
                <div class="flex items-center space-x-[1px] text-gray-500">
                    <FontAwesomeIcon v-for="i in 5" :key="i" :class="i <= product.rating ? 'fas' : 'far'" :icon="faStar"
                        class="text-xs" />
                    <span class="ml-1">5</span>
                </div>
            </div>

            <!-- Prices -->
            <div class="mb-3">
                <div class="flex justify-between text-sm font-semibold">
                    <span>{{ locale.currencyFormat(product?.currency_code, product.price) }}</span>
                    <span class="text-xs">({{ parseInt(product.units, 10) }}/{{ product.unit }})</span>
                </div>
            </div>
        </div>
        
        <!-- Bottom Section (fixed position in layout) -->
        <div v-if="layout.iris.is_logged_in">
            <div v-if="product.stock > 1" class="flex items-center gap-2 mt-2">
                <div class="flex gap-2  w-full">
                    <!-- Add to Portfolio (90%) -->
                    <button @click="() => onClickAddToPortfolio(product.id)"
                        class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                        <LoadingIcon v-if="isLoading" class="text-white" />
                        <FontAwesomeIcon v-else :icon="faPlus" class="text-base" />
                        Add to Portfolio
                    </button>

                    <!-- Buy a Sample (10%) -->
                    <button v-tooltip="'Buy  sample'"
                        class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                        <FontAwesomeIcon :icon="faVial" class="text-sm" />
                    </button>
                </div>
            </div>
            <div v-else class="mt-2">
                <button
                    class="flex items-center justify-center gap-2 bg-gray-300 text-white rounded px-4 py-2 text-sm font-semibold w-[100%] transition"
                    disabled>
                    Out of Stock
                </button>
            </div>
        </div>

        <Link v-else href="app/login" class="text-center border border-gray-200 text-sm py-2 rounded text-gray-600">
            {{ trans("Login to add to your portfolio") }}
        </Link>
    </div>
</template>
