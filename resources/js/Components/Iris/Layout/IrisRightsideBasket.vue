<script setup lang="ts">
import { inject, onMounted, ref, watch } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { debounce, get, set } from 'lodash-es'
import { faChevronRight, faTrashAlt } from "@fal"
import { faCheckCircle } from "@fas"
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
import Discount from '@/Components/Utils/Label/Discount.vue'
import { computed } from 'vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
library.add(faMinus, faArrowRight, faPlus, faChevronRight, faTrashAlt, faCheckCircle)
// import { XMarkIcon } from '@heroicons/vue/24/outline'

interface DataSideBasket {
    order_summary: any
    order_data: {
        reference: string
    }
}

interface Product {
    transaction_id: number
    quantity_ordered: number
    offers_data: {
        
    }
}

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


const dataSideBasket = ref<DataSideBasket | null>(null)
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
        
        console.log('fetchDataSideBasket:', response.data)

        // if (isWithoutSetProduct) {
            // } else {
                //     set(dataSideBasket.value, 'order_summary', response.data.order_summary)
                //     set(dataSideBasket.value, 'order_data', response.data.order_data)
            dataSideBasket.value = response.data
            set(layout, 'rightbasket.products', response.data?.products || [])
        // }
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
}, 250)

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

const onRemoveProductWhenQuantityZero = (product: ProductResource) => {
    // console.log('productRemoved', product)
    // console.log('layout.rightbasket.products', layout.rightbasket.products)
    if (layout?.rightbasket?.products) {
        layout.rightbasket.products = layout.rightbasket.products.filter(p => p.transaction_id !== product.transaction_id)
    }
}

const onRemoveFromBasket = (product) => {
    if (!product.transaction_id) {
        if (layout?.rightbasket?.products) {
            layout.rightbasket.products = layout.rightbasket.products.filter(p => p.transaction_id !== product.transaction_id)
            layout.reload_handle()
        }
        return
    }
    
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

                if (layout.temp?.fetchIrisProductCustomerData) {
                    layout.temp.fetchIrisProductCustomerData()
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


// Method: convert "15.26" to 15.26
const convertToFloat2 = (val: any) => {
    const num = parseFloat(val)
    if (isNaN(num)) return 0.00
    return parseFloat(num.toFixed(2))
}
</script>

<template>
    <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
        <!-- Toggle: collapse-expand rightbasket -->
        <div @click="handleToggleLeftBar"
            class="absolute z-10  top-2/4 -translate-y-full w-8 lg:w-8 aspect-square xborder xborder-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
            :class="layout.rightbasket?.show ? 'left-0 -translate-x-1/2' : '-left-12'"
            v-tooltip="layout.rightbasket?.show ? trans('Collapse the bar') : trans('Expand the bar')"
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
                    {{ trans("Your Basket (:xxx items)", { xxx: layout.iris_variables?.cart_count ?? 0 }) }}
                </div>
                
                <div class="relative overflow-hidden">
                    <Transition name="spin-to-down">
                        <div :key="layout.iris_variables?.cart_amount">
                            {{ locale.currencyFormat(layout.iris?.currency?.code, layout.iris_variables?.cart_amount) }}
                        </div>
                    </Transition>
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
            
            <!-- Section: Bonus list -->
            <div class="text-xs">
                <div v-if="dataSideBasket?.order_data?.reference" class="-ml-2 bg-gray-200 px-2 mb-3">
                    {{ trans("Order Number #:reference", { reference: dataSideBasket?.order_data?.reference ?? '' }) }}
                </div>
                
                <div v-for="offer in layout.offer_meters" class="grid grid-cols-2 mb-3">
                    <div :class="convertToFloat2(offer.metadata?.current) >= convertToFloat2(offer.metadata?.target) ? 'text-green-700' : ''"
                        class="flex items-center whitespace-nowrap"
                    >
                        <div v-if="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)" class="text-base">
                            {{ offer.label}}
                        </div>
                        <div v-else class="text-base text-green-600">
                            {{ offer.label_got ?? offer.label}}
                        </div>

                        <InformationIcon v-if="offer.information" :information="offer.information" class="ml-1" />
                        <FontAwesomeIcon v-if="!(convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target))" icon="fas fa-check-circle" class="ml-1" fixed-width aria-hidden="true" />
                    </div>
                    
                    <!-- Section: meter -->
                    <div v-tooltip="convertToFloat2(offer.metadata?.target) && convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target)
                        ? trans(`:current of :target products gross amount`, { current: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.current)), target: locale.currencyFormat(layout.iris?.currency?.code, convertToFloat2(offer.metadata?.target)) })
                        : trans('Bonus secured')" class="w-full flex items-center">
                        <div class="w-full rounded-full h-2 bg-gray-200 relative overflow-hidden">
                            <div class="absolute  left-0   top-0 h-full w-3/4 transition-all duration-1000 ease-in-out"
                                :class="convertToFloat2(offer.metadata?.current) < convertToFloat2(offer.metadata?.target) ? 'shimmer bg-green-400' : 'bg-green-500'"
                                :style="{
                                    width: convertToFloat2(offer.metadata?.target) ? convertToFloat2(offer.metadata?.current)/convertToFloat2(offer.metadata?.target) * 100 + '%' : '100%'
                                }"
                            />
                        </div>
                    </div>
                </div>

                <!-- <div class="grid grid-cols-2 mb-3">
                    <div>Spend <span class="text-orange-600 text-sm">$74.5</span> more for FREE delivery</div>
                    <div class="w-full flex items-center">
                        <div class="w-full rounded-full h-1.5 bg-gray-200 relative overflow-hidden">
                            <div class="absolute left-0  bg-green-500 top-0 inset-0 w-2/4 transition-all">

                            </div>
                        </div>
                    </div>
                </div> -->

                <!-- <div class="">
                    <div>Order within <span class="bg-gray-100">7 hours 42 minutes</span> for next day delivery</div>
                </div> -->
            </div>

            <!-- Section: Products List -->
            <div class="mt-8 flow-root">
                <ul role="list" class="!mx-0 -my-6">
                    <template v-if="!isLoadingProducts">
                        <li v-for="product in get(layout, 'rightbasket.products', [])" :key="product.transaction_id" class="flex py-2 relative">
                            <div v-if="product?.isLoadingRemove" class="inset-0 bg-gray-500/20 absolute z-10" />
                            <div class="relative">
                                <LinkIris :href="product.canonical_url" class="block group font-medium hover:underline size-20 shrink-0 overflow-hidden rounded-md border"
                                    :class="Object.keys(product.offers_data || {})?.length ? 'border-pink-300' : 'border-gray-200'"
                                >
                                    <Image
                                        :src="product?.web_image_thumbnail"
                                        class="w-full h-full flex justify-center items-center group-hover:scale-110 transition-all"
                                    />
                                </LinkIris>
                            </div>
                            
                            <div class="ml-4 flex justify-between gap-x-4 w-full">
                                <!-- Section: label Discount, product name, product price -->
                                <div class="flex flex-1 flex-col">
                                    <Discount v-if="Object.keys(product.offers_data || {})?.length" :offers_data="product.offers_data" />
                        
                                    <div class="flex justify-between font-medium">
                                        <h4 v-tooltip="product.name">
                                            <LinkIris :href="product.canonical_url" class="font-medium hover:underline">{{ product.code }}</LinkIris>
                                        </h4>
                                    </div>

                                    <div class="flex flex-1 items-end justify-between">
                                        <p class=" text-lg" :class="product.gross_amount != product?.net_amount ? 'text-green-500' : ''">
                                            <span v-if="product.gross_amount != product?.net_amount" class="text-gray-500 line-through mr-1 opacity-70">{{ locale.currencyFormat(layout.iris?.currency?.code, product.gross_amount) }}</span>
                                            <span>{{ locale.currencyFormat(layout.iris?.currency?.code || '', product.net_amount) }}</span>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Section: input quantity -->
                                <div class="flex flex-col justify-between items-end pt-7">
                                    <div class="max-w-32 flex gap-x-2 h-fit items-center">
                                        <InputQuantitySideBasket
                                            :product
                                            @productRemoved="() => onRemoveProductWhenQuantityZero(product)"
                                        />
                        
                                        <div @click="() => onRemoveFromBasket(product)">
                                            <LoadingIcon v-if="product?.isLoadingRemove" />
                                            <FontAwesomeIcon v-else icon="fal fa-trash-alt" class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width aria-hidden="true" />
                                        </div>
                                    </div>
                                    <!-- <div class="text-xs underline">
                                        Save for later
                                    </div> -->
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
                    :currency_code="layout.iris?.currency?.code"
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
                        href="/app/basket"
                        class="font-medium text-indigo-600 hover:text-indigo-500"
                        @click="open = false">
                        {{ trans("Open basket") }}
                        <span aria-hidden="true"> &rarr;</span>
                    </LinkIris>
                </p>
            </div>
        </div>
    </div>
</template>



<style lang="scss" scoped>
.ribbon {
    font-size: 0.6rem;
//   font-weight: bold;
    color: #fff;
}
.ribbon {
    --f: .5em; /* control the folded part*/
    --r: .8em; /* control the ribbon shape */
    
    position: absolute;
    bottom: 22px;
    left: calc(-1*var(--f));
    padding-inline: .25em;
    padding-left: 4px;
    line-height: 1.8;
    background: rgb(236 72 153);
    border-top: var(--f) solid #0005;
    border-right: var(--r) solid #0000;
    clip-path: 
        polygon(0 100%,0 var(--f),var(--f) 0,
        var(--f) var(--f),100% var(--f),
        calc(100% - var(--r)) calc(50% + var(--f)/2),100% 100%);
}

</style>