<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue';
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

const layout = inject('layout', retinaLayoutStructure)

const props = defineProps<{
    offer?: {

    }
}>()


</script>

<template>
    <div class="relative w-fit">
        <div class="background-primary rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
        <img :src="`/assets/promo/gr-${layout.retina.organisation}.png`" alt="Gold Reward logo" class="absolute -right-7 -top-0.5 inline-block h-7 ml-1 align-middle" />

        <span v-if="offer?.allowances?.[0]?.percentage_off" class="text-xs text-primary">
            {{ offer.allowances?.[0]?.percentage_off * 100 }}% {{trans("discount")}}
        </span>
    </div>
</template>

<style>
/* .background-primary {
    background-color: var(--theme-color-4);
}

.text-primary {
    color: var(--theme-color-4);
} */

</style>