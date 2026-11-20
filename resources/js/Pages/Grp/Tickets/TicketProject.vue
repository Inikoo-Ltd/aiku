<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 03 Oct 2026 12:59:07 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { Head, Link, router, useForm } from "@inertiajs/vue3"
import { Dialog, Select, MultiSelect, InputText, Textarea, Checkbox, DatePicker } from "primevue"
import Chart from "primevue/chart"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketProjectProgress from "@/Components/Tickets/TicketProjectProgress.vue"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import TicketProjectQuietBadge from "@/Components/Tickets/TicketProjectQuietBadge.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faProjectDiagram, faPlus, faPencil, faTimes, faCheckSquare, faSquare, faLink, faChevronDown, faChevronRight, faArrowUp, faArrowDown, faSearch, faList, faColumns, faPlusCircle, faCheckCircle, faRocket, faFlagCheckered, faCommentAltLines, faTachometerAlt, faTasks, faChartLine, faHistory, faCodeCommit, faExclamationTriangle, faClock, faCircle, faCalendarDay, faInfoCircle, faCheck, faTrashAlt, faThumbtack } from "@fal"

library.add(faProjectDiagram, faPlus, faPencil, faTimes, faCheckSquare, faSquare, faLink, faChevronDown, faChevronRight, faArrowUp, faArrowDown, faSearch, faList, faColumns, faPlusCircle, faCheckCircle, faRocket, faFlagCheckered, faCommentAltLines, faTachometerAlt, faTasks, faChartLine, faHistory, faCodeCommit, faExclamationTriangle, faClock, faCircle, faCalendarDay, faInfoCircle, faCheck, faTrashAlt, faThumbtack)

type Person = { id: number; name: string; avatar: Record<string, string> | null }
type Route = { name: string; parameters?: Record<string, unknown> }
type Health = "on_track" | "at_risk" | "off_track" | "overdue" | "not_started" | "work_done"
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
        is_pinned: boolean
        start_date: string
        target_date: string | null
        owner: Person | null
        members: Person[]
        health: Health | null
        health_label: string | null
        health_at: string | null
        last_update_at: string | null
        quiet_days: number | null
        parent: { id: number; slug: string; name: string } | null
    }
    sub_projects: { slug: string; name: string; status: string; status_label: string; health: Health | null; health_label: string | null; owner: Person | null; quiet_days: number | null; progress: { total: number; done: number; open: number; milestones: number; milestones_done: number; percent: number | null; time_percent: number | null; days_left: number | null } }[]
    indicators: { key: string; level: "info" | "warning" | "danger"; text: string }[]
    milestones: Milestone[]
    progress: { total: number; done: number; open: number; milestones: number; milestones_done: number; percent: number | null; time_percent: number | null; week: number; weeks: number | null; days_left: number | null }
    hidden_work: number
    work: WorkItem[]
    burn_up: { date: string; scope: number; done: number }[]
    commits: { hash: string; subject: string | null; version: string | null; deployed_at: string | null; reference: string; url: string }[]
    activity: { at: string; icon: string; text: string; url: string | null; by: string | null }[]
    workload: { person: Person | null; todo: number; in_progress: number; done: number }[]
    updates: { id: number; body: string; health: Health | null; health_label: string | null; author: Person | null; created_at: string; is_edited: boolean; can_edit: boolean; can_delete: boolean }[]
    can_edit: boolean
    options: { statuses: { label: string; value: string }[]; healths: { label: string; value: string }[]; staff: { label: string; value: number }[]; parents: { label: string; value: number }[] }
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
const asDate = (value: string | null | undefined) => (value ? parseDay(value) : null)
const fromDate = (value: Date | Date[] | (Date | null)[] | null | undefined) => (value instanceof Date ? toIso(value) : null)
const datePickerPt = { pcInputText: { root: { class: "!w-full !py-0.5 !text-xs" } } }
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"
const shortDate = (value: string | Date) => (typeof value === "string" ? parseDay(value) : value).toLocaleDateString(undefined, { day: "numeric", month: "short" })

const healthClasses: Record<string, string> = {
    on_track: "bg-green-100 text-green-700",
    at_risk: "bg-amber-100 text-amber-700",
    off_track: "bg-red-100 text-red-700",
    overdue: "bg-red-100 text-red-700",
    not_started: "bg-gray-100 text-gray-600",
    work_done: "bg-gray-100 text-gray-600",
}
const healthDots: Record<string, string> = { on_track: "bg-green-500", at_risk: "bg-amber-500", off_track: "bg-red-500", overdue: "bg-red-500", not_started: "bg-gray-400", work_done: "bg-gray-400" }
const priorityClasses: Record<string, string> = { urgent: "font-medium text-red-600", high: "text-amber-600" }
const indicatorStyles: Record<string, { icon: string; class: string }> = {
    danger: { icon: "fal fa-exclamation-triangle", class: "text-red-700" },
    warning: { icon: "fal fa-exclamation-triangle", class: "text-amber-700" },
    info: { icon: "fal fa-info-circle", class: "text-gray-700" },
}
const milestonesDone = computed(() => props.milestones.filter((milestone) => milestone.done_at).length)
const daysSince = (value: string | null) => (value ? Math.max(0, Math.round((parseDay(todayIso).getTime() - parseDay(value).getTime()) / 86400000)) : null)
const daysSinceUpdate = computed(() => daysSince(props.project.last_update_at))

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
    is_pinned: props.project.is_pinned,
    start_date: props.project.start_date,
    target_date: props.project.target_date ?? "",
    owner_id: props.project.owner?.id ?? null,
    member_ids: props.project.members.map((member) => member.id),
    parent_id: props.project.parent?.id ?? null,
})
const openEditor = () => {
    editForm.defaults({
        name: props.project.name,
        description: props.project.description ?? "",
        status: props.project.status,
        is_pinned: props.project.is_pinned,
        start_date: props.project.start_date,
        target_date: props.project.target_date ?? "",
        owner_id: props.project.owner?.id ?? null,
        member_ids: props.project.members.map((member) => member.id),
        parent_id: props.project.parent?.id ?? null,
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

const editingUpdateId = ref<number | null>(null)
const editUpdateForm = useForm({ body: "", health: null as string | null })
const startEditingUpdate = (update: (typeof props.updates)[number]) => {
    editUpdateForm.clearErrors()
    editUpdateForm.body = update.body
    editUpdateForm.health = update.health
    editingUpdateId.value = update.id
}
const toggleEditUpdateHealth = (value: string) => (editUpdateForm.health = editUpdateForm.health === value ? null : value)
const saveEditedUpdate = () => {
    if (editingUpdateId.value === null) {
        return
    }
    editUpdateForm.patch(route("grp.models.ticket_project.update.update", editingUpdateId.value), {
        preserveScroll: true,
        onSuccess: () => (editingUpdateId.value = null),
    })
}

const deletingUpdateId = ref<number | null>(null)
const deleteUpdateError = ref<string | null>(null)
const deleteUpdate = (id: number, closeModal: () => void) => {
    deletingUpdateId.value = id
    deleteUpdateError.value = null
    router.delete(route("grp.models.ticket_project.update.delete", id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal()
            if (editingUpdateId.value === id) {
                editingUpdateId.value = null
            }
        },
        onError: () => (deleteUpdateError.value = ctrans("The update could not be deleted. Reload the page and try again.")),
        onFinish: () => (deletingUpdateId.value = null),
    })
}

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
const setMilestoneDate = (milestone: Milestone, key: "start_date" | "due_date", value: Date | Date[] | (Date | null)[] | null | undefined) => {
    milestone[key] = fromDate(value)
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
const workSort = ref<"reference" | "updated">("reference")

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
    }).sort((a, b) =>
        workSort.value === "updated"
            ? Date.parse(b.updated_at) - Date.parse(a.updated_at)
            : a.reference.localeCompare(b.reference, undefined, { numeric: true }),
    )
})


const milestoneById = computed(() => new Map(props.milestones.map((milestone, index) => [milestone.id as number, { milestone, index }])))

const workTable = computed(() => {
    const rank = (item: WorkItem) => (item.milestone_id !== null ? (milestoneById.value.get(item.milestone_id)?.index ?? props.milestones.length) : props.milestones.length)
    const rows = [...filteredWork.value]
        .sort((a, b) => rank(a) - rank(b))
        .map((item, index, sorted) => ({ ...item, is_group_start: index === 0 || sorted[index - 1].milestone_id !== item.milestone_id }))
    return { data: rows, meta: { total: rows.length, per_page: Math.max(1, rows.length), current_page: 1, last_page: 1, from: rows.length ? 1 : 0, to: rows.length } }
})
const commitsTable = computed(() => ({ data: props.commits, meta: { total: props.commits.length, per_page: Math.max(1, props.commits.length), current_page: 1, last_page: 1, from: props.commits.length ? 1 : 0, to: props.commits.length } }))

const typeOptions = [
    { label: ctrans("Tickets and tasks"), value: "all" },
    { label: ctrans("Tickets"), value: "ticket" },
    { label: ctrans("Tasks"), value: "task" },
]
const milestoneFilterOptions = computed(() => [{ label: ctrans("All milestones"), value: "all" }, { label: ctrans("No milestone"), value: "none" }, ...milestoneOptions.value])
const assigneeFilterOptions = computed(() => [{ label: ctrans("Everyone"), value: "all" }, { label: ctrans("Unassigned"), value: "none" }, ...assignees.value.map((person) => ({ label: person.name, value: person.id }))])
const workSortOptions = [
    { label: ctrans("Sort by ticket ID"), value: "reference" },
    { label: ctrans("Sort by last updated"), value: "updated" },
]
const workViewOptions = [
    { label: ctrans("List"), value: "list", icon: "fal fa-list" },
    { label: ctrans("Board"), value: "board", icon: "fal fa-columns" },
]

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
    active: { fill: "bg-[var(--app-accent)]", track: "bg-[var(--app-accent-soft)]", icon: "fal fa-clock", iconClass: "text-[var(--app-accent)]", label: ctrans("In progress") },
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

    return { ticks, months, rows, todayLeft, targetLeft, position }
})

const expandedMilestones = ref<Record<number, boolean>>({})
const toggleTimelineRow = (milestone: Milestone) => {
    if (milestone.total) {
        expandedMilestones.value[milestone.id as number] = !expandedMilestones.value[milestone.id as number]
    }
}
const quickLookTicket = ref<{ id: number; reference: string; subject: string } | null>(null)
const quickLookTask = ref<{ id: number; reference: string } | null>(null)

const openQuickLook = (item: WorkItem, event: MouseEvent) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return
    const target = event.target as HTMLElement | null
    if (target?.closest("button, input, select, textarea, label, [role='combobox'], [role='listbox'], [role='option'], .p-select")) return
    event.preventDefault()
    if (item.type === "ticket") {
        quickLookTicket.value = { id: item.id, reference: item.reference, subject: item.subject }
    } else {
        quickLookTask.value = { id: item.id, reference: item.reference }
    }
}

const reloadWork = () => router.reload({ only: ["work", "milestones", "workload"] })

const workOfMilestone = (milestone: Milestone) =>
    props.work
        .filter((item) => item.milestone_id === milestone.id && item.state !== "cancelled")
        .map((item) => ({ ...item, done_left: item.done_at ? timeline.value.position(new Date(item.done_at)) : null }))
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

    <div class="grid gap-x-8 gap-y-4 border-b border-gray-200 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,28rem)]">
        <div class="min-w-0 space-y-3">
            <div>
                <p class="mb-1 text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Goal") }}</p>
                <p v-if="project.description" class="whitespace-pre-line text-base leading-relaxed text-gray-800">{{ project.description }}</p>
                <p v-else class="text-sm text-gray-400">{{ ctrans("No goal set") }}</p>
                <p v-if="project.parent" class="mt-1 text-xs text-gray-500">
                    {{ ctrans("Sub-project of") }} <Link :href="route('grp.projects.show', project.parent.slug)" class="font-medium text-gray-700 hover:underline">{{ project.parent.name }}</Link>
                </p>
            </div>
            <div v-if="sub_projects.length">
                <p class="mb-1 text-xs uppercase tracking-wide text-gray-400">{{ ctrans("Sub-projects") }}</p>
                <table class="w-full text-sm">
                    <thead class="whitespace-nowrap text-left text-xs text-gray-500">
                        <tr>
                            <th class="py-1 pr-3 font-normal">{{ ctrans("Project") }}</th>
                            <th class="py-1 pr-3 font-normal">{{ ctrans("Leader") }}</th>
                            <th class="py-1 pr-3 font-normal">{{ ctrans("Status") }}</th>
                            <th class="py-1 text-right font-normal">{{ ctrans("Work done") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="sub in sub_projects" :key="sub.slug">
                            <td class="py-1.5 pr-3"><Link :href="route('grp.projects.show', sub.slug)" class="font-medium text-gray-800 hover:underline">{{ sub.name }}</Link></td>
                            <td class="whitespace-nowrap py-1.5 pr-3">
                                <span v-if="sub.owner" class="flex items-center gap-1.5 text-xs text-gray-600">
                                    <TicketUserAvatar :name="sub.owner.name" :avatar="sub.owner.avatar" size="xs" />{{ sub.owner.name }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap py-1.5 pr-3">
                                <span class="flex gap-1">
                                    <span v-if="sub.health" class="rounded-full px-2 py-0.5 text-xs" :class="healthClasses[sub.health]">{{ sub.health_label }}</span>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ sub.status_label }}</span>
                                    <TicketProjectQuietBadge :days="sub.quiet_days" />
                                </span>
                            </td>
                            <td class="py-1.5">
                                <span class="grid grid-cols-[6rem_6.5rem] items-center justify-end gap-2 text-xs">
                                    <span class="h-2 overflow-hidden rounded-full bg-gray-100">
                                        <span class="block h-full rounded-full bg-green-500" :style="{ width: (sub.progress.percent ?? 0) + '%' }" />
                                    </span>
                                    <span class="text-right tabular-nums text-gray-600">{{ sub.progress.milestones > 0 ? ctrans(":done / :total milestones", { done: sub.progress.milestones_done, total: sub.progress.milestones }) : sub.progress.done + " / " + (sub.progress.done + sub.progress.open) }} · {{ sub.progress.percent === null ? "—" : sub.progress.percent + "%" }}</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-1 text-xs text-gray-400">{{ ctrans("The progress of this project includes the work of its sub-projects.") }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                <span v-if="project.health" class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium" :class="healthClasses[project.health]">
                    <span class="size-2 rounded-full" :class="healthDots[project.health]" />
                    {{ project.health_label }}
                    <span v-if="project.health_at" class="font-normal opacity-75">· {{ ctrans(project.health === "overdue" ? "target was :date" : "as of :date", { date: useFormatTime(project.health_at ?? undefined, { formatTime: "mdy" }) }) }}</span>
                </span>
                <span v-else class="text-xs text-gray-400">{{ ctrans("No health set") }}</span>
                <span v-if="project.is_pinned" class="inline-flex items-center gap-1 rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs text-[var(--app-accent-deep)]">
                    <FontAwesomeIcon icon="fal fa-thumbtack" fixed-width />{{ ctrans("Pinned") }}
                </span>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ project.status_label }}</span>
                <TicketProjectQuietBadge :days="project.quiet_days" />
                <div class="flex items-center gap-1">
                    <TicketUserAvatar v-if="project.owner" v-tooltip="ctrans('Leader') + ': ' + project.owner.name" :name="project.owner.name" :avatar="project.owner.avatar" size="sm" class="ring-2 ring-[var(--app-accent-strong)]" />
                    <TicketUserAvatar v-for="member in project.members" :key="member.id" v-tooltip="member.name" :name="member.name" :avatar="member.avatar" size="sm" />
                    <span v-if="!project.owner && !project.members.length" class="text-xs text-gray-400">{{ ctrans("No leader or team") }}</span>
                </div>
                <span class="text-xs text-gray-500">
                    <template v-if="daysSinceUpdate === null">{{ ctrans("No progress updates yet") }}</template>
                    <template v-else-if="daysSinceUpdate === 0">{{ ctrans("Last update today") }}</template>
                    <template v-else>{{ ctrans("Last update :days days ago", { days: daysSinceUpdate }) }}</template>
                </span>
            </div>
        </div>
        <div class="space-y-3">
            <dl class="grid grid-cols-4 divide-x divide-gray-200 rounded-md border border-gray-200 tabular-nums">
                <div class="px-3 py-2">
                    <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Days left") }}</dt>
                    <dd class="text-xl font-semibold" :class="progress.days_left !== null && progress.days_left < 0 ? 'text-red-600' : 'text-gray-800'">{{ progress.days_left ?? "-" }}</dd>
                </div>
                <div class="px-3 py-2">
                    <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Open") }}</dt>
                    <dd class="text-xl font-semibold text-gray-800">{{ progress.open }}</dd>
                </div>
                <div class="px-3 py-2">
                    <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Done") }}</dt>
                    <dd class="text-xl font-semibold text-green-700">{{ progress.done }}</dd>
                </div>
                <div class="px-3 py-2">
                    <dt class="whitespace-nowrap text-xs text-gray-500">{{ ctrans("Milestones") }}</dt>
                    <dd class="text-xl font-semibold text-gray-800">{{ milestonesDone }}<span class="text-sm font-normal text-gray-400">/{{ milestones.length }}</span></dd>
                </div>
            </dl>
            <TicketProjectProgress :progress="progress" :start-date="project.start_date" :target-date="project.target_date" />
        </div>
    </div>

    <ul v-if="indicators.length" class="divide-y divide-gray-100 border-b border-gray-200 bg-gray-50 text-sm">
        <li v-for="indicator in indicators" :key="indicator.key + indicator.text" class="flex items-start gap-2 px-4 py-1.5" :class="indicatorStyles[indicator.level].class">
            <FontAwesomeIcon :icon="indicatorStyles[indicator.level].icon" class="mt-0.5" fixed-width aria-hidden="true" />
            <span>{{ indicator.text }}</span>
        </li>
    </ul>

    <nav class="flex gap-1 overflow-x-auto border-b border-gray-200 px-4">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            class="-mb-px flex items-center gap-1.5 whitespace-nowrap border-b-2 px-3 py-2 text-sm"
            :class="[activeTab === tab.key ? 'border-[var(--app-accent-strong)] font-medium text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800', { 'ml-auto': tab.alignRight }]"
            @click="selectTab(tab.key)">
            <FontAwesomeIcon :icon="tab.icon" fixed-width aria-hidden="true" />
            {{ tab.label }}
        </button>
    </nav>

    <div v-if="activeTab === 'overview'" class="grid gap-6 p-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-6">
            <section>
                <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Progress updates") }}</h2>
                <form v-if="can_edit" class="mb-4 rounded-md border border-gray-200 p-2" @submit.prevent="postUpdate">
                    <Textarea v-model="updateForm.body" rows="3" auto-resize class="w-full text-sm" :placeholder="ctrans('What moved, what is blocked, what comes next…')" />
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
                        <Button icon="fal fa-check" :label="ctrans('Post update')" type="tertiary" :disabled="!updateForm.body.trim()" :loading="updateForm.processing" @click="postUpdate" />
                    </div>
                </form>
                <p v-if="deleteUpdateError" class="mb-2 text-xs text-red-600">{{ deleteUpdateError }}</p>
                <p v-if="!updates.length" class="text-sm text-gray-500">{{ ctrans("No updates yet") }}</p>
                <ol class="space-y-4">
                    <li v-for="update in updates" :key="update.id" class="flex gap-3">
                        <TicketUserAvatar :name="update.author?.name ?? null" :avatar="update.author?.avatar" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 text-xs text-gray-500">
                                <span class="font-medium text-gray-700">{{ update.author?.name ?? "-" }}</span>
                                <span>{{ useFormatTime(update.created_at, { formatTime: "hm" }) }}</span>
                                <span v-if="update.is_edited" class="text-gray-400">{{ ctrans("(edited)") }}</span>
                                <span v-if="update.health" class="rounded-full px-2 py-0.5" :class="healthClasses[update.health]">{{ update.health_label }}</span>
                                <span v-if="(update.can_edit || update.can_delete) && editingUpdateId !== update.id" class="ml-auto flex items-center gap-1">
                                    <button
                                        v-if="update.can_edit"
                                        v-tooltip="ctrans('Edit')"
                                        type="button"
                                        class="p-1 text-gray-500 hover:text-gray-800 disabled:opacity-40"
                                        :aria-label="ctrans('Edit update')"
                                        :disabled="deletingUpdateId === update.id"
                                        @click="startEditingUpdate(update)">
                                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                    </button>
                                    <ModalConfirmation
                                        v-if="update.can_delete"
                                        :title="update.can_edit ? ctrans('Delete this update?') : ctrans('Delete :author\'s update?', { author: update.author?.name ?? ctrans('this person') })"
                                        :description="update.can_edit ? ctrans('The update will be removed from the project.') : ctrans('This is not your update. It will be removed for everyone and cannot be undone.')">
                                        <template #default="{ changeModel }">
                                            <button
                                                v-tooltip="ctrans('Delete')"
                                                type="button"
                                                class="p-1 text-gray-500 hover:text-red-600 disabled:opacity-40"
                                                :aria-label="ctrans('Delete update')"
                                                :disabled="deletingUpdateId === update.id"
                                                @click="changeModel">
                                                <LoadingIcon v-if="deletingUpdateId === update.id" />
                                                <FontAwesomeIcon v-else icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                                            </button>
                                        </template>
                                        <template #btn-yes="{ closeModal }">
                                            <Button type="tertiary" :label="ctrans('Yes, delete')" :loading="deletingUpdateId === update.id" @click="deleteUpdate(update.id, closeModal)">
                                                <template #icon>
                                                    <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="text-amber-500" fixed-width aria-hidden="true" />
                                                </template>
                                            </Button>
                                        </template>
                                    </ModalConfirmation>
                                </span>
                            </div>
                            <form v-if="editingUpdateId === update.id" class="mt-1 rounded-md border border-gray-200 p-2" @submit.prevent="saveEditedUpdate">
                                <Textarea v-model="editUpdateForm.body" rows="3" auto-resize class="w-full text-sm" :aria-label="ctrans('Update text')" />
                                <p v-if="editUpdateForm.errors.body" class="text-xs text-red-600">{{ editUpdateForm.errors.body }}</p>
                                <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                                    <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs">
                                        <button
                                            v-for="health in options.healths"
                                            :key="health.value"
                                            type="button"
                                            class="flex items-center gap-1.5 border-r border-gray-300 px-2 py-1 last:border-r-0"
                                            :class="editUpdateForm.health === health.value ? healthClasses[health.value] : 'bg-white text-gray-600 hover:bg-gray-50'"
                                            @click="toggleEditUpdateHealth(health.value)">
                                            <span class="size-2 rounded-full" :class="healthDots[health.value]" />
                                            {{ health.label }}
                                        </button>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button type="tertiary" :label="ctrans('Cancel')" :disabled="editUpdateForm.processing" @click="editingUpdateId = null">
                                            <template #icon>
                                                <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                                            </template>
                                        </Button>
                                        <Button type="tertiary" :label="ctrans('Save')" :disabled="!editUpdateForm.body.trim()" :loading="editUpdateForm.processing" @click="saveEditedUpdate">
                                            <template #icon>
                                                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                                            </template>
                                        </Button>
                                    </div>
                                </div>
                            </form>
                            <TicketBody v-else :text="update.body" class="text-sm" />
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-lg border border-gray-200 p-4">
                <h2 class="mb-3 flex items-center justify-between text-xs font-medium uppercase tracking-wide text-gray-400">
                    {{ ctrans("Milestones") }}
                    <span class="normal-case tabular-nums">{{ ctrans(":done of :total done", { done: milestonesDone, total: milestones.length }) }}</span>
                </h2>
                <p v-if="!milestoneDraft.length" class="text-sm text-gray-500">{{ ctrans("No milestones yet") }}</p>
                <ol class="text-sm">
                    <li v-for="(milestone, index) in milestoneDraft" :key="milestone.id ?? 'new-' + index" class="group relative flex gap-3 pb-4 last:pb-0">
                        <span v-if="index < milestoneDraft.length - 1" class="absolute bottom-0 left-3 top-7 border-l border-gray-200" aria-hidden="true" />
                        <button
                            v-tooltip="can_edit ? (milestone.done_at ? ctrans('Mark not done') : ctrans('Mark done')) : milestoneStyles[milestoneStateOf(milestone)].label"
                            :disabled="!can_edit"
                            class="relative z-10 flex size-6 shrink-0 items-center justify-center rounded-full border bg-white"
                            :class="milestone.done_at ? 'border-green-500' : isOverdue(milestone) ? 'border-red-400' : milestoneStateOf(milestone) === 'active' ? 'border-[var(--app-accent)]' : 'border-gray-300'"
                            @click="toggleMilestone(milestone)">
                            <FontAwesomeIcon :icon="milestone.done_at ? 'fal fa-check' : milestoneStyles[milestoneStateOf(milestone)].icon" :class="milestoneStyles[milestoneStateOf(milestone)].iconClass" class="text-xs" fixed-width />
                        </button>
                        <div class="min-w-0 flex-1">
                            <input
                                v-if="can_edit"
                                v-model="milestone.name"
                                maxlength="255"
                                class="w-full rounded-md border-transparent px-1 py-0.5 text-sm hover:border-gray-300"
                                :class="milestone.done_at && 'text-gray-500'"
                                @change="persistMilestones" />
                            <p v-else class="px-1" :class="milestone.done_at && 'text-gray-500'">{{ milestone.name }}</p>
                            <p class="px-1 text-xs" :class="isOverdue(milestone) ? 'font-semibold text-red-600' : 'text-gray-500'">
                                <template v-if="milestone.done_at">{{ ctrans("Done :date", { date: useFormatTime(milestone.done_at, { formatTime: "mdy" }) }) }}</template>
                                <template v-else-if="isOverdue(milestone)">{{ ctrans("Overdue since :date", { date: useFormatTime(milestone.due_date ?? undefined, { formatTime: "mdy" }) }) }}</template>
                                <template v-else-if="milestone.due_date">{{ ctrans("Due :date", { date: useFormatTime(milestone.due_date, { formatTime: "mdy" }) }) }}</template>
                                <template v-else>{{ ctrans("No due date") }}</template>
                            </p>
                            <div v-if="can_edit" class="mt-1 hidden gap-1 group-focus-within:flex group-hover:flex">
                                <DatePicker :model-value="asDate(milestone.start_date)" date-format="d M yy" show-button-bar :placeholder="ctrans('Start')" :class="fieldFocusClass" :pt="datePickerPt" class="min-w-0 flex-1" @update:model-value="setMilestoneDate(milestone, 'start_date', $event)" />
                                <DatePicker :model-value="asDate(milestone.due_date)" date-format="d M yy" show-button-bar :placeholder="ctrans('Due')" :class="fieldFocusClass" :pt="datePickerPt" class="min-w-0 flex-1" @update:model-value="setMilestoneDate(milestone, 'due_date', $event)" />
                            </div>
                            <div class="mt-1 flex items-center gap-2 px-1">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-100">
                                    <div class="h-full rounded-full" :class="milestone.done_at ? 'bg-green-500' : isOverdue(milestone) ? 'bg-red-400' : 'bg-[var(--app-accent)]'" :style="{ width: percentOf(milestone.done, milestone.total) + '%' }" />
                                </div>
                                <span class="w-10 text-right text-xs tabular-nums text-gray-500">{{ milestone.done }}/{{ milestone.total }}</span>
                            </div>
                        </div>
                        <div v-if="can_edit" class="flex flex-col text-gray-400 sm:invisible sm:group-hover:visible">
                            <button v-tooltip="ctrans('Move up')" :disabled="index === 0" class="hover:text-gray-700 disabled:opacity-30" @click="moveMilestone(index, -1)">
                                <FontAwesomeIcon icon="fal fa-arrow-up" fixed-width />
                            </button>
                            <button v-tooltip="ctrans('Move down')" :disabled="index === milestoneDraft.length - 1" class="hover:text-gray-700 disabled:opacity-30" @click="moveMilestone(index, 1)">
                                <FontAwesomeIcon icon="fal fa-arrow-down" fixed-width />
                            </button>
                            <button v-tooltip="ctrans('Delete milestone')" class="hover:text-red-600" @click="removeMilestone(index)">
                                <FontAwesomeIcon icon="fal fa-times" fixed-width />
                            </button>
                        </div>
                    </li>
                </ol>
                <form v-if="can_edit" class="mt-4 space-y-1.5 border-t border-gray-100 pt-3" @submit.prevent="addMilestone">
                    <InputText v-model="newMilestone.name" maxlength="255" size="small" class="w-full" :placeholder="ctrans('New milestone')" />
                    <div class="flex gap-2">
                        <DatePicker :model-value="asDate(newMilestone.due_date)" date-format="d M yy" :placeholder="ctrans('Due')" :class="fieldFocusClass" :pt="datePickerPt" class="min-w-0 flex-1" @update:model-value="newMilestone.due_date = fromDate($event) ?? ''" />
                        <Button icon="fal fa-plus" :label="ctrans('Add')" type="tertiary" size="xs" :disabled="!newMilestone.name.trim()" @click="addMilestone" />
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
                            <div class="bg-[var(--app-accent)]" :style="{ flexGrow: row.in_progress }" />
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
            <InputText v-model="search" size="small" class="w-48" :placeholder="ctrans('Search')" />
            <Select v-model="typeFilter" :options="typeOptions" option-label="label" option-value="value" size="small" class="w-44" />
            <Select v-model="milestoneFilter" :options="milestoneFilterOptions" option-label="label" option-value="value" size="small" class="w-56" />
            <Select v-model="assigneeFilter" :options="assigneeFilterOptions" option-label="label" option-value="value" filter size="small" class="w-44" />
            <label class="flex items-center gap-1.5 text-gray-600">
                <Checkbox v-model="showClosed" binary input-id="project-show-closed" />
                {{ ctrans("Show closed") }}
            </label>
            <Select v-model="workSort" :options="workSortOptions" option-label="label" option-value="value" size="small" class="ml-auto w-52" />
            <SegmentedToggle v-model="workView" :options="workViewOptions" :aria-label="ctrans('View')" />
        </div>

        <div v-if="can_edit">
            <form class="flex flex-wrap items-center gap-2" @submit.prevent="attachWork">
                <InputText v-model="attachForm.references" size="small" class="min-w-0 flex-1 sm:max-w-md" :placeholder="ctrans('Add tickets or tasks: HELP-123, TASK-45')" />
                <Select v-model="attachForm.ticket_project_milestone_id" :options="milestoneOptions" option-label="label" option-value="value" show-clear size="small" class="w-48" :placeholder="ctrans('No milestone')" />
                <Button icon="fal fa-link" :label="ctrans('Add')" type="tertiary" size="xs" :loading="attachForm.processing" :disabled="!attachForm.references.trim()" @click="attachWork" />
            </form>
            <p v-if="attachForm.errors.references" class="mt-1 text-xs text-red-600">{{ attachForm.errors.references }}</p>
        </div>

        <p v-if="workView === 'list' && !workTable.data.length" class="rounded-md border border-dashed border-gray-300 p-4 text-center text-sm text-gray-500">{{ ctrans("No work matches") }}</p>
        <Table v-else-if="workView === 'list'" name="work" :resource="workTable" class="mt-1">
            <template #tableFilterSearch><span /></template>
            <template #pagination><span /></template>
            <template #cell(status)="{ item }">
                <FontAwesomeIcon v-tooltip="item.status_label" :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
            </template>
            <template #cell(reference)="{ item }">
                <a :href="item.url" class="whitespace-nowrap font-mono text-xs text-gray-600 hover:underline" @click="openQuickLook(item, $event)">{{ item.reference }}</a>
            </template>
            <template #cell(subject)="{ item }">
                <a :href="item.url" class="hover:underline" :class="[priorityClasses[item.priority], (item.state === 'done' || item.state === 'cancelled') && 'text-gray-500']" @click="openQuickLook(item, $event)">{{ item.subject }}</a>
            </template>
            <template #cell(milestone)="{ item }">
                <Select v-if="can_edit" :model-value="item.milestone_id" :options="milestoneOptions" option-label="label" option-value="value" show-clear size="small" class="w-56" :class="!item.is_group_start && 'opacity-60 hover:opacity-100'" :placeholder="ctrans('No milestone')" @update:model-value="moveItem(item, $event ?? null)" />
                <span v-else-if="item.is_group_start" class="text-sm text-gray-700">{{ milestoneById.get(item.milestone_id)?.milestone.name ?? ctrans("No milestone") }}</span>
            </template>
            <template #cell(status_label)="{ item }">
                <span class="whitespace-nowrap text-sm text-gray-600">{{ item.status_label }}</span>
            </template>
            <template #cell(assignee)="{ item }">
                <span v-if="item.assignee" class="flex items-center gap-2 whitespace-nowrap">
                    <TicketUserAvatar :name="item.assignee.name" :avatar="item.assignee.avatar" size="xs" />
                    {{ item.assignee.name }}
                </span>
                <span v-else class="text-gray-400">{{ ctrans("Unassigned") }}</span>
            </template>
            <template #cell(updated_at)="{ item }">
                <span class="whitespace-nowrap tabular-nums text-gray-500">{{ useFormatTime(item.updated_at, { formatTime: "mdy" }) }}</span>
            </template>
            <template #cell(actions)="{ item }">
                <button v-if="can_edit" v-tooltip="ctrans('Take out of the project')" class="text-gray-400 hover:text-red-600" @click="removeItem(item)">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
            </template>
        </Table>

        <div v-if="workView === 'board'" class="grid gap-3 md:grid-cols-3" :class="showClosed && 'xl:grid-cols-4'">
            <section v-for="column in boardColumns" :key="column.state" class="rounded-md bg-gray-50 p-2">
                <h3 class="mb-2 flex items-center justify-between px-1 text-xs font-medium uppercase tracking-wide text-gray-500">
                    {{ column.label }}
                    <span class="tabular-nums">{{ column.items.length }}</span>
                </h3>
                <div class="space-y-2">
                    <a v-for="item in column.items" :key="item.key" :href="item.url" class="block rounded-md border border-gray-200 bg-white p-2 text-sm hover:border-gray-400" @click="openQuickLook(item, $event)">
                        <p class="mb-1 flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
                            <span class="font-mono">{{ item.reference }}</span>
                        </p>
                        <p :class="priorityClasses[item.priority]">{{ item.subject }}</p>
                        <div class="mt-1.5 flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-gray-400">{{ milestones.find((milestone) => milestone.id === item.milestone_id)?.name }}</span>
                            <TicketUserAvatar v-if="item.assignee" v-tooltip="item.assignee.name" :name="item.assignee.name" :avatar="item.assignee.avatar" size="xs" />
                        </div>
                    </a>
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
                                <li v-for="item in workOfMilestone(row.milestone)" :key="item.key" :class="timelineColumns" class="cursor-pointer text-sm hover:bg-gray-50" @click="openQuickLook(item, $event)">
                                    <div class="flex min-w-0 items-center gap-1.5 py-1.5 pl-7 pr-3" :class="item.state === 'done' && 'opacity-70'">
                                        <FontAwesomeIcon v-tooltip="item.status_label" :icon="item.status_icon.icon" :class="item.status_icon.class" fixed-width />
                                        <a v-tooltip="item.subject" :href="item.url" class="min-w-0 flex-1 truncate hover:underline">
                                            <span class="mr-1.5 whitespace-nowrap font-mono text-xs text-gray-500">{{ item.reference }}</span>
                                            <span class="text-gray-700" :class="priorityClasses[item.priority]">{{ item.subject }}</span>
                                        </a>
                                        <TicketUserAvatar v-if="item.assignee" v-tooltip="item.assignee.name" :name="item.assignee.name" :avatar="item.assignee.avatar" size="sm" />
                                    </div>
                                    <div class="relative min-h-8 pr-3">
                                        <span v-for="tick in timeline.ticks" :key="tick.left" class="absolute inset-y-0 border-l border-gray-100" :style="{ left: tick.left + '%' }" />
                                        <span v-if="timeline.targetLeft !== null" class="absolute inset-y-0 border-l border-dashed border-gray-400" :style="{ left: timeline.targetLeft + '%' }" />
                                        <span v-if="timeline.todayLeft !== null" class="absolute inset-y-0 z-10 border-l-2 border-red-400" :style="{ left: timeline.todayLeft + '%' }" />
                                        <template v-if="item.done_at && item.done_left !== null">
                                            <span
                                                v-tooltip="ctrans('Done :date', { date: shortDate(new Date(item.done_at)) })"
                                                class="absolute top-1/2 z-20 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-green-500 ring-2 ring-white"
                                                :style="{ left: item.done_left + '%' }" />
                                            <span
                                                class="absolute top-1/2 -translate-y-1/2 whitespace-nowrap text-xs tabular-nums text-gray-500"
                                                :style="item.done_left <= 88 ? { left: `calc(${item.done_left}% + 10px)` } : { right: `calc(${100 - item.done_left}% + 10px)` }">
                                                {{ shortDate(new Date(item.done_at)) }}
                                            </span>
                                        </template>
                                    </div>
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
        <Table name="commits" :resource="commitsTable">
            <template #tableFilterSearch><span /></template>
            <template #pagination><span /></template>
            <template #cell(version)="{ item }">
                <span class="whitespace-nowrap">{{ item.version ?? "-" }}</span>
            </template>
            <template #cell(deployed_at)="{ item }">
                <span class="whitespace-nowrap tabular-nums">{{ item.deployed_at ? useFormatTime(item.deployed_at, { formatTime: "mdy" }) : "-" }}</span>
            </template>
            <template #cell(hash)="{ item }">
                <span class="whitespace-nowrap font-mono text-xs">{{ item.hash.slice(0, 10) }}</span>
            </template>
            <template #cell(reference)="{ item }">
                <Link :href="item.url" class="whitespace-nowrap font-mono text-xs hover:underline">{{ item.reference }}</Link>
            </template>
        </Table>
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
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Name") }} <span class="text-red-500">*</span></label>
                <InputText v-model="editForm.name" maxlength="255" required class="w-full" />
                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Goal") }}</label>
                <Textarea v-model="editForm.description" rows="5" auto-resize class="w-full text-sm" />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Status") }} <span class="text-red-500">*</span></label>
                    <Select v-model="editForm.status" :options="options.statuses" option-label="label" option-value="value" class="w-full" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Start") }} <span class="text-red-500">*</span></label>
                    <DatePicker :model-value="asDate(editForm.start_date)" date-format="d M yy" :class="fieldFocusClass" class="w-full" @update:model-value="editForm.start_date = fromDate($event) ?? editForm.start_date" />
                </div>
                <div v-if="!editForm.is_pinned">
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target") }}</label>
                    <DatePicker :model-value="asDate(editForm.target_date)" date-format="d M yy" show-button-bar :class="fieldFocusClass" class="w-full" @update:model-value="editForm.target_date = fromDate($event) ?? ''" />
                </div>
            </div>
            <label v-if="!editForm.parent_id" class="flex items-start gap-2 text-sm text-gray-700">
                <Checkbox v-model="editForm.is_pinned" binary input-id="project-is-pinned" class="mt-0.5" />
                <span>
                    <FontAwesomeIcon icon="fal fa-thumbtack" class="mr-1 text-gray-500" fixed-width />{{ ctrans("Pinned: an ongoing area with no end date") }}
                    <span class="block text-xs text-gray-500">{{ ctrans("Kept at the top of the projects list, with no target date. Its sub-projects carry the dates.") }}</span>
                </span>
            </label>
            <p v-if="editForm.errors.start_date || editForm.errors.target_date" class="text-xs text-red-600">{{ editForm.errors.start_date || editForm.errors.target_date }}</p>
            <div v-if="options.parents.length || editForm.parent_id">
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Sub-project of") }}</label>
                <Select v-model="editForm.parent_id" :options="options.parents" option-label="label" option-value="value" filter show-clear :placeholder="ctrans('None: a top-level project')" class="w-full" />
                <p v-if="editForm.errors.parent_id" class="mt-1 text-xs text-red-600">{{ editForm.errors.parent_id }}</p>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Leader") }}</label>
                <Select v-model="editForm.owner_id" :options="options.staff" option-label="label" option-value="value" filter show-clear class="w-full" />
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Team") }}</label>
                <MultiSelect v-model="editForm.member_ids" :options="options.staff" option-label="label" option-value="value" filter display="chip" class="w-full" />
            </div>
            <div class="flex w-full gap-2 pt-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isEditing = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width /></template>
                </Button>
                <Button type="primary" full :label="ctrans('Save')" :loading="editForm.processing" @click="saveProject">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width /></template>
                </Button>
            </div>
        </form>
    </Dialog>

    <TicketQuickLook v-model:ticket="quickLookTicket" @closed="reloadWork" />
    <StaffTaskQuickLook v-model:task="quickLookTask" @closed="reloadWork" />
</template>
