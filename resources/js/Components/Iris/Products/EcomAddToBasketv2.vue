<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { router } from '@inertiajs/vue3'
import { inject, ref, watch } from 'vue'
import { debounce,  set } from 'lodash-es'
import axios from 'axios'

import { ProductResource } from '@/types/Iris/Products'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faLongArrowRight } from '@fal'
import { faPlus, faMinus } from '@fas'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faLongArrowRight, faPlus, faMinus)


const product = defineModel<ProductResource>('product', { required: true })


const props = defineProps<{
    customerData: any
    buttonStyle?: any
}>()


const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)


const customer = ref({ ...props.customerData })

const status = ref<null | 'loading' | 'success' | 'error'>(null)
let statusTimeout: ReturnType<typeof setTimeout> | null = null

const isLoadingSubmitQuantityProduct = ref(false)


const setStatus = (newStatus: typeof status.value) => {
    status.value = newStatus
    if (statusTimeout) clearTimeout(statusTimeout)

    if (newStatus === 'success' || newStatus === 'error') {
        statusTimeout = setTimeout(() => (status.value = null), 3000)
    }
}

const showWarning = () => {
    notify({
        title: trans('Stock limit reached'),
        text: trans('You cannot add more than :stock items.', { stock: customer.value.stock }),
        type: 'error'
    })
}


const onAddToBasket = async (productData: ProductResource, quantity: number) => {
    try {
        setStatus('loading')
        isLoadingSubmitQuantityProduct.value = true

        const response = await axios.post(
            route('iris.models.transaction.store', { product: productData.id }),
            { quantity }
        )

        const payload = response.data

        Object.assign(customer.value, {
            transaction_id: payload.transaction_id,
            quantity_ordered: payload.quantity_ordered,
            quantity_ordered_new: payload.quantity_ordered
        })

        Object.assign(product.value, {
            transaction_id: payload.transaction_id,
            quantity_ordered: payload.quantity_ordered
        })

        const products = layout.rightbasket?.products
        if (products) {
            const index = products.findIndex((p: any) => p.transaction_id === payload.transaction_id)
            index !== -1
                ? (products[index] = { ...product.value })
                : products.push({ ...product.value })
        }

        router.reload({ only: ['iris'] })
        layout.reload_handle()
        setStatus('success')
    } catch (error: any) {
        setStatus('error')
        notify({
            title: trans('Something went wrong'),
            text: error.message || trans('Failed to add product to basket'),
            type: 'error'
        })
    } finally {
        isLoadingSubmitQuantityProduct.value = false
    }
}

const onUpdateQuantity = async () => {
    const qty = customer.value.quantity_ordered_new ?? 0
    const transactionId = customer.value.transaction_id
    const willRemove = qty === 0

    try {
        setStatus('loading')
        isLoadingSubmitQuantityProduct.value = true

        const response = await axios.post(
            route('iris.models.transaction.update', { transaction: transactionId }),
            { quantity_ordered: qty }
        )

        const payload = response.data

        customer.value.quantity_ordered = payload.quantity_ordered
        product.value.quantity_ordered = payload.quantity_ordered

        const products = layout.rightbasket?.products
        if (products) {
            const index = products.findIndex((p: any) => p.transaction_id === transactionId)
            if (index !== -1) {
                willRemove ? products.splice(index, 1) : (products[index].quantity_ordered = qty)
            }
        }

        if (willRemove) customer.value.transaction_id = null

        layout.reload_handle()
        setStatus('success')
    } catch (error: any) {
        setStatus('error')
        notify({
            title: trans('Something went wrong'),
            text: error.message || trans('Failed to update product quantity'),
            type: 'error'
        })
    } finally {
        isLoadingSubmitQuantityProduct.value = false
    }
}

const debouncedSync = debounce(() => {
    const current = customer.value.quantity_ordered || 0
    const next = customer.value.quantity_ordered_new ?? current

    if (!customer.value.transaction_id && next > 0) {
        onAddToBasket(product.value, next)
        return
    }

    onUpdateQuantity()
}, 700)


const incrementQty = () => {
    const current = customer.value.quantity_ordered_new ?? customer.value.quantity_ordered ?? 0

    if (current >= customer.value.stock && !product.value.is_on_demand) {
        showWarning()
        return
    }

    set(customer.value, ['quantity_ordered_new'], current + 1)
    debouncedSync()
}

const decrementQty = () => {
    const current = customer.value.quantity_ordered_new ?? customer.value.quantity_ordered ?? 0
    set(customer.value, ['quantity_ordered_new'], Math.max(0, current - 1))
    debouncedSync()
}

const onManualInput = (e: Event) => {
    const value = Number((e.target as HTMLInputElement).value)
    if (Number.isNaN(value)) return

    const next = Math.min(Math.max(0, value), customer.value.stock)
    if (next !== value) showWarning()

    set(customer.value, ['quantity_ordered_new'], next)
    debouncedSync()
}


watch(
    () => props.customerData,
    val => (customer.value = { ...val }),
    { deep: true }
)
</script>

<template>
    <div class="flex items-center gap-2 relative">
        <div class="flex items-center border rounded-lg overflow-hidden min-w-28 max-w-32">
            <button class="px-2.5 py-2" :disabled="isLoadingSubmitQuantityProduct" @click="decrementQty">
                <FontAwesomeIcon icon="fas fa-minus" />
            </button>

            <input
                type="number"
                class="w-full h-10 text-center outline-none"
                :disabled="isLoadingSubmitQuantityProduct"
                :value="customer.quantity_ordered_new ?? customer.quantity_ordered ?? 0"
                @input="onManualInput"
            />

            <button class="px-2.5 py-2" :disabled="isLoadingSubmitQuantityProduct" @click="incrementQty">
                <FontAwesomeIcon icon="fas fa-plus" />
            </button>
        </div>

        <ConditionIcon :state="status" class="absolute -right-7 top-1/2 -translate-y-1/2" />

        <Button
            v-if="!customer.quantity_ordered && !customer.quantity_ordered_new"
            class="ml-8"
            icon="fas fa-plus"
            :label="trans('Add to basket')"
            type="primary"
            size="lg"
            :loading="isLoadingSubmitQuantityProduct"
            :inject-style="buttonStyle"
            @click="onAddToBasket(product, 1)"
        />

        <div v-if="customer.quantity_ordered" class="text-sm text-gray-700 ml-2">
            {{ trans('Current amount in basket') }}:
            <strong>
                {{ locale.currencyFormat(layout?.iris?.currency?.code, product.price * customer.quantity_ordered) }}
            </strong>
        </div>
    </div>
</template>

<style scoped>
input[type='number']::-webkit-inner-spin-button,
input[type='number']::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
input[type='number'] {
    -moz-appearance: textfield;
}
</style>
