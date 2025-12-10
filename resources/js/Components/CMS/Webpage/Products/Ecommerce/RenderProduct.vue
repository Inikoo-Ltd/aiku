<script setup lang="ts">
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'

import { faQuestionCircle } from "@fal"
import { faStarHalfAlt } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { ProductResource } from '@/types/Iris/Products'
import { routeType } from '@/types/route'
import { getProductsRenderB2bComponent } from "@/Composables/getIrisComponents"

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
    bestSeller?: any
    buttonStyleHover?: any
    buttonStyle?: object | undefined
    buttonStyleLogin?: object | undefined
    code : string
    button?: any
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


</script>

<template>
    <component 
        :is="getProductsRenderB2bComponent(code)" 
        :product="product"
        :buttonStyle="buttonStyle"
        :buttonStyleLogin="buttonStyleLogin"
        :hasInBasket="hasInBasket"
        :buttonStyleHover="buttonStyleHover" 
        @setFavorite="onAddFavourite"
        @unsetFavorite="onUnselectFavourite"
        @setBackInStock="onAddBackInStock"
        @unsetBackInStock="onUnselectBackInStock"
        basketButton
        :isLoadingFavourite
        :isLoadingRemindBackInStock
        :button
    />
</template>

<style scoped></style>