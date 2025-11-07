<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { faEnvelope, faHeart } from '@far'
import { faCircle, faHeart as fasHeart, faMedal } from '@fas'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ProductResource } from '@/types/Iris/Products'
import NewAddToCartButton from './NewAddToCartButton.vue' // Import button baru
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons'
import { routeType } from '@/types/route'
import LinkIris from '@/Components/Iris/LinkIris.vue'
import BestsellerBadge from '@/Components/CMS/Webpage/Products1/BestsellerBadge.vue'
import Prices from './Prices.vue'

library.add(faStarHalfAlt, faQuestionCircle)

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()


const props = withDefaults(defineProps<{
    product: ProductResource
    hasInBasket?: any
    basketButton?: boolean
    attachToFavouriteRoute?: routeType
    dettachToFavouriteRoute?: routeType
    attachBackInStockRoute?: routeType
    detachBackInStockRoute?: routeType
    addToBasketRoute?: routeType
    updateBasketQuantityRoute?: routeType
    bestSeller?:any

}>(), {
    basketButton: true,
    addToBasketRoute: {
        name: 'iris.models.transaction.store',
    },
    updateBasketQuantityRoute: {
        name: 'iris.models.transaction.update',
    },
    attachToFavouriteRoute: {
        name: 'iris.models.favourites.store',
    },
    dettachToFavouriteRoute: {
        name: 'iris.models.favourites.delete',
    },
    attachBackInStockRoute: {
        name: 'iris.models.remind_back_in_stock.store',
    },
    detachBackInStockRoute: {
        name: 'iris.models.remind_back_in_stock.delete',
    },
})

const emits = defineEmits<{
    (e: 'afterOnAddFavourite', value: any[]): void
    (e: 'afterOnUnselectFavourite', value: any[]): void
    (e: 'afterOnAddBackInStock', value: any[]): void
    (e: 'afterOnUnselectBackInStock', value: any[]): void
}>()


const isLoadingRemindBackInStock = ref(false)
const currency = layout?.iris?.currency

// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route(props.attachToFavouriteRoute.name, {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            only: ['iris'],
            preserveState: true,
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                product.is_favourite = true
                layout.reload_handle()
            },
            onError: errors => {
                console.error(errors)
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingFavourite.value = false
                emits('afterOnAddFavourite', product)
            },
        }
    )
}
const onUnselectFavourite = (product: ProductResource) => {

    // Section: Submit
    router.delete(
        route(props.dettachToFavouriteRoute.name, {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ['iris'],
            onStart: () => {
                isLoadingFavourite.value = true
            },
            onSuccess: () => {
                // notify({
                //     title: trans("Success"),
                //     text: trans("Added to portfolio"),
                //     type: "success"
                // })
                layout.reload_handle()
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
                emits('afterOnUnselectFavourite', product)
            },
        }
    )
}


const onAddBackInStock = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route(props.attachBackInStockRoute.name, {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            only: ['iris'],
            preserveState: true,
            onStart: () => {
                isLoadingRemindBackInStock.value = true
            },
            onSuccess: () => {
                product.is_back_in_stock = true
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to remind back in stock"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingRemindBackInStock.value = false
                emits('afterOnAddBackInStock', product)
            },
        }
    )
}
const onUnselectBackInStock = (product: ProductResource) => {
    router.delete(
        route(props.detachBackInStockRoute.name, {
            product: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ['iris'],
            onStart: () => {
                isLoadingRemindBackInStock.value = true
            },
            onSuccess: () => {
                // notify({
                //     title: trans("Success"),
                //     text: trans("Added to portfolio"),
                //     type: "success"
                // })
                product.is_back_in_stock = false
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from remind back in stock"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingRemindBackInStock.value = false
                emits('afterOnUnselectBackInStock', product)
            },
        }
    )
}


const profitMargin = computed(() => {
    const price = props.product?.price
    const rrp = props.product?.rrp
    if (!price || !rrp) return 0
    return Math.floor(((rrp - price) / rrp) * 100)
})




</script>

<template>
    <div class="pb-3 relative flex flex-col justify-between h-full" comp="product-render-ecom">

        <!-- Top Section: Stock, Images, Title, Code, Price -->
        <div class=" text-gray-800 isolate">
            <!-- <div v-if="product?.top_seller"
                class="z-10 absolute top-2 left-2 border text-xs font-bold px-2 py-0.5 rounded" :class="{
                    'text-[#FFD700] bg-[#584b015] border-[#FFD700]': product.top_seller == 1, // Gold
                    'text-[#C0C0C0] bg-[#C0C0C033] border-[#C0C0C0]': product.top_seller === 2, // Silver
                    'text-[#CD7F32] bg-[#CD7F3222] border-[#CD7F32]': product.top_seller === 3  // Bronze
                }">
                <FontAwesomeIcon :icon="faMedal" class=" mr-0 md:mr-2" fixed-width s />

                <span class="hidden md:inline">{{ trans("BESTSELLER") }}</span>
            </div> -->
            <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" />


            <!-- Product Image -->
            <component :is="product.url ? Link : 'div'" :href="product.url"
                class="block w-full mb-1 rounded sm:h-[305px] h-[180px] relative">
                <slot name="image" :product="product">
                    <Image :src="product?.web_images?.main?.gallery" alt="product image"
                        :style="{ objectFit: 'contain' }" />
                </slot>

                <!-- New Add to Cart Button - hanya tampil jika user sudah login -->
                <div v-if="layout?.iris?.is_logged_in" class="absolute right-2 bottom-2">
                    <NewAddToCartButton v-if="product.stock > 0 && basketButton" :hasInBasket :product="product"
                        :key="product" :addToBasketRoute="addToBasketRoute"
                        :updateBasketQuantityRoute="updateBasketQuantityRoute" />
                    <button v-else-if="layout?.app?.environment === 'local' && product.stock < 1"
                        @click.prevent="() => product.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                        class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        v-tooltip="product.is_back_in_stock ? trans('You will be notified') : trans('Remind me when back in stock')">
                        <LoadingIcon v-if="isLoadingRemindBackInStock" />
                        <FontAwesomeIcon v-else :icon="product.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                            fixed-width :class="[product.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                    </button>
                </div>
            </component>

            <div class="px-3">
                <!-- Title -->
                <LinkIris v-if="product.url" :href="product.url" class="hover:text-gray-500 font-bold text-sm mb-1"
                    type="internal">
                    <template #default>
                          <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span> {{ product.name }}
                    </template>
                </LinkIris>
                <div v-else class="hover:text-gray-500 font-bold text-sm mb-1">
                     <span v-if="product.units != 1" class="text-indigo-900">{{ product.units }}x</span> {{ product.name }}
                </div>

            <!-- Price Card -->
                 <div class="flex justify-between text-xs text-gray-600 mb-1">
                <span>{{ product?.code }}</span>
                <span v-if="product.rpp">
                    RRP: {{ locale.currencyFormat((currency.code, product.rpp || 0)) }}/ {{ product.unit }}
                </span>

                <div class="flex justify-between items-center text-xs mb-2">
                    <!-- Stock indicator -->
                    <div v-if="layout?.iris?.is_logged_in"
                         class="flex items-center gap-1 px-2 py-0.5 rounded-full font-medium"
                         :class="product.stock > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'">
                        <FontAwesomeIcon :icon="faCircle" class="text-[7px]" />
                        <span>{{ product.stock > 0 ? product.stock : 0 }} {{ trans("available") }}</span>
                    </div>
                </div>
            </div>

            <!-- Price Card -->
             <Prices :product="product" :currency="currency" />
            
            </div>
        </div>

        <!-- Login Button for Non-Logged In Users -->
        <div v-if="!layout?.iris?.is_logged_in" class="px-3">
            <a :href="urlLoginWithRedirect()"
                class="block text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
            {{ trans("Login or Register for Wholesale Prices") }}
            </a>
        </div>
    </div>
</template>


<style scoped></style>