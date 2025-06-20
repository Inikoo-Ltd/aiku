<script setup lang="ts">
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject, ref } from 'vue'
import { Image as ImageTS } from '@/types/Image'
import { Popover } from 'primevue'

import { faCheck } from '@far'
import { faPlus, faVial } from '@fal'
import { faCircle, faStar, faHeart as fasHeart, faEllipsisV } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'


interface ProductResource {
    id: number
    name: string
    code: string
    image?: {
        source: ImageTS
    }
    rpp?: number
    unit: string
    stock: number
    rating: number
    price: number
    url: string | null
    units: number
    bestseller?: boolean
    is_favourite?: boolean
}

const props = withDefaults(defineProps<{
    product: ProductResource
    productHasProtofolio?: Array<number>
}>(), {
    productHasProtofolio: () => []
})

const emits = defineEmits<{
    (e: "refreshChannels"): void
}>()

const layout = inject('layout', retinaLayoutStructure)
const channelList = layout?.user?.customerSalesChannels || []
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
                product.is_exist_in_all_channel = true
                notify({
                    title: trans("Success"),
                    text: trans("Added to all portfolios"),
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
                product.exist_in_portfolios_channel?.push(channel.id)
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

const _popover = ref()

</script>

<template>
    <!-- Bottom Section (fixed position in layout) -->
    <div v-if="layout.iris.is_logged_in" class="w-full">
        <div v-if="product.stock > 0" class="flex items-center gap-2 mt-2">
            <div class="flex gap-2  w-full">
                <!-- Add to Portfolio (90%) -->
                <!-- <button 
                    class="flex items-center justify-center gap-2 bg-gray-800 hover:bg-gray-900 text-white rounded px-4 py-2 text-sm font-semibold w-[90%] transition">
                    <LoadingIcon v-if="isLoading" class="text-white" />
                    <FontAwesomeIcon v-else :icon="faPlus" class="text-base" />
                    Add to Portfolio
                </button> -->
 
                
             
                <div class="w-full flex flex-nowrap relative">
                    <Button v-if="Object.keys(channelList).length == Object.keys(productHasProtofolio).length"
                        label="Exist on all Portfolios" type="tertiary" disabled
                        class="border-none border-transparent rounded-r-none" full />
                    <Button v-else @click="() => onAddToAllPortfolios(product)" label="Add to all Portfolios"
                        :loading="isLoadingAllPortfolios" :icon="faPlus"
                        class="border-none border-transparent rounded-r-none" full size="l" style="border: 0px" />

                    <Button
                        @click="(e) => (_popover?.toggle(e), Object.keys(channelList).length ? null : emits('refreshChannels'))"
                        :icon="faEllipsisV" :loading="!!isLoadingSpecificChannel.length"
                        class="!px-1 border-none border-transparent rounded-l-none h-full" />

                    <Popover ref="_popover">
                        <div class="w-64 relative">
                            <div class="text-sm mb-2">
                                {{ trans("Add product to a specific channel") }}:
                            </div>

                            <div class="space-y-2">
                                <Button v-for="[key, channel] in Object.entries(channelList)"
                                    :key="channel.customer_sales_channel_id"
                                    @click="() => onAddPortfoliosSpecificChannel(product, channel)" type="tertiary"
                                    :label="channel.platform_name" full
                                    :loading="isLoadingSpecificChannel.includes(channel.customer_sales_channel_id)">
                                    <template #icon>
                                        <FontAwesomeIcon v-if="productHasProtofolio.includes(Number(key))" :icon="faCheck"
                                            class="text-green-500" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                            </div>

                            <!-- <div @click="() => emits('refreshChannels')" class="w-fit mx-auto mt-2 text-center text-xs hover:underline cursor-pointer text-gray-500 hover:text-gray-600">
                                {{ trans("Refresh list channels") }}
                            </div> -->
                        </div>
                    </Popover>

                </div>

                <!-- Buy a Sample (10%) -->
                <!-- <button v-tooltip="'Buy  sample'"
                    class="flex items-center justify-center border border-gray-800 text-gray-800 hover:bg-gray-800 hover:text-white rounded p-2 text-sm font-semibold w-[10%] transition">
                    <FontAwesomeIcon :icon="faVial" class="text-sm" />
                </button> -->
            </div>
        </div>
        <div v-else>
            <Button label="Out of stock" type="tertiary" disabled full />
        </div>
    </div>

    <Link v-else href="app/login" class="text-center border border-gray-200 text-sm py-2 rounded text-gray-600">
    {{ trans("Login to add to your portfolio") }}
    </Link>
</template>