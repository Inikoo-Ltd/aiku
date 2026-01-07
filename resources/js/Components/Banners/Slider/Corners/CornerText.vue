<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 24 Jul 2023 12:14:58 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
// import { CornerData } from '@/types/BannerWorkshop'
import { useRemoveHttps } from '@/Composables/useRemoveHttps'

const props = defineProps<{
    data?: {
        title?: string
        linkOfText?: string
        subtitle?: string
        fontSize?: {
            fontSubtitle?: string
            fontTitle?: string
        }
        color?: string
    }
    swiperRef?: Element 
}>()

// console.log(props)

</script>

<template>
    <component :is="data?.linkOfText ? 'a' : 'div'" v-if="data?.title || data?.subtitle" :href="`https://${useRemoveHttps(data?.linkOfText)}`" target="_top">
        <div v-if="data?.title" class="pb-1.5 text-gray-100 drop-shadow-md leading-none" :class="data?.fontSize?.fontTitle ?? 'text-[25px] lg:text-[44px]' " :style="`color: ${data?.color}`">{{ data?.title }}</div>
        <div v-if="data?.subtitle" class="pb-1.5 text-gray-300 drop-shadow-sm text-base italic tracking-widest" :class="data?.fontSize?.fontSubtitle" :style="`color: ${data?.color}`">{{ data?.subtitle }}</div>
    </component>
</template>

