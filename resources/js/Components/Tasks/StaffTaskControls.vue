<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { addDays, format, parseISO } from "date-fns"
import { Popover, Listbox, DatePicker, Dialog, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import StaffTaskPeoplePicker from "@/Components/Tasks/StaffTaskPeoplePicker.vue"
import StaffTaskEtaDialog from "@/Components/Tasks/StaffTaskEtaDialog.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import type { StaffTaskDueAccess, StaffTaskEtaProposal } from "@/types/StaffTaskEta"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faBell, faCalendar, faCalendarEdit, faUser, faSpinner, faPlay, faPause, faCheck, faTimes, faUndo, faCircle, faCheckCircle, faBan, faExchangeAlt, faHandsHelping } from "@fal"
library.add(faBuilding, faBell, faCalendar, faCalendarEdit, faUser, faSpinner, faPlay, faPause, faCheck, faTimes, faUndo, faCircle, faCheckCircle, faBan, faExchangeAlt, faHandsHelping)

type Person = { id: number; name: string; avatar: any }
type Option = { label: string; value: string; icon: any }
type StatusAction = { status: string; label: string; icon: string; class: string }

const props = defineProps<{
    task: {
        reference: string
        status: string
        status_label: string
        status_icon: any
        priority: string
        priority_icon: any
        department_label: string | null
        requester: Person | null
        assignee: Person | null
        collaborators: Person[]
        due_at: string | null
        is_overdue: boolean
        model_type: string | null
        model_id: number | null
        is_subscribed: boolean | null
        eta_proposal?: StaffTaskEtaProposal | null
    }
    canEdit: boolean
    canRemoveCollaborators?: boolean
    canReassign?: boolean
    canAskForHelp?: boolean
    dueAccess?: StaffTaskDueAccess
    options: { statuses: Option[]; priorities: Option[] }
}>()

const emit = defineEmits<{
    updated: []
}>()

const store = useStaffMessaging()
const myId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const pendingAction = ref<string | null>(null)
const isBusy = computed(() => pendingAction.value !== null)
const isPending = (action: string) => pendingAction.value === action

watch(() => props.task, () => (pendingAction.value = null))

const send = async (action: string, request: () => Promise<unknown>) => {
    if (isBusy.value) return
    pendingAction.value = action
    try {
        await request()
        emit("updated")
    } catch (error: any) {
        pendingAction.value = null
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    }
}

const patchTask = (action: string, payload: Record<string, unknown>) => send(action, () => axios.patch(route("grp.tasks.update", props.task.reference), payload))

const statusBadgeClasses: Record<string, string> = {
    gray: "bg-gray-100 text-gray-700",
    blue: "bg-blue-100 text-blue-700",
    green: "bg-green-100 text-green-700",
    red: "bg-red-100 text-red-700",
}

const statusActions = computed<StatusAction[]>(() => {
    const start = { status: "in_progress", label: ctrans("Working on it"), icon: "fal fa-play", class: "text-blue-500" }
    const done = { status: "done", label: ctrans("Done"), icon: "fal fa-check", class: "text-green-600" }
    const cancel = { status: "cancelled", label: ctrans("Can't be done"), icon: "fal fa-times", class: "text-red-500" }
    const backToTodo = { status: "todo", label: ctrans("Back to todo"), icon: "fal fa-pause", class: "text-gray-500" }
    const reopen = { status: "todo", label: ctrans("Reopen"), icon: "fal fa-undo", class: "text-gray-500" }

    return ({
        todo: [start, done, cancel],
        in_progress: [done, cancel, backToTodo],
        done: [reopen],
        cancelled: [reopen],
    } as Record<string, StatusAction[]>)[props.task.status] ?? []
})

const cancelNoteOpen = ref(false)
const cancelNote = ref("")

const runStatusAction = (status: string) => {
    if (status === "cancelled") {
        cancelNote.value = ""
        cancelNoteOpen.value = true
        return
    }
    patchTask(`status:${status}`, { status })
}

const confirmCancel = () => {
    if (!cancelNote.value.trim()) return
    cancelNoteOpen.value = false
    patchTask("status:cancelled", { status: "cancelled", note: cancelNote.value.trim() })
}

const priorityPopover = ref()
const isPriorityPickerOpen = ref(false)
const priorityOption = computed(() => props.options.priorities.find((priority) => priority.value === props.task.priority))

const choosePriority = (priority: string) => {
    priorityPopover.value?.hide()
    if (priority && priority !== props.task.priority) patchTask("priority", { priority })
}

const duePopover = ref()
const isDuePickerOpen = ref(false)
const dueDate = computed(() => props.task.due_at ? parseISO(props.task.due_at) : null)

const chooseDueDate = (date: Date | Date[] | (Date | null)[] | null | undefined) => {
    duePopover.value?.hide()
    const picked = date instanceof Date ? format(date, "yyyy-MM-dd") : null
    if (picked !== props.task.due_at) patchTask("due_at", { due_at: picked })
}

const startOfToday = new Date(new Date().setHours(0, 0, 0, 0))
const daysFromToday = (days: number) => addDays(startOfToday, days)

const quickDuePicks = [
    { label: ctrans("Today"), date: daysFromToday(0) },
    { label: ctrans("Tomorrow"), date: daysFromToday(1) },
    { label: ctrans("In 3 days"), date: daysFromToday(3) },
    { label: ctrans("Next week"), date: daysFromToday(7) },
]

const canSetDue = computed(() => props.dueAccess?.can_set ?? false)
const canSuggestEta = computed(() => (props.dueAccess?.can_suggest ?? false) && !props.task.eta_proposal)
const isDueClickable = computed(() => canSetDue.value || canSuggestEta.value)

const dueTooltip = computed(() => {
    if (canSetDue.value) return ctrans("Due date · click to change")
    if (canSuggestEta.value) return ctrans("Due date · click to suggest a new ETA")
    return ctrans("Due date")
})

const etaDialogOpen = ref(false)

const onDueClick = (event: Event) => {
    if (canSetDue.value) {
        duePopover.value?.toggle(event)
        return
    }
    if (canSuggestEta.value) etaDialogOpen.value = true
}

const decideEta = (decision: "accept" | "decline") => send(`eta:${decision}`, () => axios.post(route("grp.tasks.eta_proposal.decide", props.task.reference), { decision }))

const shortDate = (date: string) => useFormatTime(date, { formatTime: "mdy" })

const canManagePeople = computed(() => props.canEdit || props.canRemoveCollaborators)

const coworkers = ref<Person[]>([])

const loadCoworkers = async () => {
    const params = props.task.model_type && props.task.model_id ? { model_type: props.task.model_type, model_id: props.task.model_id } : {}
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"), { params })
    coworkers.value = data.data
}

const collaboratorPeople = computed<Person[]>(() => {
    const byId = new Map<number, Person>()
    for (const person of [...props.task.collaborators, ...coworkers.value]) byId.set(person.id, person)
    return [...byId.values()]
})

const savedCollaboratorIds = computed(() => props.task.collaborators.map((person) => person.id))
const draftCollaboratorIds = ref<number[] | null>(null)
const collaboratorIds = computed(() => draftCollaboratorIds.value ?? savedCollaboratorIds.value)
let collaboratorSaveTimer: ReturnType<typeof setTimeout> | null = null

const saveCollaborators = () => {
    if (collaboratorSaveTimer) clearTimeout(collaboratorSaveTimer)
    collaboratorSaveTimer = null
    const nextIds = draftCollaboratorIds.value
    if (!nextIds) return
    const isUnchanged = nextIds.length === savedCollaboratorIds.value.length && nextIds.every((id) => savedCollaboratorIds.value.includes(id))
    if (isUnchanged) {
        draftCollaboratorIds.value = null
        return
    }
    send("collaborators", () => axios.patch(route("grp.tasks.collaborators.update", props.task.reference), { collaborator_ids: nextIds }))
        .finally(() => (draftCollaboratorIds.value = null))
}

const changeCollaborators = (ids: number[]) => {
    draftCollaboratorIds.value = ids
    if (collaboratorSaveTimer) clearTimeout(collaboratorSaveTimer)
    collaboratorSaveTimer = setTimeout(saveCollaborators, 800)
}

const canSubscribe = computed(() => myId.value !== null && props.task.requester?.id !== myId.value && props.task.assignee?.id !== myId.value)

const toggleSubscription = () => send("subscription", async () => {
    await axios.post(route("grp.tasks.subscription.toggle", props.task.reference))
    await store.fetchConversations()
})

const me = computed<Person | null>(() => {
    const user = (usePage().props.auth as { user?: { id: number; contact_name?: string; username?: string; avatar?: any } } | undefined)?.user
    return user ? { id: user.id, name: user.contact_name || user.username || ctrans("Me"), avatar: user.avatar ?? null } : null
})

const assigneePopover = ref()
const isAssigneePickerOpen = ref(false)
const assigneeQuery = ref("")

const assigneeCandidates = computed(() => {
    const search = assigneeQuery.value.trim().toLowerCase()
    return coworkers.value.filter((person) => person.id !== props.task.assignee?.id && (!search || person.name.toLowerCase().includes(search)))
})

const openAssigneePicker = (event: Event) => {
    if (!coworkers.value.length) loadCoworkers()
    assigneePopover.value?.toggle(event)
}

const onAssigneePickerHide = () => {
    isAssigneePickerOpen.value = false
    assigneeQuery.value = ""
}

const reassignTo = (personId: number) => {
    assigneePopover.value?.hide()
    patchTask("assignee", { assignee_id: personId })
}

const helpDialogOpen = ref(false)
const helpNote = ref("")

const openHelpDialog = () => {
    helpNote.value = ""
    helpDialogOpen.value = true
}

const askForHelp = () => {
    helpDialogOpen.value = false
    send("help", () => axios.post(route("grp.tasks.help.store", props.task.reference), { note: helpNote.value.trim() || null }))
        .then(() => notify({ title: ctrans("Help is on its way"), text: ctrans(":name and the supervisors were told", { name: props.task.requester?.name ?? ctrans("The requester") }), type: "success" }))
}

onMounted(() => {
    if (props.canEdit || props.canReassign) loadCoworkers()
})

watch(() => props.canEdit || props.canReassign, (canPickPeople) => {
    if (canPickPeople && !coworkers.value.length) loadCoworkers()
})

onBeforeUnmount(saveCollaborators)

defineExpose({ isBusy })

const sectionLabelClass = "mb-1 text-xs font-medium uppercase tracking-wide text-gray-400"
const chipClass = "inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
const editableChipClass = "cursor-pointer hover:bg-gray-200 active:!bg-gray-300"
</script>

<template>
    <div class="space-y-4" :class="isBusy && 'pointer-events-none'" :aria-busy="isBusy">
        <div>
            <p :class="sectionLabelClass">{{ ctrans("Assignee") }}</p>
            <component
                :is="canReassign ? 'button' : 'div'"
                :type="canReassign ? 'button' : undefined"
                v-tooltip="canReassign ? ctrans('Hand this task to someone else') : undefined"
                class="flex w-full items-center gap-2 rounded-md p-2 text-left"
                :class="[canReassign && 'transition duration-200 hover:bg-gray-100 active:!bg-gray-200', isAssigneePickerOpen && '!bg-gray-200']"
                @click="canReassign && openAssigneePicker($event)">
                <TicketUserAvatar v-if="task.assignee" :name="task.assignee.name" :avatar="task.assignee.avatar" />
                <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                    <FontAwesomeIcon :icon="task.department_label ? 'fal fa-building' : 'fal fa-user'" fixed-width />
                </span>
                <span class="min-w-0 flex-1 truncate" :class="task.assignee || task.department_label ? 'text-gray-800' : 'text-gray-400'">{{ task.assignee?.name ?? task.department_label ?? ctrans("Unassigned") }}</span>
                <FontAwesomeIcon v-if="canReassign" :icon="isPending('assignee') ? 'fal fa-spinner' : 'fal fa-exchange-alt'" :spin="isPending('assignee')" class="shrink-0 text-xs text-gray-400" fixed-width aria-hidden="true" />
            </component>
            <Popover v-if="canReassign" ref="assigneePopover" @show="isAssigneePickerOpen = true" @hide="onAssigneePickerHide">
                <div class="w-64 space-y-2 text-sm">
                    <input v-model="assigneeQuery" type="text" class="w-full rounded border-gray-300 text-sm" :placeholder="ctrans('Search colleague…')" />
                    <div class="flex max-h-72 flex-col overflow-y-auto">
                        <button
                            v-if="me && task.assignee?.id !== me.id && !assigneeQuery.trim()"
                            type="button"
                            class="mb-1 flex items-center gap-2 rounded border-b border-gray-100 p-2 text-left font-medium text-[--app-accent-strong] transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                            @click="reassignTo(me.id)">
                            <TicketUserAvatar :name="me.name" :avatar="me.avatar" size="sm" />
                            <span class="truncate">{{ ctrans("Assign to me") }}</span>
                        </button>
                        <button
                            v-for="person in assigneeCandidates"
                            :key="person.id"
                            type="button"
                            class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                            @click="reassignTo(person.id)">
                            <TicketUserAvatar :name="person.name" :avatar="person.avatar" size="sm" />
                            <span class="truncate">{{ person.name }}</span>
                        </button>
                        <p v-if="!assigneeCandidates.length" class="p-2 text-gray-400">{{ ctrans("No colleague found") }}</p>
                    </div>
                </div>
            </Popover>
        </div>

        <div v-if="task.collaborators.length || canManagePeople">
            <p :class="sectionLabelClass">{{ ctrans("Working on it too") }}</p>
            <StaffTaskPeoplePicker
                :modelValue="collaboratorIds"
                :options="collaboratorPeople"
                :excludeIds="task.assignee ? [task.assignee.id] : []"
                :editable="canManagePeople"
                :canRemove="(personId) => canRemoveCollaborators || personId === myId"
                :pending="isPending('collaborators')"
                @update:modelValue="changeCollaborators"
                @hide="saveCollaborators" />
        </div>

        <div>
            <p :class="sectionLabelClass">{{ ctrans("Status") }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[task.status_icon.color]">
                    <FontAwesomeIcon :icon="task.status_icon.icon" fixed-width />
                    {{ task.status_label }}
                </span>
                <template v-if="canEdit">
                    <button
                        v-for="action in statusActions"
                        :key="action.status + action.icon"
                        v-tooltip="action.label"
                        type="button"
                        class="rounded-md p-1.5 transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                        :class="action.class"
                        @click="runStatusAction(action.status)">
                        <FontAwesomeIcon :icon="isPending(`status:${action.status}`) ? 'fal fa-spinner' : action.icon" :spin="isPending(`status:${action.status}`)" fixed-width />
                    </button>
                </template>
            </div>
            <button
                v-if="canAskForHelp"
                type="button"
                class="mt-2 inline-flex items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-800 transition duration-200 hover:bg-amber-100 active:!bg-amber-200"
                v-tooltip="ctrans('Stuck? Let the requester and the supervisors know')"
                @click="openHelpDialog">
                <FontAwesomeIcon :icon="isPending('help') ? 'fal fa-spinner' : 'fal fa-hands-helping'" :spin="isPending('help')" fixed-width aria-hidden="true" />
                {{ ctrans("Ask for help") }}
            </button>
        </div>

        <div>
            <p :class="sectionLabelClass">{{ ctrans("Priority and due date") }}</p>
            <div class="flex flex-wrap gap-2">
                <span
                    v-tooltip="canEdit ? ctrans('Priority · click to change') : ctrans('Priority')"
                    :class="[chipClass, canEdit && editableChipClass, isPriorityPickerOpen && '!bg-gray-300']"
                    :tabindex="canEdit ? 0 : undefined"
                    @click="canEdit && priorityPopover.toggle($event)"
                    @keydown.enter.prevent="canEdit && priorityPopover.toggle($event)">
                    <FontAwesomeIcon v-if="isPending('priority')" icon="fal fa-spinner" spin fixed-width />
                    <Icon v-else :data="task.priority_icon" />
                    {{ priorityOption?.label ?? task.priority }}
                </span>
                <span
                    v-tooltip="dueTooltip"
                    :class="[chipClass, isDueClickable && editableChipClass, isDuePickerOpen && '!bg-gray-300', task.is_overdue && '!bg-red-100 text-red-700']"
                    :tabindex="isDueClickable ? 0 : undefined"
                    @click="onDueClick"
                    @keydown.enter.prevent="onDueClick">
                    <FontAwesomeIcon :icon="isPending('due_at') ? 'fal fa-spinner' : 'fal fa-calendar'" :spin="isPending('due_at')" fixed-width />
                    {{ task.due_at ? shortDate(task.due_at) : ctrans("No due date") }}
                </span>
            </div>

            <div v-if="task.eta_proposal" class="mt-2 rounded-md border border-amber-200 bg-amber-50 p-2.5 text-sm">
                <p class="flex items-center gap-1.5 font-medium text-amber-800">
                    <FontAwesomeIcon icon="fal fa-calendar-edit" fixed-width />
                    {{ ctrans(":name suggests :date", { name: task.eta_proposal.by_name, date: shortDate(task.eta_proposal.due_at) }) }}
                </p>
                <p class="mt-0.5 text-xs text-amber-700">
                    {{ task.eta_proposal.previous_due_at ? ctrans("Was :date", { date: shortDate(task.eta_proposal.previous_due_at) }) : ctrans("No due date before") }}
                </p>
                <p class="mt-1.5 whitespace-pre-line text-gray-700">{{ task.eta_proposal.reason }}</p>
                <div v-if="canSetDue" class="mt-2 flex gap-2">
                    <Button size="small" :label="ctrans('Accept')" :loading="isPending('eta:accept')" @click="decideEta('accept')" />
                    <Button size="small" text severity="secondary" :label="ctrans('Decline')" :loading="isPending('eta:decline')" @click="decideEta('decline')" />
                </div>
                <p v-else class="mt-1.5 text-xs text-amber-700">{{ ctrans("Waiting for :name to answer", { name: task.requester?.name ?? ctrans("the requester") }) }}</p>
            </div>
            <Popover v-if="canEdit" ref="priorityPopover" @show="isPriorityPickerOpen = true" @hide="isPriorityPickerOpen = false">
                <Listbox :model-value="task.priority" :options="options.priorities" option-label="label" option-value="value" class="border-0" @update:model-value="choosePriority">
                    <template #option="{ option }">
                        <Icon :data="option.icon" class="mr-2" />{{ option.label }}
                    </template>
                </Listbox>
            </Popover>
            <Popover v-if="canSetDue" ref="duePopover" :pt="{ content: { class: '!p-0' } }" @show="isDuePickerOpen = true" @hide="isDuePickerOpen = false">
                <div class="w-72 text-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Due date") }}</span>
                        <span v-if="task.due_at" class="text-xs text-gray-500">{{ useFormatTime(task.due_at, { formatTime: "d MMM yyyy" }) }}</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 px-3 pt-2.5">
                        <button
                            v-for="pick in quickDuePicks"
                            :key="pick.label"
                            type="button"
                            class="rounded-full border border-gray-200 px-2.5 py-0.5 text-xs text-gray-600 transition duration-200 hover:border-gray-300 hover:bg-gray-50 active:!bg-gray-100"
                            @click="chooseDueDate(pick.date)">
                            {{ pick.label }}
                        </button>
                    </div>
                    <DatePicker
                        :modelValue="dueDate"
                        inline
                        :minDate="startOfToday"
                        :pt="{
                            panel: { class: '!min-w-0 !border-0 !p-3 !shadow-none' },
                            header: { class: '!px-0 !pt-0 !pb-2' },
                            tableHeaderCell: { class: '!p-0 !text-[11px] !font-medium !text-gray-400' },
                            dayCell: { class: '!p-0.5' },
                            day: { class: '!h-8 !w-8 !text-sm' },
                        }"
                        @update:modelValue="chooseDueDate" />
                    <div v-if="task.due_at" class="border-t border-gray-100 px-3 py-2">
                        <button type="button" class="rounded px-2 py-1 text-xs text-red-600 transition duration-200 hover:bg-red-50 active:!bg-red-100" @click="chooseDueDate(null)">
                            {{ ctrans("Remove due date") }}
                        </button>
                    </div>
                </div>
            </Popover>
        </div>

        <label v-if="canSubscribe" v-tooltip="ctrans('Get a notification when someone writes in this task')" class="flex cursor-pointer items-center gap-x-2 text-gray-600">
            <FontAwesomeIcon icon="fal fa-bell" fixed-width :class="task.is_subscribed ? 'text-gray-500' : 'text-gray-300'" />
            <input type="checkbox" :checked="!!task.is_subscribed" :disabled="isBusy" class="cursor-pointer rounded border-gray-300 disabled:cursor-wait" @change="toggleSubscription" />
            {{ ctrans("Notify me about this task") }}
            <FontAwesomeIcon v-if="isPending('subscription')" icon="fal fa-spinner" spin fixed-width class="text-gray-400" />
        </label>

        <StaffTaskEtaDialog v-model:visible="etaDialogOpen" :task="task" @suggested="emit('updated')" />

        <Dialog
            v-model:visible="helpDialogOpen"
            modal
            dismissableMask
            :header="ctrans('Ask for help with :reference', { reference: task.reference })"
            :style="{ width: '28rem' }"
            :breakpoints="{ '640px': '95vw' }">
            <form class="space-y-3" @submit.prevent="askForHelp">
                <p class="text-sm text-gray-600">{{ ctrans(":name and the supervisors will be told. Say what is stopping you, if you can.", { name: task.requester?.name ?? ctrans("The requester") }) }}</p>
                <Textarea v-model="helpNote" rows="3" maxlength="500" autofocus autoResize fluid :placeholder="ctrans('What is stopping you? (optional)')" />
                <div class="flex justify-end gap-x-2">
                    <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="helpDialogOpen = false" />
                    <Button type="submit" :label="ctrans('Ask for help')" />
                </div>
            </form>
        </Dialog>

        <Dialog
            v-model:visible="cancelNoteOpen"
            modal
            dismissableMask
            :header="ctrans(`Why can't :reference be done?`, { reference: task.reference })"
            :style="{ width: '28rem' }"
            :breakpoints="{ '640px': '95vw' }">
            <form class="space-y-3" @submit.prevent="confirmCancel">
                <Textarea v-model="cancelNote" rows="3" maxlength="1000" autofocus autoResize fluid />
                <div class="flex justify-end gap-x-2">
                    <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="cancelNoteOpen = false" />
                    <Button type="submit" severity="danger" :label="ctrans('Confirm')" :disabled="!cancelNote.trim()" />
                </div>
            </form>
        </Dialog>
    </div>
</template>
