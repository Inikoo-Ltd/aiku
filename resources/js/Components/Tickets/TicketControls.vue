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
import { useTicketStatusActions } from "@/Composables/useTicketStatusActions"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TicketAskReporterDialog from "@/Components/Tickets/TicketAskReporterDialog.vue"
import TicketStatusNoteDialog from "@/Components/Tickets/TicketStatusNoteDialog.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
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
    hideConfidential?: boolean
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

const { statusActions } = useTicketStatusActions()

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

const pendingAction = ref<string | null>(null)
const isBusy = computed(() => pendingAction.value !== null)
const isPending = (action: string) => pendingAction.value === action

const escalate = () => {
    if (isBusy.value) return
    router.post(route(props.routes.escalate.name, props.routes.escalate.parameters), {}, {
        onStart: () => (pendingAction.value = "escalate"),
        onFinish: () => (pendingAction.value = null),
    })
}

const isAskReporterOpen = ref(false)
const isStatusNoteOpen = ref(false)
const statusNoteAction = ref<"resolved" | "cancelled">("resolved")

const openAskReporter = () => {
    isAskReporterOpen.value = true
}

const openStatusNote = (status: "resolved" | "cancelled") => {
    statusNoteAction.value = status
    isStatusNoteOpen.value = true
}

const runStatusAction = (status: string) => {
    if (status === "waiting") openAskReporter()
    else if (status === "resolved" || status === "cancelled") openStatusNote(status)
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

const canAskQa = computed(() => props.can_manage && ["in_progress", "waiting", "resolved"].includes(props.ticket.status) && props.ticket.qa_status !== "requested")

const update = (field: string, value: unknown, action: string = field) => {
    if (isBusy.value) return
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, {
        preserveScroll: true,
        onStart: () => (pendingAction.value = action),
        onFinish: () => (pendingAction.value = null),
        onSuccess: () => emit("updated"),
    })
}
</script>

<template>
    <div class="space-y-4" :class="isBusy && 'pointer-events-none'" :aria-busy="isBusy">
            <div>
                <component :is="can_assign ? 'button' : 'div'" type="button" class="flex items-center gap-2 rounded p-2 transition duration-200" :class="[can_assign && 'hover:bg-gray-100 active:!bg-gray-200', isAssigneePickerOpen && '!bg-gray-200']" @click="can_assign && assigneePopover.toggle($event)">
                    <TicketUserAvatar v-if="ticket.assignee" :name="ticket.assignee" :avatar="ticket.assignee_avatar" />
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-user" fixed-width />
                    </span>
                    <span :class="ticket.assignee ? 'text-gray-800' : 'text-gray-400'">{{ ticket.assignee_short || trans("Unassigned") }}</span>
                    <FontAwesomeIcon v-if="isPending('assignee_id')" :icon="'fal fa-spinner'" spin fixed-width class="text-gray-400" />
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
                            <TicketUserAvatar :name="engineer.label" :avatar="engineer.avatar" size="lg" />
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
                        <FontAwesomeIcon :icon="isPending(`status:${action.status}`) ? 'fal fa-spinner' : action.icon" :spin="isPending(`status:${action.status}`)" fixed-width />
                    </button>
                </div>
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
                <button v-if="canAskQa" v-tooltip="ticket.qa_status ? trans('Ask QA to check again') : trans('Ask QA to check')" type="button" class="rounded-md p-1.5 text-amber-600 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('qa_status', 'requested', 'qa:request')"><FontAwesomeIcon :icon="isPending('qa:request') ? 'fal fa-spinner' : 'fal fa-vial'" :spin="isPending('qa:request')" fixed-width /></button>
                <button v-if="can_manage && ticket.qa_status === 'requested'" v-tooltip="trans('Withdraw QA request')" type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 active:!bg-gray-200 transition duration-200" @click="update('qa_status', null, 'qa:withdraw')"><FontAwesomeIcon :icon="isPending('qa:withdraw') ? 'fal fa-spinner' : 'fal fa-times'" :spin="isPending('qa:withdraw')" fixed-width /></button>
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
                    <FontAwesomeIcon :icon="isPending('kind') ? 'fal fa-spinner' : kindIcons[ticket.kind] ?? 'fal fa-question-circle'" :spin="isPending('kind')" fixed-width />
                    {{ optionLabel(options.kinds, ticket.kind) ?? trans("No kind") }}
                </span>
                <span
                    v-tooltip="can_change_kind_module ? trans('Module · double click to change') : trans('Module')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 select-none transition duration-200"
                    :class="[can_change_kind_module && 'cursor-pointer hover:bg-gray-200 active:!bg-gray-300', isModulePickerOpen && '!bg-gray-300']"
                    @dblclick="can_change_kind_module && modulePopover.toggle($event)"
                    :tabindex="can_change_kind_module ? 0 : undefined"
                    @keydown.enter.prevent="can_change_kind_module && modulePopover.toggle($event)">
                    <FontAwesomeIcon :icon="isPending('module') ? 'fal fa-spinner' : 'fal fa-cube'" :spin="isPending('module')" fixed-width />
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
                        <button v-tooltip="trans('Remove')" type="button" class="text-indigo-400 hover:text-indigo-700 active:!text-indigo-700 transition duration-200" @click="update('tags', ticket.tags.filter((t: string) => t !== tag), `tags:remove:${tag}`)">
                            <FontAwesomeIcon :icon="isPending(`tags:remove:${tag}`) ? 'fal fa-spinner' : 'fal fa-times'" :spin="isPending(`tags:remove:${tag}`)" fixed-width />
                        </button>
                    </span>
                    <button v-tooltip="trans('Add tag')" type="button" class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-500 hover:border-indigo-400 active:!border-indigo-400 hover:text-indigo-600 active:!text-indigo-600 transition duration-200" :class="isTagPickerOpen && '!border-indigo-400 !text-indigo-600 !bg-indigo-50'" @click="tagPopover.toggle($event)">
                        <FontAwesomeIcon :icon="isPending('tags:add') ? 'fal fa-spinner' : 'fal fa-plus'" :spin="isPending('tags:add')" fixed-width />
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
                                @click="update('tags', [...ticket.tags, tag], 'tags:add'); newTag = ''">
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
            <Button v-if="ticket.type === 'customer' && !ticket.escalations.length" type="secondary" icon="fal fa-level-up" :label="trans('Escalate to help desk')" full :loading="isPending('escalate')" @click="escalate" />
            <label v-if="can_flag_confidential && !hideConfidential" class="flex items-center gap-x-2 text-gray-600 cursor-pointer">
                <input type="checkbox" :checked="ticket.is_confidential" :disabled="isBusy" class="rounded border-gray-300 cursor-pointer disabled:cursor-wait" @change="update('is_confidential', ($event.target as HTMLInputElement).checked, 'confidential')" />
                {{ trans("Confidential") }} <span class="text-xs text-gray-400">({{ trans("only reporter and lead engineers") }})</span>
                <FontAwesomeIcon v-if="isPending('confidential')" :icon="'fal fa-spinner'" spin fixed-width class="text-gray-400" />
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
    <TicketAskReporterDialog v-model:visible="isAskReporterOpen" :update-route="routes.update" :default-waiting-hours="ticket.default_waiting_hours" @updated="emit('updated')" />
    <TicketStatusNoteDialog
        v-model:visible="isStatusNoteOpen"
        :status="statusNoteAction"
        :update-route="routes.update"
        :can-wait-for-deployment="can_manage && ticket.status !== 'pending_deploy'"
        @updated="emit('updated')" />
    </div>
</template>
