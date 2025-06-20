<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faHeart } from '@far'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheck } from '@far'
import { faPlus, faVial } from '@fal'
import { faCircle, faStar, faHeart as fasHeart, faEllipsisV } from '@fas'
import { Image as ImageTS } from '@/types/Image'
import ButtonAddPortfolio from '@/Components/Iris/Products/ButtonAddPortfolio.vue'


const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
    is_exist_in_all_channel: boolean
}

const props = defineProps<{
    product: ProductResource
    productHasPortfolio : Array<Number>
}>()


const currency = layout?.iris?.currency

// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route('iris.models.favourites.store', {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                product.is_favourite = true
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}
const onUnselectFavourite = (product: ProductResource) => {

    // Section: Submit
    router.delete(
        route('iris.models.favourites.delete', {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                // notify({
                //     title: trans("Success"),
                //     text: trans("Added to portfolio"),
                //     type: "success"
                // })
                product.is_favourite = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
            },
        }
    )
}
</script>

<template>
    <div class="relative flex flex-col justify-between h-full bg-white">

        <!-- Top Section -->
        <div>
            <!-- {{ product.currency_code }} -->
            <!-- Bestseller Badge -->
            <div v-if="product.bestseller"
                class="absolute top-2 left-2 bg-white border border-black text-xs font-bold px-2 py-0.5 rounded">
                BESTSELLER
            </div>

            <!-- Favorite Icon -->
            <template v-if="layout.iris.is_logged_in">
                <div v-if="isLoadingFavourite" class="absolute top-2 right-2 text-gray-500 text-xl">
                    <LoadingIcon />
                </div>
                <div v-else @click="() => product.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)" class="cursor-pointer absolute top-2 right-2 group text-xl ">
                    <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" fixed-width class="text-pink-500" />
                    <FontAwesomeIcon v-else :icon="faHeart" fixed-width class="text-gray-400 group-hover:text-pink-400" />
                </div>
            </template>

            <!-- Product Image -->
            <component :is="product.url ? Link : 'div'" :href="product.url" class="block w-full h-64 mb-3 rounded">
                <Image :src="product.image?.source" alt="product image" :imageCover="true"
                    :style="{ objectFit: 'contain' }" />
            </component>

            <!-- Title -->
            <Link v-if="product.url" :href="product.url" class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                {{ product.name }}
            </Link>
            <div v-else class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                {{ product.name }}
            </div>

            <!-- SKU and RRP -->
            <div class="flex justify-between text-xs text-gray-600 mb-1 capitalize">
                <span>{{ product?.code }}</span>
                <span v-if="product.rpp">
                    RRP: {{ locale.currencyFormat((currency.code,product.rpp || 0)) }}/ {{ product.unit }}
                </span>
            </div>

            <!-- Rating and Stock -->
            <div class="flex justify-between items-center text-xs mb-2">
                <div class="flex items-center gap-1" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                    <span>({{ product.stock > 0 ? product.stock : 0 }})</span>
                </div>
                <div class="flex items-center space-x-[1px] text-gray-500">
                    <!-- <FontAwesomeIcon v-for="i in 5" :key="i" :class="i <= product.rating ? 'fas' : 'far'" :icon="faStar"
                        class="text-xs" />
                    <span class="ml-1">5</span> -->
                </div>
            </div>

            <!-- Prices -->
            <div class="mb-3">
                <div class="flex justify-between text-sm font-semibold">
                    <span>{{ locale.currencyFormat(currency.code,product.price) }}</span>
                  <!--   <span class="text-xs">({{ locale.number(product.units) }}/{{ product.unit }})</span> -->
                </div>
            </div>
        </div>
        
        <!-- Button: add to portfolios -->
        <ButtonAddPortfolio
            :product="product"
            :productHasPortfolio="productHasPortfolio"
        />
    </div>
</template>


<style scoped>
</style>