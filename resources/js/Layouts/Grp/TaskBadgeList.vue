<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { tasksRoute } from "@/Composables/useTasksRoute"
import type { TaskBadgeRow, TaskBadges } from "@/types/TaskBadges"

const props = defineProps<{
    badges: TaskBadges
    close: () => void
}>()

const rowHref = (row: TaskBadgeRow) => tasksRoute("list_all", { filter: row.filter, elements: { status: row.status } })

const recentTooltip = (item: { title: string; body: string }) => [item.title, item.body].filter(Boolean).join(' — ')

const todayTotal = computed(() => props.badges.today.done + props.badges.today.open)
const todayPercent = computed(() => (todayTotal.value ? Math.round((props.badges.today.done / todayTotal.value) * 100) : 0))
</script>

<template>
    <div class="w-full text-sm">
        <div class="mb-2 flex items-center justify-between">
            <span class="font-semibold">{{ ctrans("My tasks") }}</span>
            <Link :href="tasksRoute('index')" class="text-xs text-[--app-accent-strong] hover:underline" @click="close()">{{ ctrans("Open") }}</Link>
        </div>

        <div class="mb-2 rounded-md bg-cyan-50 px-2 py-1.5" v-tooltip="ctrans('Tasks you finished today, out of today’s done plus still open')">
            <div class="flex items-baseline justify-between text-xs">
                <span class="text-cyan-900">{{ ctrans("Today") }}</span>
                <span class="tabular-nums text-cyan-900">
                    <span class="font-semibold">{{ badges.today.done }}</span> / {{ todayTotal }}
                    <span class="ml-1 font-semibold">{{ todayPercent }}%</span>
                </span>
            </div>
            <span class="mt-1 flex h-1.5 w-full overflow-hidden rounded-full bg-white">
                <span class="bg-cyan-500 transition-all duration-300" :style="{ width: `${todayPercent}%` }" />
            </span>
        </div>

        <ul>
            <li v-for="(row, key) in badges.mine" :key="key">
                <Link :href="rowHref(row)" class="flex items-center justify-between gap-x-3 rounded px-1 py-1.5 hover:bg-gray-50" :class="row.count ? 'text-gray-800' : 'text-gray-400'" @click="close()">
                    <span>{{ row.label }}</span>
                    <span class="min-w-6 rounded-full px-1.5 text-center text-xs font-semibold tabular-nums"
                        :class="row.count ? (key === 'overdue' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') : 'text-gray-300'">
                        {{ row.count }}
                    </span>
                </Link>
            </li>
        </ul>

        <div v-if="badges.recent.length" class="mt-3 border-t border-gray-200 pt-2">
            <div class="mb-1 text-xs text-gray-500">{{ ctrans("Recent") }}</div>
            <Link v-for="item in badges.recent" :key="item.id" v-tooltip="recentTooltip(item)" :href="item.route" class="block rounded px-1 py-1 transition duration-200 hover:bg-gray-50" @click="close()">
                <div class="flex justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-1.5">
                        <span v-if="!item.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-500" :title="ctrans('Not opened yet')" />
                        <span class="truncate" :class="item.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ item.title }}</span>
                    </span>
                    <span class="shrink-0 text-[10px] text-gray-400">{{ useFormatTime(item.created_at) }}</span>
                </div>
                <div class="truncate text-xs text-gray-500" :class="!item.read && 'pl-3'">{{ item.body }}</div>
            </Link>
        </div>
    </div>
</template>
