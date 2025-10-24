<script setup lang="ts">
import Button from '@/Components/Elements/Buttons/Button.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { InputNumber } from 'primevue'
import { router } from '@inertiajs/vue3'
import { inject, ref, computed } from 'vue'
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
    customerData : any
}>()

const customer = ref({...props.customerData})
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
                quantity: quantity ?? get(product, ['quantity_ordered_new'], product.quantity_ordered)
            }
        )

        if (response.status !== 200) {
            
        }

        router.reload({
            only: ['iris'],
        })

        product.transaction_id = response.data?.transaction_id
        product.quantity_ordered = response.data?.quantity_ordered
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

    // Section: Submit
    const stockInBasket = product.quantity_ordered ? product.quantity_ordered + get(product, ['quantity_ordered_new'], product.quantity_ordered) : product.quantity_ordered
    // console.log('stock in', stockInBasket)
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
                set(props, ['product', 'quantity_ordered'], get(product, ['quantity_ordered_new'], null))
                layout.reload_handle()
            },
            onError: errors => {
                console.log('eee',errors)
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
}, 700)

const compIsValueDirty = computed(() => {
    return get(props.product, ['quantity_ordered_new'], null) !== get(props.product, ['quantity_ordered'], null)
})

const compIsAddToBasket = computed(() => {
    return !props.product.quantity_ordered
})
// watch(() => get(props.product, ['quantity_ordered_new'], null), () => {
//     debAddAndUpdateProduct()
// })
</script>

<template>
    <div class="">
        <div class="flex items-center gap-2 relative w-36">
            <!-- {{ get(props.product, ['quantity_ordered_new'], null) }}
            {{ get(props.product, ['quantity_ordered'], null) }} -->
            <InputNumber
                :modelValue="get(product, ['quantity_ordered_new'], null) === null ? product.quantity_ordered : get(product, ['quantity_ordered_new'], null)"
                @input="(e) => (e.value ? set(product, ['quantity_ordered_new'], e.value) : set(product, ['quantity_ordered_new'], 0), debAddAndUpdateProduct())"
                inputId="integeronly"
                fluid
                showButtons
                :disabled="isLoadingSubmitQuantityProduct"
                :min="0"
                :max="product.stock"
                buttonLayout="horizontal"
                :inputStyle="{
                    textAlign: 'center',
                    minWidth: '4rem'
                }"
            >
                <template #incrementicon>
                    <FontAwesomeIcon icon="fas fa-plus" class="" fixed-width aria-hidden="true" />
                </template>
                <template #decrementicon>
                    <FontAwesomeIcon icon="fas fa-minus" class="" fixed-width aria-hidden="true" />
                </template>
            </InputNumber>
            
            <ConditionIcon :state="status" class="absolute top-1/2 -translate-y-1/2 -right-7"/>

            <!-- <template v-if="compIsValueDirty">
                <Button
                    v-if="compIsAddToBasket"
                    @click="() => onAddToBasket(props.product)"
                    icon="far fa-plus"
                    :label="trans(`Add to basket`)"
                    type="primary"
                    size="lg"
                    :disabled="product.quantity_ordered > product.stock"
                    :loading="isLoadingSubmitQuantityProduct"
                />

                <Button
                    v-else
                    @click="() => onUpdateQuantity(props.product)"
                    :label="trans(`Save`)"
                    icon="fad fa-save"
                    type="primary"
                    size="lg"
                    :disabled="product.quantity_ordered_new > product.stock"
                    :loading="isLoadingSubmitQuantityProduct"
                />
            </template> -->

            <div v-if="!product.quantity_ordered && !product.quantity_ordered_new" class="ml-8">
                <Button
                    v-if="compIsAddToBasket"
                    @click="() => onAddToBasket(props.product, 1)"
                    icon="far fa-plus"
                    :label="trans(`Add to basket`)"
                    type="primary"
                    size="lg"
                    :disabled="product.quantity_ordered > product.stock"
                    :loading="isLoadingSubmitQuantityProduct"
                />
            </div>

            
        </div>
        
        <div v-if="product.quantity_ordered" class="mt-1 xitalic text-gray-700 text-sm">
            {{ trans("Current amount in basket") }}: <span class="font-semibold">{{ locale.currencyFormat(layout?.iris?.currency?.code, (props.product.price * product.quantity_ordered)) }}</span>
            <span>
                <template v-if="product.quantity_ordered_new !== null && product.quantity_ordered_new !== undefined">
                    <span v-if="product.quantity_ordered_new > product.quantity_ordered">
                        <FontAwesomeIcon icon="fal fa-long-arrow-right" class="mx-1 align-middle" fixed-width aria-hidden="true" /> <span v-tooltip="trans('Increased :amount', { amount: locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(product.quantity_ordered_new - product.quantity_ordered)))})">{{ locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(product.quantity_ordered_new))) }}</span>
                    </span>
                    <span v-else-if="product.quantity_ordered_new < product.quantity_ordered">
                        <FontAwesomeIcon icon="fal fa-long-arrow-right" class="mx-1 align-middle" fixed-width aria-hidden="true" /> <span v-tooltip="trans('Decreased :amount', { amount: locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(product.quantity_ordered - product.quantity_ordered_new)))})">{{ locale.currencyFormat(layout?.iris?.currency?.code, Number(props.product.price * Number(product.quantity_ordered_new))) }}</span>
                    </span>
                </template>
            </span>
        </div>
    </div>
</template>