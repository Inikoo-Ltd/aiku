<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { tasksRoute } from "@/Composables/useTasksRoute"
import Icon from "@/Components/Icon.vue"
import type { TaskBadgeRow, TaskBadges } from "@/types/TaskBadges"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSun, faListUl, faCalendar, faBell } from "@fal"
library.add(faSun, faListUl, faCalendar, faBell)

const props = defineProps<{
    badges: TaskBadges | null
    tasks: { id: number; reference: string; subject: string; due_at: string | null; is_overdue: boolean; status_icon: any }[]
}>()

const emit = defineEmits<{
    open: [task: { id: number; reference: string }]
}>()

const todayTotal = computed(() => (props.badges ? props.badges.today.done + props.badges.today.open : 0))
const todayPercent = computed(() => (todayTotal.value ? Math.round(((props.badges?.today.done ?? 0) / todayTotal.value) * 100) : 0))

const rowHref = (row: TaskBadgeRow) => tasksRoute("list_all", { filter: row.filter, elements: { status: row.status } })

const dueSoon = computed(() => {
    const limit = new Date()
    limit.setDate(limit.getDate() + 7)
    return props.tasks
        .filter((task) => task.due_at && (task.is_overdue || new Date(task.due_at) <= limit))
        .sort((a, b) => new Date(a.due_at!).getTime() - new Date(b.due_at!).getTime())
        .slice(0, 5)
})

const shortDate = (value: string) => new Date(value).toLocaleDateString([], { day: "numeric", month: "short" })

const cardClass = "rounded-lg border border-gray-200 bg-white"
const headerClass = "flex items-center gap-2 border-b border-gray-100 px-3 py-2 text-xs font-medium uppercase tracking-wide text-gray-400"
</script>

<template>
    <div class="space-y-4">
        <section v-if="badges" :class="cardClass">
            <p :class="headerClass">
                <FontAwesomeIcon icon="fal fa-sun" fixed-width aria-hidden="true" />{{ ctrans("Today") }}
            </p>
            <div class="px-3 py-3" v-tooltip="ctrans('Tasks you finished today, out of today’s done plus still open')">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="text-gray-600">{{ ctrans("Done") }}</span>
                    <span class="tabular-nums text-gray-700">
                        <span class="font-semibold text-gray-900">{{ badges.today.done }}</span> / {{ todayTotal }}
                        <span class="ml-1 font-semibold" :class="todayPercent >= 80 ? 'text-green-600' : todayPercent >= 50 ? 'text-amber-600' : 'text-gray-500'">{{ todayPercent }}%</span>
                    </span>
                </div>
                <span class="mt-2 flex h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <span class="bg-cyan-500 transition-all duration-300" :style="{ width: `${todayPercent}%` }" />
                </span>
            </div>
        </section>

        <section v-if="badges" :class="cardClass">
            <p :class="headerClass">
                <FontAwesomeIcon icon="fal fa-list-ul" fixed-width aria-hidden="true" />{{ ctrans("At a glance") }}
            </p>
            <ul class="divide-y divide-gray-50 text-sm">
                <li v-for="(row, key) in badges.mine" :key="key">
                    <Link :href="rowHref(row)" class="flex items-center justify-between px-3 py-2 transition duration-200 hover:bg-gray-50" :class="row.count ? 'text-gray-700' : 'text-gray-400'">
                        <span>{{ row.label }}</span>
                        <span class="font-medium tabular-nums" :class="key === 'overdue' && row.count ? 'text-red-600' : ''">{{ row.count }}</span>
                    </Link>
                </li>
            </ul>
        </section>

        <section :class="cardClass">
            <p :class="headerClass">
                <FontAwesomeIcon icon="fal fa-calendar" fixed-width aria-hidden="true" />{{ ctrans("Due soon") }}
            </p>
            <ul v-if="dueSoon.length" class="divide-y divide-gray-50 text-sm">
                <li v-for="task in dueSoon" :key="task.id">
                    <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left transition duration-200 hover:bg-gray-50" @click="emit('open', task)">
                        <Icon :data="task.status_icon" class="shrink-0" />
                        <span class="min-w-0 flex-1 truncate text-gray-800" :title="task.subject">{{ task.subject }}</span>
                        <span class="shrink-0 text-xs tabular-nums" :class="task.is_overdue ? 'font-medium text-red-600' : 'text-gray-500'">{{ shortDate(task.due_at!) }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="px-3 py-3 text-sm text-gray-400">{{ ctrans("Nothing due in the next 7 days") }}</p>
        </section>

        <section v-if="badges" :class="cardClass">
            <p :class="headerClass">
                <FontAwesomeIcon icon="fal fa-bell" fixed-width aria-hidden="true" />{{ ctrans("Recent") }}
            </p>
            <div v-if="badges.recent.length" class="divide-y divide-gray-50">
                <Link v-for="item in badges.recent" :key="item.id" :href="item.route" class="block px-3 py-2 transition duration-200 hover:bg-gray-50">
                    <div class="flex justify-between gap-2 text-sm">
                        <span class="flex min-w-0 items-center gap-1.5">
                            <span v-if="!item.read" class="h-1.5 w-1.5 shrink-0 rounded-full bg-cyan-500" :title="ctrans('Not opened yet')" />
                            <span class="truncate" :class="item.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ item.title }}</span>
                        </span>
                        <span class="shrink-0 text-[10px] text-gray-400">{{ useFormatTime(item.created_at) }}</span>
                    </div>
                    <div class="truncate text-xs text-gray-500" :class="!item.read && 'pl-3'">{{ item.body }}</div>
                </Link>
            </div>
            <p v-else class="px-3 py-3 text-sm text-gray-400">{{ ctrans("No task updates yet") }}</p>
        </section>
    </div>
</template>
