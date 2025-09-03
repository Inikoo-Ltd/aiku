<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { inject, ref, watch } from 'vue'
import { debounce, get, set } from 'lodash-es'
import { ProductResource } from '@/types/Iris/Products'
import axios from 'axios'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt, faShoppingCart, faTimes } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
library.add(faTrashAlt, faShoppingCart, faTimes)

const props = defineProps<{
    product: ProductResource
}>()
const layout = inject('layout', retinaLayoutStructure)

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

const onUpdateQuantity = (product: ProductResource) => {

    // Section: Submit
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


const debAddAndUpdateProduct = debounce(() => {
    if (!props.product.quantity_ordered) {
        onAddToBasket(props.product)
    } else if (props.product.quantity_ordered_new === 0) {
        onUpdateQuantity(props.product)
    } else {
        onUpdateQuantity(props.product)
    }
}, 900)
watch(() => get(props.product, ['quantity_ordered_new'], null), () => {
    debAddAndUpdateProduct()
})
</script>

<template>
    <div class="xw-full flex flex-col items-center gap-2 xmt-2 relative max-w-36">
        <InputNumber
            :modelValue="get(product, ['quantity_ordered_new'], null) === null ? product.quantity_ordered : get(product, ['quantity_ordered_new'], 0) "
            @input="(e) => (e.value ? set(product, ['quantity_ordered_new'], e.value) : set(product, ['quantity_ordered_new'], 0))"
            inputId="integeronly"
            fluid
            showButtons
            :disabled="isLoadingSubmitQuantityProduct"
            :min="0"
            :max="product.stock"
            buttonLayout="horizontal"
            :inputStyle="{
                textAlign: 'center'
            }"
        >
            <template #incrementbuttonicon>
                <!-- <span class="pi pi-plus" /> -->
                <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" />
            </template>
            <template #decrementbuttonicon>
                <!-- <span class="pi pi-minus" /> -->
                <FontAwesomeIcon icon="fas fa-minus" class="" fixed-width aria-hidden="true" />
            </template>
        </InputNumber>

        <ConditionIcon :state="status" class="absolute top-1/2 -translate-y-1/2 -right-7"/>
        
        <!-- <Button
            v-if="!product.quantity_ordered"
            @click="() => onAddToBasket(product)"
            icon="fal fa-shopping-cart"
            :label="trans('Add to basket')"
            type="secondary"
            full
            :loading="isLoadingSubmitQuantityProduct"
            :disabled="product.quantity_ordered_new === product.quantity_ordered"
        />
        <Button
            v-else-if="product.quantity_ordered_new === 0"
            @click="() => onUpdateQuantity(product)"
            icon="fal fa-trash-alt"
            :label="trans('Remove from basket')"
            type="negative"
            full
            :loading="isLoadingSubmitQuantityProduct"
            :disabled="product.quantity_ordered_new === product.quantity_ordered"
        />
        <Button
            v-else
            @click="() => onUpdateQuantity(product)"
            icon="fal fa-plus"
            :label="trans('Update quantity in basket')"
            type="tertiary"
            full
            :loading="isLoadingSubmitQuantityProduct"
            :disabled="product.quantity_ordered_new === product.quantity_ordered"
        /> -->
    </div>
</template>