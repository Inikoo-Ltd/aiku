<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';

defineProps<{
    offer: {
        products_triggers_label: string
    }
}>()
</script>

<template>
    <div class="offer-max-discount">
        <div>
            <span v-if="offer?.max_percentage_discount">
               - {{ Number(offer?.max_percentage_discount) * 100 }}% <strong> OFF </strong>
            </span>
            <span>{{ trans('Special Offers') }}</span>
        </div>
    </div>
</template>

<style scoped>
.offer-max-discount {
    @apply bg-red-700 border border-red-900 text-gray-100 w-fit flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1;
}
</style>
