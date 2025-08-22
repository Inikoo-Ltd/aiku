<template>
    <div class="bg-gray-100 h-fit flex items-center border border-gray-300 overflow-hidden rounded">
        <div class="grid justify-end items-center text-base font-normal text-gray-700">
            <div class="px-2 py-[1px] whitespace-nowrap flex gap-x-1.5 flex-nowrap">
                <span class="font-semibold tabular-nums">
                    <CountUp 
                        :endVal="total" 
                        :duration="1.2"
                        :scrollSpyOnce="true" 
                        :options="{
                            formattingFn: (number: number) => locale?.number ? locale.number(number) : number.toLocaleString()
                        }" 
                    />
                </span>
                <span class="font-light">
                    {{ total > 1 ? labelPlural : labelSingular }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { inject } from 'vue'
import CountUp from 'vue-countup-v3'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'

defineProps<{
    total: number
    labelSingular: string
    labelPlural: string
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>