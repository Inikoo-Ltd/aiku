<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 24 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime, useRangeFromNow } from "@/Composables/useFormatTime"

const props = defineProps<{
    date?: string | null
    compact?: boolean
}>()

const fullDate = computed(() => (props.date ? useFormatTime(props.date, { formatTime: "short-datetime" }) : ""))
const sinceNow = computed(() => (props.date ? useRangeFromNow(props.date, { addSuffix: true }) : ""))
</script>

<template>
    <span v-if="!date" class="text-gray-400">-</span>
    <span
        v-else-if="compact"
        v-tooltip="ctrans('Waiting since :date', { date: fullDate })"
        class="whitespace-nowrap text-xs text-gray-500"
    >
        {{ sinceNow }}
    </span>
    <div v-else class="whitespace-nowrap">
        <div class="text-sm">{{ fullDate }}</div>
        <div class="text-xs text-gray-500">{{ sinceNow }}</div>
    </div>
</template>
