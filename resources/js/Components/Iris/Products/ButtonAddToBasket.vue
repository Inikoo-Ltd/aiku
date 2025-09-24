<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { inject, ref, watch, computed } from 'vue'
import { debounce, get, set } from 'lodash-es'
import { ProductResource } from '@/types/Iris/Products'
import axios from 'axios'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTrashAlt, faShoppingCart, faTimes, faCartArrowDown } from "@fal"
import { faCartPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import ConditionIcon from '@/Components/Utils/ConditionIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
library.add(faTrashAlt, faShoppingCart, faTimes, faCartArrowDown, faCartPlus)

const props = defineProps<{
    product: ProductResource
}>()
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
    const stockInBasket = product.quantity_ordered ? product.quantity_ordered + get(product, ['quantity_ordered_new'], product.quantity_ordered) : product.quantity_ordered
    router.post(
        route('iris.models.transaction.update', {
            transaction: product.transaction_id
        }),
        {
            quantity: stockInBasket
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
                // product.quantity_ordered = product.quantity_ordered_new
                set(props, ['product', 'quantity_ordered'], stockInBasket)
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


const addAndUpdateProduct = () => {
    if (!props.product.quantity_ordered) {
        onAddToBasket(props.product)
    } else if (props.product.quantity_ordered_new === 0) {
        onUpdateQuantity(props.product)
    } else {
        onUpdateQuantity(props.product)
    }
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

const compIsValueDirty = computed(() => {
    return get(props.product, ['quantity_ordered_new'], null) !== get(props.product, ['quantity_ordered'], null)
})
// watch(() => get(props.product, ['quantity_ordered_new'], null), () => {
//     debAddAndUpdateProduct()
// })
</script>

<template>
    <div class="">
        <div class="xw-full flex items-center gap-2 xmt-2 relative w-36">
            <InputNumber
                :modelValue="get(product, ['quantity_ordered_new'], null) === null ? product.quantity_ordered : get(product, ['quantity_ordered_new'], 0) "
                @input="(e) => (e.value ? set(product, ['quantity_ordered_new'], e.value) : set(product, ['quantity_ordered_new'], 0), `debAddAndUpdateProduct()`)"
                inputId="integeronly"
                fluid
                showButtons
                :disabled="isLoadingSubmitQuantityProduct"
                :min="1"
                :max="product.stock"
                buttonLayout="horizontal"
                :inputStyle="{
                    textAlign: 'center',
                    minWidth: '4rem'
                }"
            >
                <template #incrementbuttonicon>
                    <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" />
                </template>
                <template #decrementbuttonicon>
                    <FontAwesomeIcon icon="fas fa-minus" class="" fixed-width aria-hidden="true" />
                </template>
            </InputNumber>
            
            <!-- <ConditionIcon :state="status" class="absolute top-1/2 -translate-y-1/2 -right-7"/> -->

            <Button
                @click="() => addAndUpdateProduct()"
                xicon="props.product.quantity_ordered_new === 0 ? 'fal fa-cart-arrow-down' : 'fal fa-cart-plus'"
                :label="trans(`Add to basket . :estimated`, { estimated: locale.currencyFormat(layout?.iris?.currency?.code, (props.product.price * product.quantity_ordered_new))  })"
                type="primary"
                size="lg"
                xdisabled="!compIsValueDirty"
                :disabled="product.quantity_ordered > product.stock"
                v-tooltip="product.quantity_ordered > product.stock ? trans('Quantity in basket exceeds stock') : ''"
                :loading="isLoadingSubmitQuantityProduct"
            />
        </div>
        
        <div v-if="product.quantity_ordered" class="mt-1 italic text-gray-400 text-sm">
            {{ trans("Quantity in basket") }}: {{ product.quantity_ordered }}
        </div>
    </div>
</template>