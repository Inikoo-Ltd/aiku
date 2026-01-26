<script setup lang="ts">
import { ProductResource } from '@/types/Iris/Products'
import { ref, inject } from 'vue';
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n';
import { faCircle } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue';
import EcomAddToBasketv2 from '@/Components/Iris/Products/EcomAddToBasketv2.vue';

const props = withDefaults(defineProps<{
    variants: ProductResource
    hasInBasketList?: any
}>(), {})

const layout = inject('layout', retinaLayoutStructure)
const selectedIndex = ref(0)
const selectedProduct = ref(props.variants[0])

const selectVariant = (variant: ProductResource, index: number) => {
    selectedIndex.value = index
    selectedProduct.value = variant
}

const isActive = (index: number) => selectedIndex.value === index
console.log('sss', props.variants)
</script>

<template>
    <!-- SELECTED PRODUCT -->
    <div v-if="selectedProduct">
        <!-- NAME -->
        <div class="hover:text-gray-500 font-bold text-sm mb-1">
            <span v-if="selectedProduct.units !== 1" class="text-indigo-900">
                {{ selectedProduct.units }}x
            </span>
            {{ selectedProduct.name }}
        </div>

        <!-- CODE + STOCK -->
        <div class="flex items-center justify-between w-full text-xs mt-1">
            <!-- PRODUCT CODE -->
            <div>
                {{ selectedProduct.code }}
            </div>

            <!-- STOCK INFO -->
            <div v-if="layout?.iris?.is_logged_in" class="flex items-center gap-x-2 text-gray-600">
                <!-- COMING SOON -->
                <LabelComingSoon v-if="selectedProduct.is_coming_soon" :selectedProduct="selectedProduct"
                    class="text-center" />

                <!-- STOCK STATUS -->
                <div v-else v-tooltip="trans('Available stock')"
                    class="flex items-center gap-1 py-1 font-medium leading-snug" :class="selectedProduct.stock > 0
                        ? 'bg-green-50 text-green-700'
                        : 'bg-red-50 text-red-600'
                        ">
                    <font-awesome-icon :icon="faCircle" fixed-width class="shrink-0 text-[6px]" :class="selectedProduct.stock > 0
                        ? 'text-green-600'
                        : 'text-red-600'
                        " />

                    <span>
                        (
                        {{
                            selectedProduct.stock >= 250
                                ? trans('Unlimited quantity')
                                : selectedProduct.stock > 0
                                    ? selectedProduct.stock
                                    : 0
                        }}
                        )
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- VARIANT LIST -->
    <div class="flex gap-2 mt-2 flex-nowrap overflow-x-auto">
        <button v-for="(variant, index) in variants" :key="index" type="button" :disabled="variant.stock === 0"
            @click="variant.stock > 0 && selectVariant(variant, index)"
            class="relative border px-3 py-2 text-sm font-medium shrink-0 transition overflow-hidden" :class="[
                isActive(index) && variant.stock > 0
                    ? 'option-primary'
                    : 'bg-white text-gray-700 hover:bg-gray-100',

                variant.stock === 0 ? 'cursor-not-allowed opacity-60 after:content-[\'\'] after:absolute after:top-1/2 after:left-[-25%] after:w-[140%] after:h-[2px] after:bg-gray-400 after:-rotate-[34deg]' : ''
            ]">
            {{ variant.variant_label }}
        </button>
    </div>



    <div v-if="selectedProduct && selectedProduct.stock > 0" class="mt-2">
        <ecom-add-to-basketv2 class="order-input-button" :customer-data="hasInBasketList[selectedProduct.id]"
            v-model:product="selectedProduct"> </ecom-add-to-basketv2>
    </div>


</template>




<style scoped>
.option-primary {
    background-color: var(--theme-color-4) !important;
    color: var(--theme-color-5) !important;

    &:hover {
        background-color: color-mix(in srgb, var(--theme-color-4) 85%, black) !important;
    }

    &:disabled {
        background-color: color-mix(in srgb, var(--theme-color-4) 70%, grey) !important;
    }
}

:deep(.order-input-button .qty-root) {
    @apply flex-wrap gap-1;
}



:deep(.order-input-button .qty-input:focus) {
    outline: none;
    box-shadow: none;
}


:deep(.order-input-button .qty-status) {
    @apply -right-4 scale-90;
}


:deep(.order-input-button .qty-add-btn) {
    @apply ml-2 px-2 py-1 text-xs rounded;
}


:deep(.order-input-button .qty-add-btn span),
:deep(.order-input-button .qty-add-btn .p-button-label) {
    @apply text-xs;
}


:deep(.order-input-button .qty-info) {
    @apply ml-1 text-[11px];
}

:deep(.order-input-button .qty-price),
:deep(.order-input-button .qty-price-new) {
    @apply text-sm;
}
</style>