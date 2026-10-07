<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import type { StaffTaskEtaProposal } from "@/types/StaffTaskEta"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCalendarAlt, faCalendarEdit, faBuilding, faUser, faCheck } from "@fal"
library.add(faCalendarAlt, faCalendarEdit, faBuilding, faUser, faCheck)

type Urgency = "overdue" | "critical" | "soon" | "planned" | "none"

type Chip = {
    id: number
    reference: string
    subject: string
    priority: string
    priority_icon: any
    due_at: string | null
    urgency: Urgency
    has_eta_proposal: boolean
    is_mine: boolean
}

type Row = {
    key: string
    label: string
    avatar: any
    kind: "person" | "department" | "unassigned"
    is_me: boolean
    count: number
    pressure: number
    cells: Record<string, Chip[]>
}

type Column = { key: string; label: string; sublabel: string | null; is_today: boolean; is_weekend: boolean }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    map: {
        columns: Column[]
        rows: Row[]
        summary: Record<Urgency, number>
        proposals: { id: number; reference: string; subject: string; due_at: string | null; proposal: StaffTaskEtaProposal }[]
    }
    showRoute: { name: string; parameters: string[] }
}>()

const urgencies: { key: Urgency; label: string; hint: string; dot: string; bar: string }[] = [
    { key: "overdue", label: ctrans("Overdue"), hint: ctrans("The due date has passed"), dot: "bg-red-500", bar: "border-l-red-500" },
    { key: "critical", label: ctrans("Within a day"), hint: ctrans("Due today or tomorrow"), dot: "bg-orange-500", bar: "border-l-orange-500" },
    { key: "soon", label: ctrans("Soon"), hint: ctrans("Due in 3 days, or in a week when the priority is high"), dot: "bg-amber-400", bar: "border-l-amber-400" },
    { key: "planned", label: ctrans("Planned"), hint: ctrans("Due later, nothing pressing"), dot: "bg-emerald-400", bar: "border-l-emerald-400" },
    { key: "none", label: ctrans("No ETA"), hint: ctrans("Nobody set a due date"), dot: "bg-gray-300", bar: "border-l-gray-300" },
]
const urgencyStyle = Object.fromEntries(urgencies.map((urgency) => [urgency.key, urgency])) as Record<Urgency, (typeof urgencies)[number]>

const hiddenUrgencies = ref<Set<Urgency>>(new Set())
const onlyMine = ref(false)

const toggleUrgency = (urgency: Urgency) => {
    const next = new Set(hiddenUrgencies.value)
    if (next.has(urgency)) next.delete(urgency)
    else next.add(urgency)
    hiddenUrgencies.value = next
}

const isChipShown = (chip: Chip) => !hiddenUrgencies.value.has(chip.urgency) && (!onlyMine.value || chip.is_mine)

const visibleRows = computed(() => props.map.rows
    .map((row) => ({
        ...row,
        cells: Object.fromEntries(Object.entries(row.cells).map(([column, chips]) => [column, chips.filter(isChipShown)])),
    }))
    .filter((row) => Object.values(row.cells).some((chips) => chips.length)))

const MAX_CHIPS = 3
const expandedCells = ref<Set<string>>(new Set())
const cellKey = (row: Row, column: Column) => `${row.key}|${column.key}`
const isExpanded = (row: Row, column: Column) => expandedCells.value.has(cellKey(row, column))
const expandCell = (row: Row, column: Column) => {
    expandedCells.value = new Set(expandedCells.value).add(cellKey(row, column))
}
const chipsShown = (row: Row, column: Column) => {
    const chips = row.cells[column.key] ?? []
    return isExpanded(row, column) ? chips : chips.slice(0, MAX_CHIPS)
}
const hiddenChipCount = (row: Row, column: Column) => Math.max(0, (row.cells[column.key]?.length ?? 0) - chipsShown(row, column).length)

const heatClass = (count: number) => (count >= 4 ? "bg-orange-50" : count >= 2 ? "bg-amber-50/60" : "")

const columnHeaderClass = (column: Column) => {
    if (column.key === "overdue") return "text-red-600"
    if (column.is_today) return "bg-cyan-50 text-cyan-800"
    if (column.is_weekend || column.key === "later" || column.key === "none") return "bg-gray-50 text-gray-500"
    return "text-gray-700"
}

const columnCellClass = (column: Column) => {
    if (column.is_today) return "bg-cyan-50/40"
    if (column.is_weekend) return "bg-gray-50/70"
    return ""
}

const quickLook = ref<{ id: number; reference: string } | null>(null)

const decidingId = ref<string | null>(null)

const reloadMap = () => router.reload({ only: ["map"], preserveScroll: true, preserveState: true })

useLiveStaffTasks(() => {
    if (!quickLook.value && !decidingId.value) reloadMap()
})

const taskUrl = (reference: string) => route(props.showRoute.name, [...props.showRoute.parameters, reference])
const shortDate = (date: string) => useFormatTime(date, { formatTime: "mdy" })

const decide = async (task: { id: number; reference: string }, decision: "accept" | "decline") => {
    if (decidingId.value) return
    decidingId.value = `${task.id}:${decision}`
    try {
        await axios.post(route("grp.tasks.eta_proposal.decide", task.reference), { decision })
        router.reload({ only: ["map"], preserveScroll: true, preserveState: true, onFinish: () => (decidingId.value = null) })
    } catch (error: any) {
        decidingId.value = null
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    }
}
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="space-y-4 p-4">
        <section v-if="map.proposals.length" class="rounded-lg border border-amber-200 bg-amber-50">
            <p class="flex items-center gap-2 border-b border-amber-200 px-3 py-2 text-xs font-medium uppercase tracking-wide text-amber-800">
                <FontAwesomeIcon icon="fal fa-calendar-edit" fixed-width aria-hidden="true" />
                {{ ctrans("New ETAs waiting for your answer") }}
            </p>
            <ul class="divide-y divide-amber-100">
                <li v-for="task in map.proposals" :key="task.id" class="flex flex-col gap-2 px-3 py-2.5 text-sm md:flex-row md:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex min-w-0 items-center gap-2">
                            <Link :href="taskUrl(task.reference)" class="primaryLink shrink-0 font-mono text-xs">{{ task.reference }}</Link>
                            <span class="truncate text-gray-900" :title="task.subject">{{ task.subject }}</span>
                        </div>
                        <p class="mt-0.5 text-gray-700">
                            {{ ctrans(":name suggests :date", { name: task.proposal.by_name, date: shortDate(task.proposal.due_at) }) }}
                            <span class="text-gray-500">· {{ task.due_at ? ctrans("Was :date", { date: shortDate(task.due_at) }) : ctrans("No due date before") }}</span>
                        </p>
                        <p class="truncate text-xs text-gray-500" :title="task.proposal.reason">{{ task.proposal.reason }}</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <Button size="small" :label="ctrans('Accept')" :loading="decidingId === `${task.id}:accept`" :disabled="!!decidingId" @click="decide(task, 'accept')" />
                        <Button size="small" text severity="secondary" :label="ctrans('Decline')" :loading="decidingId === `${task.id}:decline`" :disabled="!!decidingId" @click="decide(task, 'decline')" />
                    </div>
                </li>
            </ul>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <button
                v-for="urgency in urgencies"
                :key="urgency.key"
                type="button"
                v-tooltip="urgency.hint"
                :aria-pressed="!hiddenUrgencies.has(urgency.key)"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition duration-200 active:!bg-gray-200"
                :class="hiddenUrgencies.has(urgency.key) ? 'border-gray-200 bg-white text-gray-400 line-through' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                @click="toggleUrgency(urgency.key)">
                <span class="h-2.5 w-2.5 rounded-full" :class="urgency.dot" />
                {{ urgency.label }}
                <span class="font-semibold tabular-nums">{{ map.summary[urgency.key] }}</span>
            </button>
            <span class="mx-1 hidden h-5 w-px bg-gray-200 sm:block" />
            <button
                type="button"
                :aria-pressed="onlyMine"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition duration-200 active:!bg-gray-200"
                :class="onlyMine ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text]' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                @click="onlyMine = !onlyMine">
                <FontAwesomeIcon v-if="onlyMine" icon="fal fa-check" fixed-width aria-hidden="true" />
                {{ ctrans("Only tasks I'm on") }}
            </button>
        </div>

        <div v-if="!visibleRows.length" class="rounded-lg border border-dashed border-gray-300 bg-white py-12 text-center text-sm text-gray-400">
            {{ ctrans("No open tasks to plan here") }}
        </div>

        <div v-else class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="min-w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 min-w-[11rem] border-b border-r border-gray-200 bg-white px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                            {{ ctrans("Who") }}
                        </th>
                        <th
                            v-for="column in map.columns"
                            :key="column.key"
                            class="min-w-[8.5rem] border-b border-gray-200 px-2 py-2 text-left align-bottom font-medium"
                            :class="columnHeaderClass(column)">
                            <span class="block text-xs">{{ column.label }}</span>
                            <span v-if="column.sublabel" class="block text-[11px] font-normal tabular-nums opacity-70">{{ column.sublabel }}</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in visibleRows" :key="row.key" class="align-top">
                        <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-white px-3 py-2 text-left font-normal" :class="row.is_me && '!bg-cyan-50'">
                            <div class="flex items-center gap-2">
                                <TicketUserAvatar v-if="row.kind === 'person'" :name="row.label" :avatar="row.avatar" size="sm" />
                                <span v-else class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-gray-500" :class="row.kind === 'department' ? 'bg-gray-100' : 'border border-dashed border-gray-300'">
                                    <FontAwesomeIcon :icon="row.kind === 'department' ? 'fal fa-building' : 'fal fa-user'" fixed-width aria-hidden="true" />
                                </span>
                                <span class="min-w-0 flex-1 truncate text-gray-800" :title="row.label">{{ row.label }}</span>
                                <span class="shrink-0 rounded-full bg-gray-100 px-1.5 text-xs tabular-nums text-gray-600" v-tooltip="ctrans('Open tasks')">{{ row.count }}</span>
                            </div>
                        </th>
                        <td
                            v-for="column in map.columns"
                            :key="column.key"
                            class="border-b border-gray-100 p-1.5"
                            :class="[columnCellClass(column), heatClass(row.cells[column.key]?.length ?? 0)]">
                            <div class="space-y-1">
                                <button
                                    v-for="chip in chipsShown(row, column)"
                                    :key="chip.id"
                                    type="button"
                                    class="block w-full rounded border border-l-4 border-gray-200 bg-white px-1.5 py-1 text-left shadow-sm transition duration-200 hover:bg-gray-50 active:!bg-gray-100"
                                    :class="urgencyStyle[chip.urgency].bar"
                                    :title="`${chip.reference} · ${chip.subject}`"
                                    @click="quickLook = { id: chip.id, reference: chip.reference }">
                                    <span class="flex items-center gap-1 text-[11px] text-gray-500">
                                        <span class="font-mono">{{ chip.reference }}</span>
                                        <Icon v-if="chip.priority !== 'normal' && chip.priority_icon" :data="chip.priority_icon" class="text-[10px]" />
                                        <FontAwesomeIcon v-if="chip.has_eta_proposal" v-tooltip="ctrans('A new ETA was suggested')" icon="fal fa-calendar-edit" class="ml-auto text-amber-600" fixed-width />
                                    </span>
                                    <span class="block truncate text-xs text-gray-800">{{ chip.subject }}</span>
                                </button>
                                <button
                                    v-if="hiddenChipCount(row, column)"
                                    type="button"
                                    class="w-full rounded px-1.5 py-0.5 text-left text-xs text-gray-500 transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                                    @click="expandCell(row, column)">
                                    {{ ctrans("+:count more", { count: String(hiddenChipCount(row, column)) }) }}
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <StaffTaskQuickLook v-model:task="quickLook" @closed="reloadMap" />
</template>
