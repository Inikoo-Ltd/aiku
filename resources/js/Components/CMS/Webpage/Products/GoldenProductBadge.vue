<script setup lang="ts">
import { faCrown } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
</script>

<template>
    <div class="golden-product-badge" v-tooltip="trans('Golden product')">
        <FontAwesomeIcon :icon="faCrown" class="w-3 h-3 text-white drop-shadow-sm" />
    </div>
</template>

<style scoped>
.golden-product-badge {
    @apply inline-flex items-center justify-center w-6 h-6 rounded-full ring-1 ring-white/60 shadow;
    background: linear-gradient(145deg, #f9e08a 0%, #e0b530 45%, #b8860b 100%);
}
</style>
