<script setup lang="ts">
/**
 * ProductCard Component
 *
 * Displays individual product information in a card format with:
 * - Product image with lazy loading
 * - Favorite toggle functionality
 * - Price display with locale formatting
 * - Stock status indication
 * - Accessibility features
 */

import {ref, inject, computed} from 'vue'
import {trans} from 'laravel-vue-i18n'
import {aikuLocaleStructure} from '@/Composables/useLocaleStructure'
import type {Product} from './types'
import Image from '@/Components/Image.vue'
import {retinaLayoutStructure} from '@/Composables/useRetinaLayoutStructure'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faHeart, faShoppingCart} from "@fal"
import {faHeart as fasHeart, faShoppingCart as fasShoppingCart, faCheck, faTimes} from "@fas"
import {library} from "@fortawesome/fontawesome-svg-core"
import {notify} from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import {router} from '@inertiajs/vue3'

library.add(faHeart, fasHeart, faShoppingCart, fasShoppingCart, faCheck, faTimes)

const props = defineProps<{
    product: Product
    existingTransaction?: {
        id: string
        quantity_ordered: number
    } | null
}>()

console.log(props)

const emit = defineEmits<{
    'toggle-favorite': [product: Product]
    'add-to-basket': [product: Product]
}>()

// console.log(props.product);

// Inject locale
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

// Local loading states
const isLoadingFavorite = ref(false)
const isLoadingAddToBasket = ref(false)

// Computed properties
const isInBasket = computed(() => {
    return props.existingTransaction !== null && props.existingTransaction !== undefined
})

const buttonLabel = computed(() => {
    return isInBasket.value 
        ? trans('Add more') 
        : trans('Add to basket')
})

const buttonTooltip = computed(() => {
    return isInBasket.value 
        ? trans('Add one more to basket (current: :quantity)', { quantity: props.existingTransaction?.quantity_ordered || 0 })
        : trans('Add to basket')
})

const navigateToProduct = () => {
    if (props.product.url) {
        router.visit(props.product.url)
    }
}

const onAddProducts = async (product: Product) => {
    // Determine if we should add new or update existing transaction
    const isExistingTransaction = props.existingTransaction !== null && props.existingTransaction !== undefined
    
    if (isExistingTransaction && props.existingTransaction) {
        // Update existing transaction
        router.patch(
            route('retina.models.transaction.update', {
                transaction: props.existingTransaction.id
            }),
            {
                quantity_ordered: (props.existingTransaction.quantity_ordered + 1).toString(),
            },
            {
                only: ['transactions', 'box_stats', 'total_products', 'balance', 'total_to_pay'],
                onStart: () => {
                    isLoadingAddToBasket.value = true
                    emit('add-to-basket', product)
                },
                onError: (error) => {
                    notify({
                        title: trans("Something went wrong."),
                        text: error.products || trans("Failed to add to basket"),
                        type: "error"
                    })
                },
                onSuccess: () => {
                    notify({
                        title: trans("Success!"),
                        text: trans("Product quantity updated in basket"),
                        type: "success"
                    })
                },
                onFinish: () => {
                    isLoadingAddToBasket.value = false
                }
            }
        )
    } else {
        // Add new product to basket
        router.post(
            route('retina.models.product.add-to-basket', { 
                product: product.id
            }),
            {
                quantity: 1,
            },
            {
                onStart: () => {
                    isLoadingAddToBasket.value = true
                    emit('add-to-basket', product)
                },
                onError: (error) => {
                    notify({
                        title: trans("Something went wrong."),
                        text: error.products || trans("Failed to add to basket"),
                        type: "error"
                    })
                },
                onSuccess: () => {
                    // Luigi: event add to cart (only for new products)
                    window?.dataLayer?.push({
                        event: "add_to_cart",
                        ecommerce: {
                            currency: layout?.retina?.currency?.code,
                            value: product.price,
                            items: [
                                {
                                    item_id: product.id,
                                }
                            ]
                        }
                    })
                    
                    notify({
                        title: trans("Success!"),
                        text: trans("Product added to basket"),
                        type: "success"
                    })
                },
                onFinish: () => {
                    isLoadingAddToBasket.value = false
                }
            }
        )
    }
}

const toggleFavorite = (product: Product) => {
    if (product.is_not_favourite) {
        // Add to favorites
        router.post(
            route('retina.models.product.favourite', {product: product.id}),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    isLoadingFavorite.value = true
                },
                onSuccess: () => {
                    product.is_not_favourite = false
                    notify({
                        title: trans("Added to favourites!"),
                        text: trans(':product has been added to favorites', { product: product.name}),
                        type: "success",
                        duration: 3000
                    })
                },
                onError: (errors) => {
                    notify({
                        title: trans("Something went wrong"),
                        text: trans("Failed to add to favorites"),
                        type: "error",
                        duration: 3000
                    })
                    console.error('Failed to favorite:', errors)
                },
                onFinish: () => {
                    isLoadingFavorite.value = false
                },
            }
        )
    } else {
        // Remove from favorites
        router.delete(
            route('retina.models.product.unfavourite', {  product: product.id}),
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    isLoadingFavorite.value = true
                },
                onSuccess: () => {
                    product.is_not_favourite = true
                    notify({
                        title: trans("Removed from favorites"),
                        text: `${product.name || 'Product'} ${trans('has been removed from your favorites')}`,
                        type: "info",
                        duration: 3000
                    })
                },
                onError: (errors) => {
                    notify({
                        title: trans("Something went wrong"),
                        text: trans("Failed to remove from favorites"),
                        type: "error",
                        duration: 3000
                    })
                    console.error('Failed to unfavorite:', errors)
                },
                onFinish: () => {
                    isLoadingFavorite.value = false
                },
            }
        )
    }
}
</script>

<template>
    <article @click="navigateToProduct"
        class="bg-white py-2 px-4 border rounded hover:shadow transition-shadow relative cursor-pointer">
        
        <!-- Favorite Button -->
        <button :disabled="isLoadingFavorite" @click.stop="() => toggleFavorite(product)"
            class="group absolute top-3 right-3 z-10 p-2 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-all xhover:scale-110 flex items-center justify-center"
            type="button">
            <LoadingIcon v-if="isLoadingFavorite" class="h-5 w-5" />
            <div v-else-if="product.is_not_favourite" class="relative">
                <FontAwesomeIcon :icon="fasHeart" class="h-5 w-5 hidden group-hover:inline text-pink-300" fixed-width />
                <FontAwesomeIcon :icon="faHeart" class="h-5 w-5 inline group-hover:hidden text-pink-500" fixed-width />
            </div>
            <FontAwesomeIcon v-else :icon="fasHeart" fixed-width
                class="h-5 w-5 text-pink-500 group-hover:text-pink-600" />
        </button>

        <!-- Product Image Container -->
        <div
            class="relative h-[100px] md:h-[150px] lg:h-[200px] aspect-square w-full rounded-lg overflow-hidden bg-gray-100 mb-4">

            <!-- Product Image with Picture Element for Format Support -->
            <Image v-if="props.product?.image?.source || props.product?.image?.thumbnail"
                :src="props.product?.image?.source || props.product?.image?.thumbnail" />

            <!-- Placeholder when no image -->
            <div v-else class="w-full h-full flex items-center justify-center bg-gray-200">
                <FontAwesomeIcon icon="fal fa-image" class="text-gray-400 text-3xl" :aria-hidden="true" />
                <span class="sr-only">{{ trans('No image available') }}</span>
            </div>
        </div>

        <!-- Product Information -->
        <div class="space-y-1">
            <!-- Product Name -->
            <h3 class="text-base font-medium line-clamp-2 min-h-[2.5rem]"
                :title="props.product.name || trans('No name')">
                {{ props.product.name || '-' }}
            </h3>

            <!-- Product Code -->
            <p class="text-xs text-gray-400" :title="`${trans('Code')}: ${product.code}`">
                {{ product.code }}
            </p>

            <!-- Price Display -->
            <div v-if="props.product?.price" class="my-2">
                <span class="xtext-sm font-semibold">
                    {{ locale.currencyFormat(layout?.retina?.currency?.code, props.product.price) }}
                </span>
            </div>
            
            <!-- Add to Basket Button -->
            <Button 
                @click.stop="() => onAddProducts(product)" 
                :loading="isLoadingAddToBasket"
                :disabled="isLoadingAddToBasket"
                :type="isInBasket ? 'secondary' : 'primary'"
                :label="buttonLabel" 
                :tooltip="buttonTooltip" 
                full 
            />
        </div>
    </article>
</template>

