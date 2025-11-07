<script setup>
import { inject, ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { set } from 'lodash-es'
import { faChevronRight } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import LinkIris from '../LinkIris.vue'
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faChevronRight)
// import { XMarkIcon } from '@heroicons/vue/24/outline'

const products = [
    {
        id: 1,
        name: 'Throwback Hip Bag',
        href: '#',
        color: 'Salmon',
        price: '$90.00',
        quantity: 1,
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-01.jpg',
        imageAlt: 'Salmon orange fabric pouch with match zipper, gray zipper pull, and adjustable hip belt.',
    },
    {
        id: 2,
        name: 'Medium Stuff Satchel',
        href: '#',
        color: 'Blue',
        price: '$32.00',
        quantity: 1,
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-02.jpg',
        imageAlt:
            'Front of satchel with blue canvas body, black straps and handle, drawstring top, and front zipper pouch.',
    },
    {
        id: 3,
        name: 'Zip Tote Basket',
        href: '#',
        color: 'White and black',
        price: '$140.00',
        quantity: 1,
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/shopping-cart-page-04-product-03.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
    },
]

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
</script>

<template>
    <div class="flex h-full flex-col overflow-y-auto bg-white shadow-xl">
        <!-- Toggle: collapse-expand rightbasket -->
        <div @click="handleToggleLeftBar"
            class="xhidden absolute z-10  top-2/4 -translate-y-full w-8 lg:w-8 aspect-square xborder xborder-gray-300 rounded-full md:flex md:justify-center md:items-center cursor-pointer"
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
            <div class="flex items-start justify-between">
                <div class="text-lg font-medium">
                    Shopping cart
                </div>
                <div class="ml-3 flex h-7 items-center">
                    <button type="button"
                        class="relative -m-2 p-2 text-gray-400 hover:text-gray-500"
                        @click="open = false">
                        <span class="absolute -inset-0.5"></span>
                        <span class="sr-only">Close panel</span>
                        <FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
                    </button>
                </div>
            </div>

            <div class="mt-8">
                <div class="flow-root">
                    <ul role="list" class="!mx-0 -my-6 divide-y divide-gray-200">
                        <li v-for="product in products" :key="product.id"
                            class="flex py-6">
                            <div
                                class="size-16 shrink-0 overflow-hidden rounded-md border border-gray-200">
                                <img :src="product.imageSrc" :alt="product.imageAlt"
                                    class="size-full object-cover" />
                            </div>

                            <div class="ml-4 flex flex-1 flex-col">
                                <div>
                                    <div
                                        class="flex justify-between  font-medium">
                                        <h4>
                                            <a :href="product.href">{{ product.name }}</a>
                                        </h4>
                                        <p class="ml-4">{{ product.price }}</p>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">{{ product.color }}</p>
                                </div>
                                <div
                                    class="flex flex-1 items-end justify-between text-sm">
                                    <p class="text-gray-500">Qty {{ product.quantity }}
                                    </p>

                                    <div class="flex">
                                        <button type="button"
                                            class="font-medium text-indigo-600 hover:text-indigo-500">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-200 px-4 py-6 sm:px-6">
            <div class="flex justify-between  font-medium">
                <p>Subtotal</p>
                <p>$262.00</p>
            </div>

            <p class="mt-0.5 text-sm text-gray-500">
                Shipping and taxes calculated at checkout.
            </p>

            <div class="mt-6">
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

