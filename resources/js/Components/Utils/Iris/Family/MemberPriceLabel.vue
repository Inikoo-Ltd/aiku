<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'


</script>

<template>
    <div class="relative w-fit">
        <div class="bg-orange-400 rounded px-2 py-0.5 text-xxs w-fit text-white">{{ trans("Member Price") }}</div>
        <img src="/assets/promo/gr.png" alt="Gold Reward logo" class="absolute -right-9 -top-1 inline-block h-10 ml-1 align-middle" />
    </div>
</template>