<script setup lang="ts">
import { inject, ref, computed } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPlus, faMinus, faCartPlus } from "@fas"
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { debounce, get, set } from 'lodash-es'
import { ProductResource } from '@/types/Iris/Products'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import axios from 'axios'
import { library } from "@fortawesome/fontawesome-svg-core"

library.add(faPlus, faMinus, faCartPlus)

const props = defineProps<{
    product: ProductResource
}>()

const layout = inject('layout', retinaLayoutStructure)
const isLoadingSubmitQuantityProduct = ref(false)
const status = ref<null | 'loading' | 'success' | 'error'>(null)

// Get current quantity - menggunakan logic yang sama dengan ButtonAddToBasketInFamily
const currentQuantity = computed(() => {
    return get(props.product, ['quantity_ordered_new'], null) !== null
        ? get(props.product, ['quantity_ordered_new'], 0)
        : (props.product.quantity_ordered || 0)
})

// Check if value is dirty (changed) - dari ButtonAddToBasketInFamily
const compIsValueDirty = computed(() => {
    return get(props.product, ['quantity_ordered_new'], null) !== get(props.product, ['quantity_ordered'], null)
})

// Set status with timeout - dari ButtonAddToBasketInFamily
let statusTimeout: ReturnType<typeof setTimeout> | null = null
const setStatus = (newStatus: null | 'loading' | 'success' | 'error') => {
    status.value = newStatus
    if (statusTimeout) clearTimeout(statusTimeout)
    if (newStatus === 'success' || newStatus === 'error') {
        statusTimeout = setTimeout(() => {
            status.value = null
        }, 3000)
    }
}

// Add to basket function - exact copy dari ButtonAddToBasketInFamily
const onAddToBasket = async (product: ProductResource) => {
    try {
        setStatus('loading')
        isLoadingSubmitQuantityProduct.value = true
        const response = await axios.post(
            route('iris.models.transaction.store', {
                product: product.id
            }),
            {
                quantity: get(product, ['quantity_ordered_new'], product.quantity_ordered)
            }
        )

        if (response.status !== 200) {
            throw new Error('Failed to add to basket')
        }

        router.reload({
            only: ['iris'],
        })

        product.transaction_id = response.data?.transaction_id
        product.quantity_ordered = response.data?.quantity_ordered
        setStatus('success')

        // Luigi: event add to cart
        window?.dataLayer?.push({
            event: "add_to_cart",
            ecommerce: {
                currency: layout?.iris?.currency?.code,
                value: product.price,
                items: [
                    {
                        item_id: product?.luigi_identity,
                    }
                ]
            }
        })

    } catch (error: any) {
        setStatus('error')
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Failed to add product to basket"),
            type: "error"
        })
    } finally {
        isLoadingSubmitQuantityProduct.value = false
    }
}

// Update quantity function - exact copy dari ButtonAddToBasketInFamily
const onUpdateQuantity = (product: ProductResource) => {
    router.post(
        route('iris.models.transaction.update', {
            transaction: product.transaction_id
        }),
        {
            quantity: get(product, ['quantity_ordered_new'], product.quantity_ordered)
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['iris'],
            onStart: () => {
                setStatus('loading')
                isLoadingSubmitQuantityProduct.value = true
            },
            onSuccess: () => {
                setStatus('success')
                product.quantity_ordered = product.quantity_ordered_new
            },
            onError: errors => {
                setStatus('error')
                notify({
                    title: trans("Something went wrong"),
                    text: errors.message || trans("Failed to update product quantity in basket"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitQuantityProduct.value = false
            },
        }
    )
}

// Main function to add/update product - dari ButtonAddToBasketInFamily
const addAndUpdateProduct = () => {
    if (!props.product.quantity_ordered) {
        onAddToBasket(props.product)
    } else if (props.product.quantity_ordered_new === 0) {
        onUpdateQuantity(props.product)
    } else {
        onUpdateQuantity(props.product)
    }
}

// Debounced version - dari ButtonAddToBasketInFamily
const debAddAndUpdateProduct = debounce(() => {
    addAndUpdateProduct()
}, 900)

// Handle quantity change
const updateQuantity = (newQuantity: number) => {
    // Clamp quantity between 0 and stock
    const clampedQuantity = Math.max(0, Math.min(newQuantity, props.product.stock))

    // Set quantity_ordered_new - sama seperti di ButtonAddToBasketInFamily
    set(props.product, ['quantity_ordered_new'], clampedQuantity)

    // Trigger debounced update jika ada perubahan
    if (compIsValueDirty.value) {
        debAddAndUpdateProduct()
    }
}

// Handle increment
const increment = () => {
    if (currentQuantity.value === 0) {
        // First time adding to cart, set to 1
        updateQuantity(1)
    } else {
        updateQuantity(currentQuantity.value + 1)
    }
}

// Handle decrement  
const decrement = () => {
    updateQuantity(currentQuantity.value - 1)
}

// Handle initial add (when qty is 0)
const handleInitialAdd = () => {
    updateQuantity(1)
}

const instantAddToBasket = () => {
    set(props.product, ['quantity_ordered_new'], 1)
    onAddToBasket(props.product)
}
</script>

<template>
    <div class="group relative">
        <!-- State awal: qty 0, tampilkan icon + -->
        <button v-if="get(props.product, ['quantity_ordered'], 0) === 0 && currentQuantity === 0"
            @click.stop.prevent="instantAddToBasket"
            :disabled="isLoadingSubmitQuantityProduct || props.product.stock === 0"
            class="rounded-full bg-gray-200 hover:bg-gray-300 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg" v-tooltip="trans('Add to basket')">
            <LoadingIcon v-if="isLoadingSubmitQuantityProduct" class="text-gray-600" />
            <FontAwesomeIcon v-else :icon="faCartPlus" fixed-width class="text-gray-600" />
            
        </button>

        <!-- State: qty > 0, tampilkan quantity dan expand saat hover -->
        <div v-else @click.stop
            class="rounded-full bg-gray-200 h-10 transition-all flex items-center justify-center group-hover:justify-between  group-hover:bg-gray-300 overflow-hidden relative shadow-lg"
            :class="[
                'group-hover:w-24 w-10',
                { 'opacity-50': isLoadingSubmitQuantityProduct }
            ]">

            <!-- Loading overlay -->
            <div v-if="isLoadingSubmitQuantityProduct"
                class="absolute inset-0 flex items-center justify-center bg-gray-200 rounded-full z-10">
                <LoadingIcon class="text-gray-600" />
            </div>

            <!-- Minus button (visible on hover) -->
            <button @click.stop.prevent="decrement" :disabled="isLoadingSubmitQuantityProduct || currentQuantity <= 0"
                class="hidden group-hover:flex w-6 h-6  items-center justify-center hover:bg-gray-100 rounded-full disabled:opacity-30 disabled:cursor-not-allowed absolute left-1 z-20">
                <FontAwesomeIcon :icon="faMinus" class="text-gray-600 text-xs" />
            </button>

            <!-- Quantity display (always visible) -->
            <span @click.stop.prevent
                class="text-sm font-medium text-gray-700 min-w-[1rem] cursor-default relative z-10 px-1 text-center self-center">
                {{ currentQuantity }}
            </span>

            <!-- Plus button (visible on hover) -->
            <button @click.stop.prevent="increment"
                :disabled="isLoadingSubmitQuantityProduct || currentQuantity >= props.product.stock"
                class="hidden group-hover:flex w-6 h-6  items-center justify-center hover:bg-gray-100 rounded-full disabled:opacity-30 disabled:cursor-not-allowed absolute right-1 z-20">
                <FontAwesomeIcon :icon="faPlus" class="text-gray-600 text-xs" />
            </button>
        </div>

        <!-- Status indicator (optional, bisa dicomment jika tidak diperlukan) -->
        <!-- <div 
            v-if="status === 'success'"
            class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full animate-ping"
        ></div>
        <div 
            v-if="status === 'error'"
            class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-ping"
        ></div> -->
    </div>
</template>

<style scoped>
/* Ensure smooth transitions */
.group:hover .group-hover\:w-20 {
    transition: width 0.2s ease-in-out;
}

/* Prevent text selection on buttons */
button {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Ensure buttons don't interfere with parent click events */
button {
    position: relative;
    z-index: 30;
}

/* Prevent event bubbling to parent Link */
.group {
    pointer-events: auto;
}

.group * {
    pointer-events: auto;
}
</style>