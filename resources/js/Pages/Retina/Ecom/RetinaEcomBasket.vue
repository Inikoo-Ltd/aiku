<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import CheckoutSummary from "@/Components/Retina/Ecom/CheckoutSummary.vue"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { inject, ref, onMounted, nextTick, onBeforeUnmount, computed } from "vue"
import { notify } from "@kyvg/vue3-notification"
import axios from "axios"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faTag } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { debounce } from 'lodash-es'
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import TableEcomBasket from "@/Components/Retina/Ecom/Order/TableEcomBasket.vue"
import { Image as ImageTS } from "@/types/Image"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import EmptyState from '@/Components/Utils/EmptyState.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import ProductsSelectorAutoSelect from '@/Components/Dropshipping/ProductsSelectorAutoSelect.vue'
import RecommendersLuigi1Iris from '@/Components/CMS/Webpage/SeeAlso1/RecommendersLuigi1Iris.vue'
library.add(faTag)

const props = defineProps<{
    pageHead: PageHeadingTS
    order: {
        id: string
        reference: string
        is_fully_paid: string
        customer_notes: string | null
        public_notes: string | null
        shipping_notes: string | null
        voucher_code: string | null
        unpaid_amount: string
        total_amount: string
        payment_amount: string
        currency_code: string
        customer_slug: string
        customer_name: string
        slug: string
    }
    transactions: {
        data: {
            id: string
            state: string
            status: string
            quantity_ordered: string
            quantity_bonus: string
            quantity_dispatched: string
            quantity_fail: string
            quantity_cancelled: string
            gross_amount: string
            net_amount: string
            asset_code: string
            asset_name: string
            asset_type: string
            product_slug: string
            image: ImageTS
            created_at: string
            available_quantity: string
            currency_code: string
            deleteRoute: routeType
        }[]
    }
    summary: {
        net_amount: string
        gross_amount: string
        tax_amount: string
        goods_amount: string
        services_amount: string
        charges_amount: string
    }
    balance: string
    total_to_pay: string
    routes: {
        update_route: routeType
        submit_route: routeType
        select_products: routeType
        pay_whit_balance: routeType
    }
    total_products: number
    is_in_basket: boolean
}>()

// console.log(props)

const layout = inject('layout', retinaLayoutStructure)
const locale = inject('locale', aikuLocaleStructure)

console.log(layout)

const isModalProductListOpen = ref(false)
const listLoadingProducts = ref({

})
const isLoadingSubmit = ref(false)

// Teleport control with key for rerender
const isTeleportReady = ref(false)
const teleportKey = ref(0)

// Responsive slides per view
const slidesPerView = ref(4)

const updateSlidesPerView = () => {
    const width = window.innerWidth
    if (width < 640) {
        slidesPerView.value = 2 // mobile
    } else if (width < 768) {
        slidesPerView.value = 2 // small tablet
    } else if (width < 1024) {
        slidesPerView.value = 3 // tablet
    } else if (width < 1280) {
        slidesPerView.value = 4 // desktop
    } else {
        slidesPerView.value = 4 // large desktop
    }
}

const checkTeleportTarget = () => {
    try {
        const targetElement = document.getElementById('retina-right-section')
        if (targetElement && targetElement.parentNode) {
            const rect = targetElement.getBoundingClientRect()
            if (rect.width > 0 || rect.height > 0 || targetElement.offsetParent !== null) {
                isTeleportReady.value = true
                teleportKey.value += 1 // Force rerender
                return true
            }
        }
    } catch (error) {
        console.warn('Error checking teleport target:', error)
    }
    return false
}

onMounted(async () => {
    await nextTick()
    
    // Set initial slides per view
    updateSlidesPerView()
    
    // Add resize listener
    window.addEventListener('resize', updateSlidesPerView)
    
    // Wait a bit for layout to stabilize
    setTimeout(async () => {
        await nextTick()
        
        // Check immediately if target exists and is ready
        if (checkTeleportTarget()) {
            return
        }
        
        // If not found, use MutationObserver to watch for the element
        const observer = new MutationObserver(() => {
            if (checkTeleportTarget()) {
                observer.disconnect()
            }
        })
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        })
        
        // Fallback timeout
        setTimeout(() => {
            observer.disconnect()
            try {
                const targetElement = document.getElementById('retina-right-section')
                if (targetElement) {
                    isTeleportReady.value = true
                    teleportKey.value += 1 // Force rerender
                }
            } catch (error) {
                console.warn('Final teleport check failed:', error)
                isTeleportReady.value = false
            }
        }, 2000)
    }, 100)
})

// Cleanup resize listener
onBeforeUnmount(() => {
    window.removeEventListener('resize', updateSlidesPerView)
})

// Section: Submit Note
const noteToSubmit = ref(props?.order?.customer_notes || '')
const deliveryInstructions = ref(props?.order?.shipping_notes || '')
const recentlySuccessNote = ref<string[]>([])
const recentlyErrorNote = ref(false)
const isLoadingNote = ref<string[]>([])
const onSubmitNote = async (key_in_db: string, value: string) => {
    try {
        isLoadingNote.value.push(key_in_db)
        await axios.patch(route(props.routes.update_route.name, props.routes.update_route.parameters), {
            [key_in_db ?? 'customer_notes']: value
        })


        isLoadingNote.value = isLoadingNote.value.filter(item => item !== key_in_db)
        recentlySuccessNote.value.push(key_in_db)
        setTimeout(() => {
            recentlySuccessNote.value = recentlySuccessNote.value.filter(item => item !== key_in_db)
        }, 3000)
    } catch  {
        recentlyErrorNote.value = true
        setTimeout(() => {
            recentlyErrorNote.value = false
        }, 3000)

        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to update the note, try again."),
            type: "error",
        })
    }
}
const debounceSubmitNote = debounce(() => onSubmitNote('customer_notes', noteToSubmit.value), 800)
const debounceDeliveryInstructions = debounce(() => onSubmitNote('shipping_notes', deliveryInstructions.value), 800)

interface Product {
    historic_asset_id: number
    id: number
    quantity_selected?: number
    transaction_id?: string
    quantity_ordered?: number
}
const onAddProducts = async (product: Product) => {
    const storeRoute = {
        route_post: route('retina.models.product.add-to-basket', { 
            product: product.id
        }),
        method: 'post',
        body: {
            quantity: product.quantity_selected ?? 1,
        }
    }
    
    const routePost = product?.transaction_id ? {
        route_post: route('retina.models.transaction.update', {
            transaction: product.transaction_id
        }),
        method: 'patch',
        body: {
            quantity_ordered: product.quantity_selected ?? 1,
        }
    } : storeRoute

    // return

    const onlyProps = product?.transaction_id ? ['transactions', 'box_stats', 'total_products', 'balance', 'total_to_pay'] : {}

    router[routePost.method](
        routePost.route_post,
        routePost.body,
        {
            ...onlyProps,
            onStart: () => {
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'loading'
            },
            onBefore: () => 'isLoadingSubmit.value = true',
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error.products || undefined,
                    type: "error"
                })
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'error'
            },
            onSuccess: () => {
                // notify({
                //     title: trans("Success!"),
                //     text: trans("Successfully added portfolios"),
                //     type: "success"
                // })
                listLoadingProducts.value[`id-${product.historic_asset_id}`] = 'success'
            },
            onFinish: () => {
                isLoadingSubmit.value = false
                setTimeout(() => {
                    listLoadingProducts.value[`id-${product.historic_asset_id}`] = null
                }, 3000)
            }
        })
}

const onAddProductFromRecommender = async (productId: string) => {
    const storeRoute = {
        route_post: route('retina.models.product.add-to-basket', {
            product: productId
        }),
        method: 'post',
        body: {
            quantity: 1,
        }
    }

    router.post(
        storeRoute.route_post,
        storeRoute.body,
        {
            preserveScroll: true,
            onStart: () => {
                listLoadingProducts.value[`recommender-${productId}`] = 'loading'
            },
            onBefore: () => {
                isLoadingSubmit.value = true
            },
            onError: (error) => {
                notify({
                    title: trans("Something went wrong."),
                    text: error.products || undefined,
                    type: "error"
                })
                listLoadingProducts.value[`recommender-${productId}`] = 'error'
            },
            onSuccess: () => {
                notify({
                    title: trans("Success!"),
                    text: trans("Product added to basket"),
                    type: "success"
                })
                listLoadingProducts.value[`recommender-${productId}`] = 'success'
            },
            onFinish: () => {
                isLoadingSubmit.value = false
                setTimeout(() => {
                    listLoadingProducts.value[`recommender-${productId}`] = null
                }, 3000)
            }
        })
}

const blackListProductIds = computed(() => {
    if (!props.transactions?.data) return []

    return props.transactions.data
        .map(transaction => {
            return transaction.id.toString()
        })
        .filter(Boolean)
})
</script>

<template>
    <Head :title="trans('Basket')" />
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex items-center border border-gray-300 rounded-md divide-x divide-gray-300">
				<!-- <Button
					v-if="upload_spreadsheet"
					@click="() => upload_spreadsheet ? isModalUploadSpreadsheet = true : onNoStructureUpload()"
					:label="trans('Upload products')"
                    icon="upload"
                    type="tertiary"
					class="rounded-none border-0"
				/> -->
                <Button
                   v-if="is_in_basket"
                    @click="() => isModalProductListOpen = true"
                    :label="trans('Add products')"
                    type="tertiary"
					icon="fas fa-plus"
					class="rounded-none border-none"
                />
			</div>

        </template>
    </PageHeading>


    <CheckoutSummary
        :summary
        :balance
    />
    
    <template v-if="order">
        <div class="mb-4 mx-4 mt-4 rounded-md border border-gray-200">
            <TableEcomBasket
                :data="transactions"
                :updateRoute="routes.update_route"
            />
        </div>
        <div class="w-full px-4 mt-8">
            <div v-if="total_products > 0" class="flex justify-end px-6 gap-x-4">
                <div class="grid grid-cols-3 gap-x-4 w-full">
                    <div></div>
        
                    <!-- Input text: Delivery instructions -->
                    <div class="">
                        <div class="text-sm text-gray-500">
                            <FontAwesomeIcon icon="fal fa-truck" class="text-[#38bdf8]" fixed-width aria-hidden="true" />
                            {{ trans("Delivery instructions") }}
                            <FontAwesomeIcon v-tooltip="trans('To be printed in shipping label')" icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-600" fixed-width aria-hidden="true" />
                            :
                        </div>
                        <PureTextarea
                            v-model="deliveryInstructions"
                            @update:modelValue="() => debounceDeliveryInstructions()"
                            :placeholder="trans('Add if needed')"
                            rows="4"
                            :disabled="!is_in_basket"
                            :loading="isLoadingNote.includes('shipping_notes')"
                            :isSuccess="recentlySuccessNote.includes('shipping_notes')"
                            :isError="recentlyErrorNote"
                        />
                    </div>
                    <!-- Input text: Other instructions -->
                    <div class="">
                        <div class="text-sm text-gray-500">
                            <FontAwesomeIcon icon="fal fa-sticky-note" style="color: rgb(255, 125, 189)" fixed-width aria-hidden="true" />
                            {{ trans("Other instructions") }}:
                        </div>
                        <PureTextarea
                            v-model="noteToSubmit"
                            @update:modelValue="() => debounceSubmitNote()"
                            :placeholder="trans('Add if needed')"
                            rows="4"
                            :loading="isLoadingNote.includes('customer_notes')"
                            :isSuccess="recentlySuccessNote.includes('customer_notes')"
                            :isError="recentlyErrorNote"
                        />
                    </div>
                </div>
                <div class="w-72 pt-5">
                    <!-- Place Order -->
                    <template v-if="Number(total_to_pay) === 0 && Number(balance) > 0">
                        <ButtonWithLink
                            iconRight="fas fa-arrow-right"
                            :label="trans('Place order')"
                            :routeTarget="routes?.pay_with_balance"
                            class="w-full"
                            full
                        >
                        </ButtonWithLink>
                        <div class="text-xs text-gray-500 mt-2 italic flex items-start gap-x-1">
                            <FontAwesomeIcon icon="fal fa-info-circle" class="mt-[4px]" fixed-width aria-hidden="true" />
                            <div class="leading-5">
                                {{ trans("This is your final confirmation. You can pay totally with your current balance.") }}
                            </div>
                        </div>
                    </template>
                    <!-- Checkout -->
                    <ButtonWithLink
                        v-else
                        iconRight="fas fa-arrow-right"
                        :label="trans('Pay with Checkout')"
                        :routeTarget="{
                            name: 'retina.ecom.checkout.show',
                            parameters: {
                                order: props?.order?.slug
                            }
                        }"
                        class="w-full"
                        full
                    />
                </div>
            </div>
        </div>
    </template>
    
    <div v-else class="text-center w-full">
        <EmptyState
            :data="{
                title: trans('Basket is empty')
            }"
        />
    </div>

    <Teleport v-if="layout.app.environment !== 'production'" to="#retina-right-section" :disabled="!isTeleportReady" :key="teleportKey">
        <div class="max-w-[calc(1280px-200px)] mt-8 p-4 bg-white rounded-md shadow-lg">
            <h2 class="text-2xl font-bold text-center mb-4">{{ trans('You might also like') }}</h2>
            <RecommendersLuigi1Iris @add-to-basket="(productId: number | string) => onAddProductFromRecommender(productId)" :is-add-to-basket="true" recommendation_type="test_reco" :slidesPerView="slidesPerView"  :listLoadingProducts :blacklistItems="blackListProductIds" />
        </div>
    </Teleport>

      <!-- Modal: add products to Order -->
    <Modal :isOpen="isModalProductListOpen" @onClose="isModalProductListOpen = false" width="w-full max-w-6xl" key="">
        <ProductsSelectorAutoSelect
            :headLabel="trans('Add products to basket')"
            :routeFetch="props.routes.select_products"
            :isLoadingSubmit
            @submit="(products: {}) => onAddProducts(products)"
            :listLoadingProducts
            withQuantity
        >
        </ProductsSelectorAutoSelect>
    </Modal>
</template>