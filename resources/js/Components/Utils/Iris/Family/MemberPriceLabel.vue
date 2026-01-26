<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    offer?: {

    }
}>()
</script>

<template>
    <div class="relative w-fit">
        <div class="bg-orange-400 rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
        <img src="/assets/promo/gr.png" alt="Gold Reward logo" class="absolute -right-7 -top-0.5 inline-block h-7 ml-1 align-middle" />

        <span v-if="offer?.allowances?.[0]?.percentage_off" class="text-xs text-orange-500">
            {{ offer.allowances?.[0]?.percentage_off * 100 }}% {{ offer.allowances?.[0]?.class }}
        </span>
    </div>
</template>