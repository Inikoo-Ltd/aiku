<script setup lang="ts">
import { inject } from 'vue';
import { retinaLayoutStructure } from '@/Composables/useRetinaLayoutStructure'

defineProps<{
    offer: {
        products_triggers_label: string
    }
}>()

const layout = inject("layout", retinaLayoutStructure)
</script>

<template>
    <div class="offer-trigger-label">
        <span v-html="offer?.products_triggers_label ?? '-'" />
    </div>
</template>

<style scoped>
.offer-trigger-label {
  @apply bg-gray-50 border border-b-4 rounded-md px-2 py-1 leading-3 text-xxs;
  border-color: var(--theme-color-4);
  color : var(--theme-color-4);
}
</style>