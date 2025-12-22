<script setup lang="ts">
import { ref, inject } from "vue"
import { Head, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"

import { library } from "@fortawesome/fontawesome-svg-core"
import { faHeart } from "@fas"
import { faBoxOpen, faImage } from "@fal"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { retinaLayoutStructure } from "@/Composables/useRetinaLayoutStructure"
import { ulid } from "ulid"

import { GridProducts } from "@/Components/Product"
import ProductRenderEcom from "@/Components/CMS/Webpage/Products1/Ecommerce/ProductRenderEcom.vue"

library.add(faHeart, faBoxOpen, faImage)

interface ProductResource {
    id: number
    is_favourite?: boolean
    is_back_in_stock?: boolean
}

const props = defineProps<{
    data: Record<string, any>
    title: string
    pageHead: Record<string, any>
    basketTransactions?: Record<
        number,
        {
            id: string
            quantity_ordered: number
            asset_id: number
        }
    >
    attachToFavouriteRoute: { name: string }
    dettachToFavouriteRoute: { name: string }
    attachBackInStockRoute: { name: string }
    detachBackInStockRoute: { name: string }
	addToBasketRoute:{ name: string }
    updateBasketQuantityRoute: { name: string }
}>()

const emits = defineEmits([
    "afterOnAddFavourite",
    "afterOnUnselectFavourite",
    "afterOnAddBackInStock",
    "afterOnUnselectBackInStock",
])

const isLoadingFavourite = ref<number[]>([])
const isLoadingRemindBackInStock = ref<number[]>([])
const key = ref(ulid())
const layout = inject("layout", retinaLayoutStructure)

const hasInBasket = (product: ProductResource) =>
    !!props.basketTransactions?.[product.id]

const startLoading = (state: typeof isLoadingFavourite, id: number) => {
    if (!state.value.includes(id)) state.value.push(id)
}

const stopLoading = (state: typeof isLoadingFavourite, id: number) => {
    state.value = state.value.filter(v => v !== id)
}

/* ================= FAVORITE ================= */

const onAddFavourite = (product: ProductResource) => {
    router.post(
        route(props.attachToFavouriteRoute.name, { product: product.id }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => startLoading(isLoadingFavourite, product.id),
            onSuccess: () => {
                product.is_favourite = true
                layout?.reload_handle?.()
				router.reload()
				key.value = ulid()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to favourites"),
                    type: "error",
                })
            },
            onFinish: () => {
                stopLoading(isLoadingFavourite, product.id)
                emits("afterOnAddFavourite", product)
            },
        }
    )
}

const onUnselectFavourite = (product: ProductResource) => {
    router.delete(
        route(props.dettachToFavouriteRoute.name, { product: product.id }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => startLoading(isLoadingFavourite, product.id),
            onSuccess: () => {
                product.is_favourite = false
                layout?.reload_handle?.()
				router.reload()
				key.value = ulid()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from favourites"),
                    type: "error",
                })
            },
            onFinish: () => {
                stopLoading(isLoadingFavourite, product.id)
                emits("afterOnUnselectFavourite", product)
            },
        }
    )
}

/* ================= BACK IN STOCK ================= */

const onAddBackInStock = (product: ProductResource) => {
    router.post(
        route(props.attachBackInStockRoute.name, { product: product.id }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => startLoading(isLoadingRemindBackInStock, product.id),
            onSuccess: () => {
                product.is_back_in_stock = true
                layout?.reload_handle?.()
				router.reload()
				key.value = ulid()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add the product to remind back in stock"),
                    type: "error",
                })
            },
            onFinish: () => {
                stopLoading(isLoadingRemindBackInStock, product.id)
                emits("afterOnAddBackInStock", product)
            },
        }
    )
}

const onUnselectBackInStock = (product: ProductResource) => {
    router.delete(
        route(props.detachBackInStockRoute.name, { product: product.id }),
        {
            preserveScroll: true,
            preserveState: true,
            only: ["iris"],
            onStart: () => startLoading(isLoadingRemindBackInStock, product.id),
            onSuccess: () => {
                product.is_back_in_stock = false
                layout?.reload_handle?.()
				router.reload()
				key.value = ulid()
            },
            onError: () => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to remove the product from remind back in stock"),
                    type: "error",
                })
            },
            onFinish: () => {
                stopLoading(isLoadingRemindBackInStock, product.id)
                emits("afterOnUnselectBackInStock", product)
            },
        }
    )
}

console.log('sdfsdf',props)
</script>


<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <GridProducts
        :resource="data"
        :basket-transactions="basketTransactions"
        :preserve-scroll="true"
        class="mt-5"
		:key="key"
    >
        <template #card="{ item }">
            <ProductRenderEcom
                :product="item"
                :hasInBasket="item"
                :isLoadingRemindBackInStock="isLoadingRemindBackInStock.includes(item.id)"
                :isLoadingFavourite="isLoadingFavourite.includes(item.id)"
                @setBackInStock="onAddBackInStock"
                @unsetBackInStock="onUnselectBackInStock"
                @set-favorite="onAddFavourite"
                @unset-favorite="onUnselectFavourite"
				:addToBasketRoute="addToBasketRoute"
				:update-basket-quantity-route="updateBasketQuantityRoute"
            />
        </template>
    </GridProducts>
</template>
