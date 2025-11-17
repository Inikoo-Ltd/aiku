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
import { routeType } from '@/types/route'
import { useIrisLayoutStore } from "@/Stores/irisLayout"
import { useLayoutStore } from "@/Stores/retinaLayout"
import { faCartShopping } from '@fortawesome/free-solid-svg-icons'
import { faShoppingCart } from '@far'
import { ButtonStyle } from 'primevue'

library.add(faPlus, faMinus, faCartPlus)

const props = withDefaults(
    defineProps<{
        product: ProductResource
        addToBasketRoute: routeType
        updateBasketQuantityRoute: routeType
        hasInBasket?: any
        buttonStyle?: any
        buttonStyleHover?: any
    }>(),
    {
        hasInBasket: {
            id: null,
            quantity_ordered: 0,
            quantity_ordered_new: 0,
            asset_id: null
        }
    }
)


const layout = inject('layout', retinaLayoutStructure)
const isLoadingSubmitQuantityProduct = ref(false)
const status = ref<null | 'loading' | 'success' | 'error'>(null)


const currentQuantity = computed(() => {
    const quantityNew = get(props.hasInBasket, ['quantity_ordered_new'])
    const quantity = get(props.hasInBasket, ['quantity_ordered'])

    // Normalize null/undefined to 0
    const safeQuantityNew = Number(quantityNew ?? 0)
    const safeQuantity = Number(quantity ?? 0)

    // Use new quantity if set, otherwise fallback
    return safeQuantityNew > 0 ? safeQuantityNew : safeQuantity
})


// Check if value is dirty (changed) - dari ButtonAddToBasketInFamily
const compIsValueDirty = computed(() => {
    return get(props.hasInBasket, ['quantity_ordered_new'], null) !== get(props.hasInBasket, ['quantity_ordered'], null)
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
const onAddToBasket = async (product: ProductResource, basket: any) => {
    try {
        setStatus('loading')
        isLoadingSubmitQuantityProduct.value = true
        const response = await axios.post(
            route(props.addToBasketRoute.name, {
                product: product.id
            }),
            {
                quantity: get(basket, ['quantity_ordered_new'], basket.quantity_ordered)
            }
        )

        if (response.status !== 200) {
            throw new Error('Failed to add to basket')
        }

        layout.reload_handle()
        router.reload({
            only: ['iris', 'data'],
        })

        product.transaction_id = response.data?.transaction_id
        basket.quantity_ordered = response.data?.quantity_ordered
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
const onUpdateQuantity = (product: ProductResource, basket: any) => {
    router[props.updateBasketQuantityRoute.method || 'post'](
        route(props.updateBasketQuantityRoute.name, {
            transaction: product.transaction_id
        }),
        {
            quantity_ordered: get(basket, ['quantity_ordered_new'], basket.quantity_ordered)
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
                layout.reload_handle()
                basket.quantity_ordered = product.quantity_ordered_new
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
    if (!props.hasInBasket.quantity_ordered) {
        onAddToBasket(props.product, props.hasInBasket)
    } else if (props.hasInBasket.quantity_ordered_new === 0) {
        onUpdateQuantity(props.product, props.hasInBasket)
    } else {
        onUpdateQuantity(props.product, props.hasInBasket)
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
    set(props.hasInBasket, ['quantity_ordered_new'], clampedQuantity)

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
    set(props.hasInBasket, ['quantity_ordered_new'], 1)
    onAddToBasket(props.product, props.hasInBasket)
}

const showChartButton = computed(() => {
    const quantityOrdered = get(props.hasInBasket, ['quantity_ordered'], 0)
    return !quantityOrdered && currentQuantity.value === 0
})



</script>

<template>
    <div class="group relative">
        <!-- State awal: qty 0, tampilkan icon + -->
        <button v-if="showChartButton" @click.stop.prevent="instantAddToBasket" :style="buttonStyle"
            :disabled="isLoadingSubmitQuantityProduct || props.product.stock === 0"
            class="rounded-full button-cart hover:bg-green-700 bg-gray-800 text-gray-600 mr-4 h-10 w-10 flex items-center justify-center transition-all disabled:opacity-50 disabled:cursor-not-allowed shadow-lg"
            v-tooltip="trans('Add to basket')">
            <LoadingIcon v-if="isLoadingSubmitQuantityProduct" class="text-gray-600" />
            <FontAwesomeIcon v-else :icon="faShoppingCart" fixed-width  />
        </button>

        <!-- State: qty > 0, tampilkan quantity dan expand saat hover -->
        <div v-else @click.stop :style="buttonStyle"
            class="rounded-full  button-cart  bg-gray-200 h-10 transition-all flex items-center justify-center group-hover:justify-between  group-hover:bg-gray-300 overflow-hidden relative shadow-lg"
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


.button-cart {
    &:hover {
        background: v-bind(buttonStyleHover?.background) !important;
        color: v-bind(buttonStyleHover?.color) !important;
        font-family: v-bind(buttonStyleHover?.fontFamily) !important;
        font-size: v-bind(buttonStyleHover?.fontSize) !important;
        font-style: v-bind(buttonStyleHover?.fontStyle) !important;
    }
}
</style>