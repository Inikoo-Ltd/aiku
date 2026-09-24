<!--
 Author Louis Perez
 Created on 14-09-2026-16h-26m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { Popover, Listbox, Dialog } from "primevue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useTicketStatusActions } from "@/Composables/useTicketStatusActions"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketAskReporterDialog from "@/Components/Tickets/TicketAskReporterDialog.vue"
import TicketStatusNoteDialog from "@/Components/Tickets/TicketStatusNoteDialog.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus, faPlusCircle, faExchange, faHourglassHalf, faVial, faShieldCheck, faShield, faRocket, faUserPlus, faCheckSquare, faSquare, faBooks, faDatabase, faSearch, faTasks, faCommentDots } from "@fal"

library.add(faBooks, faDatabase, faSearch, faTasks, faUserPlus, faCheckSquare, faSquare, faRocket, faVial, faShieldCheck, faShield, faHourglassHalf, faPlusCircle, faExchange, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus,faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faCommentDots)

type Option<Value> = { label: string; value: Value }

const props = defineProps<{
    ticket: any
    options: {
        statuses: Option<string>[]
        priorities: Option<string>[]
        assignees: (Option<number> & { avatar?: Record<string, string> | null; is_me?: boolean })[]
        qa_users?: (Option<number> & { avatar?: Record<string, string> | null })[]
        tags: string[]
        kinds: Option<string>[]
        modules: Option<string>[]
        collaborators?: (Option<number> & { avatar?: Record<string, string> | null })[]
        developers?: { username: string; name: string }[]
    }
    can_manage: boolean
    can_assign: boolean
    can_flag_confidential: boolean
    can_qa: boolean
    is_reporter: boolean
    can_change_kind_module: boolean
    can_update?: boolean
    can_cancel_as_reporter?: boolean
    can_reopen_as_reporter?: boolean
    can_contribute?: boolean
    can_manage_collaborators?: boolean
    hideConfidential?: boolean
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        collaborators?: { name: string; parameters: Record<string, unknown> }
        deploy_comment?: { name: string; parameters: Record<string, unknown> }
    }
}>()

const emit = defineEmits<{
    (e: "updated"): void
}>()

const kindIcons: Record<string, string> = {
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    escalation: "fal fa-level-up",
    task: "fal fa-tasks",
    qa: "fal fa-vial",
    documentation: "fal fa-books",
    data_integrity: "fal fa-database",
    support: "fal fa-search",
}

const kindPopover = ref()
const modulePopover = ref()
const assigneePopover = ref()
const isAssigneePickerOpen = ref(false)
const isKindPickerOpen = ref(false)
const isModulePickerOpen = ref(false)
const isTagPickerOpen = ref(false)

const optionLabel = (options: { label: string; value: string }[], value: string | null) => options.find((option) => option.value === value)?.label

const statusBadgeClasses: Record<string, string> = {
    gray: "bg-gray-100 text-gray-700",
    blue: "bg-blue-100 text-blue-700",
    green: "bg-green-100 text-green-700",
    amber: "bg-amber-100 text-amber-700",
    red: "bg-red-100 text-red-700",
}

const { statusActions, actionsFor, cancel, reopenAsReporter } = useTicketStatusActions()

const selectableKinds = computed(() => props.options.kinds.filter((kind) => kind.value !== "escalation"))
const canChangeKind = computed(() => props.can_change_kind_module && props.ticket.kind !== "escalation")

const newTag = ref("")
const tagOptions = computed(() => Array.from(new Set([...props.options.tags, ...props.ticket.tags])))
const tagPopover = ref()
const availableTags = computed(() => tagOptions.value.filter((tag) => !props.ticket.tags.includes(tag) && tag.includes(newTag.value.trim().toLowerCase())))
const addTypedTag = () => {
    const tag = newTag.value.trim().toLowerCase()
    if (!tag || props.ticket.tags.includes(tag)) return
    update("tags", [...props.ticket.tags, tag], "tags:add")
    newTag.value = ""
}

// Who to name: the tooltip spells out the usernames, since @ in the comment box is the only
// thing that actually reaches somebody.
const developers = computed(() => props.options?.developers ?? [])

const developersTooltip = computed(() =>
    [ctrans("Type @ in a comment and pick:"), ...developers.value.map((person) => `@${person.username} (${person.name})`)].join("\n")
)

const pendingAction = ref<string | null>(null)
const isBusy = computed(() => pendingAction.value !== null)
const isPending = (action: string) => pendingAction.value === action

const isAskReporterOpen = ref(false)
const isStatusNoteOpen = ref(false)
const statusNoteAction = ref<"resolved" | "cancelled" | "answered">("resolved")

const openAskReporter = () => {
    isAskReporterOpen.value = true
}

const openStatusNote = (status: "resolved" | "cancelled" | "answered") => {
    statusNoteAction.value = status
    isStatusNoteOpen.value = true
}

const runStatusAction = (status: string) => {
    if (status === "waiting") openAskReporter()
    else if (status === "resolved" || status === "cancelled" || status === "answered") openStatusNote(status)
    else update("status", status, `status:${status}`)
}

const isQaVerdictOpen = ref(false)
const qaVerdict = ref<"passed" | "failed">("passed")
const qaNote = ref("")
const isSendingVerdict = ref(false)

const openQaVerdict = (verdict: "passed" | "failed") => {
    qaVerdict.value = verdict
    qaNote.value = ""
    isQaVerdictOpen.value = true
}

const sendQaVerdict = () => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        { qa_status: qaVerdict.value, qa_note: qaNote.value },
        {
            preserveScroll: true,
            onStart: () => (isSendingVerdict.value = true),
            onFinish: () => (isSendingVerdict.value = false),
            onSuccess: () => {
                isQaVerdictOpen.value = false
                emit("updated")
            },
        }
    )
}

const isClosed = computed(() => ["resolved", "cancelled"].includes(props.ticket.status))
const isResolvedWithinADay = computed(() => props.ticket.status === "resolved" && !!props.ticket.resolved_at && Date.now() - new Date(props.ticket.resolved_at).getTime() < 24 * 60 * 60 * 1000)
const canAskQa = computed(() => props.can_contribute && (["in_progress", "waiting", "pending_deploy"].includes(props.ticket.status) || isResolvedWithinADay.value) && props.ticket.qa_status !== "requested")

// Asking for a check is a question, and a question with nothing said about what changed makes
// QA guess. The note is optional, since sometimes the ticket already says it.
const isQaRequestOpen = ref(false)
const qaRequestNote = ref("")
const qaRequestUserId = ref<number | null>(null)

const openQaRequest = () => {
    qaRequestNote.value = ""
    qaRequestUserId.value = null
    isQaRequestOpen.value = true
}

const askQa = () => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        { qa_status: "requested", qa_user_id: qaRequestUserId.value, qa_note: qaRequestNote.value.trim() },
        {
            preserveScroll: true,
            onStart: () => (pendingAction.value = "qa:request"),
            onFinish: () => (pendingAction.value = null),
            onSuccess: () => {
                isQaRequestOpen.value = false
                emit("updated")
            },
        }
    )
}

const collaboratorPopover = ref()
const isCollaboratorPickerOpen = ref(false)

const collaboratorCandidates = computed(() => (props.options.collaborators ?? []).filter((person) => person.value !== props.ticket.assignee_id))

const savedCollaboratorIds = computed<number[]>(() => (props.ticket.collaborators ?? []).map((collaborator: { id: number }) => collaborator.id))
const draftCollaboratorIds = ref<number[] | null>(null)
const collaboratorIds = computed<number[]>(() => draftCollaboratorIds.value ?? savedCollaboratorIds.value)
let collaboratorSaveTimer: ReturnType<typeof setTimeout> | null = null

const saveCollaborators = () => {
    if (collaboratorSaveTimer) clearTimeout(collaboratorSaveTimer)
    collaboratorSaveTimer = null
    const nextIds = draftCollaboratorIds.value
    if (!nextIds || !props.routes.collaborators) return
    const isUnchanged = nextIds.length === savedCollaboratorIds.value.length && nextIds.every((id) => savedCollaboratorIds.value.includes(id))
    if (isUnchanged) {
        draftCollaboratorIds.value = null
        return
    }
    router.patch(route(props.routes.collaborators.name, props.routes.collaborators.parameters), { collaborator_ids: nextIds }, {
        preserveScroll: true,
        onStart: () => (pendingAction.value = "collaborators"),
        onFinish: () => {
            pendingAction.value = null
            draftCollaboratorIds.value = null
        },
        onSuccess: () => emit("updated"),
    })
}

const toggleCollaborator = (userId: number) => {
    if (isPending("collaborators")) return
    const currentIds = collaboratorIds.value
    draftCollaboratorIds.value = currentIds.includes(userId) ? currentIds.filter((id) => id !== userId) : [...currentIds, userId]
    if (collaboratorSaveTimer) clearTimeout(collaboratorSaveTimer)
    collaboratorSaveTimer = setTimeout(saveCollaborators, 800)
}

const onCollaboratorPickerHide = () => {
    isCollaboratorPickerOpen.value = false
    saveCollaborators()
}

onBeforeUnmount(saveCollaborators)

const me = computed(() => props.options.assignees.find((engineer: any) => engineer.is_me))
const otherAssignees = computed(() => props.options.assignees.filter((engineer: any) => !engineer.is_me))

const update = (field: string, value: unknown, action: string = field) => {
    if (isBusy.value) return
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, {
        preserveScroll: true,
        onStart: () => (pendingAction.value = action),
        onFinish: () => (pendingAction.value = null),
        onSuccess: () => emit("updated"),
    })
}

const isEditingDeployComment = ref(false)
const deployCommentDraft = ref("")

const canEditDeployComment = computed(
    () => props.can_contribute && props.ticket.status === "pending_deploy" && !!props.routes.deploy_comment
)

const startEditDeployComment = () => {
    deployCommentDraft.value = props.ticket.deploy_comment ?? ""
    isEditingDeployComment.value = true
}

const cancelEditDeployComment = () => {
    isEditingDeployComment.value = false
    deployCommentDraft.value = ""
}

const saveDeployComment = () => {
    if (isBusy.value || !props.routes.deploy_comment) return

    router.patch(
        route(props.routes.deploy_comment.name, props.routes.deploy_comment.parameters),
        { body: deployCommentDraft.value },
        {
            preserveScroll: true,
            onStart: () => (pendingAction.value = "deploy_comment"),
            onFinish: () => (pendingAction.value = null),
            onSuccess: () => {
                isEditingDeployComment.value = false
                emit("updated")
            },
        }
    )
}
</script>

<template>
    <div class="space-y-4" :class="isBusy && 'pointer-events-none'" :aria-busy="isBusy">
            <div>
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Assignee") }}</p>
                <component :is="can_assign ? 'button' : 'div'" type="button" class="flex items-center gap-2 rounded p-2 transition duration-200" :class="[can_assign && 'hover:bg-gray-100 active:!bg-gray-200', isAssigneePickerOpen && '!bg-gray-200']" @click="can_assign && assigneePopover.toggle($event)">
                    <TicketUserAvatar v-if="ticket.assignee" :name="ticket.assignee" :avatar="ticket.assignee_avatar" />
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-user" fixed-width />
                    </span>
                    <span :class="ticket.assignee ? 'text-gray-800' : 'text-gray-400'">{{ ticket.assignee_short || ctrans("Unassigned") }}</span>
                    <FontAwesomeIcon v-if="isPending('assignee_id')" :icon="'fal fa-spinner'" spin fixed-width class="text-gray-400" />
                </component>
                <Popover v-if="can_assign" ref="assigneePopover" @show="isAssigneePickerOpen = true" @hide="isAssigneePickerOpen = false">
                    <button
                        v-if="me && me.value !== ticket.assignee_id"
                        type="button"
                        class="mb-2 flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-sm font-medium text-gray-700 hover:border-[--app-accent-muted] hover:text-[--app-accent-strong] active:!bg-gray-50 transition duration-200"
                        @click="update('assignee_id', me.value); assigneePopover.hide()">
                        <TicketUserAvatar :name="me.label" :avatar="me.avatar" />
                        {{ ctrans("Assign to me") }}
                    </button>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="engineer in otherAssignees"
                            :key="engineer.value"
                            type="button"
                            class="flex w-16 flex-col items-center gap-1 rounded p-1 text-xs hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                            :class="engineer.value === ticket.assignee_id && 'bg-[--app-accent-soft] text-[--app-accent-strong]'"
                            @click="update('assignee_id', engineer.value); assigneePopover.hide()">
                            <TicketUserAvatar :name="engineer.label" :avatar="engineer.avatar" size="lg" />
                            <span class="w-full truncate text-center">{{ engineer.label }}</span>
                        </button>
                    </div>
                    <button v-if="can_flag_confidential && ticket.assignee_id" type="button" class="mt-2 w-full rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('assignee_id', null); assigneePopover.hide()">
                        {{ ctrans("Unassign") }}
                    </button>
                </Popover>
            </div>
            <div v-if="ticket.collaborators?.length || (can_manage_collaborators && !isClosed && ticket.status !== 'pending_deploy')">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Collaborators") }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span v-for="collaborator in ticket.collaborators" :key="collaborator.id" v-tooltip="collaborator.name" class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 py-1 pl-1 pr-2.5 text-xs text-gray-700">
                        <TicketUserAvatar :name="collaborator.name" :avatar="collaborator.avatar" size="xs" />
                        {{ collaborator.short }}
                    </span>
                    <button
                        v-if="can_manage_collaborators && !isClosed && ticket.status !== 'pending_deploy'"
                        v-tooltip="ctrans('Add or remove collaborators')"
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-full border border-dashed text-sm border-gray-300 text-gray-500 transition duration-200 hover:border-[--app-accent] hover:text-[--app-accent-strong] active:!border-[--app-accent] active:!text-[--app-accent-strong]"
                        :class="isCollaboratorPickerOpen && '!border-[--app-accent] !bg-[--app-accent-soft] !text-[--app-accent-strong]'"
                        @click="collaboratorPopover.toggle($event)">
                        <FontAwesomeIcon :icon="isPending('collaborators') ? 'fal fa-spinner' : 'fal fa-user-plus'" :spin="isPending('collaborators')" fixed-width />
                    </button>
                </div>
                <Popover v-if="can_manage_collaborators && !isClosed && ticket.status !== 'pending_deploy'" ref="collaboratorPopover" @show="isCollaboratorPickerOpen = true" @hide="onCollaboratorPickerHide">
                    <div class="flex max-h-72 w-60 flex-col overflow-y-auto text-sm">
                        <button
                            v-for="person in collaboratorCandidates"
                            :key="person.value"
                            type="button"
                            class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                            @click="toggleCollaborator(person.value)">
                            <FontAwesomeIcon :icon="collaboratorIds.includes(person.value) ? 'fal fa-check-square' : 'fal fa-square'" fixed-width :class="collaboratorIds.includes(person.value) ? 'text-[--app-accent-strong]' : 'text-gray-400'" />
                            <TicketUserAvatar :name="person.label" :avatar="person.avatar" size="sm" />
                            <span class="truncate">{{ person.label }}</span>
                        </button>
                        <p v-if="!collaboratorCandidates.length" class="p-2 text-gray-400">{{ ctrans("Nobody to add") }}</p>
                    </div>
                </Popover>
            </div>
            <div v-if="can_manage || is_reporter || ticket.qa_status || canAskQa" class="space-y-2">
                <div v-if="can_manage || is_reporter">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Status") }}</p>
                <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[ticket.status_icon.color]">
                            <FontAwesomeIcon :icon="ticket.status_icon.icon" fixed-width />
                            {{ ticket.status_label }}
                        </span>
                        <span v-if="ticket.status === 'waiting' && ticket.waiting_until" v-tooltip="ctrans('Cancelled if no reply by then')" class="text-xs text-gray-500">
                            <FontAwesomeIcon icon="fal fa-hourglass-half" fixed-width />
                            {{ useFormatTime(ticket.waiting_until, { formatTime: "hm" }) }}
                        </span>
                        <button
                            v-for="action in can_update ? actionsFor(statusActions, ticket) : (can_cancel_as_reporter ? [cancel] : (can_reopen_as_reporter ? [reopenAsReporter] : []))"
                            :key="action.status"
                            v-tooltip="action.label"
                            type="button"
                            class="rounded-md p-1.5 hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                            :class="action.class"
                            @click="runStatusAction(action.status)">
                            <FontAwesomeIcon :icon="isPending(`status:${action.status}`) ? 'fal fa-spinner' : action.icon" :spin="isPending(`status:${action.status}`)" fixed-width />
                        </button>
                </div>
                </div>
                <div v-if="ticket.qa_status || canAskQa">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("QA") }}</p>
                <div class="flex flex-wrap items-center gap-2">
                        <span v-if="ticket.qa_status" v-tooltip="ticket.qa_user ? `${ticket.qa_status_label} · ${ticket.qa_user}` : ticket.qa_status_label" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[ticket.qa_status_icon.color]">
                            <FontAwesomeIcon :icon="ticket.qa_status_icon.icon" fixed-width />
                            {{ ticket.qa_status_label }}
                        </span>
                        <template v-if="can_qa && ticket.qa_status === 'requested'">
                            <button v-tooltip="ctrans('QA passed')" type="button" class="rounded-md p-1.5 text-green-600 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="openQaVerdict('passed')"><FontAwesomeIcon icon="fal fa-shield-check" fixed-width /></button>
                            <button v-tooltip="ctrans('QA failed')" type="button" class="rounded-md p-1.5 text-red-500 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="openQaVerdict('failed')"><FontAwesomeIcon icon="fal fa-shield" fixed-width /></button>
                        </template>
                        <button v-if="canAskQa" v-tooltip="ticket.qa_status ? ctrans('Ask QA to check again') : ctrans('Ask QA to check')" type="button" class="rounded-md p-1.5 text-amber-600 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="openQaRequest"><FontAwesomeIcon :icon="isPending('qa:request') ? 'fal fa-spinner' : 'fal fa-vial'" :spin="isPending('qa:request')" fixed-width /></button>
                        <button v-if="can_contribute && ticket.qa_status === 'requested'" v-tooltip="ctrans('Withdraw QA request')" type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('qa_status', null, 'qa:withdraw')"><FontAwesomeIcon :icon="isPending('qa:withdraw') ? 'fal fa-spinner' : 'fal fa-times'" :spin="isPending('qa:withdraw')" fixed-width /></button>
                </div>
                </div>
                <div v-if="ticket.status === 'pending_deploy' && (ticket.deploy_comment || canEditDeployComment)" class="mt-2 rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-gray-700">
                    <div class="mb-1 flex items-start justify-between gap-2">
                        <div class="text-xs font-medium text-green-700">{{ ctrans("Posted to the reporter when the deployment lands") }}</div>
                        <button v-if="canEditDeployComment && !isEditingDeployComment" v-tooltip="ctrans('Edit')" type="button" class="p-0.5 text-green-700 hover:text-green-900" @click="startEditDeployComment">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </button>
                    </div>

                    <template v-if="isEditingDeployComment">
                        <textarea
                            v-model="deployCommentDraft"
                            rows="4"
                            class="w-full rounded-md border border-green-300 bg-white px-2 py-1 text-sm focus:border-green-500 focus:outline-none focus:ring-1 focus:ring-green-500"
                            :placeholder="ctrans('Leave empty to post nothing when the deployment lands')" />
                        <div class="mt-1 text-xs text-gray-400">{{ ctrans("(markdown works: **bold**, lists, links)") }}</div>
                        <div class="mt-2 flex justify-end gap-2">
                            <button type="button" class="rounded-md px-2 py-1 text-xs text-gray-500 hover:text-gray-700" @click="cancelEditDeployComment">
                                {{ ctrans("Cancel") }}
                            </button>
                            <button type="button" class="rounded-md bg-green-600 px-3 py-1 text-xs font-medium text-white hover:bg-green-700 disabled:opacity-60" :disabled="isPending('deploy_comment')" @click="saveDeployComment">
                                <FontAwesomeIcon v-if="isPending('deploy_comment')" icon="fal fa-spinner" spin fixed-width aria-hidden="true" />
                                {{ ctrans("Save") }}
                            </button>
                        </div>
                    </template>

                    <TicketBody v-else-if="ticket.deploy_comment" :text="ticket.deploy_comment" />
                    <div v-else class="text-xs italic text-gray-500">{{ ctrans("Nothing will be posted when the deployment lands.") }}</div>
                </div>
            </div>
            <template v-if="can_manage || can_contribute">
            <div v-if="ticket.type === 'help'">
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Kind and module") }}</p>
                <div class="flex flex-wrap gap-2">
                <span
                    v-tooltip="canChangeKind ? ctrans('Kind · click to change') : ctrans('Kind')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
                    :class="[canChangeKind && 'cursor-pointer hover:bg-gray-200 active:!bg-gray-300', isKindPickerOpen && '!bg-gray-300']"
                    @click="canChangeKind && kindPopover.toggle($event)"
                    :tabindex="canChangeKind ? 0 : undefined"
                    @keydown.enter.prevent="canChangeKind && kindPopover.toggle($event)">
                    <FontAwesomeIcon :icon="isPending('kind') ? 'fal fa-spinner' : kindIcons[ticket.kind] ?? 'fal fa-question-circle'" :spin="isPending('kind')" fixed-width />
                    {{ optionLabel(options.kinds, ticket.kind) ?? ctrans("No kind") }}
                </span>
                <span
                    v-tooltip="can_change_kind_module ? ctrans('Module · click to change') : ctrans('Module')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
                    :class="[can_change_kind_module && 'cursor-pointer hover:bg-gray-200 active:!bg-gray-300', isModulePickerOpen && '!bg-gray-300']"
                    @click="can_change_kind_module && modulePopover.toggle($event)"
                    :tabindex="can_change_kind_module ? 0 : undefined"
                    @keydown.enter.prevent="can_change_kind_module && modulePopover.toggle($event)">
                    <FontAwesomeIcon :icon="isPending('module') ? 'fal fa-spinner' : 'fal fa-cube'" :spin="isPending('module')" fixed-width />
                    {{ optionLabel(options.modules, ticket.module) ?? ctrans("No module") }}
                </span>
                <Popover v-if="canChangeKind" ref="kindPopover" @show="isKindPickerOpen = true" @hide="isKindPickerOpen = false">
                    <Listbox :model-value="ticket.kind" :options="selectableKinds" option-label="label" option-value="value" class="border-0" @update:model-value="update('kind', $event); kindPopover.hide()">
                        <template #option="{ option }">
                            <FontAwesomeIcon :icon="kindIcons[option.value]" fixed-width class="mr-2" />{{ option.label }}
                        </template>
                    </Listbox>
                </Popover>
                <Popover v-if="can_change_kind_module" ref="modulePopover" @show="isModulePickerOpen = true" @hide="isModulePickerOpen = false">
                    <Listbox :model-value="ticket.module" :options="options.modules" option-label="label" option-value="value" filter scroll-height="16rem" class="border-0" @update:model-value="update('module', $event); modulePopover.hide()" />
                </Popover>
                </div>
            </div>
            <div>
                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Tags") }}</p>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span v-for="tag in ticket.tags" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-[--app-accent-soft] px-2.5 py-0.5 text-xs text-[--app-accent-strong]">
                        {{ tag }}
                        <button v-if="can_contribute" v-tooltip="ctrans('Remove')" type="button" class="text-[--app-accent-strong] hover:text-[--app-accent-strong] active:!text-[--app-accent-strong] transition duration-200" @click="update('tags', ticket.tags.filter((t: string) => t !== tag), `tags:remove:${tag}`)">
                            <FontAwesomeIcon :icon="isPending(`tags:remove:${tag}`) ? 'fal fa-spinner' : 'fal fa-times'" :spin="isPending(`tags:remove:${tag}`)" fixed-width />
                        </button>
                    </span>
                    <button v-if="can_contribute" v-tooltip="ctrans('Add tag')" type="button" class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-500 hover:border-[--app-accent] active:!border-[--app-accent] hover:text-[--app-accent-strong] active:!text-[--app-accent-strong] transition duration-200" :class="isTagPickerOpen && '!border-[--app-accent] !text-[--app-accent-strong] !bg-[--app-accent-soft]'" @click="tagPopover.toggle($event)">
                        <FontAwesomeIcon :icon="isPending('tags:add') ? 'fal fa-spinner' : 'fal fa-plus'" :spin="isPending('tags:add')" fixed-width />
                    </button>
                </div>
                <Popover ref="tagPopover" @show="isTagPickerOpen = true" @hide="isTagPickerOpen = false">
                    <div class="w-60 space-y-2">
                        <input v-model="newTag" type="text" class="w-full rounded border-gray-300 text-sm" :placeholder="ctrans('Search or create a tag')" @keydown.enter.prevent="addTypedTag" />
                        <div class="max-h-60 overflow-y-auto">
                            <button
                                v-for="tag in availableTags"
                                :key="tag"
                                type="button"
                                class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                                @click="update('tags', [...ticket.tags, tag], 'tags:add'); newTag = ''">
                                {{ tag }}
                            </button>
                            <button v-if="newTag.trim() && !tagOptions.includes(newTag.trim().toLowerCase())" type="button" class="block w-full rounded px-2 py-1 text-left text-sm text-[--app-accent-strong] hover:bg-[--app-accent-soft] active:!bg-[--app-accent-muted] transition duration-200" @click="addTypedTag">
                                <FontAwesomeIcon icon="fal fa-plus" fixed-width /> {{ ctrans("Create") }} "{{ newTag.trim() }}"
                            </button>
                            <p v-if="!availableTags.length && !newTag.trim()" class="px-2 py-1 text-sm text-gray-400">{{ ctrans("No more tags") }}</p>
                        </div>
                    </div>
                </Popover>
            </div>
            <!-- A customer ticket used to be handed off to the help desk as a second ticket, which
                 split the thread in two. Naming the developer in this one keeps it whole. -->
            <p v-if="can_update && ticket.type === 'customer'" class="flex items-start gap-x-1.5 rounded-md bg-amber-50 px-2.5 py-2 text-xs text-amber-800">
                <span>{{ ctrans("Mention the developers if it's a bug that needs to be fixed ASAP") }}</span>
                <FontAwesomeIcon v-if="developers.length" icon="fal fa-question-circle" v-tooltip="developersTooltip"
                    class="mt-0.5 shrink-0 text-amber-500" fixed-width />
            </p>
            <label v-if="can_flag_confidential && !hideConfidential" class="flex items-center gap-x-2 text-gray-600 cursor-pointer">
                <input type="checkbox" :checked="ticket.is_confidential" :disabled="isBusy" class="rounded border-gray-300 cursor-pointer disabled:cursor-wait" @change="update('is_confidential', ($event.target as HTMLInputElement).checked, 'confidential')" />
                {{ ctrans("Confidential") }} <span class="text-xs text-gray-400">({{ ctrans("only reporter and lead engineers") }})</span>
                <FontAwesomeIcon v-if="isPending('confidential')" :icon="'fal fa-spinner'" spin fixed-width class="text-gray-400" />
            </label>
            </template>
    <Dialog v-model:visible="isQaRequestOpen" modal :header="ctrans('Ask QA to check')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ ctrans("What should they look at?") }} <span class="text-gray-400">{{ ctrans("(optional)") }}</span></p>
                <textarea v-model="qaRequestNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="ctrans('e.g. rounding on the invoice totals, worth trying a voucher order too')" />
                <p class="mt-1 text-xs text-gray-400">{{ ctrans("Posted as a comment on the ticket.") }}</p>
            </div>

            <div>
                <p class="mb-1 text-xs text-gray-500">{{ ctrans("Who should check it?") }}</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button"
                        class="rounded-md border px-2.5 py-1.5 text-sm transition duration-200"
                        :class="qaRequestUserId === null ? 'border-amber-300 bg-amber-50 font-medium text-amber-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click="qaRequestUserId = null">
                        {{ ctrans("Anyone in QA") }}
                    </button>
                    <button v-for="qaUser in options.qa_users ?? []" :key="qaUser.value" type="button"
                        class="flex items-center gap-2 rounded-md border px-2.5 py-1.5 text-sm transition duration-200"
                        :class="qaRequestUserId === qaUser.value ? 'border-amber-300 bg-amber-50 font-medium text-amber-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                        @click="qaRequestUserId = qaUser.value">
                        <TicketUserAvatar :name="qaUser.label" :avatar="qaUser.avatar" size="sm" />
                        <span class="truncate">{{ qaUser.label }}</span>
                    </button>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isQaRequestOpen = false" />
                <Button :label="ctrans('Ask QA')" icon="fal fa-vial" :loading="isPending('qa:request')" @click="askQa" />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="isQaVerdictOpen" modal :header="qaVerdict === 'passed' ? ctrans('QA passed') : ctrans('QA failed')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ qaVerdict === 'passed' ? ctrans("What did you check?") : ctrans("What is still wrong?") }}</p>
                <textarea v-model="qaNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="qaVerdict === 'passed' ? ctrans('e.g. tried it on the SK shop with three orders, all fine') : ctrans('e.g. the total is still wrong when the order has a voucher')" />
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isQaVerdictOpen = false" />
                <Button :type="qaVerdict === 'passed' ? 'primary' : 'negative'" :label="qaVerdict === 'passed' ? ctrans('Pass') : ctrans('Fail')" :icon="qaVerdict === 'passed' ? 'fal fa-shield-check' : 'fal fa-shield'" :loading="isSendingVerdict" :disabled="qaVerdict === 'failed' && !qaNote.trim()" @click="sendQaVerdict" />
            </div>
        </div>
    </Dialog>
    <TicketAskReporterDialog v-model:visible="isAskReporterOpen" :update-route="routes.update" :default-waiting-hours="ticket.default_waiting_hours" @updated="emit('updated')" />
    <TicketStatusNoteDialog
        v-model:visible="isStatusNoteOpen"
        :status="statusNoteAction"
        :update-route="routes.update"
        :can-wait-for-deployment="can_update && ticket.status !== 'pending_deploy'"
        :closes-conversation="ticket.closes_source"
        @updated="emit('updated')" />
    </div>
</template>
