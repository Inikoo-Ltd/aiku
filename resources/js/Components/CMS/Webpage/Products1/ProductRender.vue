<script setup lang="ts">
import Image from "@/Components/Image.vue"
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref, computed } from "vue"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { faHeart } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCircle, faHeart as fasHeart, faMedal } from "@fas"
import { Image as ImageTS } from "@/types/Image"
import ButtonAddPortfolio from "@/Components/Iris/Products/ButtonAddPortfolio.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import BestsellerBadge from "@/Components/CMS/Webpage/Products1/BestsellerBadge.vue"
import Prices from "./Prices.vue"

const layout = inject("layout", retinaLayoutStructure)

const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS,
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
    top_seller: number | null
    web_images: {
        main: {
            original: ImageTS,
            gallery: ImageTS
        }
    }
}

const props = defineProps<{
    product: ProductResource
    productHasPortfolio: Array<Number>
    bestSeller: any
    currency?: {
        code: string
        name: string
    }
}>()


const currency = layout?.iris?.currency || props.currency
const isLoadingRemindBackInStock = ref(false)

// Section: Add to Favourites
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route("iris.models.favourites.store", {
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
            }
        }
    )
}
const onUnselectFavourite = (product: ProductResource) => {

    // Section: Submit
    router.delete(
        route("iris.models.favourites.delete", {
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
            }
        }
    )
}

const onAddBackInStock = (product: ProductResource) => {

    // Section: Submit
    router.post(
        route("iris.models.remind_back_in_stock.store", {
            product: product.id
        }),
        {
            // item_id: [product.id]
        },
        {
            preserveScroll: true,
            only: ["iris"],
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
            }
        }
    )
}
const onUnselectBackInStock = (product: ProductResource) => {
    router.delete(
        route("iris.models.remind_back_in_stock.delete", {
            backInStockReminder: product.id
        }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
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
                isLoadingFavourite.value = false
            }
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
    <div class="relative flex flex-col justify-between h-full">
        <!-- Top Section -->
        <div>
            <!-- <div v-if="product?.top_seller"
                class="absolute top-2 left-2 bg-white border border-black text-xs font-bold px-2 py-0.5 rounded">
                <FontAwesomeIcon :icon="faMedal" class="w-3.5 h-3.5 mr-0 md:mr-2" :class="{

                    'text-[#FFD700]': product.top_seller === 1, // Gold
                    'text-[#C0C0C0]': product.top_seller === 2, // Silver
                    'text-[#CD7F32]': product.top_seller === 3  // Bronze
                }" />

                <span class="hidden md:inline">BESTSELLER</span>
            </div> -->

            <BestsellerBadge v-if="product?.top_seller" :topSeller="product?.top_seller" :data="bestSeller" />
            <!-- Favorite Icon -->
            <template v-if="layout?.retina?.type != 'dropshipping' && layout?.iris?.is_logged_in">

                <div v-if="isLoadingFavourite" class="absolute top-2 right-2 text-gray-500 text-xl">
                    <LoadingIcon />
                </div>
                <div v-else @click="() => product.is_favourite ? onUnselectFavourite(product) : onAddFavourite(product)"
                     class="cursor-pointer absolute top-2 right-2 group text-xl ">

                    <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" fixed-width class="text-pink-500" />
                    <div v-else class="relative">
                        <FontAwesomeIcon :icon="fasHeart" class="hidden group-hover:inline text-pink-400" fixed-width />
                        <FontAwesomeIcon :icon="faHeart" class="inline group-hover:hidden text-pink-300" fixed-width />
                    </div>

                </div>
            </template>

            <!-- Product Image -->
            <component :is="product.url ? Link : 'div'" :href="product.url"
                       class="block w-full mb-1 rounded sm:h-[305px] h-[180px]">
                <Image :src="product?.web_images?.main?.gallery" alt="product image"
                       :style="{ objectFit: 'contain' }" />
            </component>

            <!-- Title -->

            <LinkIris v-if="product.url" :href="product.url" type="internal" class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                <template #default>
                    <span  class="text-indigo-900">{{ product.units }}x</span> {{ product.name }}
                </template>
            </LinkIris>

            <div v-else class="text-gray-800 hover:text-gray-500 font-bold text-sm mb-1">
                <span class="text-indigo-900">{{ product.units }}x</span> {{ product.name }}
            </div>

            <!-- SKU and RRP -->
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

                    <!-- Notify button as tag -->
                    <!-- <button v-if="product.stock == 0 && layout?.app?.environment === 'local'" type="button"
                    @click.prevent="() => product?.is_back_in_stock ? onUnselectBackInStock(product) : onAddBackInStock(product)"
                    v-tooltip="'Will notify you when product is in stock'"
                    class="flex items-center gap-1 px-2 rounded-full border text-gray-500 hover:bg-gray-100 hover:text-gray-800 transition text-xs">
                    <LoadingIcon v-if="isLoadingRemindBackInStock" />
                    <FontAwesomeIcon v-else :icon="product?.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                        fixed-width :class="[product?.is_back_in_stock ? 'text-green-600' : 'text-gray-600']" />
                    <span>{{ trans('Notify me') }}</span>
                </button> -->
                </div>
            </div>

            <!-- Price Card -->
           <Prices :product="product" :currency="currency" />
        </div>


        <ButtonAddPortfolio :product="product" :productHasPortfolio="productHasPortfolio" />
    </div>
</template>


<style scoped></style>