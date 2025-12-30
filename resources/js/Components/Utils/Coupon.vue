<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { inject } from 'vue'

const props = defineProps<{
    first_order_bonus: {
        name: string
        state: string
        status: string
        trigger_data: {
            min_amount: number
            order_number: 1
        }
        duration: string  // 'permanent'
        created_at: string
        end_at: string
    }[]
    currency_code: string
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div class="p-8 flex flex-wrap gap-2">
        <section v-for="offer in first_order_bonus" class="card w-96 bg-gradient-to-l from-teal-300 to-teal-500 text-white">
            <div class="text-center  text-base w-[88px] flex flex-col justify-center px-1">
                {{ locale.currencyFormat(currency_code, offer.trigger_data?.min_amount ?? 0) }}
                <span class="text-xs">Min. quantity: {{ offer.trigger_data?.order_number ?? '-' }}</span>
            </div>
            <div class="card-right">
                <p class="card-info">{{ offer.name }}</p>
                <strong class="text-xxs italic font-normal opacity-70">{{ useFormatTime(offer.created_at)}} - {{ offer.end_at ? useFormatTime(offer.end_at) : 'Not described' }}</strong>
            </div>
        </section>
    </div>
</template>

<style lang="scss" scoped>
.card{
    display: flex;
    align-items: center;
    border-radius: 8px;
    -webkit-mask-image: radial-gradient(circle at 88px 4px, transparent 4px, red 4.5px), radial-gradient(closest-side circle at 50%, red 99%, transparent 100%);
    mask-image: radial-gradient(circle at 88px 4px, transparent 4px, red 4.5px), radial-gradient(closest-side circle at 50%, red 99%, transparent 100%);
    -webkit-mask-size: 100%, 2px 4px;
    mask-size: 100%, 2px 4px;
    -webkit-mask-position: 0 -4px, 87px;
    mask-position: 0 -4px, 87px;
    mask-repeat: repeat, repeat-y;
    -webkit-mask-repeat: repeat, repeat-y;
    -webkit-mask-composite: source-out;
    mask-composite: subtract;
}

.card-right{
    padding: 16px 12px;
    display: flex;
    flex: 1;
    flex-direction: column;
}
.card-info{
    margin: 0;
    font-size: 14px;
    line-height: 20px;
}
</style>