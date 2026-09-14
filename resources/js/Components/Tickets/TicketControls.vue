<!--
 Author Louis Perez
 Created on 14-09-2026-16h-26m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { Popover, Listbox, Dialog } from "primevue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus, faPlusCircle, faExchange, faHourglassHalf, faVial, faShieldCheck, faShield, faRocket } from "@fal"

library.add(faRocket, faVial, faShieldCheck, faShield, faHourglassHalf, faPlusCircle, faExchange, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus,faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle)

type Option<Value> = { label: string; value: Value }

const props = defineProps<{
    ticket: any
    options: {
        statuses: Option<string>[]
        priorities: Option<string>[]
        assignees: (Option<number> & { avatar?: Record<string, string> | null; is_me?: boolean })[]
        tags: string[]
        kinds: Option<string>[]
        modules: Option<string>[]
    }
    can_manage: boolean
    can_assign: boolean
    can_flag_confidential: boolean
    can_qa: boolean
    is_reporter: boolean
    can_change_kind_module: boolean
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        escalate: { name: string; parameters: Record<string, unknown> }
    }
}>()

const emit = defineEmits<{
    (e: "updated"): void
}>()

const kindIcons: Record<string, string> = {
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    escalation: "fal fa-level-up",
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

const done = { status: "resolved", label: trans("Done"), icon: "fal fa-check", class: "text-green-600" }
const cancel = { status: "cancelled", label: trans("Cancel"), icon: "fal fa-ban", class: "text-red-500" }
const start = { status: "in_progress", label: trans("Start"), icon: "fal fa-play", class: "text-blue-600" }

const statusActions: Record<string, { status: string; label: string; icon: string; class: string }[]> = {
    open: [],
    assigned: [start, done, cancel],
    in_progress: [
        { status: "waiting", label: trans("Ask reporter"), icon: "fal fa-question-circle", class: "text-blue-500" },
        { status: "assigned", label: trans("Stop, back to assigned"), icon: "fal fa-stop", class: "text-gray-600" },
        done,
        cancel,
    ],
    waiting: [{ ...start, label: trans("Resume") }, done, cancel],
    resolved: [{ status: "open", label: trans("Reopen"), icon: "fal fa-undo", class: "text-gray-600" }],
    cancelled: [{ status: "open", label: trans("Reopen"), icon: "fal fa-undo", class: "text-gray-600" }],
}

const selectableKinds = computed(() => props.options.kinds.filter((kind) => kind.value !== "escalation"))
const canChangeKind = computed(() => props.can_change_kind_module && props.ticket.kind !== "escalation")

const newTag = ref("")
const tagOptions = computed(() => Array.from(new Set([...props.options.tags, ...props.ticket.tags])))
const tagPopover = ref()
const availableTags = computed(() => tagOptions.value.filter((tag) => !props.ticket.tags.includes(tag) && tag.includes(newTag.value.trim().toLowerCase())))
const addTypedTag = () => {
    const tag = newTag.value.trim().toLowerCase()
    if (!tag || props.ticket.tags.includes(tag)) return
    update("tags", [...props.ticket.tags, tag])
    newTag.value = ""
}

const escalate = () => router.post(route(props.routes.escalate.name, props.routes.escalate.parameters))

const waitingPresets = [
    { label: trans("2 hours"), hours: 2 },
    { label: trans("1 day"), hours: 24 },
    { label: trans("2 days"), hours: 48 },
    { label: trans("3 days"), hours: 72 },
    { label: trans("14 days"), hours: 336 },
]

const isAskReporterOpen = ref(false)
const question = ref("")
const waitingHours = ref(72)
const isAsking = ref(false)

const openAskReporter = () => {
    question.value = ""
    waitingHours.value = props.ticket.default_waiting_hours
    isAskReporterOpen.value = true
}

const askReporter = () => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        { status: "waiting", question: question.value, waiting_hours: waitingHours.value },
        {
            preserveScroll: true,
            onStart: () => (isAsking.value = true),
            onFinish: () => (isAsking.value = false),
            onSuccess: () => {
                isAskReporterOpen.value = false
                emit("updated")
            },
        }
    )
}

const isStatusNoteOpen = ref(false)
const statusNoteAction = ref<"resolved" | "cancelled">("resolved")
const statusNote = ref("")
const isSendingStatusNote = ref(false)

const openStatusNote = (status: "resolved" | "cancelled") => {
    statusNoteAction.value = status
    statusNote.value = ""
    isStatusNoteOpen.value = true
}

const sendStatusNote = (isWaitingForDeployment = false) => {
    router.patch(
        route(props.routes.update.name, props.routes.update.parameters),
        isWaitingForDeployment
            ? { is_waiting_for_deployment: true, status_comment: statusNote.value }
            : { status: statusNoteAction.value, status_comment: statusNote.value },
        {
            preserveScroll: true,
            onStart: () => (isSendingStatusNote.value = true),
            onFinish: () => (isSendingStatusNote.value = false),
            onSuccess: () => {
                isStatusNoteOpen.value = false
                emit("updated")
            },
        }
    )
}

const runStatusAction = (status: string) => {
    if (status === "waiting") openAskReporter()
    else if (status === "resolved" || status === "cancelled") openStatusNote(status)
    else update("status", status)
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

const canAskQa = computed(() => props.can_manage && ["in_progress", "waiting", "resolved"].includes(props.ticket.status) && props.ticket.qa_status !== "requested")

const update = (field: string, value: unknown) => {
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, { preserveScroll: true, onSuccess: () => emit("updated") })
}
</script>

<template>
    <div class="space-y-4">
            <div>
                <component :is="can_assign ? 'button' : 'div'" type="button" class="flex items-center gap-2 rounded p-2 transition duration-200" :class="[can_assign && 'hover:bg-gray-100 active:!bg-gray-200', isAssigneePickerOpen && '!bg-gray-200']" @click="can_assign && assigneePopover.toggle($event)">
                    <img v-if="ticket.assignee_avatar?.original" :src="ticket.assignee_avatar.original" class="h-7 w-7 rounded-full object-cover" alt="" />
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-user" fixed-width />
                    </span>
                    <span :class="ticket.assignee ? 'text-gray-800' : 'text-gray-400'">{{ ticket.assignee || trans("Unassigned") }}</span>
                </component>
                <Popover v-if="can_assign" ref="assigneePopover" @show="isAssigneePickerOpen = true" @hide="isAssigneePickerOpen = false">
                    <button
                        v-if="options.assignees.some((engineer) => engineer.is_me && engineer.value !== ticket.assignee_id)"
                        type="button"
                        class="mb-2 w-full rounded bg-indigo-50 px-2 py-1 text-sm font-medium text-indigo-700 hover:bg-indigo-100 active:!bg-indigo-200 transition duration-200"
                        @click="update('assignee_id', options.assignees.find((engineer) => engineer.is_me)!.value); assigneePopover.hide()">
                        {{ trans("Assign to me") }}
                    </button>
                    <div class="grid grid-cols-4 gap-2">
                        <button
                            v-for="engineer in options.assignees"
                            :key="engineer.value"
                            type="button"
                            class="flex w-16 flex-col items-center gap-1 rounded p-1 text-xs hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                            :class="engineer.value === ticket.assignee_id && 'bg-indigo-50 text-indigo-700'"
                            @click="update('assignee_id', engineer.value); assigneePopover.hide()">
                            <img v-if="engineer.avatar?.original" :src="engineer.avatar.original" class="h-9 w-9 rounded-full object-cover" alt="" />
                            <span v-else class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                                <FontAwesomeIcon icon="fal fa-user" fixed-width />
                            </span>
                            <span class="w-full truncate text-center">{{ engineer.label }}</span>
                        </button>
                    </div>
                    <button v-if="can_flag_confidential && ticket.assignee_id" type="button" class="mt-2 w-full rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('assignee_id', null); assigneePopover.hide()">
                        {{ trans("Unassign") }}
                    </button>
                </Popover>
            </div>
            <div v-if="can_manage || is_reporter">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[ticket.status_icon.color]">
                        <FontAwesomeIcon :icon="ticket.status_icon.icon" fixed-width />
                        {{ ticket.status_label }}
                    </span>
                    <span v-if="ticket.status === 'waiting' && ticket.waiting_until" v-tooltip="trans('Cancelled if no reply by then')" class="text-xs text-gray-500">
                        <FontAwesomeIcon icon="fal fa-hourglass-half" fixed-width />
                        {{ useFormatTime(ticket.waiting_until, { formatTime: "hm" }) }}
                    </span>
                    <button
                        v-for="action in statusActions[ticket.status]"
                        :key="action.status"
                        v-tooltip="action.label"
                        type="button"
                        class="rounded-md p-1.5 hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                        :class="action.class"
                        @click="runStatusAction(action.status)">
                        <FontAwesomeIcon :icon="action.icon" fixed-width />
                    </button>
                </div>
            </div>
            <div v-if="ticket.is_waiting_for_deployment" class="flex items-center gap-2">
                <span v-tooltip="trans('Marked as done automatically on the next deployment')" class="inline-flex items-center gap-1.5 rounded-md bg-purple-50 px-2 py-1 text-sm font-medium text-purple-700">
                    <FontAwesomeIcon icon="fal fa-rocket" fixed-width />
                    {{ trans("Waiting for deployment") }}
                </span>
                <button v-if="can_manage" v-tooltip="trans('Withdraw')" type="button" class="rounded-md p-1.5 text-gray-400 transition duration-200 hover:bg-gray-100 active:!bg-gray-200" @click="update('is_waiting_for_deployment', false)">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
            </div>
            <div v-if="ticket.qa_status || canAskQa" class="flex items-center gap-2">
                <span v-if="ticket.qa_status" v-tooltip="ticket.qa_user ? `${ticket.qa_status_label} · ${ticket.qa_user}` : ticket.qa_status_label" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[ticket.qa_status_icon.color]">
                    <FontAwesomeIcon :icon="ticket.qa_status_icon.icon" fixed-width />
                    {{ ticket.qa_status_label }}
                </span>
                <template v-if="can_qa && ticket.qa_status === 'requested'">
                    <button v-tooltip="trans('QA passed')" type="button" class="rounded-md p-1.5 text-green-600 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="openQaVerdict('passed')"><FontAwesomeIcon icon="fal fa-shield-check" fixed-width /></button>
                    <button v-tooltip="trans('QA failed')" type="button" class="rounded-md p-1.5 text-red-500 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="openQaVerdict('failed')"><FontAwesomeIcon icon="fal fa-shield" fixed-width /></button>
                </template>
                <button v-if="canAskQa" v-tooltip="ticket.qa_status ? trans('Ask QA to check again') : trans('Ask QA to check')" type="button" class="rounded-md p-1.5 text-amber-600 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('qa_status', 'requested')"><FontAwesomeIcon icon="fal fa-vial" fixed-width /></button>
                <button v-if="can_manage && ticket.qa_status === 'requested'" v-tooltip="trans('Withdraw QA request')" type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('qa_status', null)"><FontAwesomeIcon icon="fal fa-times" fixed-width /></button>
            </div>
            <template v-if="can_manage">
            <div v-if="ticket.type === 'help'" class="flex flex-wrap gap-2">
                <span
                    v-tooltip="canChangeKind ? trans('Kind · double click to change') : trans('Kind')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
                    :class="[canChangeKind && 'cursor-pointer hover:bg-gray-200 active:!bg-gray-300', isKindPickerOpen && '!bg-gray-300']"
                    @dblclick="canChangeKind && kindPopover.toggle($event)"
                    :tabindex="canChangeKind ? 0 : undefined"
                    @keydown.enter.prevent="canChangeKind && kindPopover.toggle($event)">
                    <FontAwesomeIcon :icon="kindIcons[ticket.kind] ?? 'fal fa-question-circle'" fixed-width />
                    {{ optionLabel(options.kinds, ticket.kind) ?? trans("No kind") }}
                </span>
                <span
                    v-tooltip="can_change_kind_module ? trans('Module · double click to change') : trans('Module')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
                    :class="[can_change_kind_module && 'cursor-pointer hover:bg-gray-200 active:!bg-gray-300', isModulePickerOpen && '!bg-gray-300']"
                    @dblclick="can_change_kind_module && modulePopover.toggle($event)"
                    :tabindex="can_change_kind_module ? 0 : undefined"
                    @keydown.enter.prevent="can_change_kind_module && modulePopover.toggle($event)">
                    <FontAwesomeIcon icon="fal fa-cube" fixed-width />
                    {{ optionLabel(options.modules, ticket.module) ?? trans("No module") }}
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
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Tags") }}</p>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span v-for="tag in ticket.tags" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs text-indigo-700">
                        {{ tag }}
                        <button v-tooltip="trans('Remove')" type="button" class="text-indigo-400 hover:text-indigo-700 active:!text-indigo-700 transition duration-200" @click="update('tags', ticket.tags.filter((t: string) => t !== tag))">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width />
                        </button>
                    </span>
                    <button v-tooltip="trans('Add tag')" type="button" class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-500 hover:border-indigo-400 active:!border-indigo-400 hover:text-indigo-600 active:!text-indigo-600 transition duration-200" :class="isTagPickerOpen && '!border-indigo-400 !text-indigo-600 !bg-indigo-50'" @click="tagPopover.toggle($event)">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width />
                    </button>
                </div>
                <Popover ref="tagPopover" @show="isTagPickerOpen = true" @hide="isTagPickerOpen = false">
                    <div class="w-60 space-y-2">
                        <input v-model="newTag" type="text" class="w-full rounded border-gray-300 text-sm" :placeholder="trans('Search or create a tag')" @keydown.enter.prevent="addTypedTag" />
                        <div class="max-h-60 overflow-y-auto">
                            <button
                                v-for="tag in availableTags"
                                :key="tag"
                                type="button"
                                class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100 active:!bg-gray-200 transition duration-200"
                                @click="update('tags', [...ticket.tags, tag]); newTag = ''">
                                {{ tag }}
                            </button>
                            <button v-if="newTag.trim() && !tagOptions.includes(newTag.trim().toLowerCase())" type="button" class="block w-full rounded px-2 py-1 text-left text-sm text-indigo-600 hover:bg-indigo-50 active:!bg-indigo-100 transition duration-200" @click="addTypedTag">
                                <FontAwesomeIcon icon="fal fa-plus" fixed-width /> {{ trans("Create") }} "{{ newTag.trim() }}"
                            </button>
                            <p v-if="!availableTags.length && !newTag.trim()" class="px-2 py-1 text-sm text-gray-400">{{ trans("No more tags") }}</p>
                        </div>
                    </div>
                </Popover>
            </div>
            <Button v-if="ticket.type === 'customer' && !ticket.escalations.length" type="secondary" icon="fal fa-level-up" :label="trans('Escalate to help desk')" full @click="escalate" />
            <label v-if="can_flag_confidential" class="flex items-center gap-x-2 text-gray-600 cursor-pointer">
                <input type="checkbox" :checked="ticket.is_confidential" class="rounded border-gray-300 cursor-pointer" @change="update('is_confidential', ($event.target as HTMLInputElement).checked)" />
                {{ trans("Confidential") }} <span class="text-xs text-gray-400">({{ trans("only reporter and lead engineers") }})</span>
            </label>
            </template>
    <Dialog v-model:visible="isQaVerdictOpen" modal :header="qaVerdict === 'passed' ? trans('QA passed') : trans('QA failed')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ qaVerdict === 'passed' ? trans("What did you check?") : trans("What is still wrong?") }}</p>
                <textarea v-model="qaNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="qaVerdict === 'passed' ? trans('e.g. tried it on the SK shop with three orders, all fine') : trans('e.g. the total is still wrong when the order has a voucher')" />
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="isQaVerdictOpen = false" />
                <Button :type="qaVerdict === 'passed' ? 'primary' : 'negative'" :label="qaVerdict === 'passed' ? trans('Pass') : trans('Fail')" :icon="qaVerdict === 'passed' ? 'fal fa-shield-check' : 'fal fa-shield'" :loading="isSendingVerdict" :disabled="qaVerdict === 'failed' && !qaNote.trim()" @click="sendQaVerdict" />
            </div>
        </div>
    </Dialog>
    <Dialog v-model:visible="isAskReporterOpen" modal :header="trans('Ask reporter')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("What do we need to continue?") }}</p>
                <textarea v-model="question" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="trans('e.g. please send the order number and a screenshot of the error')" />
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Cancel the ticket if there is no reply in") }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="preset in waitingPresets"
                        :key="preset.hours"
                        type="button"
                        class="rounded-full border px-3 py-1 transition duration-200"
                        :class="waitingHours === preset.hours ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50 active:!bg-gray-100'"
                        @click="waitingHours = preset.hours">
                        {{ preset.label }}
                    </button>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Cancel')" @click="isAskReporterOpen = false" />
                <Button :label="trans('Send and wait')" icon="fal fa-question-circle" :loading="isAsking" :disabled="!question.trim()" @click="askReporter" />
            </div>
        </div>
    </Dialog>
    <Dialog v-model:visible="isStatusNoteOpen" modal :header="statusNoteAction === 'cancelled' ? trans('Cancel ticket') : trans('Mark as done')" :style="{ width: '32rem' }">
        <div class="space-y-4 text-sm">
            <div>
                <p class="mb-1 text-xs text-gray-500">{{ statusNoteAction === "cancelled" ? trans("Why is this ticket being cancelled?") : trans("What was done?") }}</p>
                <textarea v-model="statusNote" rows="5" class="w-full rounded border-gray-300 text-sm" :placeholder="statusNoteAction === 'cancelled' ? trans('e.g. duplicate of HELP-12, following up there') : trans('e.g. fixed the rounding in the invoice totals')" />
                <p class="mt-1 text-xs text-gray-400">{{ trans("This is published as a comment on the ticket.") }}</p>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="tertiary" :label="trans('Back')" @click="isStatusNoteOpen = false" />
                <Button
                    v-if="statusNoteAction === 'resolved' && can_manage && !ticket.is_waiting_for_deployment"
                    type="secondary"
                    icon="fal fa-rocket"
                    :label="trans('Set as Done on Next Deployment')"
                    :loading="isSendingStatusNote"
                    :disabled="!statusNote.trim()"
                    @click="sendStatusNote(true)" />
                <Button
                    :type="statusNoteAction === 'cancelled' ? 'negative' : 'primary'"
                    :label="statusNoteAction === 'cancelled' ? trans('Cancel ticket') : trans('Done')"
                    :icon="statusNoteAction === 'cancelled' ? 'fal fa-ban' : 'fal fa-check'"
                    :loading="isSendingStatusNote"
                    :disabled="!statusNote.trim()"
                    @click="sendStatusNote()" />
            </div>
        </div>
    </Dialog>
    </div>
</template>
