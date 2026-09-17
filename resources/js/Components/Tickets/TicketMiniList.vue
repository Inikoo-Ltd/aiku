<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 13 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faVial, faShieldCheck, faShield, faLifeRing, faToolbox, faUserHeadset, faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan } from "@fal"

library.add(faLifeRing, faToolbox, faUserHeadset, faVial, faShieldCheck, faShield, faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan)

const props = defineProps<{
    title: string
    tickets: any[]
    empty: string
    dateKey?: string
    showAssignee?: boolean
}>()

const sortFields = [
    { key: "created_at", label: trans("Created") },
    { key: "updated_at", label: trans("Updated") },
]

const sortField = ref("created_at")
const sortDesc = ref(true)

const cycleSortField = () => {
    sortField.value = sortFields[(sortFields.findIndex((option) => option.key === sortField.value) + 1) % sortFields.length].key
}

const sortedTickets = computed(() =>
    [...props.tickets].sort((a, b) => (new Date(a[sortField.value] ?? 0).getTime() - new Date(b[sortField.value] ?? 0).getTime()) * (sortDesc.value ? -1 : 1))
)
const daysAgo = (date?: string) => {
    const days = date ? Math.floor((Date.now() - new Date(date).getTime()) / 86400000) : -1
    return days >= 0 ? days : null
}
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-300">
        <div class="flex items-center gap-2 px-4 py-2 border-b border-gray-200">
            <h3 class="font-semibold">{{ title }}</h3>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 tabular-nums">{{ tickets.length }}</span>
            <span v-if="tickets.length > 1" class="ml-auto flex items-center text-xs text-gray-500">
                <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="trans('Sort by')" @click="cycleSortField">
                    {{ sortFields.find((option) => option.key === sortField)?.label }}
                </button>
                <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="sortDesc ? trans('Newest first') : trans('Oldest first')" @click="sortDesc = !sortDesc">
                    {{ sortDesc ? "↓" : "↑" }}
                </button>
            </span>
        </div>
        <ul v-if="tickets.length" class="divide-y divide-gray-100 text-sm">
            <li v-for="ticket in sortedTickets" :key="ticket.id" class="flex items-center gap-3 px-4 py-2">
                <Icon :data="ticket.status_icon" />
                <span class="inline-flex items-center whitespace-nowrap"><Icon v-if="ticket.type_icon" :data="ticket.type_icon" class="mr-1 text-gray-400" /><Link :href="route('grp.tickets.show', ticket.reference)" class="primaryLink whitespace-nowrap">{{ ticket.reference }}</Link></span>
                <span class="truncate flex-1" :title="ticket.subject">{{ ticket.subject }}</span>
                <Icon v-if="ticket.qa_status_icon" :data="ticket.qa_status_icon" />
                <span v-if="showAssignee" class="text-xs text-gray-500 whitespace-nowrap">{{ ticket.assignee_username || "-" }}</span>
                <span class="text-xs text-gray-500 whitespace-nowrap" :title="useFormatTime(ticket[dateKey ?? sortField], { formatTime: 'hm' })">
                    {{ useFormatTime(ticket[dateKey ?? sortField], { formatTime: "d MMM" }) }}
                    <span v-if="daysAgo(ticket[dateKey ?? sortField]) !== null" class="text-gray-400 tabular-nums">· {{ daysAgo(ticket[dateKey ?? sortField]) }}d</span>
                </span>
            </li>
        </ul>
        <p v-else class="px-4 py-6 text-center text-sm text-gray-400">{{ empty }}</p>
    </div>
</template>
