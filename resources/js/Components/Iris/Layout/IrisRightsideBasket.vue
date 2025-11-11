<script setup lang="ts">
import { inject, onMounted, ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { debounce, get, set } from 'lodash-es'
import { faChevronRight, faTrashAlt } from "@fal"
import { faMinus, faArrowRight, faPlus } from "@far"
import { library } from "@fortawesome/fontawesome-svg-core"
import LinkIris from '../LinkIris.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { InputNumber } from 'primevue'
import OrderSummary from '@/Components/Summary/OrderSummary.vue'
import PureInput from '@/Components/Pure/PureInput.vue'
import { ProductResource } from '@/types/Iris/Products'
import InputQuantitySideBasket from '../Products/InputQuantitySideBasket.vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import Image from '@/Components/Image.vue'
library.add(faMinus, faArrowRight, faPlus, faChevronRight, faTrashAlt)
// import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps<{
    isOpen: boolean
}>()

const locale = inject('locale', aikuLocaleStructure)

const open = ref(true)

// Set the rightbasket value to local storage
const layout = inject('layout', layoutStructure)
const handleToggleLeftBar = () => {
    const xxx = layout.rightbasket?.show ?? false
    if (typeof window !== "undefined") {
        localStorage.setItem("rightbasket", (!xxx).toString())
    }

    set(layout, 'rightbasket.show', !xxx)
}

// const dummyOrderSummary = { "0": [ { "label": "Елементи", "quantity": 1, "price_base": "Multiple", "price_total": "55.20" } ], "1": [ { "label": "Такси", "information": "", "price_total": "0.00" }, { "label": "Доставяне", "information": "", "price_total": "9.95" } ], "2": [ { "label": "Нетно", "information": "", "price_total": "65.15" }, { "label": "Данък (ДДС 20%)", "information": "", "price_total": "13.03" } ], "3": [ { "label": "Общо", "price_total": "78.18" } ], "currency": { "data": { "id": 49, "code": "EUR", "name": "Euro", "symbol": "€" } } } 


const dataSideBasket = ref(null)
const isLoadingFetch = ref(false)
const isLoadingProducts = ref(false)
const fetchDataSideBasket = async (isWithoutSetProduct?: boolean) => {
    if (!isWithoutSetProduct) {
        isLoadingProducts.value = true
    }
    try {
        isLoadingFetch.value = true
        const response = await axios.get(
            route('iris.json.fetch_basket')
        )
        if (response.status !== 200) {
            
        }
        console.log('Response axios:', response.data)
        if (isWithoutSetProduct) {
            set(dataSideBasket.value, 'order_summary', response.data.order_summary)
            set(dataSideBasket.value, 'order_data', response.data.order_data)
        } else {
            dataSideBasket.value = response.data
            set(layout, 'rightbasket.products', response.data?.products || [])
        }
    } catch (error: any) {
        console.log('errorzzzzz', error)
        // notify({
        //     title: trans("Something went wrong"),
        //     text: error.message || trans("Please try again or contact administrator"),
        //     type: 'error'
        // })
    } finally {
        isLoadingFetch.value = false
    }

    if (!isWithoutSetProduct) {
        isLoadingProducts.value = false
    }
}

const debFetchDataSideBasket = debounce((isWithoutSetProduct?: boolean) => {
    fetchDataSideBasket(isWithoutSetProduct)
}, 500)

watch(() => [layout.iris_variables?.cart_amount, layout.iris_variables?.cart_count], (newValue) => {
    if (props.isOpen) {
        debFetchDataSideBasket(true)
    }
}, {
    immediate: true,
})

watch(() => props.isOpen, (newValue) => {
    if (newValue) {
        debFetchDataSideBasket()
    }
}, {
    immediate: true
})

const onRemoveFromBasket = (product) => {
    router.post(
        route('iris.models.transaction.update', {
            transaction: product.transaction_id
        }),
        {
            quantity_ordered: 0
        },
        {
            preserveScroll: true,
            preserveState: true,
            // only: ['zzzziris'],
            onStart: () => {
                product.isLoadingRemove = true
                // setStatus('loading')

                // isLoadingSubmitQuantityProduct.value = true
            },
            onError: (e) => {
                console.log('error', e)
                product.isLoadingRemove = false
            },
            onSuccess: () => {
                
                layout.reload_handle()
                if (layout?.rightbasket?.products) {
                    layout.rightbasket.products = layout.rightbasket.products.filter(p => p.transaction_id !== product.transaction_id)
                }
                // fetchDataSideBasket(true)
            },
            // onError: errors => {
            //     setStatus('error')
            //     notify({
            //         title: trans("Something went wrong"),
            //         text: errors.message || trans("Failed to update product quantity in basket"),
            //         type: "error"
            //     })
            // },
            // onFinish: () => {
            //     // isLoadingSubmitQuantityProduct.value = false
            // },
        }
    )
}
</script>

<template>
    <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
        <!-- Toggle: collapse-expand rightbasket -->
        <div @click="handleToggleLeftBar"
            class="absolute z-10  top-2/4 -translate-y-full w-8 lg:w-8 aspect-square xborder xborder-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
            :class="layout.rightbasket?.show ? 'left-0 -translate-x-1/2' : '-left-12'"
            :title="layout.rightbasket?.show ? 'Collapse the bar' : 'Expand the bar'"
            :style="{
                'background-color':  `color-mix(in srgb, ${layout.app.theme[0]} 85%, black)`,
                'color': layout.app.theme[1]
            }"
        >
            <div class="flex items-center justify-center transition-all duration-300 ease-in-out">
                <FontAwesomeIcon v-if="layout.rightbasket?.show" icon="far fa-chevron-right" class="h-[14px] leading-none" aria-hidden="true"
                    :class="[
                        layout.rightbasket?.show ? '-translate-x-[1px]' : '',
                    ]"
                    fixed-width
                />
                <FontAwesomeIcon v-else icon="fal fa-shopping-cart" class="" fixed-width aria-hidden="true" />
            </div>
        </div>


        <div class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
            <div class="flex items-start justify-between mb-1">
                <div class="text-lg font-medium">
                    {{ trans("Your Basket (:items items)", { items: layout.iris_variables?.cart_count ?? 0 }) }}
                </div>
                
                <div>
                    <div v-if="isLoadingFetch" class="h-7 w-20 skeleton" />
                    <div v-else>{{ locale.currencyFormat(layout.iris?.currency?.code, layout.iris_variables?.cart_amount) }}</div>
                </div>

                <!-- <div class="ml-3 flex h-7 items-center">
                    <button type="button"
                        class="relative -m-2 p-2 text-gray-400 hover:text-gray-500"
                        @click="open = false">
                        <span class="absolute -inset-0.5"></span>
                        <span class="sr-only">Close panel</span>
                        <FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
                    </button>
                </div> -->
            </div>
            
            <!-- Section: Order Number & 3 points -->
            <div class="text-xs">
                <div v-if="dataSideBasket?.order_data?.reference" class="-ml-2 bg-gray-200 px-2 mb-3">
                    {{ trans("Order Number #:reference", { reference: dataSideBasket?.order_data?.reference ?? '' }) }}
                </div>

                <div class="grid grid-cols-2 mb-3">
                    <div>FREE Click + Collect from Our Warehouse</div>
                    <div class="w-full">
                        <div class="w-full rounded-full h-1.5 bg-gray-200 relative overflow-hidden">
                            <div class="absolute left-0  bg-green-500 top-0 h-full w-3/4 transition-all duration-300 ease-in-out">

                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 mb-3">
                    <div>Spend <span class="text-orange-600 text-sm">$74.5</span> more for FREE delivery</div>
                    <div class="w-full">
                        <div class="w-full rounded-full h-1.5 bg-gray-200 relative overflow-hidden">
                            <div class="absolute left-0  bg-green-500 top-0 inset-0 w-2/4 transition-all">

                            </div>
                        </div>
                    </div>
                </div>

                <div class="">
                    <div>Order within <span class="bg-gray-100">7 hours 42 minutes</span> for next day delivery</div>
                </div>
            </div>

            <!-- Section: Products List -->
            <div class="mt-8">
                <div class="flow-root">
                    <ul role="list" class="!mx-0 -my-6">
                        <template v-if="!isLoadingProducts">
                            <li v-for="product in get(layout, 'rightbasket.products', [])" :key="product.transaction_id" class="flex py-2 relative">
                                <div v-if="product?.isLoadingRemove" class="inset-0 bg-gray-500/20 absolute z-10" />
                                <div
                                    class="size-20 shrink-0 overflow-hidden rounded-md border border-gray-200">
                                    <!-- <img :src="product.image" :alt="product.imageAlt"
                                        class="size-full object-cover" /> -->
                                    <Image
                                        :src="product?.web_images?.main?.original"
                                    />
                                </div>
                                <div>
                                    <!-- <pre>{{ product }}</pre> -->
                                </div>
                                <div class="ml-4 flex justify-between gap-x-4 w-full">
                                    <div class="flex flex-1 flex-col">
                                        <div class="text-orange-600 text-sm">
                                            Volume Discount 5% OFF
                                        </div>
                            
                                        <div class="flex justify-between font-medium">
                                            <h4>
                                                <LinkIris :href="product.canonical_url" class=" hover:underline">{{ product.name }}</LinkIris>
                                            </h4>
                                        </div>
                                        <div class="flex flex-1 items-end justify-between">
                                            <p class=" text-lg">
                                                <!-- <span class="text-gray-500 line-through">{{ product.price }}</span> -->
                                                {{ product?.price ? locale.currencyFormat(dataSideBasket?.order_data?.currency_code, product.price) : '' }}
                                            </p>
                                        </div>
                                    </div>
                                    <!-- Section: input quantity -->
                                    <div class="flex flex-col justify-between items-end pt-7">
                                        <div class="max-w-32 flex gap-x-2 h-fit items-center">
                                            <InputQuantitySideBasket
                                                :product
                                            />
                            
                                            <div @click="() => onRemoveFromBasket(product)">
                                                <LoadingIcon v-if="product?.isLoadingRemove" />
                                                <FontAwesomeIcon v-else icon="fal fa-trash-alt" class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width aria-hidden="true" />
                                            </div>
                                        </div>
                                        <div class="text-xs underline">
                                            Save for later
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </template>

                        <template v-else>
                            <li class="h-24 w-full skeleton"></li>
                            <li class="h-24 w-full skeleton"></li>
                            <li class="h-24 w-full skeleton"></li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section: Voucher Code -->
        <div v-if="false" class="px-6 mb-4">
            <div class="text-rose-700 font-semibold text-sm mb-3">
                You missed ( 1 ) offer
            </div>

            <div>
                <div class="text-gray-500 text-sm">Voucher Code:</div>
                <div class="flex gap-x-4">
                    <PureInput
                        :modelValue="''"
                        placeholder="Enter voucher code"
                    />
                    <Button
                        label="Apply"
                    />
                </div>
            </div>
        </div>
        
        <!-- Section: Order Summary -->
        <div class="border-t border-gray-200 px-4 py-6 sm:px-6">
            <div class="relative isolate">

                <OrderSummary
                    :order_summary="dataSideBasket?.order_summary"
                    :currency_code="dataSideBasket?.order_summary?.currency?.code"
                />

                <div v-if="isLoadingFetch" class="absolute inset-0">
                    <div class="inset-0 h-full w-full skeleton z-10" />
                </div>
            </div>

            <!-- <div class="flex justify-between  font-medium">
                <p>Subtotal</p>
                <p>$262.00</p>
            </div>

            <p class="mt-0.5 text-sm text-gray-500">
                Shipping and taxes calculated at checkout.
            </p> -->

            <div class="mt-12">
                <LinkIris href="/app/checkout">
                    <Button
                        full
                        :label="trans('Checkout')"
                        iconRight="far fa-arrow-right"
                        key="1"
                    />
                </LinkIris>
            </div>
            
            <div class="mt-6 flex justify-start text-center text-sm text-gray-500">
                <p>
                    or{{ ' ' }}
                    <LinkIris
                        href="/app/basket" type="button"
                        class="font-medium text-indigo-600 hover:text-indigo-500"
                        @click="open = false">
                        Open basket
                        <span aria-hidden="true"> &rarr;</span>
                    </LinkIris>
                </p>
            </div>
        </div>
    </div>
</template>

