<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { tasksRoute } from "@/Composables/useTasksRoute"
import Icon from "@/Components/Icon.vue"
import type { CreatedTask, TaskBadges } from "@/types/TaskBadges"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarEdit, faHandsHelping, faClock } from "@fal"
library.add(faCalendarEdit, faHandsHelping, faClock)

const props = defineProps<{
    created: NonNullable<TaskBadges["created"]>
    myId: number
    close: () => void
}>()

const allMineHref = () => tasksRoute("list_all", { filter: { requester: props.myId }, elements: { status: "todo,in_progress" } })

const sections = computed<{ key: string; label: string; icon: string; tone: string; tasks: CreatedTask[] }[]>(() => {
    const grouped = props.created.sections ?? {
        eta_change: props.created.tasks.filter((task) => task.has_eta_proposal),
        help_request: props.created.tasks.filter((task) => task.asked_for_help),
        recent: props.created.tasks.filter((task) => !task.has_eta_proposal && !task.asked_for_help),
    }

    return [
        { key: "eta_change", label: ctrans("ETA date change"), icon: "fal fa-calendar-edit", tone: "text-amber-700", tasks: grouped.eta_change },
        { key: "help_request", label: ctrans("Request help"), icon: "fal fa-hands-helping", tone: "text-amber-700", tasks: grouped.help_request },
        { key: "recent", label: ctrans("Recent"), icon: "fal fa-clock", tone: "text-gray-500", tasks: grouped.recent },
    ].filter((section) => section.tasks.length)
})
</script>

<template>
    <div class="text-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
            <span class="font-semibold text-gray-900">{{ ctrans("Tasks I created") }}</span>
            <Link :href="allMineHref()" class="text-xs text-[--app-accent-strong] hover:underline" @click="close">{{ ctrans("Open all") }}</Link>
        </div>

        <template v-if="sections.length">
            <section v-for="section in sections" :key="section.key" class="mt-2">
                <div class="flex items-center gap-1.5 px-1 text-[11px] font-semibold uppercase tracking-wide" :class="section.tone">
                    <FontAwesomeIcon :icon="section.icon" fixed-width aria-hidden="true" />
                    {{ section.label }}
                    <span class="rounded-full bg-gray-100 px-1.5 font-normal tabular-nums text-gray-500">{{ section.tasks.length }}</span>
                </div>
                <ul class="divide-y divide-gray-50">
                    <li v-for="task in section.tasks" :key="section.key + '-' + task.id">
                        <Link :href="task.route" class="flex items-start gap-2 rounded px-1 py-1.5 transition duration-200 hover:bg-gray-50" @click="close">
                            <Icon :data="task.status_icon" class="mt-0.5 shrink-0" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-gray-900" :title="task.subject">{{ task.subject }}</span>
                                <span class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                                    <span class="font-mono">{{ task.reference }}</span>
                                    <span v-if="task.assignee">· {{ task.assignee }}</span>
                                </span>
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>
        </template>
        <p v-else class="py-3 text-xs text-gray-400">{{ ctrans("Nothing open that you asked for") }}</p>
    </div>
</template>
