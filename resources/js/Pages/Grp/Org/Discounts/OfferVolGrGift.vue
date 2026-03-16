<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { trans } from 'laravel-vue-i18n'

import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { inject } from 'vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import UnderConstruction from '@/Pages/Iris/Disclosure/UnderConstruction.vue'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Image from '@/Components/Image.vue'

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    url_master?: routeType
    currency_code: string

    data: {

    }
}>()


const layout = inject('layout', layoutStructure)
const locale = inject('locale', aikuLocaleStructure)

const getCategoryLink = (productCategory: {}) => {
    if (productCategory) {
        return route('grp.org.shops.show.catalogue.families.show', {
            organisation: route().params.organisation,
            shop: route().params.shop,
            family: productCategory.slug,
        })
    }
    return '#'
}

const routeProduct = (gift: { id: string }) => {
    switch (route().current()) {
        case 'grp.org.shops.show.discounts.campaigns.gift.show':
            return route('grp.org.shops.show.catalogue.products.current_products.show', {
                organisation: route().params.organisation,
                shop: route().params.shop,
                product: gift.slug
            })
        default:
            return '#'
    }
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle2>
            <div class="whitespace-nowrap">
                <Link v-if="url_master?.name" :href="route(url_master.name,url_master.parameters)" v-tooltip="trans('Go to Master Family section Offer GR/Vol')" class="mr-1 opacity-70 hover:opacity-100">
                    <FontAwesomeIcon
                        icon="fab fa-octopus-deploy"
                        color="#4B0082"
                        fixed-width
                    />
                </Link>
            </div>
        </template>
    </PageHeading>

    <!-- <div v-if="layout.app.environment === 'production'">
        <UnderConstruction />
    </div> -->

    <div class="px-8 py-6">
        <div class="flex justify-between gap-8">
            <!-- Section: Trigger -->
            <div class="max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
                <div class="bg-amber-100 font-bold border-b border-gray-200 text-amber-700 text-center mb-1 py-1">
                    Trigger
                </div>
                
                <!-- Trigger: Item Quantity -->
                <div v-if="(typeof data.offer.trigger_data.item_quantity !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                    <dt class="col-span-4 flex flex-col">
                        <div class="flex items-center leading-none">
                            <span>Item quantity</span>
                            <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'Minimum of quantity the item ordered'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                        </div>
                        <span v-tooltip="'Minimum of quantity the item ordered'" class="text-xs text-gray-400 truncate">
                            Minimum of quantity the item ordered
                        </span>
                    </dt>
            
                    <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                        <dd class="">
                            {{ data.offer.trigger_data.item_quantity }}
                        </dd>
                    </div>
                </div>

                <!-- Trigger: Item Quantity -->
                <div v-if="(typeof data.offer.trigger_data.min_amount !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                    <dt class="col-span-4 flex flex-col">
                        <div class="flex items-center leading-none">
                            <span>Order amount</span>
                            <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'Minimum of amount of the order'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                        </div>
                        <span v-tooltip="'Minimum of amount of the order'" class="text-xs text-gray-400 truncate">
                            Minimum of amount of the order
                        </span>
                    </dt>
            
                    <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                        <dd class="">
                            {{ locale.currencyFormat(currency_code, data.offer.trigger_data.min_amount) }}
                        </dd>
                    </div>
                </div>

                <!-- Trigger: Item Quantity -->
                <div v-if="(typeof data.offer.trigger_data.order_number !== 'undefined')" class="mb-2 grid grid-cols-7 gap-x-4 items-center justify-between">
                    <dt class="col-span-4 flex flex-col">
                        <div class="flex items-center leading-none">
                            <span>Minimum order</span>
                            <FontAwesomeIcon icon='fal fa-question-circle' v-tooltip="'Minimum order required to activate the discount'" class='ml-1 cursor-pointer text-gray-400 hover:text-gray-500' fixed-width aria-hidden='true' />
                        </div>
                        <span v-tooltip="`The order count required to activate the discount (e.g., '7' is mean their 7th order)`" class="text-xs text-gray-400 truncate">
                            The order count required to activate the discount (e.g., 7 = 7th order)
                        </span>
                    </dt>
            
                    <div class="relative col-span-3 justify-self-end font-medium overflow-hidden">
                        <dd class="">
                            {{ data.offer.trigger_data.order_number }}
                        </dd>
                    </div>
                </div>
            </div>

            <!-- Section: product as free gift -->
            <div class="ml-4 max-w-lg first:pt-0 pr-2 flex flex-col first:border-t-0 gap-y-1 pt-1 pb-1.5">
                <div class="bg-green-100 font-bold border-b border-gray-200 text-green-700 text-center mb-1 py-1">
                    Products as free gift ({{ data.products?.length }})
                </div>
                <div class="flex flex-col gap-2 w-96">
                    <div
                        v-for="gift in data.products"
                        :key="gift.id"
                        class="flex items-center gap-2"
                    >
                        <div class="w-14 aspect-square h-14 border border-gray-300">
                            <Image :src="gift.web_images_main?.thumbnail" :alt="gift.name" class="object-contain w-full h-full" />
                        </div>
                        <label :for="gift.id.toString()" class="">
                            <Link :href="routeProduct(gift)" class="secondaryLink font-bold text-sm">{{ gift.code }}</Link>
                            <span v-if="gift.default" v-tooltip="ctrans(`This product will auto selected in Customer's order`)" class="ml-2 bg-pink-500 text-white text-xs rounded-sm px-1">Default</span>
                            <br />
                            <span class="text-xs leading-4 inline-block opacity-80">{{ gift.name }}</span>
                        </label>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>