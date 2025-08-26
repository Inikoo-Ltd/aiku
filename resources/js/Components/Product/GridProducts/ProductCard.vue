<template>
    <article 
        class="bg-white p-2 border rounded-xl hover:shadow-md transition-shadow relative cursor-pointer group"
        :aria-label="`Product: ${productName}`"
    >
        <!-- Favorite Button -->
        <button
            @click.stop="handleToggleFavorite"
            class="absolute top-3 right-3 z-10 p-2 backdrop-blur-sm rounded-full shadow-md hover:shadow-lg transition-all hover:scale-110 flex items-center justify-center"
            :class="favoriteButtonClass"
            :aria-label="favoriteButtonLabel"
            :aria-pressed="isFavorite"
            type="button"
        >
            <FontAwesomeIcon 
                :icon="favoriteIcon" 
                class="w-5 h-5"
                :aria-hidden="true"
            />
        </button>

        <!-- Product Image Container -->
        <div class="relative h-[100px] md:h-[150px] lg:h-[200px] aspect-square w-full rounded-lg overflow-hidden bg-gray-100 mb-4">

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
                class="text-xs text-gray-600"
                :title="`${trans('Code')}: ${product.code}`"
            >
                {{ product.code }}
            </p>
            
            <!-- Price Display -->
            <div v-if="props.product?.price" class="mt-2">
                <span class="text-sm font-semibold">
                    {{ locale.currencyFormat(layout?.retina?.currency?.code, props.product.price) }}
                </span>
            </div>

        </div>
    </article>
</template>

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

import { ref, computed, inject } from 'vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import type { IconProp } from '@fortawesome/fontawesome-svg-core'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import type { Product, ImageSource } from './types'
import Image from '@/Components/Image.vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

// Props definition
interface Props {
    product: Product
}

const props = withDefaults(defineProps<Props>(), {
  
})

// Emits
const emit = defineEmits<{
    'toggle-favorite': [product: Product]
    'click': [product: Product]
}>()

// Inject locale
const locale = inject('locale', aikuLocaleStructure)
const layout = inject('layout', retinaLayoutStructure)

// const imageError = ref(false)

// Computed properties
const productName = computed(() => {
    return props.product.name || trans('Unnamed Product')
})

// Default is_favourite to true if undefined
const isFavorite = computed(() => {
    return props.product.is_favourite !== undefined ? props.product.is_favourite : true
})

const favoriteIcon = computed<IconProp>(() => {
    return props.product.is_favourite 
        ? 'fas fa-heart' as IconProp
        : 'fal fa-heart' as IconProp
})

const favoriteButtonClass = computed(() => {
    return props.product.is_favourite 
        ? 'bg-white text-red-500' 
        : 'bg-white/90 text-gray-400 hover:text-red-500'
})

const favoriteButtonLabel = computed(() => {
    return props.product.is_favourite 
        ? trans('Remove from favorites') 
        : trans('Add to favorites')
})

// Methods
const handleToggleFavorite = () => {
    emit('toggle-favorite', props.product)
}
</script>