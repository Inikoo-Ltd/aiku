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
import { routeType } from '@/types/route'
import { urlLoginWithRedirect } from '@/Composables/urlLoginWithRedirect'
import { toInteger } from 'lodash-es'
import LabelComingSoon from './LabelComingSoon.vue'
import axios from 'axios'


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
    productHasPortfolio?: number[]
    routeToAllPortfolios?: routeType
    routeToSpecificChannel?: routeType
    buttonStyle?: object
    buttonStyleLogin?:object | undefined

}>(), {
    productHasPortfolio: () => [],
    routeToAllPortfolios : {
        name: 'iris.models.all_channels.portfolio.store',
        parameters: {}
    },
    routeToSpecificChannel : {
        name: 'iris.models.multi_channels.portfolio.store',
        parameters: {}
    }
})

const emits = defineEmits<{
    (e: "refreshChannels"): void
}>()

const productHasPortfolioList = ref(toRaw(props.productHasPortfolio))
const layout = inject('layout', retinaLayoutStructure)
const channelList = ref(layout?.user?.customerSalesChannels || [])
// Section: Add to all Portfolios
const isLoadingAllPortfolios = ref(false)
const onAddToAllPortfolios = async (product: ProductResource) => {
    isLoadingAllPortfolios.value = true

    // Luigi: event add to cart
    window?.dataLayer?.push({
        event: "add_to_cart",
        ecommerce: {
            currency: layout?.iris?.currency?.code,
            value: product.price,
            channel: 'all',
            items: [
                {
                    item_id: product?.luigi_identity,
                }
            ]
        }
    })

    try {
        const response = await axios.post(
            route(props.routeToAllPortfolios.name, props.routeToAllPortfolios.parameters),
            {
                item_id: [product.id]
            }
        )

        // Update portfolio list to include all channel IDs
        const keys = Object.keys(channelList.value).map(key => Number(key))
        productHasPortfolioList.value = keys

        notify({
            title: trans("Success"),
            text: trans("Added to all portfolios"),
            type: "success"
        })

        return response.data
    } catch (errors: any) {
        console.error(errors)
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to add to portfolio"),
            type: "error"
        })
    } finally {
        isLoadingAllPortfolios.value = false
    }
}
// Section: Add to a specific Portfolios channel
const isRecentlyAddPortfolio = ref(false)
const isLoadingSpecificChannel = ref([])
const onAddPortfoliosSpecificChannel = async (product: ProductResource, channel: any) => {
    if (!channel || typeof channel.id === 'undefined') {
        console.warn('Channel ID is undefined', channel)
        return
    }

    const channelId = Number(channel.id)
    console.log(`Adding product with ID ${product.id} to portfolio for channel ID ${channelId}`)

    // Start loading
    isLoadingSpecificChannel.value.push(channelId)

    // Luigi: event add to cart
    window?.dataLayer?.push({
        event: "add_to_cart",
        ecommerce: {
            currency: layout?.iris?.currency?.code,
            value: product.price,
            channel: channel?.platform_slug || null,
            items: [
                {
                    item_id: product?.luigi_identity,
                }
            ]
        }
    })

    try {
        const response = await axios.post(
            route(props.routeToSpecificChannel.name, props.routeToSpecificChannel.parameters),
            {
                customer_sales_channel_ids: [channelId],
                item_id: [product.id]
            }
        )

        // Update portfolio list if not already included
        if (!productHasPortfolioList.value?.includes(channelId)) {
            productHasPortfolioList.value = [...productHasPortfolioList.value, channelId]
        }

        notify({
            title: trans("Success"),
            text: trans(`Added product ${product.name}`),
            type: "success"
        })

        return response.data
    } catch (errors: any) {
        console.error(errors)
        notify({
            title: trans("Something went wrong"),
            text: trans("Failed to add to portfolio"),
            type: "error"
        })
    } finally {
        // Remove loading state
        const idx = isLoadingSpecificChannel.value.indexOf(channelId)
        if (idx !== -1) {
            isLoadingSpecificChannel.value.splice(idx, 1)
        }
    }
}

const _popover = ref()

const isInAllChannels = computed(() => {
  const allChannelIds = Object.keys(channelList.value).map(Number)
  return allChannelIds.some(id => productHasPortfolioList.value?.includes(toInteger(id)))
})

const CheckChannels = computed(() => {
  const allChannelIds = Object.keys(channelList.value).map(Number)
  return allChannelIds.every(id => productHasPortfolioList.value?.includes(toInteger(id)))
})


watch(() => props.productHasPortfolio, (newVal) => {
  if (Array.isArray(newVal)) {
    productHasPortfolioList.value = [...newVal]
  } else {
    productHasPortfolioList.value = []
  } 
})

watch(
  () => layout.iris,
  newVal => {
    channelList.value = layout?.user?.customerSalesChannels || []
  },
  { deep: true }
)

</script>

<template>
    <div v-if="layout?.iris?.is_logged_in" class="w-full">
        <div v-if="product.is_coming_soon">
            <LabelComingSoon v-if="product.is_coming_soon" :product="product" class="w-full inline-block text-center" />

        </div>

        <div v-else-if="product.state != 'discontinued'" class="flex items-center gap-2 xmt-2">
            <div class="flex gap-2  w-full">
                <div class="w-full flex flex-nowrap relative">

                    <Button v-if="isInAllChannels"
                        :label="CheckChannels ? trans('Exist on all channels') : trans('Exist on some channels')"
                        type="tertiary"
                        disabled
                        class="border-none border-transparent"
                        :class="!CheckChannels ? 'rounded-r-none' : ''"
                        full
                        :iconRight="CheckChannels ? 'fal fa-check-double' : ''"
                    />
                    <Button v-else @click="() => onAddToAllPortfolios(product)" :label="trans('Add to all channels')"
                        :loading="isLoadingAllPortfolios" :icon="faPlus" :class="!CheckChannels ? 'rounded-r-none' : ''"
                        class="border-none border-transparent" full   :injectStyle="buttonStyle"/>

                    <Button v-if="!CheckChannels"
                        @click="(e) => (_popover?.toggle(e), Object.keys(channelList).length ? null : emits('refreshChannels'))"
                        :icon="faEllipsisV" :loading="!!isLoadingSpecificChannel.length"
                        class="!px-1 border-none border-transparent rounded-l-none h-full"  :injectStyle="buttonStyle"/>

                    <Popover  ref="_popover">
                        <div class="w-64 relative">
                            <div class="text-sm mb-2">
                                {{ trans("Add product to a specific channel") }}:
                            </div>

                            <div class="space-y-2">
                                <Button v-for="[key, channel] in Object.entries(channelList)"
                                    :key="channel.customer_sales_channel_id"
                                    @click="() => onAddPortfoliosSpecificChannel(product, { ...channel, id: Number(key) })" type="tertiary"
                                    xlabel="channel.customer_sales_channel_name + `${channel.platform_name}`"
                                    full
                                    :loading="isLoadingSpecificChannel.includes(channel.customer_sales_channel_id)" >
                                    <template #icon>
                                        <FontAwesomeIcon v-if="productHasPortfolioList.includes(Number(key))" :icon="faCheck"
                                            class="text-green-500" fixed-width aria-hidden="true" />
                                    </template>
                                    <template #label>
                                        <div class="flex items-center gap-2">
                                            <img
                                                :src="`/assets/channel_logo/${channel.platform_code}.svg`"
                                                class="h-4 w-4"
                                                :alt="channel.platform_name"
                                                v-tooltip="channel.platform_name"
                                            />
                                            {{ channel.customer_sales_channel_name || '-' }}
                                            <span class="text-gray-500 text-xs">({{ channel.platform_name }})</span>
                                        </div>
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </Popover>
                </div>
            </div>
        </div>

        <div v-else>
            <Button :label="trans('Product Discontinued')" type="tertiary" disabled full />
        </div>
    </div>

    <a  v-else  :href="urlLoginWithRedirect()" class="w-full">
        <Button label="Login / Register to Start" full :injectStyle="buttonStyleLogin"/>
    </a>
</template>