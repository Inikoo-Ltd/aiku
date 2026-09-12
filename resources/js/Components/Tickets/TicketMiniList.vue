<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"

defineProps<{
    title: string
    tickets: any[]
    empty: string
    dateKey?: string
    showAssignee?: boolean
}>()
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-300">
        <div class="flex items-center justify-between px-4 py-2 border-b border-gray-200">
            <h3 class="font-semibold">{{ title }}</h3>
            <span class="text-xs text-gray-500">{{ tickets.length }}</span>
        </div>
        <ul v-if="tickets.length" class="divide-y divide-gray-100 text-sm">
            <li v-for="ticket in tickets" :key="ticket.id" class="flex items-center gap-3 px-4 py-2">
                <Icon :data="ticket.status_icon" />
                <Link :href="route('grp.tickets.show', ticket.reference)" class="primaryLink whitespace-nowrap">{{ ticket.reference }}</Link>
                <span class="truncate flex-1" :title="ticket.subject">{{ ticket.subject }}</span>
                <Icon :data="ticket.priority_icon" />
                <span v-if="showAssignee" class="text-xs text-gray-500 whitespace-nowrap">{{ ticket.assignee_username || "-" }}</span>
                <span class="text-xs text-gray-500 whitespace-nowrap" :title="useFormatTime(ticket[dateKey ?? 'updated_at'], { formatTime: 'hm' })">
                    {{ useFormatTime(ticket[dateKey ?? "updated_at"], { formatTime: "d MMM" }) }}
                </span>
            </li>
        </ul>
        <p v-else class="px-4 py-6 text-center text-sm text-gray-400">{{ empty }}</p>
    </div>
</template>
