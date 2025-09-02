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

import {ref, inject} from 'vue'
import {trans} from 'laravel-vue-i18n'
import {aikuLocaleStructure} from '@/Composables/useLocaleStructure'
import type {Product} from './types'
import Image from '@/Components/Image.vue'
import {retinaLayoutStructure} from '@/Composables/useRetinaLayoutStructure'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome"
import {faHeart} from "@fal"
import {faHeart as fasHeart} from "@fas"
import {library} from "@fortawesome/fontawesome-svg-core"
import {notify} from '@kyvg/vue3-notification'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import {router} from '@inertiajs/vue3'

library.add(faHeart, fasHeart)

const props = defineProps<{
    product: Product
}>()

// Inject locale
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)


const isLoading = ref(false)
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
                    isLoading.value = true
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
                    isLoading.value = false
                },
            }
        )
    } else {
        // Remove from favorites
        router.delete(
            route('retina.models.product.unfavourite', {favourite: product.favourite_id}),
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    isLoading.value = true
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
                    isLoading.value = false
                },
            }
        )
    }
}
</script>

<template>
    <article
        class="bg-white py-2 px-4 border rounded hover:shadow transition-shadow relative cursor-pointer"
    >
        <!-- Favorite Button -->
        <button :disabled="isLoading"
            @click.stop="() => toggleFavorite(product)"
            class="group absolute top-3 right-3 z-10 p-2 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-all xhover:scale-110 flex items-center justify-center"
            type="button"
        >
            <!-- <FontAwesomeIcon icon="fal fa-heart" class="w-5 h-5 bg-white text-red-500" fixed-width aria-hidden="true" />
            <FontAwesomeIcon icon="fas fa-heart" class="w-5 h-5 bg-white/90 text-gray-400 hover:text-red-500" fixed-width aria-hidden="true" /> -->
            <LoadingIcon v-if="isLoading" class="h-5 w-5"/>
            <div v-else-if="product.is_not_favourite" class="relative">
                <FontAwesomeIcon
                    :icon="fasHeart"
                    class="h-5 w-5 hidden group-hover:inline text-pink-300"
                    fixed-width
                />
                <FontAwesomeIcon
                    :icon="faHeart"
                    class="h-5 w-5 inline group-hover:hidden text-pink-500"
                    fixed-width
                />
            </div>
            <FontAwesomeIcon
                v-else
                :icon="fasHeart"
                fixed-width
                class="h-5 w-5 text-pink-500 group-hover:text-pink-600"
            />
        </button>

        <!-- Product Image Container -->
        <div
            class="relative h-[100px] md:h-[150px] lg:h-[200px] aspect-square w-full rounded-lg overflow-hidden bg-gray-100 mb-4">

            <!-- Product Image with Picture Element for Format Support -->
            <Image
                v-if="props.product?.image?.source || props.product?.image?.thumbnail"
                :src="props.product?.image?.source || props.product?.image?.thumbnail"
            />

            <!-- Placeholder when no image -->
            <div
                v-else
                class="w-full h-full flex items-center justify-center bg-gray-200"
            >
                <FontAwesomeIcon
                    icon="fal fa-image"
                    class="text-gray-400 text-3xl"
                    :aria-hidden="true"
                />
                <span class="sr-only">{{ trans('No image available') }}</span>
            </div>
        </div>

        <!-- Product Information -->
        <div class="space-y-1">
            <!-- Product Name -->
            <h3
                class="text-base font-medium line-clamp-2 min-h-[2.5rem]"
                :title="props.product.name || trans('No name')"
            >
                {{ props.product.name || '-' }}
            </h3>

            <!-- Product Code -->
            <p
                class="text-xs text-gray-400"
                :title="`${trans('Code')}: ${product.code}`"
            >
                {{ product.code }}
            </p>

            <!-- Price Display -->
            <div v-if="props.product?.price" class="mt-2">
                <span class="xtext-sm font-semibold">
                    {{ locale.currencyFormat(layout?.retina?.currency?.code, props.product.price) }}
                </span>
            </div>

        </div>
    </article>
</template>

