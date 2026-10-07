<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faGift } from "@fal"

defineProps<{
    data: {
        label: string
    }
}>()
</script>

<template>
    <div
        v-tooltip="ctrans('First order bonus')"
        class="flex items-center gap-2.5 rounded-md border border-[#2a919e]/30 bg-[#2a919e]/5 px-3 py-2 text-xs text-gray-700">
        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-[#2a919e] text-white">
            <FontAwesomeIcon :icon="faGift" class="text-xs" fixed-width aria-hidden="true" />
        </span>
        <div class="leading-snug">{{ data?.label }}</div>
    </div>
</template>
