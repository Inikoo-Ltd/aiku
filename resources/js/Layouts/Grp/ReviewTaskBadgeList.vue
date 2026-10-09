<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { tasksRoute } from "@/Composables/useTasksRoute"
import type { TaskBadges } from "@/types/TaskBadges"

defineProps<{
    review: NonNullable<TaskBadges["review"]>
    close: () => void
}>()
</script>

<template>
    <div class="text-sm">
        <div class="flex items-center justify-between border-b border-gray-100 pb-2">
            <span class="font-semibold text-gray-900">{{ ctrans("To review & publish") }}</span>
            <Link :href="tasksRoute('review')" class="text-xs text-[--app-accent-strong] hover:underline" @click="close">{{ ctrans("Open all") }}</Link>
        </div>
        <ul v-if="review.tasks.length" class="divide-y divide-gray-50">
            <li v-for="task in review.tasks" :key="task.id">
                <Link :href="task.route" class="flex items-center gap-2 rounded px-1 py-1.5 transition duration-200 hover:bg-gray-50" @click="close">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-gray-900" :title="task.subject">{{ task.subject }}</span>
                        <span class="font-mono text-xs text-gray-500">{{ task.reference }}</span>
                    </span>
                    <span class="shrink-0 text-xs tabular-nums text-gray-600" v-tooltip="ctrans('Still to publish, out of all the changes')">{{ task.pending }} / {{ task.total }}</span>
                </Link>
            </li>
        </ul>
        <p v-else class="py-3 text-xs text-gray-400">{{ ctrans("Nothing waiting to be published") }}</p>
    </div>
</template>
