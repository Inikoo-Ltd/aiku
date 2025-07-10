<script setup lang="ts">
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject, ref, toRaw, watch, computed, onMounted } from 'vue'
import { Image as ImageTS } from '@/types/Image'
import { Popover } from 'primevue'

import Button from '@/Components/Elements/Buttons/Button.vue'
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'
import axios from 'axios'

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from '@far'
import { faEllipsisV } from '@fas'
import { faCheckDouble, faPlus } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faCheckDouble)


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
    // product: ProductResource
    products: ProductResource[]
    // category: {

    // }
    categoryId: number
    categoryHasChannels?: number[]
}>(), {
    categoryHasChannels: () => []
})

const emits = defineEmits<{
    (e: "refreshChannels"): void
}>()

const categoryHasChannelsList = ref(toRaw(props.categoryHasChannels))
const layout = inject('layout', retinaLayoutStructure)
const layoutChannelList = layout?.user?.customerSalesChannels || []


// Section: Add to all Portfolios
const isLoadingAllPortfolios = ref(false)
const onAddToAllPortfolios = (product: ProductResource) => {
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
                const keys = Object.keys(layoutChannelList).map(key => Number(key))
                categoryHasChannelsList.value = keys

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

// Section: Add to category to a specific channel
const isLoadingSpecificChannel = ref<number[]>([])
const onAddCategoryToChannel = (channel: {}) => {
    if (!channel || typeof channel.customer_sales_channel_id === 'undefined') {
        console.warn('Channel ID is undefined', channel)
        return
    }

    const channelId = Number(channel.customer_sales_channel_id)
    // console.log(`Adding product with ID ${product.id} to portfolio for channel ID ${channelId}`)

    router.post(
        route('iris.models.multi_channels.product_category.portfolio.store',
            {
                productCategory: props.categoryId,
            }
        ),
        {
            customer_sales_channel_ids: [channelId],
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSpecificChannel.value.push(channelId)
            },
            onSuccess: () => {
                if (!categoryHasChannelsList.value?.includes(channelId)) {
                    categoryHasChannelsList.value = [...categoryHasChannelsList.value, channelId]
                }

                // notify({
                //     title: trans("Success"),
                //     text: trans(`Added product ${product.name}`),
                //     type: "success"
                // })
            },
            onError: (errors) => {
                console.error(errors)
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to add to portfolio"),
                    type: "error"
                })
            },
            onFinish: () => {
                const idx = isLoadingSpecificChannel.value.indexOf(channelId)
                if (idx !== -1) {
                    isLoadingSpecificChannel.value.splice(idx, 1)
                }
            }
        }
    )
}


const _popover = ref()

const isCategoryExistInSomeChannel = computed(() => {
    const allChannelIds = Object.keys(layoutChannelList).map(Number)
    return allChannelIds.some(id => categoryHasChannelsList.value?.includes(id))
})

const isExistInAllChannel = computed(() => {
    const allChannelIds = Object.keys(layoutChannelList).map(Number)
    return allChannelIds.every(id => categoryHasChannelsList.value?.includes(id))
})


watch(() => props.categoryHasChannels, (newVal) => {
    if (Array.isArray(newVal)) {
        categoryHasChannelsList.value = [...newVal]
    } else {
        categoryHasChannelsList.value = []
    } 
})

// Method: to fetch the category existence in channels: [1111, 222, 333]
const isLoadingFetchExistenceChannels = ref(false)
// const categoryExistenceInChannels = ref<number[]>([])
const fetchProductExistInChannel = async () => {
    isLoadingFetchExistenceChannels.value = true
    try {
        const response = await axios.get(
            route(
                'iris.json.customer.product_category.channel_ids.index',
                {
                    customer: layout.iris?.customer?.id,
                    productCategory: props.categoryId,
                }
            )
        )

        if (response.status !== 200) {
            throw new Error('Failed to fetch product existence in channel')
        }

        console.log('Xxx product exist in channel response:', response.data)
        categoryHasChannelsList.value = response.data || []
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error.message,
            type: 'error'
        })
    } finally {
        isLoadingFetchExistenceChannels.value = false
    }
}

onMounted(() => {
    fetchProductExistInChannel()
})

</script>

<template>
    <!-- Bottom Section (fixed position in layout) -->
    <div v-if="layout?.iris?.is_logged_in" class="w-full">
        <div v-if="products.length" class="flex items-center gap-2 xmt-2">
            <div class="flex gap-2  w-full">
                <div class="w-full flex flex-nowrap relative">

                    <!-- <Button v-if="isCategoryExistInSomeChannel"
                        :label="isExistInAllChannel ? trans('Exist on all channels') : trans('Exist on some channels')"
                        type="tertiary"
                        disabled
                        :icon="'fal fa-check-double'"
                        class="border-none border-transparent"
                        :class="!isExistInAllChannel ? 'rounded-r-none' : ''"
                        full
                    />
                    <Button v-else
                        aclick="() => onAddToAllPortfolios(product)"
                        @click="(e) => _popover?.toggle(e)"
                        :label="trans('Add category to channel')"
                        :loading="isLoadingAllPortfolios"
                        :icon="faPlus"
                        :class="!isExistInAllChannel ? 'rounded-r-none' : ''"
                        class="border-none border-transparent"
                        full
                        xsize="l"
                        xdisabled
                        xstyle="border: 0px"
                    /> -->

                    <!-- Popup: list of channels -->
                    <Button v-if="!isExistInAllChannel"
                        @click="(e) => (_popover?.toggle(e), Object.keys(layoutChannelList).length ? null : emits('refreshChannels'))"
                        :icon="faEllipsisV"
                        label="Add category to channel"
                        :loading="!!isLoadingSpecificChannel.length"
                        xclass="!px-1 border-none border-transparent rounded-l-none h-full"
                    />

                    <Button
                        v-else
                        v-tooltip="trans('All products in this category exist on portfolios in all channels')"
                        :label="trans('This category exists in all channels')"
                        type="positive"
                        icon="fal fa-check-double"
                        noHover
                    />
                    
                    <div v-if="isLoadingFetchExistenceChannels" class="absolute inset-0">
                        <div class="h-full w-full skeleton rounded" />
                    </div>
                </div>
            </div>
        </div>

        <div v-else>
            <Button :label="trans('Out of stock')" type="tertiary" disabled full />
        </div>
    </div>

    <Link v-else href="/app/login" class="text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
        {{ trans("Login to add to your portfolio") }}
    </Link>

    <Popover ref="_popover">
        <div class="w-64 relative">
            <div class="text-sm mb-2">
                {{ trans("Add all products in this category to portfolios in channel") }}:
            </div>

            <div class="space-y-2">
                <Button v-for="channel in Object.values(layoutChannelList)"
                    :key="channel.customer_sales_channel_id"
                    xclick="() => onAddCategoryToChannel(product, { ...channel, id: Number(key) })"
                    @click="() => onAddCategoryToChannel(channel)"
                    type="tertiary"
                    xlabel="channel.customer_sales_channel_name + `${channel.platform_name}`"
                    full
                    :loading="isLoadingSpecificChannel.includes(channel.customer_sales_channel_id)">
                    <template #icon>
                        <FontAwesomeIcon v-if="categoryHasChannelsList.includes(channel.customer_sales_channel_id)" :icon="faCheck"
                            class="text-green-500" fixed-width aria-hidden="true" />
                    </template>
                    <template #label>
                        <div class="flex items-center gap-2">
                            <div v-tooltip="channel.platform_name" v-html="ChannelLogo(channel.platform_code)" class="h-4 w-4"></div>
                            {{ channel.customer_sales_channel_name || '-' }}
                            <span class="text-gray-500 text-xs">({{ channel.platform_name }})</span>
                        </div>
                    </template>
                </Button>
            </div>
        </div>
    </Popover>
</template>