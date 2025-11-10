<script setup lang="ts">
import { inject, onMounted, ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { set } from 'lodash-es'
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
library.add(faMinus, faArrowRight, faPlus, faChevronRight, faTrashAlt)
// import { XMarkIcon } from '@heroicons/vue/24/outline'

// const products = ref([
//     {
//         id: 1,
//         name: 'Throwback Hip Bag Throwback Hip Bag Throwback Hip Bag Throwback Hip Bag',
//         href: '#',
//         color: 'Salmon',
//         stock: 23,
//         price: '$90.00',
//         quantity: 4,
//         imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-01.jpg',
//         imageAlt: 'Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt.',
//     },
//     {
//         id: 2,
//         name: 'Medium Stuff Satchel Medium Stuff Satchel Medium Stuff Satchel Medium Stuff Satchel',
//         href: '#',
//         color: 'Blue',
//         stock: 19,
//         price: '$32.00',
//         quantity: 5,
//         imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-02.jpg',
//         imageAlt:
//             'Front of satchel with blue canvas body, black straps and handle, drawstring top, and front zipper pouch.',
//     },
//     {
//         id: 3,
//         name: 'Zip Tote Basket Zip Tote Basket Zip Tote Basket Zip Tote Basket',
//         href: '#',
//         color: 'White and black',
//         stock: 37,
//         price: '$140.00',
//         quantity: 14,
//         imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-03.jpg',
//         imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
//     },
// ])

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
onMounted(async () => {
    try {
        const response = await axios.get(
            route('iris.json.fetch_basket')
        )
        if (response.status !== 200) {
            
        }
        dataSideBasket.value = response.data
        console.log('Response axios:', response.data)
    } catch (error: any) {
        console.log('error', error)
        // notify({
        //     title: trans("Something went wrong"),
        //     text: error.message || trans("Please try again or contact administrator"),
        //     type: 'error'
        // })
    }
})
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
            <div class="flex items-center justify-center transition-all duration-300 ease-in-out"
                
            >
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
                    Your Basket (4 items)
                </div>
                
                <div>
                    $100.50
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
                <div class="bg-gray-300 px-2 mb-3">
                    Order Number GB56432
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
                        <li v-for="product in dataSideBasket?.products" :key="product.id" class="flex py-2">
                            <div
                                class="size-20 shrink-0 overflow-hidden rounded-md border border-gray-200">
                                <img :src="product.imageSrc" :alt="product.imageAlt"
                                    class="size-full object-cover" />
                            </div>

                            <div class="ml-4 flex justify-between gap-x-4">
                                <div class="flex flex-1 flex-col">
                                    <div class="text-orange-600 text-sm">
                                        Volume Discount 5% OFF
                                    </div>
                                    
                                    <div class="flex justify-between  font-medium">
                                        <h4>
                                            <a :href="product.href">{{ product.name }}</a>
                                        </h4>
                                    </div>

                                    <div class="flex flex-1 items-end justify-between">
                                        <p class=" text-lg">
                                            <span class="text-gray-500 line-through">$90.00</span>
                                            72.56
                                        </p>
                                    </div>
                                </div>

                                <!-- Section: input quantity -->
                                <div class="flex flex-col justify-between items-end pt-7">
                                    <div class="max-w-32 flex gap-x-2 h-fit items-center">
                                        <InputQuantitySideBasket
                                            :product
                                        />
                                        <div>
                                            <FontAwesomeIcon icon="fal fa-trash-alt" class="text-red-400 hover:text-red-600 cursor-pointer" fixed-width aria-hidden="true" />
                                        </div>
                                    </div>

                                    <div class="text-xs underline">
                                        Save for later
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Section: Voucher Code -->
        <div class="px-6 mb-4">
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
            <div class="">
                <OrderSummary
                    :order_summary="dataSideBasket?.order_summary"
                    :currency_code="dataSideBasket?.order_summary?.currency?.code"
                />
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

