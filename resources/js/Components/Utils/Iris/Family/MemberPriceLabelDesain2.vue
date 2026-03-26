<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

const layout = inject('layout', retinaLayoutStructure)

const props = defineProps<{
    offer?: {
        allowances?: {
            percentage_off?: number
        }[]
    }
}>()
</script>

<template>
    <div class="inline-flex items-center gap-1">
    
        <img
            :src="`/assets/promo/gr-${layout.retina.organisation}.png`"
            alt="Gold Reward logo"
            class="pointer-events-none
                   h-5 sm:h-6 md:h-6 lg:h-7 xl:h-7 2xl:h-8"
        />
    
        <div
            class="background-primary flex items-center gap-1 rounded whitespace-nowrap leading-none text-white
                   px-1.5 py-[2px]
                   text-[9px] sm:text-[10px] md:text-[11px] lg:text-[12px] xl:text-[13px] 2xl:text-[14px]
                   2xl:px-2 2xl:py-[5px]"
        >
            <span>{{ trans("Member Price") }}</span>
    
            <span
                v-if="offer?.allowances?.[0]?.percentage_off"
                class="opacity-90
                       text-[8px] sm:text-[9px] md:text-[10px] lg:text-[11px] xl:text-[12px] 2xl:text-[13px]"
            >
                {{ offer.allowances[0].percentage_off * 100 }}% {{ trans("OFF") }}
            </span>
        </div>
    
    </div>
</template>