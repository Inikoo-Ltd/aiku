<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { Head, Link, router, useForm } from "@inertiajs/vue3"
import { Dialog, Select, MultiSelect } from "primevue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketProjectProgress from "@/Components/Tickets/TicketProjectProgress.vue"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faProjectDiagram, faPlus, faPencil, faTimes, faCheckSquare, faSquare, faLink, faChevronDown, faChevronRight, faArrowUp, faArrowDown, faSearch, faList, faColumns, faPlusCircle, faCheckCircle, faRocket, faFlagCheckered, faCommentAltLines, faTachometerAlt, faTasks, faChartLine, faHistory, faCodeCommit, faExclamationTriangle, faClock, faCircle, faCalendarDay } from "@fal"

library.add(faProjectDiagram, faPlus, faPencil, faTimes, faCheckSquare, faSquare, faLink, faChevronDown, faChevronRight, faArrowUp, faArrowDown, faSearch, faList, faColumns, faPlusCircle, faCheckCircle, faRocket, faFlagCheckered, faCommentAltLines, faTachometerAlt, faTasks, faChartLine, faHistory, faCodeCommit, faExclamationTriangle, faClock, faCircle, faCalendarDay)

type Person = { id: number; name: string; avatar: Record<string, string> | null }
type Route = { name: string; parameters?: Record<string, unknown> }
type Health = "on_track" | "at_risk" | "off_track"
type WorkState = "todo" | "in_progress" | "done" | "cancelled"
type Milestone = { id?: number; name: string; description: string | null; start_date: string | null; due_date: string | null; done_at: string | null; total: number; done: number }
type WorkItem = {
    key: string
    type: "ticket" | "task"
    id: number
    reference: string
    subject: string
    url: string
    status: string
    status_label: string
    status_icon: { icon: string; class: string }
    state: WorkState
    priority: "urgent" | "high" | "normal" | "low"
    assignee: Person | null
    milestone_id: number | null
    created_at: string
    updated_at: string
    done_at: string | null
    project_route: Route
}

const props = defineProps<{
    pageHead: any
    title: string
    project: {
        id: number
        slug: string
        name: string
        description: string | null
        status: string
        status_label: string
        start_date: string
        target_date: string | null
        owner: Person | null
        members: Person[]
        health: Health | null
        health_label: string | null
        health_at: string | null
    }
    milestones: Milestone[]
    progress: { total: number; done: number; open: number; percent: number | null; time_percent: number | null; week: number; weeks: number | null; days_left: number | null }
    hidden_work: number
    work: WorkItem[]
    burn_up: { date: string; scope: number; done: number }[]
    commits: { hash: string; subject: string | null; version: string | null; deployed_at: string | null; reference: string; url: string }[]
    activity: { at: string; icon: string; text: string; url: string | null; by: string | null }[]
    workload: { person: Person | null; todo: number; in_progress: number; done: number }[]
    updates: { id: number; body: string; health: Health | null; health_label: string | null; author: Person | null; created_at: string }[]
    can_edit: boolean
    options: { statuses: { label: string; value: string }[]; healths: { label: string; value: string }[]; staff: { label: string; value: number }[] }
    routes: { update: Route; post_update: Route; attach_work: Route; create_ticket: Route }
}>()

type TabKey = "overview" | "work" | "timeline" | "commits" | "activity"

const tabs: { key: TabKey; label: string; icon: string; alignRight?: boolean }[] = [
    { key: "overview", label: ctrans("Overview"), icon: "fal fa-tachometer-alt" },
    { key: "work", label: ctrans("Work"), icon: "fal fa-tasks" },
    { key: "timeline", label: ctrans("Timeline"), icon: "fal fa-chart-line" },
    { key: "activity", label: ctrans("Activity"), icon: "fal fa-history" },
    { key: "commits", label: ctrans("Commits"), icon: "fal fa-code-commit", alignRight: true },
]

const activeTab = ref<TabKey>("overview")
onMounted(() => {
    const fromHash = window.location.hash.replace("#", "") as TabKey
    if (tabs.some((tab) => tab.key === fromHash)) {
        activeTab.value = fromHash
    }
})
const selectTab = (key: TabKey) => {
    activeTab.value = key
    window.history.replaceState(window.history.state, "", "#" + key)
}

const toIso = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const parseDay = (value: string) => new Date(value.slice(0, 10) + "T00:00:00")
const addDays = (date: Date, days: number) => {
    const next = new Date(date)
    next.setDate(next.getDate() + days)
    return next
}
const todayIso = toIso(new Date())
const shortDate = (value: string | Date) => (typeof value === "string" ? parseDay(value) : value).toLocaleDateString(undefined, { day: "numeric", month: "short" })

const healthClasses: Record<string, string> = {
    on_track: "bg-green-100 text-green-700",
    at_risk: "bg-amber-100 text-amber-700",
    off_track: "bg-red-100 text-red-700",
}
const healthDots: Record<string, string> = { on_track: "bg-green-500", at_risk: "bg-amber-500", off_track: "bg-red-500" }
const priorityClasses: Record<string, string> = { urgent: "font-medium text-red-600", high: "text-amber-600" }

const isCreatingTask = ref(false)
const onTaskCreated = () => {
    isCreatingTask.value = false
    router.reload({ preserveScroll: true })
}

const isEditing = ref(false)
const editForm = useForm({
    name: props.project.name,
    description: props.project.description ?? "",
    status: props.project.status,
    start_date: props.project.start_date,
    target_date: props.project.target_date ?? "",
    owner_id: props.project.owner?.id ?? null,
    member_ids: props.project.members.map((member) => member.id),
})
const openEditor = () => {
    editForm.defaults({
        name: props.project.name,
        description: props.project.description ?? "",
        status: props.project.status,
        start_date: props.project.start_date,
        target_date: props.project.target_date ?? "",
        owner_id: props.project.owner?.id ?? null,
        member_ids: props.project.members.map((member) => member.id),
    })
    editForm.reset()
    isEditing.value = true
}
const saveProject = () =>
    editForm
        .transform((data) => ({ ...data, target_date: data.target_date || null }))
        .patch(route(props.routes.update.name, props.routes.update.parameters), { preserveScroll: true, onSuccess: () => (isEditing.value = false) })

const updateForm = useForm({ body: "", health: null as string | null })
const toggleUpdateHealth = (value: string) => (updateForm.health = updateForm.health === value ? null : value)
const postUpdate = () =>
    updateForm.post(route(props.routes.post_update.name, props.routes.post_update.parameters), { preserveScroll: true, onSuccess: () => updateForm.reset() })

const milestoneDraft = ref<Milestone[]>([])
watch(() => props.milestones, (milestones) => (milestoneDraft.value = milestones.map((milestone) => ({ ...milestone }))), { immediate: true })

const isSavingMilestones = ref(false)
const persistMilestones = () => {
    if (isSavingMilestones.value) {
        return
    }
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        {
            milestones: milestoneDraft.value
                .filter((milestone) => milestone.name.trim())
                .map((milestone) => ({
                    ...(milestone.id ? { id: milestone.id } : {}),
                    name: milestone.name.trim(),
                    description: milestone.description,
                    start_date: milestone.start_date || null,
                    due_date: milestone.due_date || null,
                    done: !!milestone.done_at,
                })),
        },
        { preserveScroll: true, onStart: () => (isSavingMilestones.value = true), onFinish: () => (isSavingMilestones.value = false) }
    )
}
const toggleMilestone = (milestone: Milestone) => {
    if (isSavingMilestones.value) {
        return
    }
    milestone.done_at = milestone.done_at ? null : todayIso
    persistMilestones()
}
const removeMilestone = (index: number) => {
    if (isSavingMilestones.value) {
        return
    }
    milestoneDraft.value.splice(index, 1)
    persistMilestones()
}
const moveMilestone = (index: number, step: number) => {
    if (isSavingMilestones.value) {
        return
    }
    const target = index + step
    if (target < 0 || target >= milestoneDraft.value.length) {
        return
    }
    const list = milestoneDraft.value
    ;[list[index], list[target]] = [list[target], list[index]]
    persistMilestones()
}
const newMilestone = ref({ name: "", due_date: "" })
const addMilestone = () => {
    if (isSavingMilestones.value) {
        return
    }
    if (!newMilestone.value.name.trim()) {
        return
    }
    milestoneDraft.value.push({ name: newMilestone.value.name, description: null, start_date: null, due_date: newMilestone.value.due_date || null, done_at: null, total: 0, done: 0 })
    newMilestone.value = { name: "", due_date: "" }
    persistMilestones()
}
const isOverdue = (milestone: Milestone) => !milestone.done_at && !!milestone.due_date && milestone.due_date < todayIso
const percentOf = (done: number, total: number) => (total ? Math.round((done / total) * 100) : 0)

const maxWorkload = computed(() => Math.max(1, ...props.workload.map((row) => row.todo + row.in_progress + row.done)))

const search = ref("")
const typeFilter = ref<"all" | "ticket" | "task">("all")
const milestoneFilter = ref<"all" | "none" | number>("all")
const assigneeFilter = ref<"all" | "none" | number>("all")
const showClosed = ref(false)
const workView = ref<"list" | "board">("list")
const collapsedGroups = ref<Record<string, boolean>>({})

const milestoneOptions = computed(() => props.milestones.map((milestone) => ({ label: milestone.name, value: milestone.id as number })))

const assignees = computed(() => {
    const people = new Map<number, Person>()
    props.work.forEach((item) => item.assignee && people.set(item.assignee.id, item.assignee))
    return [...people.values()].sort((a, b) => a.name.localeCompare(b.name))
})

const filteredWork = computed(() => {
    const needle = search.value.trim().toLowerCase()
    return props.work.filter((item) => {
        if (typeFilter.value !== "all" && item.type !== typeFilter.value) return false
        if (milestoneFilter.value === "none" && item.milestone_id !== null) return false
        if (typeof milestoneFilter.value === "number" && item.milestone_id !== milestoneFilter.value) return false
        if (assigneeFilter.value === "none" && item.assignee) return false
        if (typeof assigneeFilter.value === "number" && item.assignee?.id !== assigneeFilter.value) return false
        if (needle && !`${item.reference} ${item.subject}`.toLowerCase().includes(needle)) return false
        if (item.state === "cancelled" && !showClosed.value) return false
        return true
    })
})

const listItems = computed(() => filteredWork.value.filter((item) => showClosed.value || item.state === "todo" || item.state === "in_progress"))

type Group = { key: string; milestone: Milestone | null; items: WorkItem[]; total: number; done: number }

const groups = computed<Group[]>(() => {
    const build = (key: string, milestone: Milestone | null, milestoneId: number | null): Group => {
        const everything = props.work.filter((item) => item.milestone_id === milestoneId && item.state !== "cancelled")
        return {
            key,
            milestone,
            items: listItems.value.filter((item) => item.milestone_id === milestoneId),
            total: everything.length,
            done: everything.filter((item) => item.state === "done").length,
        }
    }
    const result = props.milestones.map((milestone) => build("m" + milestone.id, milestone, milestone.id as number))
    result.push(build("none", null, null))
    return result.filter((group) => group.items.length)
})

const boardColumns = computed(() => {
    const columns: { state: WorkState; label: string }[] = [
        { state: "todo", label: ctrans("Todo") },
        { state: "in_progress", label: ctrans("In progress") },
        { state: "done", label: ctrans("Done") },
    ]
    if (showClosed.value) {
        columns.push({ state: "cancelled", label: ctrans("Cancelled") })
    }
    return columns.map((column) => ({ ...column, items: filteredWork.value.filter((item) => item.state === column.state) }))
})

const moveItem = (item: WorkItem, milestoneId: number | null) =>
    router.patch(route(item.project_route.name, item.project_route.parameters), { ticket_project_milestone_id: milestoneId }, { preserveScroll: true })
const removeItem = (item: WorkItem) =>
    router.patch(route(item.project_route.name, item.project_route.parameters), { ticket_project_id: null }, { preserveScroll: true })

const attachForm = useForm({ references: "", ticket_project_milestone_id: null as number | null })
const attachWork = () =>
    attachForm.post(route(props.routes.attach_work.name, props.routes.attach_work.parameters), { preserveScroll: true, onSuccess: () => attachForm.reset("references") })

type MilestoneState = "done" | "late" | "active" | "upcoming"
type TimelineRow = { milestone: Milestone; state: MilestoneState; kind: "bar" | "marker" | "none"; left: number; width: number; percent: number; when: string }

const milestoneStateOf = (milestone: Milestone): MilestoneState => {
    if (milestone.done_at) return "done"
    if (isOverdue(milestone)) return "late"
    const startsOn = milestone.start_date ?? milestone.due_date
    if (startsOn && startsOn <= todayIso) return "active"
    return "upcoming"
}
const milestoneStyles: Record<MilestoneState, { fill: string; track: string; icon: string; iconClass: string; label: string }> = {
    done: { fill: "bg-green-500", track: "bg-green-100", icon: "fal fa-check-circle", iconClass: "text-green-600", label: ctrans("Done") },
    active: { fill: "bg-blue-500", track: "bg-blue-100", icon: "fal fa-clock", iconClass: "text-blue-600", label: ctrans("In progress") },
    late: { fill: "bg-red-500", track: "bg-red-100", icon: "fal fa-exclamation-triangle", iconClass: "text-red-600", label: ctrans("Late") },
    upcoming: { fill: "bg-gray-400", track: "bg-gray-100", icon: "fal fa-circle", iconClass: "text-gray-400", label: ctrans("Upcoming") },
}
const timelineColumns = "grid grid-cols-[minmax(9rem,16rem)_minmax(0,1fr)]"

const timeline = computed(() => {
    const start = parseDay(props.project.start_date)
    const dueTimes = props.milestones.filter((milestone) => milestone.due_date).map((milestone) => parseDay(milestone.due_date as string).getTime())
    const candidates = [...dueTimes, props.project.target_date ? parseDay(props.project.target_date).getTime() : 0]
    let endTime = Math.max(...candidates)
    if (endTime <= start.getTime()) {
        endTime = addDays(start, 56).getTime()
    }
    const end = addDays(new Date(endTime), 3)
    const span = end.getTime() - start.getTime()
    const position = (date: Date) => Math.min(100, Math.max(0, ((date.getTime() - start.getTime()) / span) * 100))

    const totalWeeks = Math.ceil(span / (7 * 86400000))
    const stepWeeks = Math.max(1, Math.ceil(totalWeeks / 12))
    const ticks: { left: number; label: string }[] = []
    for (let day = new Date(start); day.getTime() <= end.getTime(); day = addDays(day, stepWeeks * 7)) {
        ticks.push({ left: position(day), label: shortDate(day) })
    }

    const months: { left: number; width: number; label: string }[] = []
    for (let month = new Date(start.getFullYear(), start.getMonth(), 1); month.getTime() <= end.getTime(); month = new Date(month.getFullYear(), month.getMonth() + 1, 1)) {
        const left = position(month)
        const width = position(new Date(month.getFullYear(), month.getMonth() + 1, 1)) - left
        months.push({ left, width, label: month.toLocaleDateString(undefined, width > 12 ? { month: "long" } : { month: "short" }) })
    }

    const rows: TimelineRow[] = props.milestones.map((milestone) => {
        const state = milestoneStateOf(milestone)
        const percent = milestone.total ? percentOf(milestone.done, milestone.total) : (milestone.done_at ? 100 : 0)
        if (!milestone.due_date) {
            return { milestone, state, kind: "none", left: 0, width: 0, percent, when: milestone.start_date ? shortDate(milestone.start_date) : "" }
        }
        const right = position(parseDay(milestone.due_date))
        if (!milestone.start_date) {
            return { milestone, state, kind: "marker", left: right, width: 0, percent, when: shortDate(milestone.due_date) }
        }
        const left = position(parseDay(milestone.start_date < milestone.due_date ? milestone.start_date : milestone.due_date))
        return { milestone, state, kind: "bar", left, width: Math.max(1.5, right - left), percent, when: `${shortDate(milestone.start_date)} – ${shortDate(milestone.due_date)}` }
    })

    const today = parseDay(todayIso)
    const todayLeft = today.getTime() >= start.getTime() && today.getTime() <= end.getTime() ? position(today) : null
    const targetLeft = props.project.target_date ? position(parseDay(props.project.target_date)) : null

    return { ticks, months, rows, todayLeft, targetLeft }
})

const expandedMilestones = ref<Record<number, boolean>>({})
const toggleTimelineRow = (milestone: Milestone) => {
    if (milestone.total) {
        expandedMilestones.value[milestone.id as number] = !expandedMilestones.value[milestone.id as number]
    }
}
const workOfMilestone = (milestone: Milestone) => props.work.filter((item) => item.milestone_id === milestone.id && item.state !== "cancelled")
const openMilestoneInWork = (milestone: Milestone) => {
    milestoneFilter.value = milestone.id as number
    showClosed.value = true
    selectTab("work")
}

const burnUpChart = computed(() => {
    const dates = props.burn_up.map((week) => week.date)
    const targetWeek = props.project.target_date ? toIso(addDays(parseDay(props.project.target_date), -((parseDay(props.project.target_date).getDay() + 6) % 7))) : null
    let cursor = dates.length ? parseDay(dates[dates.length - 1]) : null
    while (cursor && targetWeek && toIso(cursor) < targetWeek) {
        cursor = addDays(cursor, 7)
        dates.push(toIso(cursor))
    }
    const finalScope = props.burn_up.length ? props.burn_up[props.burn_up.length - 1].scope : 0
    const pad = (values: number[]) => [...values, ...Array(Math.max(0, dates.length - values.length)).fill(null)]
    const datasets: Record<string, unknown>[] = [
        { label: ctrans("Scope"), data: pad(props.burn_up.map((week) => week.scope)), borderColor: "#c0399f", backgroundColor: "#c0399f", tension: 0, borderWidth: 1.5, pointRadius: 2 },
        { label: ctrans("Done"), data: pad(props.burn_up.map((week) => week.done)), borderColor: "#1f845a", backgroundColor: "#1f845a", tension: 0, borderWidth: 1.5, pointRadius: 2 },
    ]
    if (props.project.target_date && dates.length > 1) {
        datasets.push({
            label: ctrans("Ideal"),
            data: dates.map((_date, index) => Math.round((index / (dates.length - 1)) * finalScope * 10) / 10),
            borderColor: "#9ca3af",
            backgroundColor: "#9ca3af",
            borderDash: [6, 4],
            tension: 0,
            borderWidth: 1.5,
            pointRadius: 0,
        })
    }
    return { labels: dates.map((date) => shortDate(date)), datasets }
})

const burnUpOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <div class="flex gap-2">
                <Link :href="route(routes.create_ticket.name, routes.create_ticket.parameters)">
                    <Button icon="fal fa-plus" :label="ctrans('New ticket')" type="secondary" />
                </Link>
                <Button icon="fal fa-plus" :label="ctrans('New task')" type="secondary" @click="isCreatingTask = true" />
                <Button v-if="can_edit" icon="fal fa-pencil" :label="ctrans('Edit')" type="tertiary" @click="openEditor" />
            </div>
        </template>
    </PageHeading>
    <StaffTaskDialog :is-open="isCreatingTask" :ticket-project-id="project.id" @close="isCreatingTask = false" @created="onTaskCreated" />

    <div class="grid gap-x-6 gap-y-3 border-b border-gray-200 px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-[auto_auto_auto_minmax(14rem,1fr)_auto]">
        <div>
            <p class="mb-1 text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Health") }}</p>
            <span v-if="project.health" class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" :class="healthClasses[project.health]">
                <span class="size-2 rounded-full" :class="healthDots[project.health]" />
                {{ project.health_label }}
                <span class="font-normal opacity-75">· {{ ctrans("as of :date", { date: useFormatTime(project.health_at ?? undefined, { formatTime: "mdy" }) }) }}</span>
            </span>
            <span v-else class="text-xs text-gray-400">{{ ctrans("No health set") }}</span>
        </div>
        <div>
            <p class="mb-1 text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Status") }}</p>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ project.status_label }}</span>
        </div>
        <div>
            <p class="mb-1 text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Owner and team") }}</p>
            <div class="flex items-center gap-1">
                <TicketUserAvatar v-if="project.owner" v-tooltip="ctrans('Owner') + ': ' + project.owner.name" :name="project.owner.name" :avatar="project.owner.avatar" size="sm" class="ring-2 ring-[--app-accent-strong]" />
                <TicketUserAvatar v-for="member in project.members" :key="member.id" v-tooltip="member.name" :name="member.name" :avatar="member.avatar" size="sm" />
                <span v-if="!project.owner && !project.members.length" class="text-xs text-gray-400">-</span>
            </div>
        </div>
        <TicketProjectProgress :progress="progress" :start-date="project.start_date" :target-date="project.target_date" />
        <div class="flex items-start gap-4 tabular-nums">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Open") }}</p>
                <p class="text-lg font-semibold text-gray-800">{{ progress.open }}</p>
            </div>
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Done") }}</p>
                <p class="text-lg font-semibold text-green-700">{{ progress.done }}</p>
            </div>
            <div v-if="progress.days_left !== null">
                <p class="text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Days left") }}</p>
                <p class="text-lg font-semibold" :class="progress.days_left < 0 ? 'text-red-600' : 'text-gray-800'">{{ progress.days_left }}</p>
            </div>
        </div>
    </div>

    <nav class="flex gap-1 overflow-x-auto border-b border-gray-200 px-4">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            class="-mb-px flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm"
            :class="[activeTab === tab.key ? 'border-[--app-accent-strong] font-medium text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800', { 'ml-auto': tab.alignRight }]"
            @click="selectTab(tab.key)">
            <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
            {{ tab.label }}
        </button>
    </nav>

    <div v-if="activeTab === 'overview'" class="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-6">
            <section v-if="project.description">
                <h2 class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Goal") }}</h2>
                <p class="whitespace-pre-line text-gray-700">{{ project.description }}</p>
            </section>

            <section>
                <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Progress updates") }}</h2>
                <form v-if="can_edit" class="mb-4 rounded-md border border-gray-200 p-2" @submit.prevent="postUpdate">
                    <textarea v-model="updateForm.body" rows="3" class="w-full rounded-md border-gray-300 text-sm" :placeholder="ctrans('What moved, what is blocked, what comes next…')" />
                    <p v-if="updateForm.errors.body" class="text-xs text-red-600">{{ updateForm.errors.body }}</p>
                    <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                        <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
                            <button
                                v-for="health in options.healths"
                                :key="health.value"
                                type="button"
                                class="flex items-center gap-1.5 border-r border-gray-300 px-2 py-1 last:border-r-0"
                                :class="updateForm.health === health.value ? healthClasses[health.value] : 'bg-white text-gray-600 hover:bg-gray-50'"
                                @click="toggleUpdateHealth(health.value)">
                                <span class="size-2 rounded-full" :class="healthDots[health.value]" />
                                {{ health.label }}
                            </button>
                        </div>
                        <Button :label="ctrans('Post update')" :disabled="!updateForm.body.trim()" :loading="updateForm.processing" @click="postUpdate" />
                    </div>
                </form>
                <p v-if="!updates.length" class="text-sm text-gray-500">{{ ctrans("No updates yet") }}</p>
                <ol class="space-y-4">
                    <li v-for="update in updates" :key="update.id" class="flex gap-3">
                        <TicketUserAvatar :name="update.author?.name ?? null" :avatar="update.author?.avatar" />
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                                <span class="font-medium text-gray-700">{{ update.author?.name ?? "-" }}</span>
                                <span>{{ useFormatTime(update.created_at, { formatTime: "hm" }) }}</span>
                                <span v-if="update.health" class="rounded-full px-2 py-0.5" :class="healthClasses[update.health]">{{ update.health_label }}</span>
                            </p>
                            <TicketBody :text="update.body" class="text-sm" />
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-lg border border-gray-200 p-4">
                <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Milestones") }}</h2>
                <p v-if="!milestoneDraft.length" class="text-sm text-gray-500">{{ ctrans("No milestones yet") }}</p>
                <ul class="space-y-3 text-sm">
                    <li v-for="(milestone, index) in milestoneDraft" :key="milestone.id ?? 'new-' + index" class="group">
                        <div class="flex items-start gap-2">
                            <button :disabled="!can_edit" class="mt-1" @click="toggleMilestone(milestone)">
                                <FontAwesomeIcon :icon="milestone.done_at ? 'fal fa-check-square' : 'fal fa-square'" :class="milestone.done_at ? 'text-green-600' : 'text-gray-400'" fixed-width />
                            </button>
                            <div class="min-w-0 flex-1">
                                <input
                                    v-if="can_edit"
                                    v-model="milestone.name"
                                    maxlength="255"
                                    class="w-full rounded-md border-transparent px-1 py-0.5 text-sm hover:border-gray-300"
                                    :class="milestone.done_at && 'text-gray-400 line-through'"
                                    @change="persistMilestones" />
                                <p v-else :class="milestone.done_at && 'text-gray-400 line-through'">{{ milestone.name }}</p>
                                <div v-if="can_edit" class="mt-0.5 flex gap-1">
                                    <input v-model="milestone.start_date" type="date" :title="ctrans('Start')" class="min-w-0 flex-1 rounded-md border-transparent px-1 py-0.5 text-xs text-gray-500 hover:border-gray-300" @change="persistMilestones" />
                                    <input v-model="milestone.due_date" type="date" :title="ctrans('Due')" class="min-w-0 flex-1 rounded-md border-transparent px-1 py-0.5 text-xs hover:border-gray-300" :class="isOverdue(milestone) ? 'font-semibold text-red-600' : 'text-gray-500'" @change="persistMilestones" />
                                </div>
                                <p v-else class="text-xs" :class="isOverdue(milestone) ? 'font-semibold text-red-600' : 'text-gray-400'">
                                    <template v-if="milestone.done_at">{{ ctrans("Done :date", { date: useFormatTime(milestone.done_at, { formatTime: "mdy" }) }) }}</template>
                                    <template v-else-if="milestone.due_date">{{ ctrans("Due :date", { date: useFormatTime(milestone.due_date, { formatTime: "mdy" }) }) }}</template>
                                </p>
                                <div class="mt-1 flex items-center gap-2 px-1">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full" :class="milestone.done_at ? 'bg-green-500' : isOverdue(milestone) ? 'bg-red-400' : 'bg-blue-500'" :style="{ width: percentOf(milestone.done, milestone.total) + '%' }" />
                                    </div>
                                    <span class="text-xs tabular-nums text-gray-500">{{ milestone.done }}/{{ milestone.total }}</span>
                                    <span v-if="isOverdue(milestone)" class="text-xs font-semibold text-red-600">{{ ctrans("Overdue") }}</span>
                                </div>
                            </div>
                            <div v-if="can_edit" class="flex flex-col text-gray-400 sm:invisible sm:group-hover:visible">
                                <button :disabled="index === 0" class="hover:text-gray-700 disabled:opacity-30" @click="moveMilestone(index, -1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width />
                                </button>
                                <button :disabled="index === milestoneDraft.length - 1" class="hover:text-gray-700 disabled:opacity-30" @click="moveMilestone(index, 1)">
                                    <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width />
                                </button>
                                <button class="hover:text-red-600" @click="removeMilestone(index)">
                                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <form v-if="can_edit" class="mt-3 space-y-1.5" @submit.prevent="addMilestone">
                    <input v-model="newMilestone.name" maxlength="255" class="w-full rounded-md border-gray-300 py-1 text-sm" :placeholder="ctrans('New milestone')" />
                    <div class="flex gap-2">
                        <input v-model="newMilestone.due_date" type="date" class="flex-1 rounded-md border-gray-300 py-1 text-sm" />
                        <Button icon="fal fa-plus" type="tertiary" size="xs" :disabled="!newMilestone.name.trim()" @click="addMilestone" />
                    </div>
                </form>
            </section>

            <section class="rounded-lg border border-gray-200 p-4">
                <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Workload") }}</h2>
                <p v-if="!workload.length" class="text-sm text-gray-500">{{ ctrans("No work yet") }}</p>
                <ul class="space-y-2 text-sm">
                    <li v-for="(row, index) in workload" :key="row.person?.id ?? 'none-' + index">
                        <div class="mb-0.5 flex items-center gap-2">
                            <TicketUserAvatar v-if="row.person" :name="row.person.name" :avatar="row.person.avatar" size="xs" />
                            <span class="min-w-0 flex-1 truncate">{{ row.person?.name ?? ctrans("Unassigned") }}</span>
                            <span class="text-xs tabular-nums text-gray-500">{{ row.todo }} / {{ row.in_progress }} / {{ row.done }}</span>
                        </div>
                        <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-100" :style="{ width: ((row.todo + row.in_progress + row.done) / maxWorkload) * 100 + '%' }">
                            <div class="bg-gray-400" :style="{ flexGrow: row.todo }" />
                            <div class="bg-blue-500" :style="{ flexGrow: row.in_progress }" />
                            <div class="bg-green-500" :style="{ flexGrow: row.done }" />
                        </div>
                    </li>
                </ul>
                <p v-if="workload.length" class="mt-2 text-xs text-gray-400">{{ ctrans("Todo / in progress / done") }}</p>
            </section>
        </aside>
    </div>

    <div v-else-if="activeTab === 'work'" class="space-y-3 p-4">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <div class="relative">
                <FontAwesomeIcon icon="fal fa-search" class="pointer-events-none absolute left-2 top-2 text-gray-400" fixed-width />
                <input v-model="search" class="w-48 rounded-md border-gray-300 py-1 pl-8 text-sm" :placeholder="ctrans('Search')" />
            </div>
            <select v-model="typeFilter" class="rounded-md border-gray-300 py-1 text-sm">
                <option value="all">{{ ctrans("Tickets and tasks") }}</option>
                <option value="ticket">{{ ctrans("Tickets") }}</option>
                <option value="task">{{ ctrans("Tasks") }}</option>
            </select>
            <select v-model="milestoneFilter" class="rounded-md border-gray-300 py-1 text-sm">
                <option value="all">{{ ctrans("All milestones") }}</option>
                <option value="none">{{ ctrans("No milestone") }}</option>
                <option v-for="milestone in milestones" :key="milestone.id" :value="milestone.id">{{ milestone.name }}</option>
            </select>
            <select v-model="assigneeFilter" class="rounded-md border-gray-300 py-1 text-sm">
                <option value="all">{{ ctrans("Everyone") }}</option>
                <option value="none">{{ ctrans("Unassigned") }}</option>
                <option v-for="person in assignees" :key="person.id" :value="person.id">{{ person.name }}</option>
            </select>
            <label class="flex items-center gap-1.5 text-gray-600">
                <input v-model="showClosed" type="checkbox" class="rounded border-gray-300" />
                {{ ctrans("Show closed") }}
            </label>
            <div class="ml-auto inline-flex overflow-hidden rounded-md border border-gray-300">
                <button class="flex items-center gap-1.5 px-2 py-1" :class="workView === 'list' ? 'bg-gray-100 font-medium' : 'text-gray-600 hover:bg-gray-50'" @click="workView = 'list'">
                    <FontAwesomeIcon icon="fal fa-list" fixed-width />{{ ctrans("List") }}
                </button>
                <button class="flex items-center gap-1.5 border-l border-gray-300 px-2 py-1" :class="workView === 'board' ? 'bg-gray-100 font-medium' : 'text-gray-600 hover:bg-gray-50'" @click="workView = 'board'">
                    <FontAwesomeIcon icon="fal fa-columns" fixed-width />{{ ctrans("Board") }}
                </button>
            </div>
        </div>

        <div v-if="can_edit">
            <form class="flex flex-wrap items-center gap-2" @submit.prevent="attachWork">
                <input v-model="attachForm.references" class="min-w-0 flex-1 rounded-md border-gray-300 py-1 text-sm sm:max-w-md" :placeholder="ctrans('Add tickets or tasks: HELP-123, TASK-45')" />
                <Select v-model="attachForm.ticket_project_milestone_id" :options="milestoneOptions" option-label="label" option-value="value" show-clear size="small" class="w-48" :placeholder="ctrans('No milestone')" />
                <Button icon="fal fa-link" :label="ctrans('Add')" type="tertiary" size="xs" :loading="attachForm.processing" :disabled="!attachForm.references.trim()" @click="attachWork" />
            </form>
            <p v-if="attachForm.errors.references" class="mt-1 text-xs text-red-600">{{ attachForm.errors.references }}</p>
        </div>

        <p v-if="!filteredWork.length" class="rounded-md border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500">{{ ctrans("No work matches") }}</p>

        <div v-if="workView === 'list'" class="space-y-3">
            <section v-for="group in groups" :key="group.key" class="rounded-md border border-gray-200">
                <button class="flex w-full items-center gap-2 bg-gray-50 px-3 py-1.5 text-left text-sm" @click="collapsedGroups[group.key] = !collapsedGroups[group.key]">
                    <FontAwesomeIcon :icon="collapsedGroups[group.key] ? 'fal fa-chevron-right' : 'fal fa-chevron-down'" class="text-gray-400" fixed-width />
                    <span class="font-medium">{{ group.milestone?.name ?? ctrans("No milestone") }}</span>
                    <span class="text-xs text-gray-500">{{ group.done }}/{{ group.total }}</span>
                    <span class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-200">
                        <span class="block h-full rounded-full bg-green-500" :style="{ width: percentOf(group.done, group.total) + '%' }" />
                    </span>
                    <span v-if="group.milestone?.due_date" class="ml-auto text-xs" :class="isOverdue(group.milestone) ? 'font-semibold text-red-600' : 'text-gray-400'">{{ ctrans("Due :date", { date: useFormatTime(group.milestone.due_date, { formatTime: "mdy" }) }) }}</span>
                </button>
                <ul v-show="!collapsedGroups[group.key]" class="divide-y divide-gray-100">
                    <li v-for="item in group.items" :key="item.key" class="group flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 hover:bg-gray-50" :class="(item.state === 'done' || item.state === 'cancelled') && 'opacity-70'">
                        <FontAwesomeIcon v-tooltip="item.status_label" :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
                        <Link :href="item.url" class="min-w-0 flex-1 basis-60 truncate">
                            <span class="mr-2 font-mono text-xs text-gray-500">{{ item.reference }}</span>
                            <span :class="priorityClasses[item.priority]">{{ item.subject }}</span>
                        </Link>
                        <TicketUserAvatar v-if="item.assignee" v-tooltip="item.assignee.name" :name="item.assignee.name" :avatar="item.assignee.avatar" size="sm" />
                        <Select v-if="can_edit" :model-value="item.milestone_id" :options="milestoneOptions" option-label="label" option-value="value" show-clear size="small" class="w-44" :placeholder="ctrans('No milestone')" @update:model-value="moveItem(item, $event ?? null)" />
                        <button v-if="can_edit" v-tooltip="ctrans('Take out of the project')" class="text-gray-400 hover:text-red-600 sm:invisible sm:group-hover:visible" @click="removeItem(item)">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width />
                        </button>
                    </li>
                </ul>
            </section>
        </div>

        <div v-else class="grid gap-3 md:grid-cols-3" :class="showClosed && 'xl:grid-cols-4'">
            <section v-for="column in boardColumns" :key="column.state" class="rounded-md bg-gray-50 p-2">
                <h3 class="mb-2 flex items-center justify-between px-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                    {{ column.label }}
                    <span class="tabular-nums">{{ column.items.length }}</span>
                </h3>
                <div class="space-y-2">
                    <Link v-for="item in column.items" :key="item.key" :href="item.url" class="block rounded-md border border-gray-200 bg-white p-2 text-sm hover:border-gray-400">
                        <p class="mb-1 flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
                            <span class="font-mono">{{ item.reference }}</span>
                        </p>
                        <p :class="priorityClasses[item.priority]">{{ item.subject }}</p>
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-gray-400">{{ milestones.find((milestone) => milestone.id === item.milestone_id)?.name }}</span>
                            <TicketUserAvatar v-if="item.assignee" v-tooltip="item.assignee.name" :name="item.assignee.name" :avatar="item.assignee.avatar" size="xs" />
                        </div>
                    </Link>
                </div>
            </section>
        </div>

        <p v-if="hidden_work > 0" class="text-xs text-gray-400">{{ ctrans(":count confidential items not shown", { count: hidden_work }) }}</p>
    </div>

    <div v-else-if="activeTab === 'timeline'" class="space-y-6 p-4">
        <section>
            <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                <h2 class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Milestones") }}</h2>
                <ul class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                    <li v-for="(style, state) in milestoneStyles" :key="state" class="flex items-center gap-1">
                        <FontAwesomeIcon :icon="style.icon" :class="style.iconClass" fixed-width />
                        {{ style.label }}
                    </li>
                    <li class="flex items-center gap-1.5">
                        <span class="size-2 rotate-45 bg-gray-400" />
                        {{ ctrans("Deadline") }}
                    </li>
                </ul>
            </div>
            <p v-if="!milestones.length" class="text-sm text-gray-500">{{ ctrans("No milestones yet") }}</p>
            <div v-else class="overflow-x-auto rounded-md border border-gray-200">
                <div class="min-w-[560px] sm:min-w-0">
                    <div :class="timelineColumns" class="border-b border-gray-200 bg-gray-50 text-xs text-gray-600">
                        <div class="self-end px-3 pb-1 font-medium">{{ ctrans("Milestone") }}</div>
                        <div class="relative pr-3">
                            <div class="relative h-6">
                                <span
                                    v-if="timeline.todayLeft !== null"
                                    class="absolute top-1 z-10 flex -translate-x-1/2 items-center gap-1 whitespace-nowrap rounded-full bg-red-500 px-1.5 py-0.5 font-medium text-white"
                                    :style="{ left: timeline.todayLeft + '%' }">
                                    <FontAwesomeIcon icon="fal fa-calendar-day" fixed-width />
                                    {{ ctrans("Today") }}
                                </span>
                                <span
                                    v-if="timeline.targetLeft !== null"
                                    v-tooltip="ctrans('Target :date', { date: useFormatTime(project.target_date ?? undefined, { formatTime: 'mdy' }) })"
                                    class="absolute top-1 flex -translate-x-full items-center gap-1 whitespace-nowrap pr-1.5 font-medium text-gray-700"
                                    :style="{ left: timeline.targetLeft + '%' }">
                                    <FontAwesomeIcon icon="fal fa-flag-checkered" fixed-width />
                                    {{ ctrans("Target") }}
                                </span>
                            </div>
                            <div class="relative h-5">
                                <span v-for="month in timeline.months" :key="month.label + month.left" class="absolute inset-y-0 overflow-hidden truncate border-l border-gray-300 pl-1.5 font-medium leading-5" :style="{ left: month.left + '%', width: month.width + '%' }">
                                    <template v-if="month.width > 5">{{ month.label }}</template>
                                </span>
                            </div>
                            <div class="relative h-5 text-gray-500">
                                <span v-for="tick in timeline.ticks" :key="tick.left" class="absolute -translate-x-1/2 whitespace-nowrap tabular-nums leading-5" :style="{ left: tick.left + '%' }">{{ tick.label }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-for="row in timeline.rows" :key="row.milestone.id" class="border-b border-gray-100 last:border-b-0">
                        <div
                            :class="[timelineColumns, row.milestone.total ? 'cursor-pointer hover:bg-gray-50 focus:outline-none focus-visible:bg-gray-50' : '']"
                            :role="row.milestone.total ? 'button' : undefined"
                            :tabindex="row.milestone.total ? 0 : undefined"
                            :aria-expanded="row.milestone.total ? !!expandedMilestones[row.milestone.id as number] : undefined"
                            @click="toggleTimelineRow(row.milestone)"
                            @keydown.enter.prevent="toggleTimelineRow(row.milestone)"
                            @keydown.space.prevent="toggleTimelineRow(row.milestone)">
                            <div class="flex items-start gap-1.5 py-2 pl-2 pr-3 text-sm">
                                <FontAwesomeIcon :icon="expandedMilestones[row.milestone.id as number] ? 'fal fa-chevron-down' : 'fal fa-chevron-right'" class="mt-1 text-gray-400" :class="!row.milestone.total && 'invisible'" fixed-width />
                                <div class="min-w-0">
                                    <p v-tooltip="row.milestone.name" class="line-clamp-2 text-gray-700" :class="row.state === 'done' && 'text-gray-500'">
                                        <FontAwesomeIcon :icon="milestoneStyles[row.state].icon" :class="milestoneStyles[row.state].iconClass" fixed-width />
                                        {{ row.milestone.name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500" :class="row.state === 'late' && 'text-red-600'">
                                        <template v-if="row.milestone.done_at">{{ ctrans("Done :date", { date: shortDate(row.milestone.done_at) }) }}</template>
                                        <template v-else-if="row.kind === 'none'">{{ ctrans("No due date") }}</template>
                                        <template v-else-if="row.state === 'late'">{{ ctrans("Overdue since :date", { date: shortDate(row.milestone.due_date as string) }) }}</template>
                                        <template v-else>{{ row.when }}</template>
                                    </p>
                                </div>
                            </div>
                            <div class="relative min-h-[3.25rem] pr-3">
                                <span v-for="tick in timeline.ticks" :key="tick.left" class="absolute inset-y-0 border-l border-gray-100" :style="{ left: tick.left + '%' }" />
                                <span v-if="timeline.targetLeft !== null" class="absolute inset-y-0 border-l border-dashed border-gray-400" :style="{ left: timeline.targetLeft + '%' }" />
                                <span v-if="timeline.todayLeft !== null" class="absolute inset-y-0 z-10 border-l-2 border-red-400" :style="{ left: timeline.todayLeft + '%' }" />
                                <template v-if="row.kind === 'bar'">
                                    <div
                                        v-tooltip="row.milestone.total ? `${row.milestone.name} · ${row.milestone.done}/${row.milestone.total}` : row.milestone.name"
                                        class="absolute top-1/2 h-4 -translate-y-1/2 overflow-hidden rounded"
                                        :class="row.milestone.total || row.milestone.done_at ? milestoneStyles[row.state].track : 'border border-dashed border-gray-300'"
                                        :style="{ left: row.left + '%', width: row.width + '%' }">
                                        <div v-if="row.milestone.total || row.milestone.done_at" class="h-full rounded" :class="milestoneStyles[row.state].fill" :style="{ width: row.percent + '%' }" />
                                    </div>
                                </template>
                                <span
                                    v-else-if="row.kind === 'marker'"
                                    v-tooltip="row.milestone.name"
                                    class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-sm"
                                    :class="row.milestone.total || row.milestone.done_at ? milestoneStyles[row.state].fill : 'border border-dashed border-gray-400 bg-white'"
                                    :style="{ left: row.left + '%' }" />
                                <span
                                    v-if="row.kind !== 'none' && row.milestone.total"
                                    class="absolute top-1/2 -translate-y-1/2 whitespace-nowrap text-xs tabular-nums text-gray-700"
                                    :style="row.left + row.width <= 88 ? { left: `calc(${row.left + row.width}% + 8px)` } : { right: `calc(${100 - row.left}% + 8px)` }">
                                    {{ row.milestone.done }}/{{ row.milestone.total }}
                                </span>
                            </div>
                        </div>
                        <div v-if="expandedMilestones[row.milestone.id as number]" class="border-t border-gray-100 bg-gray-50/60">
                            <ul class="divide-y divide-gray-100">
                                <li v-for="item in workOfMilestone(row.milestone)" :key="item.key" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-1.5 pl-9 pr-3 text-sm" :class="item.state === 'done' && 'opacity-70'">
                                    <FontAwesomeIcon v-tooltip="item.status_label" :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
                                    <Link :href="item.url" class="min-w-0 flex-1 basis-60 truncate hover:underline">
                                        <span class="mr-2 font-mono text-xs text-gray-500">{{ item.reference }}</span>
                                        <span class="text-gray-700" :class="priorityClasses[item.priority]">{{ item.subject }}</span>
                                    </Link>
                                    <span class="text-xs text-gray-500">{{ item.status_label }}</span>
                                    <TicketUserAvatar v-if="item.assignee" v-tooltip="item.assignee.name" :name="item.assignee.name" :avatar="item.assignee.avatar" size="sm" />
                                    <span v-else class="text-xs text-gray-500">{{ ctrans("Unassigned") }}</span>
                                </li>
                            </ul>
                            <button class="flex items-center gap-1.5 py-1.5 pl-9 pr-3 text-xs text-gray-500 hover:text-gray-800" @click="openMilestoneInWork(row.milestone)">
                                <FontAwesomeIcon icon="fal fa-tasks" fixed-width />
                                {{ ctrans("Open in Work") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Burn-up") }}</h2>
            <p v-if="!burn_up.length" class="text-sm text-gray-500">{{ ctrans("Not enough data yet") }}</p>
            <div v-else class="h-56 rounded-md border border-gray-200 p-3">
                <Chart type="line" :data="burnUpChart" :options="burnUpOptions" class="h-full" />
            </div>
        </section>
    </div>

    <div v-else-if="activeTab === 'commits'" class="p-4">
        <p v-if="!commits.length" class="rounded-md border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500">{{ ctrans("No commits recorded on this project's tickets yet") }}</p>
        <div v-else class="overflow-x-auto rounded-md border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2">{{ ctrans("Version") }}</th>
                        <th class="px-3 py-2">{{ ctrans("Deployed") }}</th>
                        <th class="px-3 py-2">{{ ctrans("Commit") }}</th>
                        <th class="px-3 py-2">{{ ctrans("Subject") }}</th>
                        <th class="px-3 py-2">{{ ctrans("Ticket") }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="commit in commits" :key="commit.hash + commit.reference">
                        <td class="whitespace-nowrap px-3 py-2">{{ commit.version ?? "-" }}</td>
                        <td class="whitespace-nowrap px-3 py-2">{{ commit.deployed_at ? useFormatTime(commit.deployed_at, { formatTime: "mdy" }) : "-" }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ commit.hash.slice(0, 10) }}</td>
                        <td class="px-3 py-2">{{ commit.subject }}</td>
                        <td class="whitespace-nowrap px-3 py-2">
                            <Link :href="commit.url" class="font-mono text-xs underline">{{ commit.reference }}</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div v-else class="p-4">
        <p v-if="!activity.length" class="text-sm text-gray-500">{{ ctrans("No activity yet") }}</p>
        <ol class="ml-3 border-l border-gray-200">
            <li v-for="(event, index) in activity" :key="index" class="relative pb-4 pl-6">
                <span class="absolute -left-3 top-0 flex size-6 items-center justify-center rounded-full bg-white text-gray-500">
                    <FontAwesomeIcon :icon="event.icon" fixed-width />
                </span>
                <p class="text-xs text-gray-400">{{ useFormatTime(event.at, { formatTime: "hm" }) }}<template v-if="event.by"> · {{ event.by }}</template></p>
                <Link v-if="event.url" :href="event.url" class="text-sm hover:underline">{{ event.text }}</Link>
                <p v-else class="text-sm">{{ event.text }}</p>
            </li>
        </ol>
    </div>

    <Dialog v-model:visible="isEditing" modal :header="ctrans('Edit project')" class="w-full max-w-lg">
        <form class="space-y-3" @submit.prevent="saveProject">
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }}</label>
                <input v-model="editForm.name" maxlength="255" required class="w-full rounded-md border-gray-300 text-sm" />
                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Goal") }}</label>
                <textarea v-model="editForm.description" rows="5" class="w-full rounded-md border-gray-300 text-sm" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Status") }}</label>
                    <select v-model="editForm.status" class="w-full rounded-md border-gray-300 text-sm">
                        <option v-for="status in options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Start") }}</label>
                    <input v-model="editForm.start_date" type="date" required class="w-full rounded-md border-gray-300 text-sm" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target") }}</label>
                    <input v-model="editForm.target_date" type="date" class="w-full rounded-md border-gray-300 text-sm" />
                </div>
            </div>
            <p v-if="editForm.errors.start_date || editForm.errors.target_date" class="text-xs text-red-600">{{ editForm.errors.start_date || editForm.errors.target_date }}</p>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Owner") }}</label>
                <Select v-model="editForm.owner_id" :options="options.staff" option-label="label" option-value="value" filter show-clear class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Team") }}</label>
                <MultiSelect v-model="editForm.member_ids" :options="options.staff" option-label="label" option-value="value" filter display="chip" class="w-full" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isEditing = false" />
                <Button :label="ctrans('Save')" :loading="editForm.processing" @click="saveProject" />
            </div>
        </form>
    </Dialog>
</template>
