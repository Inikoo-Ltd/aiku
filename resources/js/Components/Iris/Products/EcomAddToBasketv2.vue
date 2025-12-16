<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { inject, ref, computed, watch } from 'vue'
import { debounce, get, set } from 'lodash-es'
import { ProductResource } from '@/types/Iris/Products'
import axios from 'axios'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt, faShoppingCart, faTimes, faCartArrowDown, faLongArrowRight } from "@fal"
import { faPlus } from "@far"
import { faSave } from "@fad"
import { faPlus as fasPlus, faMinus } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
library.add(faTrashAlt, faShoppingCart, faTimes, faCartArrowDown, faLongArrowRight, faSave, faPlus, fasPlus, faMinus)

const props = defineProps<{
    product: ProductResource
    customerData: any
    buttonStyle?: any
}>()

const customer = ref({ ...props.customerData })
const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

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

const isLoadingSubmitQuantityProduct = ref(false)
const onAddToBasket = async (product: ProductResource, quantity?: number) => {
    try {
        setStatus('loading')
        isLoadingSubmitQuantityProduct.value = true
        const response = await axios.post(
            route('iris.models.transaction.store', {
                product: product.id
            }),
            {
                quantity: quantity ?? get(customer.value, ['quantity_ordered_new'], customer.value.quantity_ordered)
            }
        )

        if (response.status !== 200) {

        }

        const productToAddToBasket = {
            ...product,
            transaction_id: response.data?.transaction_id,
            quantity_ordered: response.data?.quantity_ordered,
            quantity_ordered_new: response.data?.quantity_ordered,
        }

        // Check the product in Basket, if exist: replace, not exist: push
        const products = layout.rightbasket?.products
        if (products) {
            const index = products.findIndex((p: any) => p.transaction_id === productToAddToBasket.transaction_id)
            if (index !== -1) {
                products[index] = productToAddToBasket
            } else {
                products.push(productToAddToBasket)
            }
        }

        router.reload({
            only: ['iris'],
        })

        /* product.transaction_id = response.data?.transaction_id
        product.quantity_ordered = response.data?.quantity_ordered */
        customer.value.quantity_ordered = response.data?.quantity_ordered
        customer.value.quantity_ordered_new = response.data?.quantity_ordered
        customer.value.transaction_id = response.data?.transaction_id
        setStatus('success')
        layout.reload_handle()

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

const onUpdateQuantity = (product: ProductResource) => {
    // console.log('stock in', stockInBasket)
    const isWillRemoveFrombasket = get(product, ['quantity_ordered_new'], 0) === 0
    const productTransactionId = product.transaction_id

    router.post(
        route('iris.models.transaction.update', {
            transaction: customer.value.transaction_id
        }),
        {
            quantity_ordered: get(product, ['quantity_ordered_new'], null)
        },
        {
            preserveScroll: true,
            preserveState: true,
            // only: ['iris'],
            onStart: () => {
                setStatus('loading')
                isLoadingSubmitQuantityProduct.value = true
            },
            onSuccess: () => {
                setStatus('success')
                // product.quantity_ordered = product.quantity_ordered_new
                customer.value.quantity_ordered = product.quantity_ordered_new

                if (isWillRemoveFrombasket) {
                    // Remove product from layout basket
                    const products = layout.rightbasket?.products
                    if (products) {
                        const index = products.findIndex((p: any) => p.transaction_id === productTransactionId)
                        if (index !== -1) {
                            products.splice(index, 1)
                        }
                    }
                } else {
                    // Update product quantity in layout basket
                    const products = layout.rightbasket?.products
                    if (products) {
                        const index = products.findIndex((p: any) => p.transaction_id === productTransactionId)
                        if (index !== -1) {
                            products[index].quantity_ordered = product.quantity_ordered_new
                            products[index].quantity_ordered_new = product.quantity_ordered_new
                        }
                    }
                }

                set(props, ['product', 'quantity_ordered'], get(product, ['quantity_ordered_new'], null))
                layout.reload_handle()
            },
            onError: errors => {
                console.log('eee', errors)
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


const debAddAndUpdateProduct = debounce(() => {
    const currentQty = customer.value.quantity_ordered || 0
    const newQty = customer.value.quantity_ordered_new ?? currentQty

    if (!customer.value.transaction_id || currentQty === 0) {
        if (newQty > 0) {
            onAddToBasket(props.product, newQty)
        }
        return
    }

    if (newQty === 0) {
        onUpdateQuantity(customer.value)
        customer.value.transaction_id = null
        // customer.value.quantity_ordered = 0
        return
    }

    // Otherwise just update normally
    onUpdateQuantity(customer.value)
}, 700)


const compIsAddToBasket = computed(() => {
    return !customer.value.quantity_ordered
})

const showWarning = () => {
    notify({
        title: "Stock limit reached",
        text: `You cannot add more than ${customer.value.stock} items.`,
        type: "error",
    })
}

const incrementQty = () => {
    const current = customer.value.quantity_ordered_new ?? customer.value.quantity_ordered ?? 0

    if (current >= customer.value.stock) {
        showWarning()
        return
    }

    set(customer.value, ['quantity_ordered_new'], current + 1)
    debAddAndUpdateProduct()
}

const decrementQty = () => {
    const current = customer.value.quantity_ordered_new ?? customer.value.quantity_ordered ?? 0
    const next = Math.max(0, current - 1)

    set(customer.value, ['quantity_ordered_new'], next)
    debAddAndUpdateProduct()
}

const onManualInput = (e: Event) => {
    const value = Number((e.target as HTMLInputElement).value)

    if (Number.isNaN(value)) return

    if (value > customer.value.stock) {
        showWarning()
        set(customer.value, ['quantity_ordered_new'], customer.value.stock)
    } else {
        set(customer.value, ['quantity_ordered_new'], Math.max(0, value))
    }

    debAddAndUpdateProduct()
}


// Watch: if parent refetch the Customer Data (maybe RightSideBasket have update the quantity)
watch(() => props.customerData, (newVal) => {
    customer.value = { ...newVal }
}, {
    deep: true
})
</script>

<template>
    <div class="">
        <div class="flex items-center gap-2 relative">
            <div class="flex items-center border rounded-lg overflow-hidden w-32">
                <!-- Decrement -->
                <button type="button" class="px-3 py-2 disabled:opacity-50" :disabled="isLoadingSubmitQuantityProduct"
                    @click="decrementQty">
                    <FontAwesomeIcon icon="fas fa-minus" />
                </button>

                <!-- Input -->
                <input type="number" class="w-full h-10 text-center leading-none outline-none appearance-none" :min="0"
                    :max="customer.stock" :disabled="isLoadingSubmitQuantityProduct" :value="customer.quantity_ordered_new === null || customer.quantity_ordered_new === undefined
                        ? customer.quantity_ordered ?? 0
                        : customer.quantity_ordered_new
                        " @input="onManualInput" />

                <!-- Increment -->
                <button type="button" class="px-3 py-2 disabled:opacity-50" :disabled="isLoadingSubmitQuantityProduct"
                    @click="incrementQty">
                    <FontAwesomeIcon icon="fas fa-plus" />
                </button>
            </div>

            <ConditionIcon :state="status" class="absolute top-1/2 -translate-y-1/2 -right-7" />

            <div v-if="!customer.quantity_ordered && !customer.quantity_ordered_new" class="ml-8">
                <Button @click="() => onAddToBasket(props.product, 1)" icon="far fa-plus"
                    :label="trans('Add to basket')" type="primary" size="lg" :loading="isLoadingSubmitQuantityProduct"
                    :inject-style="buttonStyle" />
            </div>
            <div v-if="customer.quantity_ordered" class="mt-1 xitalic text-gray-700 text-sm">
                {{ trans("Current amount in basket") }}: <span class="font-semibold">{{
                    locale.currencyFormat(layout?.iris?.currency?.code, (props.product.price *
                        customer.quantity_ordered))
                    }}</span>
                <span>
                    <template
                        v-if="customer.quantity_ordered_new !== null && customer.quantity_ordered_new !== undefined">
                        <span v-if="customer.quantity_ordered_new > customer.quantity_ordered">
                            <FontAwesomeIcon icon="fal fa-long-arrow-right" class="mx-1 align-middle" fixed-width
                                aria-hidden="true" /> <span
                                v-tooltip="trans('Increased :amount', { amount: locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(customer.quantity_ordered_new - customer.quantity_ordered))) })">{{
                                    locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price *
                                        Number(customer.quantity_ordered_new))) }}</span>
                        </span>
                        <span v-else-if="customer.quantity_ordered_new < customer.quantity_ordered">
                            <FontAwesomeIcon icon="fal fa-long-arrow-right" class="mx-1 align-middle" fixed-width
                                aria-hidden="true" /> <span
                                v-tooltip="trans('Decreased :amount', { amount: locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(customer.quantity_ordered - customer.quantity_ordered_new))) })">{{
                                    locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price *
                                        Number(customer.quantity_ordered_new))) }}</span>
                        </span>
                    </template>
                </span>
            </div>
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