<script setup lang="ts">
defineProps<{
    offer: {
        products_triggers_label: string
    }
}>()
</script>

<template>
    <div class="offer-trigger-label">
        <span v-html="offer?.products_triggers_label ?? '-'" />
    </div>
</template>

<style scoped>
.offer-trigger-label {
    @apply bg-red-700 border border-red-900 text-gray-100 w-fit flex items-center rounded-sm px-1 py-0.5 text-[10px] sm:px-1.5 sm:py-1 sm:text-xxs md:px-2 md:py-1;
}
</style>
