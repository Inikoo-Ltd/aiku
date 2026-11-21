<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    progress: { total: number; done: number; open: number; milestones: number; milestones_done: number; tickets?: { done: number; total: number }; tasks?: { done: number; total: number }; percent: number | null; time_percent: number | null; week: number; weeks: number | null; days_left: number | null }
    startDate: string
    targetDate: string | null
}>()

const isBehind = computed(() => props.progress.percent !== null && props.progress.time_percent !== null && props.progress.percent + 10 < props.progress.time_percent)

const workRows = computed(() => [
    { label: ctrans("Tickets"), ...props.progress.tickets },
    { label: ctrans("Tasks"), ...props.progress.tasks },
].filter((row): row is { label: string; done: number; total: number } => (row.total ?? 0) > 0))

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
                <span>{{ progress.milestones > 0 ? ctrans("Milestones") : ctrans("Tickets done") }}</span>
                <span class="tabular-nums" :class="isBehind && 'font-semibold text-red-600'">{{ progress.milestones > 0 ? progress.milestones_done + " / " + progress.milestones : progress.done + " / " + (progress.done + progress.open) }}<template v-if="progress.percent !== null"> · {{ progress.percent }}%</template></span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full transition-all" :class="isBehind ? 'bg-red-400' : 'bg-green-500'" :style="{ width: (progress.percent ?? 0) + '%' }" />
            </div>
        </div>
        <div v-for="row in workRows" :key="row.label">
            <div class="mb-0.5 flex justify-between">
                <span>{{ row.label }}</span>
                <span class="tabular-nums">{{ row.done }} / {{ row.total }} · {{ Math.round((100 * row.done) / row.total) }}%</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-green-400 transition-all" :style="{ width: (100 * row.done) / row.total + '%' }" />
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
