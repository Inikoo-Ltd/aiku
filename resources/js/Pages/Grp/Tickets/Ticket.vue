<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import TicketRating from "@/Components/Tickets/TicketRating.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import { Popover, Listbox, Dialog } from "primevue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus, faPlusCircle, faExchange, faHourglassHalf, faVial, faShieldCheck, faShield } from "@fal"

library.add(faVial, faShieldCheck, faShield, faHourglassHalf, faPlusCircle, faExchange, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus,faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle)

const kindIcons: Record<string, string> = {
    bug: "fal fa-bug",
    feature: "fal fa-lightbulb",
    escalation: "fal fa-level-up",
}

const kindPopover = ref()
const modulePopover = ref()
const moreMenu = ref()
const assigneePopover = ref()

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
    open: [start, done, cancel],
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

const props = defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    timeline: { at: string; icon: string; text: string; by: string | null }[]
    can_rate: boolean
    can_manage: boolean
    can_assign: boolean
    can_flag_confidential: boolean
    can_qa: boolean
    is_reporter: boolean
    options: {
        statuses: { label: string; value: string }[]
        priorities: { label: string; value: string }[]
        assignees: { label: string; value: number }[]
        tags: string[]
        kinds: { label: string; value: string }[]
        modules: { label: string; value: string }[]
    }
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        comment: { name: string; parameters: Record<string, unknown> }
        rate: { name: string; parameters: Record<string, unknown> }
        escalate: { name: string; parameters: Record<string, unknown> }
    }
}>()

const staffMessaging = useStaffMessaging()

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

const openStaffChat = async () => {
    if (!staffMessaging.fetched) await staffMessaging.fetchConversations()
    staffMessaging.openConversation(props.ticket.staff_conversation_ulid)
}


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
            onSuccess: () => (isAskReporterOpen.value = false),
        }
    )
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
            onSuccess: () => (isQaVerdictOpen.value = false),
        }
    )
}

const canAskQa = computed(() => props.can_manage && ["in_progress", "waiting", "resolved"].includes(props.ticket.status) && props.ticket.qa_status !== "requested")

const update = (field: string, value: unknown) => {
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="p-4 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-start justify-between gap-2">
                <h2 class="text-lg font-semibold">{{ ticket.subject }}</h2>
                <ModalConfirmationDelete
                    v-if="can_flag_confidential"
                    :title="trans('Delete :reference?', { reference: ticket.reference })"
                    :description="trans('The ticket and its comments will be removed for good.')"
                    :noLabel="trans('Yes, delete')"
                    :routeDelete="routes.delete">
                    <template #default="{ changeModel }">
                        <button v-tooltip="trans('More')" type="button" class="rounded-md px-2 py-1 text-gray-500 hover:bg-gray-100" @click="moreMenu.toggle($event)">
                            <FontAwesomeIcon icon="fal fa-ellipsis-v" fixed-width />
                        </button>
                        <Popover ref="moreMenu">
                            <button type="button" class="flex items-center gap-2 px-2 py-1 text-sm text-red-600 hover:bg-red-50 rounded" @click="moreMenu.hide(); changeModel()">
                                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width />
                                {{ trans("Delete ticket") }}
                            </button>
                        </Popover>
                    </template>
                </ModalConfirmationDelete>
            </div>
            <TicketRating :rating="ticket.rating" :rating-comment="ticket.rating_comment" :can-rate="can_rate" :rate-route="routes.rate" />
            <TicketThread :ticket="ticket" :comments="comments" :comment-route="routes.comment" :allow-internal="can_manage" />
        </div>
        <div class="space-y-4 self-start">
        <aside class="bg-white rounded-lg border border-gray-300 p-4 space-y-4 text-sm">
            <div>
                <div class="flex items-center gap-2">
                    <img v-if="ticket.assignee_avatar?.original" :src="ticket.assignee_avatar.original" class="h-7 w-7 rounded-full object-cover" alt="" />
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-200 text-gray-500">
                        <FontAwesomeIcon icon="fal fa-user" fixed-width />
                    </span>
                    <span :class="ticket.assignee ? 'text-gray-800' : 'text-gray-400'">{{ ticket.assignee || trans("Unassigned") }}</span>
                    <button v-if="can_assign" v-tooltip="trans('Change assignee')" type="button" class="rounded p-1 text-gray-400 hover:text-gray-700" @click="assigneePopover.toggle($event)">
                        <FontAwesomeIcon icon="fal fa-pencil" fixed-width />
                    </button>
                </div>
                <Popover v-if="can_assign" ref="assigneePopover">
                    <Listbox :model-value="ticket.assignee_id" :options="options.assignees" option-label="label" option-value="value" filter scroll-height="16rem" class="border-0" @update:model-value="update('assignee_id', $event); assigneePopover.hide()" />
                    <button v-if="can_flag_confidential && ticket.assignee_id" type="button" class="mt-2 w-full rounded px-2 py-1 text-sm text-gray-500 hover:bg-gray-100" @click="update('assignee_id', null); assigneePopover.hide()">
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
                        class="rounded-md p-1.5 hover:bg-gray-100"
                        :class="action.class"
                        @click="action.status === 'waiting' ? openAskReporter() : update('status', action.status)">
                        <FontAwesomeIcon :icon="action.icon" fixed-width />
                    </button>
                </div>
            </div>
            <div v-if="ticket.qa_status || canAskQa" class="flex items-center gap-2">
                <span v-if="ticket.qa_status" v-tooltip="ticket.qa_user ? `${ticket.qa_status_label} · ${ticket.qa_user}` : ticket.qa_status_label" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium" :class="statusBadgeClasses[ticket.qa_status_icon.color]">
                    <FontAwesomeIcon :icon="ticket.qa_status_icon.icon" fixed-width />
                    {{ ticket.qa_status_label }}
                </span>
                <template v-if="can_qa && ticket.qa_status === 'requested'">
                    <button v-tooltip="trans('QA passed')" type="button" class="rounded-md p-1.5 text-green-600 hover:bg-gray-100" @click="openQaVerdict('passed')"><FontAwesomeIcon icon="fal fa-shield-check" fixed-width /></button>
                    <button v-tooltip="trans('QA failed')" type="button" class="rounded-md p-1.5 text-red-500 hover:bg-gray-100" @click="openQaVerdict('failed')"><FontAwesomeIcon icon="fal fa-shield" fixed-width /></button>
                </template>
                <button v-if="canAskQa" v-tooltip="ticket.qa_status ? trans('Ask QA to check again') : trans('Ask QA to check')" type="button" class="rounded-md p-1.5 text-amber-600 hover:bg-gray-100" @click="update('qa_status', 'requested')"><FontAwesomeIcon icon="fal fa-vial" fixed-width /></button>
                <button v-if="can_manage && ticket.qa_status === 'requested'" v-tooltip="trans('Withdraw QA request')" type="button" class="rounded-md p-1.5 text-gray-400 hover:bg-gray-100" @click="update('qa_status', null)"><FontAwesomeIcon icon="fal fa-times" fixed-width /></button>
            </div>
            <template v-if="can_manage">
            <div v-if="ticket.type === 'help'" class="flex flex-wrap gap-2">
                <span
                    v-tooltip="trans('Kind · double click to change')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 cursor-pointer select-none"
                    @dblclick="kindPopover.toggle($event)">
                    <FontAwesomeIcon :icon="kindIcons[ticket.kind] ?? 'fal fa-question-circle'" fixed-width />
                    {{ optionLabel(options.kinds, ticket.kind) ?? trans("No kind") }}
                </span>
                <span
                    v-tooltip="trans('Module · double click to change')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-2 py-1 cursor-pointer select-none"
                    @dblclick="modulePopover.toggle($event)">
                    <FontAwesomeIcon icon="fal fa-cube" fixed-width />
                    {{ optionLabel(options.modules, ticket.module) ?? trans("No module") }}
                </span>
                <Popover ref="kindPopover">
                    <Listbox :model-value="ticket.kind" :options="options.kinds" option-label="label" option-value="value" class="border-0" @update:model-value="update('kind', $event); kindPopover.hide()">
                        <template #option="{ option }">
                            <FontAwesomeIcon :icon="kindIcons[option.value]" fixed-width class="mr-2" />{{ option.label }}
                        </template>
                    </Listbox>
                </Popover>
                <Popover ref="modulePopover">
                    <Listbox :model-value="ticket.module" :options="options.modules" option-label="label" option-value="value" filter scroll-height="16rem" class="border-0" @update:model-value="update('module', $event); modulePopover.hide()" />
                </Popover>
            </div>
            <div>
                <p class="text-xs text-gray-500 mb-1">{{ trans("Tags") }}</p>
                <div class="flex flex-wrap items-center gap-1.5">
                    <span v-for="tag in ticket.tags" :key="tag" class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs text-indigo-700">
                        {{ tag }}
                        <button v-tooltip="trans('Remove')" type="button" class="text-indigo-400 hover:text-indigo-700" @click="update('tags', ticket.tags.filter((t: string) => t !== tag))">
                            <FontAwesomeIcon icon="fal fa-times" fixed-width />
                        </button>
                    </span>
                    <button v-tooltip="trans('Add tag')" type="button" class="flex h-6 w-6 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-500 hover:border-indigo-400 hover:text-indigo-600" @click="tagPopover.toggle($event)">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width />
                    </button>
                </div>
                <Popover ref="tagPopover">
                    <div class="w-60 space-y-2">
                        <input v-model="newTag" type="text" class="w-full rounded border-gray-300 text-sm" :placeholder="trans('Search or create a tag')" @keydown.enter.prevent="addTypedTag" />
                        <div class="max-h-60 overflow-y-auto">
                            <button
                                v-for="tag in availableTags"
                                :key="tag"
                                type="button"
                                class="block w-full rounded px-2 py-1 text-left text-sm hover:bg-gray-100"
                                @click="update('tags', [...ticket.tags, tag]); newTag = ''">
                                {{ tag }}
                            </button>
                            <button v-if="newTag.trim() && !tagOptions.includes(newTag.trim().toLowerCase())" type="button" class="block w-full rounded px-2 py-1 text-left text-sm text-indigo-600 hover:bg-indigo-50" @click="addTypedTag">
                                <FontAwesomeIcon icon="fal fa-plus" fixed-width /> {{ trans("Create") }} "{{ newTag.trim() }}"
                            </button>
                            <p v-if="!availableTags.length && !newTag.trim()" class="px-2 py-1 text-sm text-gray-400">{{ trans("No more tags") }}</p>
                        </div>
                    </div>
                </Popover>
            </div>
            <Button v-if="ticket.type === 'customer' && !ticket.escalations.length" type="secondary" icon="fal fa-level-up" :label="trans('Escalate to help desk')" full @click="escalate" />
            <Button v-if="ticket.staff_conversation_ulid" type="tertiary" icon="fal fa-comments" :label="trans('Staff chat')" full @click="openStaffChat" />
            <label v-if="can_flag_confidential" class="flex items-center gap-x-2 text-gray-600 cursor-pointer">
                <input type="checkbox" :checked="ticket.is_confidential" class="rounded border-gray-300" @change="update('is_confidential', ($event.target as HTMLInputElement).checked)" />
                {{ trans("Confidential") }} <span class="text-xs text-gray-400">({{ trans("only reporter, assignee and admins") }})</span>
            </label>
            </template>
            <div v-if="ticket.commits?.length">
                <p class="text-xs text-gray-500 mb-1">{{ trans("Commits") }}</p>
                <ul class="space-y-1 text-xs">
                    <li v-for="commit in ticket.commits" :key="commit.hash">
                        <a v-if="commit.url" :href="commit.url" target="_blank" class="font-mono text-blue-600 hover:underline">{{ commit.hash.slice(0, 8) }}</a>
                        <span v-else class="font-mono">{{ commit.hash.slice(0, 8) }}</span>
                        <span class="text-gray-600"> {{ commit.subject }}</span>
                        <span v-if="commit.version || commit.deployed_at" class="text-gray-400"> · {{ commit.version || trans("deployed") }} {{ commit.deployed_at ? new Date(commit.deployed_at).toLocaleDateString() : "" }}</span>
                    </li>
                </ul>
            </div>
            <div v-if="ticket.attachments?.length">
                <p class="text-xs text-gray-500 mb-1">{{ trans("Attachments") }}</p>
                <ul class="space-y-1">
                    <li v-for="file in ticket.attachments" :key="file.url"><a :href="file.url" target="_blank" class="text-blue-600 hover:underline break-all"><FontAwesomeIcon icon="fal fa-paperclip" class="mr-1" />{{ file.name }}</a></li>
                </ul>
            </div>
            <dl class="space-y-1 text-gray-600">
                <div class="flex justify-between"><dt>{{ trans("Type") }}</dt><dd>{{ ticket.type }}</dd></div>
                <div v-if="ticket.parent" class="flex justify-between"><dt>{{ trans("Escalated from") }}</dt><dd><Link :href="route('grp.tickets.show', ticket.parent)" class="text-blue-600 hover:underline">{{ ticket.parent }}</Link></dd></div>
                <div v-if="ticket.escalations.length" class="flex justify-between"><dt>{{ trans("Escalated to") }}</dt><dd class="space-x-1"><Link v-for="ref in ticket.escalations" :key="ref" :href="route('grp.tickets.show', ref)" class="text-blue-600 hover:underline">{{ ref }}</Link></dd></div>
                <div class="flex justify-between"><dt>{{ trans("Reporter") }}</dt><dd>{{ ticket.reporter || "-" }}</dd></div>
                <div v-if="ticket.customer" class="flex justify-between"><dt>{{ trans("Customer") }}</dt><dd>{{ ticket.customer }}</dd></div>
                <div v-if="ticket.shop" class="flex justify-between"><dt>{{ trans("Shop") }}</dt><dd>{{ ticket.shop }}</dd></div>
            </dl>
        </aside>
        <div class="bg-white rounded-lg border border-gray-300 p-4 text-sm">
            <p class="text-xs text-gray-500 mb-3">{{ trans("History") }}</p>
            <ol class="relative ml-2 border-l border-gray-200">
                <li v-for="(event, index) in timeline" :key="index" class="mb-4 ml-5 last:mb-0">
                    <span class="absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-200">
                        <FontAwesomeIcon :icon="event.icon" fixed-width class="text-[10px]" />
                    </span>
                    <p class="text-gray-800">{{ event.text }}</p>
                    <p class="text-xs text-gray-400">
                        {{ useFormatTime(event.at, { formatTime: "hm" }) }}<template v-if="event.by"> · {{ event.by }}</template>
                    </p>
                </li>
            </ol>
        </div>
        </div>
    </div>
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
                        class="rounded-full border px-3 py-1"
                        :class="waitingHours === preset.hours ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
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
</template>
