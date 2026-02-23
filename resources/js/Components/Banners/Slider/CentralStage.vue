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


const removeHttps = (val?: string) => {
    return useRemoveHttps(val)
}

const getAlignClass = (align: string) => {
    if (align === 'left') return 'text-left'
    if (align === 'center') return 'text-center'
    if (align === 'right') return 'text-right'
    return 'text-center'
}
</script>

<template>
    <component :is="data?.linkOfText ? 'a' : 'div'"
        v-if="(data?.titles && data.titles.length) || data?.title || data?.subtitle"
        :href="`https://${removeHttps(data?.linkOfText)}`" target="_top" class="absolute inset-0 px-4 lg:px-6"
        :class="[{ 'left-0 text-left': data?.textAlign == 'left', 'right-0 text-right': data?.textAlign == 'right' }]"
        :style="`text-shadow : ${get(data, ['style', 'textShadow']) ? '2px 2px black;' : 'none'} `">
        <template v-if="data?.titles?.length">
            <!-- <div v-for="(item, index) in data.titles" :key="index" class="absolute" :class="[
                // Horizontal
                item.align === 'left' ? 'left-0 text-left' : '',
                item.align === 'center' ? 'left-1/2 -translate-x-1/2 text-center' : '',
                item.align === 'right' ? 'right-0 text-right' : '',

                // Vertical
                item.vertical === 'top' ? 'top-0' : '',
                item.vertical === 'middle' ? 'top-1/2 -translate-y-1/2' : '',
                item.vertical === 'bottom' ? 'bottom-0' : ''
            ]" :style="{ color: item.color }">
                <div class="leading-none font-bold text-[25px] lg:text-[44px]">
                    {{ item.text }}
                </div>
            </div> -->
            <div>
                <div v-for="(item, i) in data.titles.filter(t => t.vertical === 'top')" :key="'top-' + i"
                    :class="getAlignClass(item.align)" :style="{
                        color: item.color,
                        fontSize: item.fontSize + 'px',
                        transform: `translate(${item.offsetX || 0}px, ${item.offsetY || 0}px)`
                    }" class="font-bold leading-tight break-words">
                    {{ item.text }}
                </div>
            </div>

            <!-- MIDDLE -->
            <div class="flex-1 flex items-center">
                <div class="w-full">
                    <div v-for="(item, i) in data.titles.filter(t => t.vertical === 'middle')" :key="'middle-' + i"
                        :class="getAlignClass(item.align)" :style="{
                            color: item.color,
                            fontSize: item.fontSize + 'px',
                            transform: `translate(${item.offsetX || 0}px, ${item.offsetY || 0}px)`
                        }" class="font-bold leading-tight break-words">
                        {{ item.text }}
                    </div>
                </div>
            </div>

            <!-- BOTTOM -->
            <div>
                <div v-for="(item, i) in data.titles.filter(t => t.vertical === 'bottom')" :key="'bottom-' + i"
                    :class="getAlignClass(item.align)" :style="{
                        color: item.color,
                        fontSize: item.fontSize + 'px',
                        transform: `translate(${item.offsetX || 0}px, ${item.offsetY || 0}px)`
                    }" class="font-bold leading-tight break-words">
                    {{ item.text }}
                </div>
            </div>
        </template>
        <template v-else>
            <!-- Fallback for FontSize is normal size -->
            <div v-if="data?.title" :style="{ ...data?.style }"
                :class="[data?.style?.fontSize?.fontTitle ?? 'text-[25px] lg:text-[44px]']"
                class="text-gray-100 drop-shadow-md leading-none font-bold">{{ data?.title }}</div>
            <div v-if="data?.subtitle" :style="{ ...data?.style }"
                :class="[data?.style?.fontSize?.fontSubtitle ?? 'text-[12px] lg:text-[20px]']"
                class="text-gray-300 drop-shadow leading-none tracking-widest">{{ data?.subtitle }}</div>
        </template>
    </component>
</template>