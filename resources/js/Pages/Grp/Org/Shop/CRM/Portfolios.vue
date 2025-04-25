<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import TablePortfolios from '@/Components/Tables/Grp/Org/CRM/TablePortfolios.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { inject, ref, watch } from 'vue'
import axios from 'axios'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { notify } from '@kyvg/vue3-notification'
import PureInput from '@/Components/Pure/PureInput.vue'
import Tag from '@/Components/Tag.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { trans } from 'laravel-vue-i18n'

defineProps<{
    data: {}
    title: string
    pageHead: PageHeadingTypes
}>()

const layout = inject('layout', layoutStructure)

const isLoadingSubmit = ref(false)
const isLoadingFetch = ref(false)
const portfoliosList = ref([])
const selectedPortfolio = ref<number | null>(null)
const errorMessage = ref(null)

// Method: Get portfolios list
const getPortfoliosList = async () => {
    isLoadingFetch.value = true
    try {
        const response = await axios.get(route("grp.org.shops.show.crm.customers.show.portfolios.filtered-products", { "organisation": layout?.currentParams?.organisation, "shop": layout?.currentParams?.shop, "customer": layout?.currentParams?.customer }))

        portfoliosList.value = response.data.data
        isLoadingFetch.value = false
    } catch (error) {
        isLoadingFetch.value = false
        notify({
            title: "Something went wrong.",
            text: "Error while get the products list.",
            type: "error"
        })
    }
}

// Method: Submit the selected item
const onSubmitAddItem = async (url: string, close: Function, idProduct: number) => {
    router.post(url, {
        product_id: idProduct
    }, {
        onBefore: () => isLoadingSubmit.value = true,
        onError: (error) => {
            errorMessage.value = error
            notify({
                title: "Something went wrong.",
                text: error.product_id || undefined,
                type: "error"
            })
        },
        onSuccess: () => {
            router.reload({only: ['data']}),
            close()
        },
        onFinish: () => isLoadingSubmit.value = false
    })
}

const selectedProduct = ref([])
const productsFake = [
    {
        id: 1,
        name: 'Zip Tote Basket',
        code: 'White and black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-01.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
        price: '$140',
    },
    {
        id: 2,
        name: 'Leather Long Wallet',
        code: 'Brown',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-02.jpg',
        imageAlt: 'Front of leather long wallet in brown color.',
        price: '$85',
    },
    {
        id: 3,
        name: 'Canvas Backpack',
        code: 'Gray',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-03.jpg',
        imageAlt: 'Front of canvas backpack in gray color.',
        price: '£5.42 (£0.2/ball)',
    },
    {
        id: 4,
        name: 'Wool Hat',
        code: 'Black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-04.jpg',
        imageAlt: 'Front of wool hat in black color.',
        price: '£7.42 (£0.62/letter)',
    },
    {
        id: 5,
        name: 'Silk Scarf',
        code: 'Red',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/category-page-04-image-card-01.jpg',
        imageAlt: 'Front of silk scarf in red color.',
        price: '$60',
    },
    {
        id: 6,
        name: 'Leather Belt',
        code: 'Tan',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/category-page-04-image-card-06.jpg',
        imageAlt: 'Front of leather belt in tan color.',
        price: '$50',
    },
    {
        id: 1,
        name: 'Zip Tote Basket',
        code: 'White and black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-01.jpg',
        imageAlt: 'Front of zip tote bag with white canvas, black canvas straps and handle, and black zipper pulls.',
        price: '$140',
    },
    {
        id: 2,
        name: 'Leather Long Wallet',
        code: 'Brown',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-02.jpg',
        imageAlt: 'Front of leather long wallet in brown color.',
        price: '$85',
    },
    {
        id: 3,
        name: 'Canvas Backpack',
        code: 'Gray',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-03.jpg',
        imageAlt: 'Front of canvas backpack in gray color.',
        price: '£5.42 (£0.2/ball)',
    },
    {
        id: 4,
        name: 'Wool Hat',
        code: 'Black',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/product-page-03-related-product-04.jpg',
        imageAlt: 'Front of wool hat in black color.',
        price: '£7.42 (£0.62/letter)',
    },
    {
        id: 5,
        name: 'Silk Scarf',
        code: 'Red',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/category-page-04-image-card-01.jpg',
        imageAlt: 'Front of silk scarf in red color.',
        price: '$60',
    },
    {
        id: 6,
        name: 'Leather Belt',
        code: 'Tan',
        href: '#',
        imageSrc: 'https://tailwindcss.com/plus-assets/img/ecommerce-images/category-page-04-image-card-06.jpg',
        imageAlt: 'Front of leather belt in tan color.',
        price: '$50',
    },
]

const selectProduct = (item: any) => {
    const index = selectedProduct.value?.indexOf(item);
    if (index === -1) {
        selectedProduct.value?.push(item);
    } else {
        selectedProduct.value?.splice(index, 1);
    }
}
    
const isOpenModalPortfolios = ref(false)
watch(isOpenModalPortfolios, (newVal) => {
    if (newVal) {
        getPortfoliosList()
    }
})
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                @click="() => isOpenModalPortfolios = true"
                :type="'secondary'"
                icon="fal fa-plus"
                :xxstooltip="'action.tooltip'"
                :label="trans('Add portfolios')"
            />
        </template>
    </PageHeading>

    <TablePortfolios :data="data" />

    <Modal :isOpen="isOpenModalPortfolios" @onClose="isOpenModalPortfolios = false" class="w-full max-w-5xl">
        <div class="">
            
            <div class="mb-2">
                <PureInput
                    modelValue="444"
                />
            </div>
            
            <div class="h-[500px] grid grid-cols-5 text-base font-normal">
                <div class="overflow-y-auto bg-gray-200 rounded h-full px-3 py-1">
                    <div class="font-semibold text-lg py-1">Suggestions</div>
                    <div class="border-t border-gray-300 mb-1"></div>
                </div>

                <div class="col-span-4 pb-2 px-4 h-fit overflow-auto flex flex-col">
                    <div class="font-semibold text-lg py-1">Product</div>
                    <div class="border-t border-gray-300 mb-1"></div>
                    <!-- Products list -->
                    <div class="h-[400px] overflow-auto py-2">
                        <div class="grid grid-cols-3 gap-3">
                            <div
                                v-show="!isLoadingFetch"
                                v-for="(item, index) in portfoliosList"
                                :key="index"
                                @click="() => selectProduct(item)"
                                class="h-fit rounded cursor-pointer p-2 flex gap-x-2 border"
                                :class="selectedProduct.includes(item) ? 'bg-indigo-100 border-indigo-300' : 'bg-white hover:bg-gray-200 border-transparent'"
                            >
                                <img :src="item.imageSrc" class="w-16 h-16 object-cover" alt="" />
                                <div class="flex flex-col justify-between">
                                    <div>
                                        <div class="font-semibold leading-none mb-1">{{ item.name || 'no name' }}</div>
                                        <div class="text-xs text-gray-400 italic">{{ item.code || 'no code' }}</div>
                                    </div>
                                    <div class="text-xs text-gray-500">{{ item.price || 'no price' }}</div>
                                </div>
                            </div>
                            <div
                                v-show="isLoadingFetch"
                                v-for="(item, index) in 6"
                                :key="index"
                                class="rounded cursor-pointer w-full h-20 flex gap-x-2 border skeleton"
                            >
                            </div>
                        </div>

                        <TransitionGroup name="list" tag="ul" class="mt-2 flex flex-wrap gap-x-2 gap-y-1">
                            <li
                                v-for="product in selectedProduct"
                                :key="product.id"
                            >
                                <Tag
                                    :label="product.name"
                                    closeButton
                                    noHoverColor
                                    @onClose="() => {
                                        selectProduct(product)
                                    }"
                                />
                            </li>
                        </TransitionGroup>
                    </div>
                    
                    <div class="mt-4">
                        <Button
                            label="Add"
                            type="primary"
                            full
                        />
                        
                    </div>
                </div>
            </div>
        </div>
    </Modal>
</template>