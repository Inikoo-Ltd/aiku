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
    @apply bg-gray-50 border border-orange-500 border-b-4 rounded-md px-2 py-1 leading-3 text-xxs;
}
</style>
