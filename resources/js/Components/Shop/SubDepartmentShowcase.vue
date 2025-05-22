<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import Button from '@/Components/Elements/Buttons/Button.vue'

const props = defineProps<{
    data: {
        title: string
        description: string
        image: string
        link: string
    }
}>()

const products = [
    {
        id: 1,
        name: 'Zip Tote Basket',
        color: 'White and black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-01.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
        price: '$140',
    },
    {
        id: 1,
        name: 'Zip Tote Basket',
        color: 'White and black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-01.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
        price: '$140',
    },
    {
        id: 1,
        name: 'Zip Tote Basket',
        color: 'White and black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-01.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
        price: '$140',
    },
]
</script>

<template>
    <div class="">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:max-w-7xl lg:px-8">
            <h2 class="text-xl font-bold">{{ trans("Family list") }} ({{ products.length }})</h2>

            <div class="mt-8 grid grid-cols-1 gap-y-12 sm:grid-cols-2 sm:gap-x-6 lg:grid-cols-4 xl:gap-x-8">
                <div v-for="product in products" :key="product.id" class="">
                    <div class="relative">
                        <div class="relative border border-gray-100 shadow h-72 w-full overflow-hidden rounded-lg">
                            <img :src="product.imageSrc" :alt="product.imageAlt" class="size-full object-cover" />
                        </div>

                        <div class="relative mt-4">
                            <h3 class="font-bold">{{ product.name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ product.color }}</p>
                        </div>

                        <div class="absolute inset-x-0 top-0 flex h-72 items-end justify-end overflow-hidden rounded-lg p-4">
                            <div aria-hidden="true"
                                class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-black opacity-30" />
                            <p class="relative text-lg font-semibold text-white">{{ product.price }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <Button label="Unlink" type="negative" full>

                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>