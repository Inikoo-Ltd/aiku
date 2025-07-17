<script setup lang="ts">
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'
import { router } from '@inertiajs/vue3'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { inject, ref, toRaw, watch, computed } from 'vue'
import { Popover } from 'primevue'
import { faCheck } from '@far'
import { faPlus, faEllipsisV } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ChannelLogo } from '@/Composables/Icon/ChannelLogoSvg'

const props = withDefaults(defineProps<{
  productCategory?: Array<{ id: number, name: string }>
  productHasPortfolio?: number[]
}>(), {
  productCategory: () => [],
  productHasPortfolio: () => []
})

const emits = defineEmits<{
  (e: "refreshChannels"): void
}>()

const productHasPortfolioList = ref([...toRaw(props.productHasPortfolio)])
const layout = inject('layout',{})
const channelList = layout?.iris?.customer?.customer_sales_channels || {}
const isLoadingAllPortfolios = ref(false)
const isLoadingSpecificChannel = ref<number[]>([])
const _popover = ref()

watch(() => props.productHasPortfolio, (newVal) => {
  productHasPortfolioList.value = Array.isArray(newVal) ? [...newVal] : []
})

const isInAllChannels = computed(() => {
  const allIds = Object.keys(channelList).map(Number)
  return allIds.some(id => productHasPortfolioList.value.includes(id))
})

const CheckChannels = computed(() => {
  const allIds = Object.keys(channelList).map(Number)
  return allIds.every(id => productHasPortfolioList.value.includes(id))
})

// Add all products to all channels
const onAddToAllPortfolios = () => {
  const itemIds = props.productCategory.map(p => p.id)

  router.post(route('iris.models.all_channels.portfolio.store'), {
    item_id: itemIds
  }, {
    preserveScroll: true,
    preserveState: true,
    onStart: () => (isLoadingAllPortfolios.value = true),
    onSuccess: () => {
      const keys = Object.keys(channelList).map(Number)
      productHasPortfolioList.value = keys

      notify({
        title: trans("Success"),
        text: trans("Added to all portfolios"),
        type: "success"
      })
    },
    onError: () => {
      notify({
        title: trans("Something went wrong"),
        text: trans("Failed to add to portfolio"),
        type: "error"
      })
    },
    onFinish: () => (isLoadingAllPortfolios.value = false)
  })
}

// Add all products to a specific channel
const onAddPortfoliosSpecificChannel = (channel: any) => {
  if (!channel || typeof channel.id === 'undefined') return

  const channelId = Number(channel.id)
  const itemIds = props.productCategory.map(p => p.id)

  router.post(route('iris.models.multi_channels.portfolio.store'), {
    customer_sales_channel_ids: [channelId],
    item_id: itemIds
  }, {
    preserveScroll: true,
    preserveState: true,
    onStart: () => isLoadingSpecificChannel.value.push(channelId),
    onSuccess: () => {
      if (!productHasPortfolioList.value.includes(channelId)) {
        productHasPortfolioList.value.push(channelId)
      }

      notify({
        title: trans("Success"),
        text: trans(`Products added to ${channel.platform.platform_name}`),
        type: "success"
      })
    },
    onError: () => {
      notify({
        title: trans("Something went wrong"),
        text: trans("Failed to add to portfolio"),
        type: "error"
      })
    },
    onFinish: () => {
      const idx = isLoadingSpecificChannel.value.indexOf(channelId)
      if (idx !== -1) isLoadingSpecificChannel.value.splice(idx, 1)
    }
  })
}

console.log(layout)
</script>

<template>
    <div class="w-full">
        <div class="flex items-center gap-2 xmt-2">
            <div class="flex gap-2  w-full">
                <div class="w-full flex flex-nowrap relative">

                    <Button v-if="isInAllChannels"
                        :label="CheckChannels ? 'Exist on all channels' : 'Exist on some channels'" type="tertiary"
                        disabled class="border-none border-transparent" :class="!CheckChannels ? 'rounded-r-none' : ''"
                        full />
                    <Button v-else @click="() => onAddToAllPortfolios(product)" label="Add to all Portfolios"
                        :loading="isLoadingAllPortfolios" :icon="faPlus" :class="!CheckChannels ? 'rounded-r-none' : ''"
                        class="border-none border-transparent" full xsize="l" xstyle="border: 0px" />

                    <Button v-if="!CheckChannels"
                        @click="(e) => (_popover?.toggle(e), Object.keys(channelList).length ? null : emits('refreshChannels'))"
                        :icon="faEllipsisV" :loading="!!isLoadingSpecificChannel.length"
                        class="!px-1 border-none border-transparent rounded-l-none h-full" />

                    <Popover ref="_popover">
                        <div class="w-64 relative">
                            <div class="text-sm mb-2">
                                {{ trans("Add product to a specific channel") }}:
                            </div>

                            <div class="space-y-2">
                                <Button v-for="(channel,key) of channelList"
                                    :key="channel.platform.customer_sales_channel_id"
                                    @click="() => onAddPortfoliosSpecificChannel(product, { ...channel, id: Number(key) })"
                                    type="tertiary"
                                    xlabel="channel.customer_sales_channel_name + `${channel.platform_name}`" full
                                    :loading="isLoadingSpecificChannel.includes(channel.customer_sales_channel_id)">
                                    <template #icon>
                                        <FontAwesomeIcon v-if="productHasPortfolioList.includes(Number(key))"
                                            :icon="faCheck" class="text-green-500" fixed-width aria-hidden="true" />
                                    </template>
                                    <template #label>
                                        <div class="flex items-center gap-2">
                                            <div v-tooltip="channel.platform.platform_name"
                                                v-html="ChannelLogo(channel.platform_code)" class="h-4 w-4"></div>
                                                {{ channel.platform.customer_sales_channel_name || '-' }}
                                            <span class="text-gray-500 text-xs">({{ channel.platform.platform_name }})</span>
                                        </div>
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </Popover>
                </div>
            </div>
        </div>
    </div>
</template>