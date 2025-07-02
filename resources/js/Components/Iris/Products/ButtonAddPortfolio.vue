<script setup lang="ts">
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { Link, router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject, ref, toRaw, watch, computed } from 'vue'
import { Image as ImageTS } from '@/types/Image'
import { Popover } from 'primevue'

import { faCheck } from '@far'
import { faPlus } from '@fal'
import { faEllipsisV } from '@fas'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'


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
    productHasPortfolio?: Array<number>
}>(), {
    productHasPortfolio: () => []
})

const emits = defineEmits<{
    (e: "refreshChannels"): void
}>()

const productHasPortfolioList = ref(toRaw(props.productHasPortfolio))
const layout = inject('layout', retinaLayoutStructure)
const channelList = layout?.user?.customerSalesChannels || []
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
                const keys = Object.keys(channelList).map(key => Number(key))
                productHasPortfolioList.value = keys

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

// Section: Add to a specific Portfolios channel
const isLoadingSpecificChannel = ref([])
const onAddPortfoliosSpecificChannel = (product: ProductResource, channel: any) => {
    if (!channel || typeof channel.id === 'undefined') {
        console.warn('Channel ID is undefined', channel)
        return
    }

    const channelId = Number(channel.id)
    console.log(`Adding product with ID ${product.id} to portfolio for channel ID ${channelId}`)

    router.post(
        route('iris.models.multi_channels.portfolio.store'),
        {
            customer_sales_channel_ids: [channelId],
            item_id: [product.id]
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                isLoadingSpecificChannel.value.push(channelId)
            },
            onSuccess: () => {
                if (!productHasPortfolioList.value?.includes(channelId)) {
                    productHasPortfolioList.value = [...productHasPortfolioList.value, channelId]
                }

                notify({
                    title: trans("Success"),
                    text: trans(`Added product ${product.name}`),
                    type: "success"
                })
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

const isInAllChannels = computed(() => {
  const allChannelIds = Object.keys(channelList).map(Number)
  return allChannelIds.some(id => productHasPortfolioList.value?.includes(id))
})

const CheckChannels = computed(() => {
  const allChannelIds = Object.keys(channelList).map(Number)
  return allChannelIds.every(id => productHasPortfolioList.value?.includes(id))
})


watch(() => props.productHasPortfolio, (newVal) => {
  if (Array.isArray(newVal)) {
    productHasPortfolioList.value = [...newVal]
  } else {
    productHasPortfolioList.value = []
  } 
})

</script>

<template>
    <!-- Bottom Section (fixed position in layout) -->
    <div v-if="layout?.iris?.is_logged_in" class="w-full">
        <div v-if="product.stock > 0" class="flex items-center gap-2 mt-2">
            <div class="flex gap-2  w-full">
                <div class="w-full flex flex-nowrap relative">
         
                    <Button v-if="isInAllChannels"
                        :label="CheckChannels ? 'Exist on all channels' : 'Exist on some channels'" type="tertiary" disabled
                        class="border-none border-transparent" :class="!CheckChannels ? 'rounded-r-none' : ''" full />
                    <Button v-else @click="() => onAddToAllPortfolios(product)" label="Add to all Portfolios"
                        :loading="isLoadingAllPortfolios" :icon="faPlus" :class="!CheckChannels ? 'rounded-r-none' : ''"
                        class="border-none border-transparent" full size="l" style="border: 0px" />

                    <Button v-if="!CheckChannels"
                        @click="(e) => (_popover?.toggle(e), Object.keys(channelList).length ? null : emits('refreshChannels'))"
                        :icon="faEllipsisV" :loading="!!isLoadingSpecificChannel.length"
                        class="!px-1 border-none border-transparent rounded-l-none h-full" />

                    <Popover  ref="_popover">
                        <div class="w-64 relative">
                            <div class="text-sm mb-2">
                                {{ trans("Add product to a specific channel") }}:
                            </div>

                            <div class="space-y-2">
                                <Button v-for="[key, channel] in Object.entries(channelList)"
                                    :key="channel.customer_sales_channel_id"
                                    @click="() => onAddPortfoliosSpecificChannel(product, { ...channel, id: Number(key) })" type="tertiary"
                                    :label="channel.platform_name" full
                                    :loading="isLoadingSpecificChannel.includes(channel.customer_sales_channel_id)">
                                    <template #icon>
                                        <FontAwesomeIcon v-if="productHasPortfolioList.includes(Number(key))" :icon="faCheck"
                                            class="text-green-500" fixed-width aria-hidden="true" />
                                    </template>
                                </Button>
                            </div>

                        </div>
                    </Popover>

                </div>


            </div>
        </div>
        <div v-else>
            <Button label="Out of stock" type="tertiary" disabled full />
        </div>
    </div>

    <Link v-else href="app/login" class="text-center border border-gray-200 text-sm px-3 py-2 rounded text-gray-600 w-full">
    {{ trans("Login to add to your portfolio") }}
    </Link>
</template>