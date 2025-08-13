<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { get, set } from 'lodash-es'
import { ProductResource } from '@/types/Iris/Products'
import axios from 'axios'

const props = defineProps<{
    product: ProductResource
}>()

const isLoadingSubmitQuantityProduct = ref(false)
const onAddToBasket = async (product: ProductResource) => {

    try {
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

    } catch (error: any) {
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
                isLoadingSubmitQuantityProduct.value = true
            },
            onSuccess: () => {
                product.quantity_ordered = product.quantity_ordered_new
            },
            onError: errors => {
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

</script>

<template>
    <div class="flex flex-col items-center gap-2 xmt-2">
        <InputNumber
            :modelValue="get(product, ['quantity_ordered_new'], product.quantity_ordered) || product.quantity_ordered"
            @update:modelValue="(e) => set(product, ['quantity_ordered_new'], e)"
            inputId="integeronly"
            fluid
            showButtons
            :min="0"
            :max="product.stock"
        />
        
        <Button
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
            v-else
            @click="() => onUpdateQuantity(product)"
            icon="fal fa-plus"
            :label="trans('Update quantity in basket')"
            type="tertiary"
            full
            :loading="isLoadingSubmitQuantityProduct"
            :disabled="product.quantity_ordered_new === product.quantity_ordered"
        />
    </div>
</template>