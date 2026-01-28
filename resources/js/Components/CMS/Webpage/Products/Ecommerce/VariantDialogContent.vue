<script setup lang="ts">
import { ProductResource } from '@/types/Iris/Products'
import { ref, inject, computed, onMounted, onBeforeUnmount } from 'vue';
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { trans } from 'laravel-vue-i18n';
import { faChevronCircleLeft, faChevronDown, faCircle } from '@fas';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import LabelComingSoon from '@/Components/Iris/Products/LabelComingSoon.vue';
import EcomAddToBasketv2 from '@/Components/Iris/Products/EcomAddToBasketv2.vue';
import { faEnvelopeCircleCheck } from '@fortawesome/free-solid-svg-icons';
import { faChevronCircleDown, faEnvelope, faShoppingCart } from '@far';
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue';

const props = withDefaults(defineProps<{
    variants: ProductResource
    hasInBasketList?: any
    isLoadingRemindBackInStock? : boolean
}>(), {})


const emits = defineEmits<{
    (e: 'setBackInStock', value: any[]): void
    (e: 'unsetBackInStock', value: any[]): void
}>()

const layout = inject('layout', retinaLayoutStructure)
const selectedIndex = ref(0)
const selectedProduct = ref(props.variants[0])
const width = ref(window.innerWidth)

const isMobile = computed(() => width.value < 768)

const onResize = () => {
  width.value = window.innerWidth
}

const selectVariant = (variant: ProductResource, index: number) => {
    selectedIndex.value = index
    selectedProduct.value = variant
}

const isActive = (index: number) => selectedIndex.value === index

const onAddBackInStock = (product: ProductResource) => {
     emits('setBackInStock', product)
}

const onUnselectBackInStock = (product: ProductResource) => {
    emits('unsetBackInStock', product)
}


onMounted(() => {
  window.addEventListener('resize', onResize)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', onResize)
})



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
    <!-- DESKTOP -->
    <div class="hidden md:flex gap-2 mt-2 flex-nowrap overflow-x-auto">
        <button v-for="(variant, index) in variants" :key="index" type="button" @click="selectVariant(variant, index)"
            class="relative border px-3 py-2 text-sm font-medium shrink-0 transition overflow-hidden" :class="[
                isActive(index) && variant.stock > 0
                    ? 'option-primary'
                    : 'bg-white text-gray-700 hover:bg-gray-100',

                variant.stock === 0
                    ? 'opacity-60 after:content-[\'\'] after:absolute after:top-1/2 after:left-[-25%] after:w-[140%] after:h-[2px] after:bg-gray-400 after:-rotate-[34deg]'
                    : ''
            ]">
            {{ variant.variant_label }}
        </button>
    </div>

    <!-- MOBILE -->
    <div class="md:hidden mt-2">
        <details class="group border rounded-lg">
            <summary class="px-3 py-2 text-sm font-medium cursor-pointer flex justify-between items-center list-none">
                <span>{{ trans('Choose variant') }}</span>

                <font-awesome-icon :icon="faChevronDown"
                    class="text-gray-400 transition-transform duration-200 group-open:rotate-180" />
            </summary>

            <div class="px-3 py-2 space-y-2">
                <label v-for="(variant, index) in variants" :key="index"
                    class="flex items-center gap-2 text-sm cursor-pointer"
                    :class="variant.stock === 0 ? 'opacity-50 cursor-not-allowed' : ''">
                    <input type="radio" name="variant" class="accent-primary" :checked="isActive(index)"
                       @change="selectVariant(variant, index)" />

                    <span class="flex-1">
                        {{ variant.variant_label }}
                    </span>

                    <span v-if="variant.stock === 0" class="flex items-center gap-1 text-xs text-gray-400">
                        {{ trans('Out of stock') }}
                        <font-awesome-icon :icon="faEnvelope" class="text-[10px]" />
                    </span>

                </label>
            </div>
        </details>
    </div>


    <div v-if="selectedProduct" class="mt-2">
        <ecom-add-to-basketv2 v-if="selectedProduct.stock > 0" 
            class="order-input-button"
            :classContainer="!hasInBasketList[selectedProduct.id].quantity_ordered && !hasInBasketList[selectedProduct.id].quantity_ordered_new ? 'relative' : 'relative'"
            :customer-data="hasInBasketList[selectedProduct.id]" v-model:product="selectedProduct">
            <!-- <template v-if="isMobile" #qty-add-button="{ data }">
                <div v-if="!data.customer.quantity_ordered && !data.customer.quantity_ordered_new">
                    <button @click="data.onAddToBasket(data.product, 1)" :disabled="data.isLoadingSubmitQuantityProduct"
                        class="rounded-full option-primary bg-gray-800 hover:bg-green-700
                       text-gray-300 h-10 w-10 flex items-center justify-center
                       transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
                        v-tooltip="trans('Add to basket')">
                        <LoadingIcon v-if="data.isLoadingSubmitQuantityProduct" class="text-gray-600" />
                        <FontAwesomeIcon v-else :icon="faShoppingCart" fixed-width />
                    </button>
                </div>
            </template> -->
            <template #qty-add-button="{ data }">
                <div></div>
            </template>
        </ecom-add-to-basketv2>

        <button v-if="!selectedProduct.stock && layout?.outboxes?.oos_notification?.state == 'active'" v-tooltip="selectedProduct?.is_back_in_stock
            ? trans('You will be notify via email when the product back in stock')
            : trans('Click to be notified via email when the product back in stock')" @click="() =>
                selectedProduct?.is_back_in_stock
                    ? onUnselectBackInStock(selectedProduct)
                    : onAddBackInStock(selectedProduct)
            "
            class=" w-full inline-flex flex-wrap items-center justify-center gap-2 rounded border border-gray-300 bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 text-center shadow-sm transition hover:bg-gray-200 hover:border-gray-400">
            <LoadingIcon v-if="isLoadingRemindBackInStock" />

            <FontAwesomeIcon v-else :icon="selectedProduct?.is_back_in_stock ? faEnvelopeCircleCheck : faEnvelope"
                :class="selectedProduct?.is_back_in_stock ? 'text-green-600' : 'text-gray-600'" />

            <span class="whitespace-normal">
                {{ selectedProduct?.is_back_in_stock ? trans('Notified') : trans('Remind me') }}
            </span>
        </button>
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

summary::-webkit-details-marker {
    display: none;
}

/* :deep(.order-input-button .qty-root) {
    @apply flex-wrap gap-1;
}
 */

 :deep(.order-input-button .qty-control) {
    min-width: 100%;
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
    @apply mt-2 text-[11px];
}

:deep(.order-input-button .qty-price),
:deep(.order-input-button .qty-price-new) {
    @apply text-sm;
}
</style>