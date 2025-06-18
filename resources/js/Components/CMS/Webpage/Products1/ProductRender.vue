<script setup lang="ts">
import Image from '@/Components/Image.vue'
import { useLocaleStore } from "@/Stores/locale"
import { inject, ref } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faHeart } from '@far'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Popover from '@/Components/Popover.vue'
import { faCheck } from '@far'
import { faPlus, faVial } from '@fal'
import { faCircle, faStar, faHeart as fasHeart, faEllipsisV } from '@fas'

const layout = inject('layout', retinaLayoutStructure)

const locale = useLocaleStore()

interface ProductResource {
    id: number
    name: string
    code: string
    image?: { source: string }
    currency_code: string
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    units: number
    bestseller?: boolean
    is_favourite?: boolean
    exist_in_portfolios_channel: number[]
}

const props = defineProps<{
    product: ProductResource
    channels: {
        isLoading: boolean
        list: {}[]
    }
}>()

const emits = defineEmits<{
    (e: "refreshChannels"): void
}>()

// Section: Add to all Portfolios
const isLoadingAllPortfolios = ref(false)
const onAddToAllPortfolios = (product: ProductResource) => {
    // Emit an event or call a method to handle adding the product to the portfolio
    console.log(`Adding product with ID ${product.name} to portfolio`)
    
    // Section: Submit
    router.post(
        route('iris.models.all_channels.portfolio.store'),
        {
            item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingAllPortfolios.value = true
            },
            onSuccess: () => {
                product.exist_in_portfolios_channel = props.channels.list.map(channel => channel.id)
                notify({
                    title: trans("Success"),
                    text: trans("Added to portfolio"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add to portfolio"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingAllPortfolios.value = false
            },
        }
    )
}

// Section: Add to specific Portfolios channel
const isLoadingSpecificChannel = ref([])
const onAddPortfoliosSpecificChannel = (product: ProductResource, channel: {}) => {
    console.log(`Adding product with ID ${product.id} to portfolio`)
    
    router.post(
        route('iris.models.multi_channels.portfolio.store'),
        {
            customer_sales_channel_ids: [channel.id],
            item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSpecificChannel.value.push(channel.id)
            },
            onSuccess: () => {
                product.exist_in_portfolios_channel.push(channel.id)
                notify({
                    title: trans("Success"),
                    text: `Added product ${product.name} to channel ${channel.name ?? channel.reference}`,
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add to portfolio"),
                    type: "error"
                })
            },
            onFinish: () => {
                if (isLoadingSpecificChannel.value.includes(channel.id)) {
                    isLoadingSpecificChannel.value.splice(
                        isLoadingSpecificChannel.value.indexOf(channel.id), 1
                    )
                }
            },
        }
    )
}


// Section: Add to all Portfolios
const isLoadingFavourite = ref(false)
const onAddFavourite = (product: {}) => {
    // Emit an event or call a method to handle adding the product to the portfolio
    console.log(`Adding product with ID ${product.name} to portfolio`)
    

    product.is_favourite = !product.is_favourite
    isLoadingFavourite.value = true
    setTimeout(() => {
        isLoadingFavourite.value = false
    }, 200)

    // Section: Submit
    // router.post(
    //     route('iris.models.all_channels.portfolio.store'),
    //     {
    //         item_id: [productId]
    //     },
    //     {
    //         preserveScroll: true,
    //         preserveState: true,
    //         onStart: () => { 
    //             isLoadingFavourite.value = true
    //         },
    //         onSuccess: () => {
    //             notify({
    //                 title: trans("Success"),
    //                 text: trans("Added to portfolio"),
    //                 type: "success"
    //             })
    //         },
    //         onError: errors => {
    //             notify({
    //                 title: trans("Something went wrong"),
    //                 text: trans("Failed to add to portfolio"),
    //                 type: "error"
    //             })
    //         },
    //         onFinish: () => {
    //             isLoadingFavourite.value = false
    //         },
    //     }
    // )
}
</script>

<template>
    <div class="relative flex flex-col justify-between h-full bg-white">

        <!-- Top Section -->
        <div>
            <!-- {{ product.currency_code }} -->
            <!-- Bestseller Badge -->
            <div v-if="product.bestseller"
                class="absolute top-2 left-2 bg-white border border-black text-black text-xs font-bold px-2 py-0.5 rounded">
                BESTSELLER
            </div>

            <!-- Favorite Icon -->
            <div v-if="isLoadingFavourite" class="absolute top-2 right-2 text-gray-500 text-xl">
                <LoadingIcon />
            </div>
            <div v-else @click="() => onAddFavourite(product)" class="cursor-pointer absolute top-2 right-2 group text-xl ">
                <FontAwesomeIcon v-if="product.is_favourite" :icon="fasHeart" fixed-width class="text-pink-500" />
                <FontAwesomeIcon v-else :icon="faHeart" fixed-width class="text-gray-400 group-hover:text-pink-400" />
            </div>

            <!-- Product Image -->
            <div class="w-full h-64 mb-3 rounded">
                <Image :src="product.image?.source" alt="product image" :imageCover="true"
                    :style="{ objectFit: 'contain' }" />
            </div>

            <!-- Title -->
            <div class="font-medium text-sm mb-1">{{ product.name }}</div>

            <!-- SKU and RRP -->
            <div class="flex justify-between text-xs text-gray-600 mb-1 capitalize">
                <span>{{ product?.code }}</span>
                <span>
                    RRP: {{ locale.currencyFormat(product?.currency_code, (product.rpp || 0)) }}/ {{ product.unit }}
                </span>
            </div>

            <!-- Rating and Stock -->
            <div class="flex justify-between items-center text-xs mb-2">
                <div class="flex items-center gap-1" :class="product.stock > 0 ? 'text-green-600' : 'text-red-600'">
                    <FontAwesomeIcon :icon="faCircle" class="text-[8px]" />
                    <span>({{ product.stock > 0 ? product.stock : 0 }})</span>
                </div>
                <div class="flex items-center space-x-[1px] text-gray-500">
                    <FontAwesomeIcon v-for="i in 5" :key="i" :class="i <= product.rating ? 'fas' : 'far'" :icon="faStar"
                        class="text-xs" />
                    <span class="ml-1">5</span>
                </div>
            </div>

            <!-- Prices -->
            <div class="mb-3">
                <div class="flex justify-between text-sm font-semibold">
                    <span>{{ locale.currencyFormat(product?.currency_code, product.price) }}</span>
                    <span class="text-xs">({{ locale.number(product.units) }}/{{ product.unit }})</span>
                </div>
            </div>
        </div>
        
        <!-- Bottom Section (fixed position in layout) -->
        <div v-if="layout.iris.is_logged_in">
            <div v-if="product.stock > 1" class="flex items-center gap-2 mt-2">
                <div class="flex gap-2  w-full">
                    <!-- Add to Portfolio (90%) -->
                    <!-- <button 
                        class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                        <LoadingIcon v-if="isLoading" class="text-white" />
                        <FontAwesomeIcon v-else :icon="faPlus" class="text-base" />
                        Add to Portfolio
                    </button> -->

                    <div class="w-full flex flex-nowrap relative">
                        <Button
                            v-if="product.is_exist_on_all_portfolios"
                            label="Exist on all Portfolios"
                            type="tertiary"
                            disabled
                            class="border-none border-transparent rounded-r-none"
                            full
                        />
                        <Button
                            v-else
                            @click="() => onAddToAllPortfolios(product)"
                            label="Add to all Portfolios"
                            :loading="isLoadingAllPortfolios"
                            :icon="faPlus"
                            class="border-none border-transparent rounded-r-none"
                            full
                            style="border: 0px"
                        />
                        <Popover position="bottom-full left-full -translate-y-2 -translate-x-3" >
                            <template #button="{ open, close}">
                                <Button
                                    @click="() => channels.list?.length ? null : emits('refreshChannels')"
                                    :icon="faEllipsisV"
                                    :loading="!!isLoadingSpecificChannel.length"
                                    class="!px-1 border-none border-transparent rounded-l-none"
                                />
                            </template>

                            <template #content>
                                <div class="w-64 relative">
                                    <div class="text-sm mb-2">
                                        {{ trans("Add product to a specific channel") }}:
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <Button
                                            v-for="channel in channels.list"
                                            @click="() => onAddPortfoliosSpecificChannel(product, channel)"
                                            type="tertiary"
                                            :label="channel.name ?? channel.reference"
                                            full
                                            :loading="isLoadingSpecificChannel.includes(channel.id)"
                                        >
                                            <template #icon>
                                                <FontAwesomeIcon v-if="product.exist_in_portfolios_channel.includes(channel.id)" :icon="faCheck" class="text-green-500" fixed-width aria-hidden="true" />
                                            </template>
                                        </Button>
                                    </div>

                                    <div @click="() => emits('refreshChannels')" class="w-fit mx-auto mt-2 text-center text-xs hover:underline cursor-pointer text-gray-500 hover:text-gray-600">
                                        {{ trans("Refresh list channels") }}
                                    </div>

                                    <div v-if="channels.isLoading" class="absolute inset-0 bg-black/20 text-4xl flex items-center justify-center">
                                        <LoadingIcon class="text-white" />
                                    </div>
                                </div>
                            </template>
                        </Popover>
                    </div>

                    <!-- Buy a Sample (10%) -->
                    <!-- <button v-tooltip="'Buy  sample'"
                        class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                        <FontAwesomeIcon :icon="faVial" class="text-sm" />
                    </button> -->
                </div>
            </div>
            <div v-else class="mt-2">
                <button
                    class="flex items-center justify-center gap-2 bg-gray-300 text-white rounded px-4 py-2 text-sm font-semibold w-[100%] transition"
                    disabled>
                    Out of Stock
                </button>
            </div>
        </div>

        <Link v-else href="app/login" class="text-center border border-gray-200 text-sm py-2 rounded text-gray-600">
            {{ trans("Login to add to your portfolio") }}
        </Link>
    </div>
</template>
