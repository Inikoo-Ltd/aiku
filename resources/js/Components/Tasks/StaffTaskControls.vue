<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import axios from "axios"
import { format, parseISO } from "date-fns"
import { Popover, Listbox, DatePicker, Dialog, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import StaffTaskPeoplePicker from "@/Components/Tasks/StaffTaskPeoplePicker.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBuilding, faBell, faCalendar, faUser, faSpinner, faPlay, faPause, faCheck, faTimes, faUndo, faCircle, faCheckCircle, faBan } from "@fal"
library.add(faBuilding, faBell, faCalendar, faUser, faSpinner, faPlay, faPause, faCheck, faTimes, faUndo, faCircle, faCheckCircle, faBan)

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
    }
    canEdit: boolean
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

onMounted(() => {
    if (props.canEdit) loadCoworkers()
})

watch(() => props.canEdit, (canEdit) => {
    if (canEdit && !coworkers.value.length) loadCoworkers()
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
            <div class="flex items-center gap-2 p-2">
                <TicketUserAvatar v-if="task.assignee" :name="task.assignee.name" :avatar="task.assignee.avatar" />
                <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                    <FontAwesomeIcon :icon="task.department_label ? 'fal fa-building' : 'fal fa-user'" fixed-width />
                </span>
                <span :class="task.assignee || task.department_label ? 'text-gray-800' : 'text-gray-400'">{{ task.assignee?.name ?? task.department_label ?? ctrans("Unassigned") }}</span>
            </div>
        </div>

        <div v-if="task.collaborators.length || canEdit">
            <p :class="sectionLabelClass">{{ ctrans("Working on it too") }}</p>
            <StaffTaskPeoplePicker
                :modelValue="collaboratorIds"
                :options="collaboratorPeople"
                :excludeIds="task.assignee ? [task.assignee.id] : []"
                :editable="canEdit"
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
                    v-tooltip="canEdit ? ctrans('Due date · click to change') : ctrans('Due date')"
                    :class="[chipClass, canEdit && editableChipClass, isDuePickerOpen && '!bg-gray-300', task.is_overdue && '!bg-red-100 text-red-700']"
                    :tabindex="canEdit ? 0 : undefined"
                    @click="canEdit && duePopover.toggle($event)"
                    @keydown.enter.prevent="canEdit && duePopover.toggle($event)">
                    <FontAwesomeIcon :icon="isPending('due_at') ? 'fal fa-spinner' : 'fal fa-calendar'" :spin="isPending('due_at')" fixed-width />
                    {{ task.due_at ? useFormatTime(task.due_at, { formatTime: "mdy" }) : ctrans("No due date") }}
                </span>
            </div>
            <Popover v-if="canEdit" ref="priorityPopover" @show="isPriorityPickerOpen = true" @hide="isPriorityPickerOpen = false">
                <Listbox :model-value="task.priority" :options="options.priorities" option-label="label" option-value="value" class="border-0" @update:model-value="choosePriority">
                    <template #option="{ option }">
                        <Icon :data="option.icon" class="mr-2" />{{ option.label }}
                    </template>
                </Listbox>
            </Popover>
            <Popover v-if="canEdit" ref="duePopover" @show="isDuePickerOpen = true" @hide="isDuePickerOpen = false">
                <div class="space-y-2">
                    <DatePicker :modelValue="dueDate" inline @update:modelValue="chooseDueDate" />
                    <button v-if="task.due_at" type="button" class="w-full rounded px-2 py-1 text-sm text-gray-500 transition duration-200 hover:bg-gray-100 active:!bg-gray-200" @click="chooseDueDate(null)">
                        {{ ctrans("Remove due date") }}
                    </button>
                </div>
            </Popover>
        </div>

        <label v-if="canSubscribe" v-tooltip="ctrans('Get a notification when someone writes in this task')" class="flex cursor-pointer items-center gap-x-2 text-gray-600">
            <FontAwesomeIcon icon="fal fa-bell" fixed-width :class="task.is_subscribed ? 'text-gray-500' : 'text-gray-300'" />
            <input type="checkbox" :checked="!!task.is_subscribed" :disabled="isBusy" class="cursor-pointer rounded border-gray-300 disabled:cursor-wait" @change="toggleSubscription" />
            {{ ctrans("Notify me about this task") }}
            <FontAwesomeIcon v-if="isPending('subscription')" icon="fal fa-spinner" spin fixed-width class="text-gray-400" />
        </label>

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
