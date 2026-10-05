<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { tasksRoute } from "@/Composables/useTasksRoute"
import Icon from "@/Components/Icon.vue"
import type { TaskBadges } from "@/types/TaskBadges"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarEdit, faHandsHelping } from "@fal"
library.add(faCalendarEdit, faHandsHelping)

const props = defineProps<{
    created: NonNullable<TaskBadges["created"]>
    myId: number
    close: () => void
}>()

const allMineHref = () => tasksRoute("list_all", { filter: { requester: props.myId }, elements: { status: "todo,in_progress" } })
</script>

<template>
    <div class="text-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
            <span class="font-semibold text-gray-900">{{ ctrans("Tasks I created") }}</span>
            <Link :href="allMineHref()" class="text-xs text-[--app-accent-strong] hover:underline" @click="close">{{ ctrans("Open all") }}</Link>
        </div>
        <p v-if="created.needs_answer" class="mt-2 rounded-md bg-amber-50 px-2 py-1.5 text-xs text-amber-800">
            {{ ctrans(":count waiting for your answer", { count: String(created.needs_answer) }) }}
        </p>
        <ul v-if="created.tasks.length" class="mt-1 divide-y divide-gray-50">
            <li v-for="task in created.tasks" :key="task.id">
                <Link :href="task.route" class="flex items-start gap-2 py-2 transition duration-200 hover:bg-gray-50" @click="close">
                    <Icon :data="task.status_icon" class="mt-0.5 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-gray-900" :title="task.subject">{{ task.subject }}</span>
                        <span class="flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                            <span class="font-mono">{{ task.reference }}</span>
                            <span v-if="task.assignee">· {{ task.assignee }}</span>
                            <span v-if="task.has_eta_proposal" class="inline-flex items-center gap-1 rounded bg-amber-50 px-1 text-amber-700">
                                <FontAwesomeIcon icon="fal fa-calendar-edit" fixed-width aria-hidden="true" />{{ ctrans("New ETA") }}
                            </span>
                            <span v-if="task.asked_for_help" class="inline-flex items-center gap-1 rounded bg-amber-50 px-1 text-amber-700">
                                <FontAwesomeIcon icon="fal fa-hands-helping" fixed-width aria-hidden="true" />{{ ctrans("Needs help") }}
                            </span>
                        </span>
                    </span>
                </Link>
            </li>
        </ul>
        <p v-else class="py-3 text-xs text-gray-400">{{ ctrans("Nothing open that you asked for") }}</p>
    </div>
</template>
