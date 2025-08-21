<template>
    <article 
        class="bg-white p-2 border rounded-xl hover:shadow-md transition-shadow relative cursor-pointer group"
        :aria-label="`Product: ${productName}`"
        @click="handleClick"
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
            <!-- Loading State -->
            <div 
                v-if="imageLoading" 
                class="absolute inset-0 flex items-center justify-center bg-gray-100"
            >
                <div class="animate-pulse">
                    <FontAwesomeIcon 
                        icon="fal fa-spinner" 
                        class="text-gray-400 text-2xl animate-spin"
                    />
                </div>
            </div>

            <!-- Product Image with Picture Element for Format Support -->
            <picture v-if="productImageSources">
                <source 
                    v-if="productImageSources.avif" 
                    :srcset="`${productImageSources.avif} 1x, ${productImageSources.avif_2x || productImageSources.avif} 2x`"
                    type="image/avif"
                />
                <source 
                    v-if="productImageSources.webp" 
                    :srcset="`${productImageSources.webp} 1x, ${productImageSources.webp_2x || productImageSources.webp} 2x`"
                    type="image/webp"
                />
                <img 
                    :src="productImageSources.original" 
                    :srcset="productImageSources.original_2x ? `${productImageSources.original} 1x, ${productImageSources.original_2x} 2x` : undefined"
                    :alt="productImageAlt"
                    class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                    loading="lazy"
                    @load="handleImageLoad"
                    @error="handleImageError"
                />
            </picture>
            
            <!-- Fallback Simple Image -->
            <img 
                v-else-if="productImage" 
                :src="productImage" 
                :alt="productImageAlt"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                loading="lazy"
                @load="handleImageLoad"
                @error="handleImageError"
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
                :title="productName"
            >
                {{ productName }}
            </h3>
            
            <!-- Product Code -->
            <p 
                class="text-xs text-gray-600"
                :title="`${trans('Code')}: ${productCode}`"
            >
                {{ productCode }}
            </p>
            
            <!-- Price Display -->
            <div v-if="hasPrice" class="mt-2">
                <span class="text-sm font-semibold text-gray-900">
                    {{ formattedPrice }}
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

// State
const imageLoading = ref(true)
const imageError = ref(false)

// Computed properties
const productName = computed(() => {
    return props.product.name || trans('Unnamed Product')
})

const productCode = computed(() => {
    return props.product.code || ''
})

const productImageSources = computed<ImageSource | null>(() => {
    if (imageError.value) return null
    
    const image = props.product.image
    if (!image || typeof image === 'string') return null
    
    // Return thumbnail sources for picture element
    if (image.thumbnail) {
        return image.thumbnail
    }
    
    // Fallback to source if thumbnail not available
    if (image.source) {
        return image.source
    }
    
    return null
})

const productImage = computed(() => {
    if (imageError.value) return null
    
    const image = props.product.image
    if (!image) return null
    
    // Handle string format (simple URL)
    if (typeof image === 'string') {
        return image
    }
    
    // For complex image objects, return a fallback URL
    // This is used when picture element is not supported
    if (image.thumbnail) {
        return image.thumbnail.webp_2x || 
               image.thumbnail.webp || 
               image.thumbnail.original_2x || 
               image.thumbnail.original
    }
    
    if (image.source) {
        return image.source.webp || image.source.original
    }
    
    return null
})

const productImageAlt = computed(() => {
    return `${trans('Product image for')} ${productName.value}`
})

const hasPrice = computed(() => {
    return props.product.price !== undefined && props.product.price !== null
})

const formattedPrice = computed(() => {
    if (!hasPrice.value) return ''
    
    // Convert string price to number if needed
    const price = typeof props.product.price === 'string' 
        ? parseFloat(props.product.price) 
        : props.product.price || 0
    
    const currency = props.product.currency || '' // need code_currency from BE
    return locale.currencyFormat(currency, price)
})

// Default is_favourite to true if undefined
const isFavorite = computed(() => {
    return props.product.is_favourite !== undefined ? props.product.is_favourite : true
})

const favoriteIcon = computed<IconProp>(() => {
    return isFavorite.value 
        ? 'fas fa-heart' as IconProp
        : 'fal fa-heart' as IconProp
})

const favoriteButtonClass = computed(() => {
    return isFavorite.value 
        ? 'bg-white text-red-500' 
        : 'bg-white/90 text-gray-400 hover:text-red-500'
})

const favoriteButtonLabel = computed(() => {
    return isFavorite.value 
        ? trans('Remove from favorites') 
        : trans('Add to favorites')
})

// Methods
const handleToggleFavorite = () => {
    emit('toggle-favorite', props.product)
}

const handleImageLoad = () => {
    imageLoading.value = false
}

const handleImageError = () => {
    imageLoading.value = false
    imageError.value = true
}

const handleClick = () => {
    emit('click', props.product)
}
</script>