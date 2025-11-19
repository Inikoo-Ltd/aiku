<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { debounce, get, set } from 'lodash-es'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { ProductResource } from '@/types/Iris/Products'

const props = defineProps<{
    product: {
        transaction_id: number
        quantity_ordered_new: number
        quantity_ordered: number
        available_quantity: number
    }
}>()

const emits = defineEmits<{
    (e: 'productRemoved'): void
}>()

const layout = inject('layout', retinaLayoutStructure)

// Section: status for loading, success, error
const status = ref<null | 'loading' | 'success' | 'error'>(null)
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

// Update quantity function - exact copy dari ButtonAddToBasketInFamily
const onUpdateQuantity = (newVal?: number) => {
    const selectedQuantity = newVal ?? props.product.quantity_ordered_new

    router.post(
        route('iris.models.transaction.update', {
            transaction: props.product.transaction_id
        }),
        {
            quantity_ordered: selectedQuantity
        },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['zzzziris'],
            onStart: () => {
                setStatus('loading')
                // isLoadingSubmitQuantityProduct.value = true
            },
            onSuccess: () => {
                setStatus('success')
                layout.reload_handle()
                props.product.quantity_ordered = props.product.quantity_ordered_new

                if (selectedQuantity < 1) {
                    emits('productRemoved')
                }
                
                if (layout.temp?.fetchIrisProductCustomerData) {
                    layout.temp.fetchIrisProductCustomerData()
                }
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
                // isLoadingSubmitQuantityProduct.value = false
            },
        }
    )
}

const debUpdateQuantity = debounce((newVal?: number) => {
    onUpdateQuantity(newVal)
}, 900)
</script>

<template>
    <div class="flex gap-x-0.5 items-center">
        <div @click="() => product.quantity_ordered_new > 0 ? (product.quantity_ordered_new--, debUpdateQuantity()) : null"
            class="cursor-pointer opacity-50 hover:opacity-100"
            :class="product.quantity_ordered_new < 1 ? 'opacity-20' : 'cursor-pointer opacity-50 hover:opacity-100'"
        >
            <FontAwesomeIcon icon="far fa-minus" class="" fixed-width aria-hidden="true" />
        </div>

        <div class="max-w-full">
            <InputNumber
                :modelValue="product.quantity_ordered_new"
                @input="(e) => (set(product, 'quantity_ordered_new', e?.value), debUpdateQuantity())"
                @update:modelValue="e => (set(product, 'quantity_ordered_new', e), debUpdateQuantity())"
                size="small"
                inputId="minmax-buttons"
                mode="decimal"
                inputClass="text-center"
                :min="0"
                :max="product.available_quantity"
                fluid
            />
        </div>

        <div @click="() => product.quantity_ordered_new < product.available_quantity ? (product.quantity_ordered_new++, debUpdateQuantity()) : null"
            class=""
            :class="product.quantity_ordered_new >= product.available_quantity ? 'opacity-20' : 'cursor-pointer opacity-50 hover:opacity-100'"
        >
            <FontAwesomeIcon icon="far fa-plus" class="" fixed-width aria-hidden="true" />
        </div>
    </div>
</template>