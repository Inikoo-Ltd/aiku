<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 24 Jul 2023 12:40:53 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { get } from 'lodash-es'
import { useRemoveHttps } from '@/Composables/useRemoveHttps'
import { CentralStageData } from '@/types/BannerWorkshop'

const props = defineProps<{
    data?: CentralStageData
}>()

</script>

<template>
    <component :is="data?.linkOfText ? 'a' : 'div'" v-if="data?.title || data?.subtitle" :href="`https://${useRemoveHttps(data?.linkOfText)}`" target="_top" class="absolute px-4 lg:px-6" :class="[{ 'left-0 text-left': data?.textAlign == 'left', 'right-0 text-right': data?.textAlign == 'right' }]" :style="`text-shadow : ${get(data,['style','textShadow']) ? '2px 2px black;' : 'none'} `">
        <!-- Fallback for FontSize is normal size -->
        <div v-if="data?.title" :style="{ ...data?.style }" :class="[data?.style?.fontSize?.fontTitle ?? 'text-[25px] lg:text-[44px]']" class="text-gray-100 drop-shadow-md leading-none font-bold">{{ data?.title }}</div>
        <div v-if="data?.subtitle" :style="{...data?.style}" :class="[data?.style?.fontSize?.fontSubtitle ?? 'text-[12px] lg:text-[20px]']" class="text-gray-300 drop-shadow leading-none tracking-widest">{{ data?.subtitle }}</div>
    </component>
</template>