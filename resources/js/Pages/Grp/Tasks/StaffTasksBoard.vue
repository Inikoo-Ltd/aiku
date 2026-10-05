<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import draggable from "vuedraggable"
import { Dialog, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import { useBoardDropZones } from "@/Composables/useBoardDropZones"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import StaffTaskDueBadge from "@/Components/Tasks/StaffTaskDueBadge.vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Icon from "@/Components/Icon.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faColumns, faCalendar, faSpinner, faCircle, faCheckCircle, faBan, faBuilding, faLink, faCheck, faLock, faGripLines, faChevronRight } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faColumns, faCalendar, faSpinner, faCircle, faCheckCircle, faBan, faBuilding, faLink, faCheck, faLock, faGripLines, faChevronRight)

type Column = { status: string; label: string; color: string; icon: any; tasks: any[] }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    columns: Column[]
    can_manage: boolean
    me: number
    showRoute: { name: string; parameters: string[] }
    createdIntervals: Record<string, string>
    createdInterval: string
}>()

const taskUrl = (task: { reference: string }) => route(props.showRoute.name, [...props.showRoute.parameters, task.reference])

const columnClasses: Record<string, string> = {
    gray: "bg-gray-100 border-t-4 border-gray-400",
    blue: "bg-blue-50 border-t-4 border-blue-400",
    green: "bg-green-50 border-t-4 border-green-500",
    red: "bg-red-50 border-t-4 border-red-400",
}

type SortField = "created_at" | "due_at" | "priority" | "in_column"

const sortFields: { key: SortField; label: string }[] = [
    { key: "created_at", label: ctrans("Created") },
    { key: "due_at", label: ctrans("Due") },
    { key: "priority", label: ctrans("Urgency") },
    { key: "in_column", label: ctrans("In column") },
]

const columnStamps: Record<string, string> = { todo: "created_at", in_progress: "started_at", done: "closed_at", cancelled: "closed_at" }
const priorityRank: Record<string, number> = { urgent: 3, high: 2, normal: 1, low: 0 }

const readSorts = (): Record<string, { field: SortField; desc: boolean }> => {
    try {
        return JSON.parse(localStorage.getItem("staff-tasks-board-sorts") ?? "{}")
    } catch {
        return {}
    }
}

const columnSorts = reactive(readSorts())
const sortOf = (status: string) => columnSorts[status] ?? { field: "in_column" as SortField, desc: true }

const sortValue = (task: any, status: string, field: SortField) => {
    if (field === "priority") return priorityRank[task.priority] ?? 0
    const stamp = field === "in_column" ? task[columnStamps[status]] ?? task.created_at : task[field]
    return stamp ? new Date(stamp).getTime() : 0
}

const sortColumn = (column: Column) => {
    const { field, desc } = sortOf(column.status)
    column.tasks.sort((a, b) => (sortValue(a, column.status, field) - sortValue(b, column.status, field)) * (desc ? -1 : 1))
}

const openSortPicker = ref<string | null>(null)

const changeSort = (column: Column, change: SortField | "direction") => {
    openSortPicker.value = null
    const current = sortOf(column.status)
    columnSorts[column.status] = change === "direction" ? { ...current, desc: !current.desc } : { field: change, desc: current.desc }
    try {
        localStorage.setItem("staff-tasks-board-sorts", JSON.stringify(columnSorts))
    } catch {
        return
    }
    sortColumn(column)
}

const columns = ref<Column[]>(props.columns)
props.columns.forEach(sortColumn)

watch(() => props.columns, (value) => {
    value.forEach(sortColumn)
    columns.value = value
})

type FilterKey = "department_label" | "priority_label" | "assignee_name"

const filters = reactive<Record<FilterKey, string[]>>({ department_label: [], priority_label: [], assignee_name: [] })
const filterLabels: Record<FilterKey, string> = { department_label: ctrans("Department"), priority_label: ctrans("Urgency"), assignee_name: ctrans("Assignee") }

const nobodyLabel = ctrans("Nobody yet")
const filterValue = (task: any, key: FilterKey) => key === "assignee_name" ? task.assignee?.name ?? nobodyLabel : task[key]

const allTasks = computed(() => props.columns.flatMap((column) => column.tasks))

const countedBy = (key: FilterKey) => {
    const counts: Record<string, number> = {}
    allTasks.value.forEach((task) => {
        const value = filterValue(task, key)
        if (value) counts[value] = (counts[value] ?? 0) + 1
    })
    return Object.entries(counts).sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])).map(([value, count]) => ({ value, count }))
}

const filterOptions = computed(() => ({
    department_label: countedBy("department_label"),
    priority_label: countedBy("priority_label"),
    assignee_name: countedBy("assignee_name"),
}))

const toggleFilter = (key: FilterKey, value: string) => {
    const index = filters[key].indexOf(value)
    index === -1 ? filters[key].push(value) : filters[key].splice(index, 1)
}

const myName = computed(() => allTasks.value.flatMap((task) => [task.assignee, ...task.collaborators]).find((person) => person?.id === props.me)?.name ?? null)
const onlyMine = computed(() => filters.assignee_name.length === 1 && filters.assignee_name[0] === myName.value)
const toggleMine = () => (filters.assignee_name = onlyMine.value || !myName.value ? [] : [myName.value])

const assigneeMenuOpen = ref(false)
const avatarOf = (name: string) => allTasks.value.find((task) => task.assignee?.name === name)?.assignee?.avatar ?? null

const activeFilters = computed(() => Object.values(filters).reduce((total, values) => total + values.length, 0))
const clearFilters = () => (Object.keys(filters) as FilterKey[]).forEach((key) => (filters[key] = []))

const matches = (task: any) => (Object.keys(filters) as FilterKey[]).every((key) => !filters[key].length || filters[key].includes(filterValue(task, key)))
const visibleCount = (column: Column) => column.tasks.filter(matches).length

const isWorkingOn = (task: any) => task.assignee?.id === props.me || task.collaborators.some((person: { id: number }) => person.id === props.me)

const canDragTask = (task: any) => props.can_manage || isWorkingOn(task) || (!task.assignee && task.status === "todo")

const workerMoves: Record<string, string[]> = {
    todo: ["in_progress", "done", "cancelled"],
    in_progress: ["todo", "done", "cancelled"],
    done: ["todo"],
    cancelled: ["todo"],
}

const canDropTask = (task: any, from: string, to: string) => {
    if (from === to) return true
    if (!canDragTask(task)) return false
    if (props.can_manage) return true
    if (!isWorkingOn(task)) return from === "todo" && to === "in_progress"
    return workerMoves[from]?.includes(to) ?? false
}

const onMoveCheck = (event: { draggedContext: { element: any }; from: HTMLElement; to: HTMLElement }) =>
    canDropTask(event.draggedContext.element, event.from.dataset.column ?? "", event.to.dataset.column ?? "")

const dragging = ref(false)
let lastDragEndedAt = 0

const { startDrag, endDrag, dropZone } = useBoardDropZones(canDropTask)

const onDragStart = (column: Column, event: { oldIndex: number }) => {
    dragging.value = true
    startDrag(column.tasks[event.oldIndex], column.status)
}

const onDragEnd = () => {
    dragging.value = false
    endDrag()
    lastDragEndedAt = Date.now()
}

useLiveStaffTasks(() => {
    if (!dragging.value && !savingTaskIds.value.length && !cancelFor.value && !quickLook.value) router.reload({ only: ["columns"] })
})

const savingTaskIds = ref<number[]>([])

const moveBack = (task: any, from: string, to: string) => {
    const source = columns.value.find((column) => column.status === to)
    const target = columns.value.find((column) => column.status === from)
    if (!source || !target) return
    source.tasks = source.tasks.filter((candidate) => candidate.id !== task.id)
    target.tasks.unshift(task)
}

const patch = async (task: any, from: string, to: string, extra: Record<string, unknown> = {}) => {
    savingTaskIds.value.push(task.id)
    try {
        const { data } = await axios.patch(route("grp.tasks.update", task.reference), { status: to, ...extra })
        const column = columns.value.find((candidate) => candidate.status === to)
        const index = column?.tasks.findIndex((candidate) => candidate.id === task.id) ?? -1
        if (column && index !== -1) column.tasks[index] = data.data
    } catch (error: any) {
        notify({ title: ctrans("Could not move task"), text: error.response?.data?.message, type: "error" })
        moveBack(task, from, to)
    } finally {
        savingTaskIds.value = savingTaskIds.value.filter((id) => id !== task.id)
    }
}

const cancelFor = ref<{ task: any; from: string } | null>(null)
const cancelNote = ref("")

const onMoved = (column: Column, event: { added?: { element: any } }) => {
    if (!event.added) return
    const task = event.added.element
    const from = task.status
    if (!canDropTask(task, from, column.status)) {
        moveBack(task, from, column.status)
        return
    }
    if (column.status === "cancelled") {
        cancelFor.value = { task, from }
        cancelNote.value = ""
        return
    }
    patch(task, from, column.status)
}

const confirmCancel = () => {
    if (!cancelFor.value || !cancelNote.value.trim()) return
    const { task, from } = cancelFor.value
    cancelFor.value = null
    patch(task, from, "cancelled", { note: cancelNote.value.trim() })
}

const abortCancel = () => {
    if (cancelFor.value) moveBack(cancelFor.value.task, cancelFor.value.from, "cancelled")
    cancelFor.value = null
}

const quickLook = ref<{ id: number; reference: string } | null>(null)

const CANCELLED_OPEN_KEY = "staff-tasks-board-cancelled-open"

const readCancelledOpen = () => {
    try {
        return localStorage.getItem(CANCELLED_OPEN_KEY) === "true"
    } catch {
        return false
    }
}

const isCancelledOpen = ref(readCancelledOpen())

const toggleCancelledColumn = (open: boolean) => {
    isCancelledOpen.value = open
    try {
        localStorage.setItem(CANCELLED_OPEN_KEY, String(open))
    } catch { }
}

const isFoldedColumn = (column: Column) => column.status === "cancelled" && !isCancelledOpen.value && !dragging.value

const openTask = (task: any, event: MouseEvent) => {
    const target = event.target as HTMLElement | null
    if (target?.closest("a, button") || Date.now() - lastDragEndedAt < 300) return
    quickLook.value = task
}

const closeQuickLook = () => router.reload({ only: ["columns"] })

const onBoardClick = (event: MouseEvent) => {
    if (!(event.target as HTMLElement)?.closest?.("[data-picker]")) openSortPicker.value = null
}

onMounted(() => window.addEventListener("click", onBoardClick, true))
onBeforeUnmount(() => window.removeEventListener("click", onBoardClick, true))

const shortDate = (value: string | null) => value ? new Date(value).toLocaleDateString([], { day: "numeric", month: "short" }) : ""

const ageIn = (column: Column, task: any) => {
    const since = task[columnStamps[column.status]] ?? task.created_at
    if (!since) return ""
    if (column.status === "done" || column.status === "cancelled") return shortDate(since)
    const hours = Math.floor((Date.now() - new Date(since).getTime()) / 3600000)
    return hours < 48 ? `${Math.max(hours, 0)}h` : `${Math.floor(hours / 24)}d`
}

const cardPeople = (task: any) => [
    ...(task.assignee ? [task.assignee] : []),
    ...task.collaborators,
]

const subtaskSummary = (task: any) => task.subtasks?.length
    ? `${task.subtasks.filter((subtask: { status: string }) => subtask.status === "done").length}/${task.subtasks.length}`
    : null
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="min-w-0 p-4">
        <TicketsCreatedInterval :options="createdIntervals" :selected="createdInterval" class="mb-3" />

        <div class="mb-3 flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm">
            <div v-for="key in ['department_label', 'priority_label'] as const" v-show="filterOptions[key].length" :key="key" class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ filterLabels[key] }}</span>
                <button
                    v-for="option in filterOptions[key]"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="filters[key].includes(option.value)
                        ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm'
                        : option.value === 'Urgent' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="toggleFilter(key, option.value)">
                    <span>{{ option.value }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="filters[key].includes(option.value) ? 'bg-white/20' : 'bg-white text-gray-500'">{{ option.count }}</span>
                </button>
            </div>

            <div v-if="filterOptions.assignee_name.length" class="relative flex items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ filterLabels.assignee_name }}</span>
                <button
                    v-if="myName"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="onlyMine ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="toggleMine">
                    {{ ctrans("Me") }}
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="filters.assignee_name.length && !onlyMine ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="assigneeMenuOpen = !assigneeMenuOpen">
                    <span class="max-w-48 truncate">{{ filters.assignee_name.length && !onlyMine ? filters.assignee_name.join(", ") : ctrans("Everybody") }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="filters.assignee_name.length && !onlyMine ? 'bg-white/20' : 'bg-white text-gray-500'">
                        {{ (!onlyMine && filters.assignee_name.length) || filterOptions.assignee_name.length }}
                    </span>
                </button>
                <div v-if="assigneeMenuOpen" class="fixed inset-0 z-30" @click="assigneeMenuOpen = false" />
                <div v-if="assigneeMenuOpen" class="absolute left-0 top-8 z-40 max-h-72 w-60 overflow-y-auto rounded-lg border border-gray-200 bg-white p-1.5 shadow-xl">
                    <label v-for="option in filterOptions.assignee_name" :key="option.value" class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-gray-50">
                        <input type="checkbox" :checked="filters.assignee_name.includes(option.value)" @change="toggleFilter('assignee_name', option.value)" />
                        <TicketUserAvatar :name="option.value" :avatar="avatarOf(option.value)" size="xs" />
                        <span class="truncate text-sm text-gray-700">{{ option.value }}</span>
                        <span class="ml-auto text-xs text-gray-400">{{ option.count }}</span>
                    </label>
                    <button v-if="filters.assignee_name.length" type="button" class="mt-1 w-full rounded px-2 py-1 text-left text-xs text-gray-400 hover:bg-gray-50" @click="filters.assignee_name = []">
                        {{ ctrans("Everybody") }}
                    </button>
                </div>
            </div>

            <button v-if="activeFilters" type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="clearFilters">× {{ ctrans("Clear") }}</button>
        </div>

        <div class="-mx-4 overflow-x-auto px-4 pb-2">
            <div class="flex w-max min-w-full gap-3">
                <template v-for="column in columns" :key="column.status">
                <button
                    v-if="isFoldedColumn(column)"
                    type="button"
                    v-tooltip="ctrans(':label · :count, click to show', { label: column.label, count: String(visibleCount(column)) })"
                    class="flex w-11 shrink-0 flex-col items-center gap-2 rounded-lg px-1 py-2 opacity-70 transition duration-200 hover:opacity-100"
                    :class="columnClasses[column.color] ?? columnClasses.gray"
                    @click="toggleCancelledColumn(true)">
                    <Icon :data="column.icon" />
                    <span class="rounded bg-white/70 px-1.5 py-0.5 text-xs tabular-nums text-gray-600">{{ visibleCount(column) }}</span>
                    <span class="text-xs font-medium text-gray-600 [writing-mode:vertical-rl]">{{ column.label }}</span>
                </button>
                <div v-else class="flex min-w-[17rem] flex-1 flex-col rounded-lg p-2 transition duration-200" :class="[columnClasses[column.color] ?? columnClasses.gray, dropZone(column.status, column.color).class]" :style="dropZone(column.status, column.color).style">
                    <div class="flex flex-nowrap items-center gap-1.5 whitespace-nowrap px-1 pb-2">
                        <button
                            v-if="column.status === 'cancelled' && !dragging"
                            type="button"
                            v-tooltip="ctrans('Fold away')"
                            class="-ml-1 rounded p-0.5 text-gray-400 transition duration-200 hover:bg-white/70 hover:text-gray-700"
                            @click="toggleCancelledColumn(false)">
                            <FontAwesomeIcon icon="fal fa-chevron-right" fixed-width aria-hidden="true" />
                        </button>
                        <Icon :data="column.icon" />
                        <span class="text-sm font-semibold">{{ column.label }}</span>
                        <span class="rounded bg-white/70 px-1.5 py-0.5 text-xs tabular-nums text-gray-600">{{ visibleCount(column) }}</span>
                        <span class="ml-auto flex items-center rounded bg-white/70 text-xs text-gray-500">
                            <span class="relative" data-picker>
                                <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort by')" @click="openSortPicker = openSortPicker === column.status ? null : column.status">
                                    {{ sortFields.find((option) => option.key === sortOf(column.status).field)?.label }}
                                </button>
                                <span v-if="openSortPicker === column.status" class="absolute right-0 z-20 mt-1 block w-28 rounded border border-gray-200 bg-white py-1 shadow-lg">
                                    <button
                                        v-for="option in sortFields"
                                        :key="option.key"
                                        type="button"
                                        class="block w-full px-2 py-1 text-left text-xs hover:bg-gray-100"
                                        :class="option.key === sortOf(column.status).field ? 'font-semibold text-gray-900' : 'text-gray-600'"
                                        @click="changeSort(column, option.key)">
                                        {{ option.label }}
                                    </button>
                                </span>
                            </span>
                            <button type="button" class="px-1 py-0.5 hover:text-gray-900" :title="sortOf(column.status).desc ? ctrans('Newest or highest first') : ctrans('Oldest or lowest first')" @click="changeSort(column, 'direction')">
                                {{ sortOf(column.status).desc ? "↓" : "↑" }}
                            </button>
                        </span>
                    </div>

                    <draggable
                        v-model="column.tasks"
                        item-key="id"
                        group="staff-tasks"
                        :data-column="column.status"
                        :move="onMoveCheck"
                        handle=".task-drag-handle"
                        filter=".task-card-locked"
                        :prevent-on-filter="false"
                        :force-fallback="true"
                        :fallback-tolerance="4"
                        class="thinScrollbar max-h-[70vh] min-h-24 flex-1 space-y-2 overflow-y-auto pr-1"
                        @start="onDragStart(column, $event)"
                        @end="onDragEnd"
                        @change="onMoved(column, $event)">
                        <template #item="{ element: task }">
                            <div
                                v-show="matches(task)"
                                class="relative cursor-pointer select-none rounded-md border border-gray-200 bg-white p-2.5 pl-7 shadow-sm hover:border-gray-400"
                                :class="!canDragTask(task) && 'task-card-locked'"
                                :aria-busy="savingTaskIds.includes(task.id)"
                                @click="openTask(task, $event)">
                                <span
                                    v-if="canDragTask(task)"
                                    v-tooltip="{ content: ctrans('Drag to move'), delay: 300 }"
                                    class="task-drag-handle absolute inset-y-0 left-0 flex w-6 cursor-grab items-center justify-center rounded-l-md text-gray-300 transition duration-200 hover:bg-gray-50 hover:text-gray-500 active:cursor-grabbing"
                                    data-drag-handle
                                    @click.stop>
                                    <FontAwesomeIcon icon="fal fa-grip-lines" fixed-width aria-hidden="true" />
                                </span>
                                <FontAwesomeIcon v-if="savingTaskIds.includes(task.id)" icon="fal fa-spinner" spin fixed-width class="absolute right-1.5 top-1.5 text-xs text-gray-400" />
                                <FontAwesomeIcon
                                    v-else-if="!canDragTask(task)"
                                    v-tooltip="ctrans('Only the assignee, collaborators or a supervisor can move this')"
                                    icon="fal fa-lock"
                                    fixed-width
                                    class="absolute right-1.5 top-1.5 text-[10px] text-gray-300" />

                                <div class="flex items-start justify-between gap-2 text-xs">
                                    <span class="flex min-w-0 flex-col gap-0.5">
                                        <Link :href="taskUrl(task)" class="primaryLink font-medium" @click.stop>{{ task.reference }}</Link>
                                        <span class="flex items-center gap-1.5 text-[11px]">
                                            <span v-tooltip="{ content: ctrans('Raised'), delay: 0 }" class="text-gray-400">{{ shortDate(task.created_at) }}</span>
                                            <span v-tooltip="{ content: column.label, delay: 0 }" class="text-gray-500">{{ ageIn(column, task) }}</span>
                                        </span>
                                    </span>
                                    <span v-if="task.priority !== 'normal'" v-tooltip="{ content: task.priority_label, delay: 0 }" class="mr-4 shrink-0">
                                        <Icon :data="task.priority_icon" />
                                    </span>
                                </div>

                                <p class="mt-1.5 line-clamp-3 break-words text-sm leading-snug">{{ task.subject }}</p>

                                <div class="mt-2 flex items-end justify-between gap-2 text-xs">
                                    <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-0.5 text-gray-500">
                                        <StaffTaskDueBadge v-if="task.due_at" :dueAt="task.due_at" :priority="task.priority" :status="task.status" />
                                        <span v-if="subtaskSummary(task)" v-tooltip="{ content: ctrans('Sub tasks done'), delay: 0 }" class="inline-flex items-center gap-0.5 tabular-nums">
                                            <FontAwesomeIcon icon="fal fa-check" fixed-width class="text-green-600" />{{ subtaskSummary(task) }}
                                        </span>
                                        <span v-if="task.model_label" class="inline-flex min-w-0 items-center gap-0.5 truncate">
                                            <FontAwesomeIcon icon="fal fa-link" fixed-width />{{ task.model_label }}
                                        </span>
                                    </span>
                                    <span
                                        v-if="cardPeople(task).length"
                                        v-tooltip="{ content: cardPeople(task).map((person) => person.name).join(', '), delay: 0 }"
                                        class="flex shrink-0 -space-x-1.5">
                                        <TicketUserAvatar v-for="person in cardPeople(task).slice(0, 3)" :key="person.id" :name="person.name" :avatar="person.avatar" size="xs" class="ring-2 ring-white" />
                                        <span v-if="cardPeople(task).length > 3" class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-200 text-[8px] font-medium text-gray-600 ring-2 ring-white">+{{ cardPeople(task).length - 3 }}</span>
                                    </span>
                                    <span v-else-if="task.department_label" v-tooltip="{ content: task.department_label, delay: 0 }" class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                                        <FontAwesomeIcon icon="fal fa-building" fixed-width class="text-[9px]" />
                                    </span>
                                </div>
                            </div>
                        </template>
                    </draggable>
                </div>
                </template>
            </div>
        </div>
    </div>

    <Dialog
        :visible="!!cancelFor"
        modal
        :header="ctrans(`Why can't :reference be done?`, { reference: cancelFor?.task.reference ?? '' })"
        :style="{ width: '28rem' }"
        :breakpoints="{ '640px': '95vw' }"
        @update:visible="(visible) => !visible && abortCancel()">
        <form class="space-y-3" @submit.prevent="confirmCancel">
            <Textarea v-model="cancelNote" rows="3" maxlength="1000" autofocus autoResize fluid />
            <div class="flex justify-end gap-x-2">
                <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="abortCancel" />
                <Button type="submit" severity="danger" :label="ctrans('Confirm')" :disabled="!cancelNote.trim()" />
            </div>
        </form>
    </Dialog>
    <StaffTaskQuickLook v-model:task="quickLook" @closed="closeQuickLook" />
</template>

<style scoped>
.thinScrollbar {
    scrollbar-width: thin;
    scrollbar-color: theme('colors.gray.300') transparent;
}
</style>
