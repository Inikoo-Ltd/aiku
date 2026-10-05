<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    progress: { total: number; done: number; open: number; percent: number | null; time_percent: number | null; week: number; weeks: number | null; days_left: number | null }
    startDate: string
    targetDate: string | null
}>()

const isBehind = computed(() => props.progress.percent !== null && props.progress.time_percent !== null && props.progress.percent + 10 < props.progress.time_percent)

const timeLabel = computed(() => {
    const { week, weeks, days_left } = props.progress
    if (days_left === null) return ctrans("Week :week", { week })
    if (days_left < 0) return ctrans(":days days past target", { days: -days_left })
    return ctrans("Week :week of :weeks · :days days left", { week: Math.min(week, weeks ?? week), weeks: weeks ?? "?", days: days_left })
})
</script>

<template>
    <div class="space-y-2 text-xs text-gray-500">
        <div>
            <div class="mb-0.5 flex justify-between">
                <span>{{ ctrans("Tickets done") }}</span>
                <span class="tabular-nums" :class="isBehind && 'font-semibold text-red-600'">{{ progress.done }} / {{ progress.done + progress.open }}<template v-if="progress.percent !== null"> · {{ progress.percent }}%</template></span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full transition-all" :class="isBehind ? 'bg-red-400' : 'bg-green-500'" :style="{ width: (progress.percent ?? 0) + '%' }" />
            </div>
        </div>
        <div v-if="progress.time_percent !== null">
            <div class="mb-0.5 flex justify-between">
                <span>{{ ctrans("Time used") }}</span>
                <span class="tabular-nums">{{ timeLabel }} · {{ progress.time_percent }}%</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-gray-400 transition-all" :style="{ width: progress.time_percent + '%' }" />
            </div>
        </div>
        <p v-else class="tabular-nums">{{ timeLabel }} · {{ ctrans("no target date") }}</p>
        <p class="tabular-nums">{{ startDate }} → {{ targetDate ?? "—" }}</p>
    </div>
</template>
