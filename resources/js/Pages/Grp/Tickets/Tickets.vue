<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import { Popover, Listbox } from "primevue"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import { useTicketStatusActions, type TicketStatusAction } from "@/Composables/useTicketStatusActions"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import TicketAskReporterDialog from "@/Components/Tickets/TicketAskReporterDialog.vue"
import TicketStatusNoteDialog from "@/Components/Tickets/TicketStatusNoteDialog.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlay, faQuestionCircle, faCheck, faBan, faChevronDown, faUser, faStop, faUndo, faSpinner, faUsers } from "@fal"

import { faArrowDown as faSolidArrowDown, faArrowUp as faSolidArrowUp, faMinus as faSolidMinus, faExclamationTriangle as faSolidExclamationTriangle } from "@fas"

library.add(faPlay, faQuestionCircle, faCheck, faBan, faChevronDown, faUser, faStop, faUndo, faSpinner, faUsers, faSolidArrowDown, faSolidArrowUp, faSolidMinus, faSolidExclamationTriangle)

type Option<Value> = { label: string; value: Value }

const props = defineProps<{
    pageHead: any
    title: string
    data: any
    createdIntervals: Record<string, string>
    createdInterval: string
    searchHelp: string[]
    updateRoute: string
    can_assign: boolean
    mineFilter: string | null
    options: {
        priorities: (Option<string> & { icon: any })[]
        kinds: Option<string>[]
        modules: Option<string>[]
        assignees: (Option<number> & { avatar?: Record<string, string> | null })[]
    }
}>()

useLiveTickets(["data"])

const { statusActions, assigneeStatusActions } = useTicketStatusActions()

const myUserId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const isAssignedToMe = (item: { assignee_id: number | null }) => myUserId.value !== null && item.assignee_id === myUserId.value

const canEditRow = (item: { assignee_id: number | null }) => props.can_assign || isAssignedToMe(item)

const statusActionsFor = (item: { status: string; assignee_id: number | null }): TicketStatusAction[] => {
    if (props.can_assign) return statusActions[item.status] ?? []
    return isAssignedToMe(item) ? assigneeStatusActions[item.status] ?? [] : []
}

const canEditKind = (item: any) => canEditRow(item) && item.type === "help" && item.kind !== "escalation"

const canEditModule = (item: any) => canEditRow(item) && item.type === "help"

const selectableKinds = computed(() => props.options.kinds.filter((kind) => kind.value !== "escalation"))

const updateRouteFor = (item: { id: number }) => ({ name: props.updateRoute, parameters: { ticket: item.id } })

const pendingEdit = ref<string | null>(null)

const isSaving = (item: { id: number }, field: string) => pendingEdit.value === `${item.id}:${field}`

const isRowSaving = (item: { id: number }) => pendingEdit.value?.startsWith(`${item.id}:`) ?? false

const update = (item: { id: number }, field: string, value: unknown) => {
    if (pendingEdit.value) return
    router.patch(route(props.updateRoute, { ticket: item.id }), { [field]: value }, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (pendingEdit.value = `${item.id}:${field}`),
        onFinish: () => (pendingEdit.value = null),
    })
}

const activeItem = ref<any | null>(null)
const activeField = ref<string | null>(null)
const statusPopover = ref()
const priorityPopover = ref()
const assigneePopover = ref()
const kindPopover = ref()
const modulePopover = ref()

const popovers: Record<string, typeof statusPopover> = {
    status: statusPopover,
    priority: priorityPopover,
    assignee_id: assigneePopover,
    kind: kindPopover,
    module: modulePopover,
}

const isEditing = (field: string, item: { id: number }) => activeField.value === field && activeItem.value?.id === item.id

const openEditor = (field: string, item: any, event: Event) => {
    if (isRowSaving(item)) return
    activeItem.value = item
    popovers[field].value?.toggle(event)
}

const onEditorShown = (field: string) => {
    activeField.value = field
}

const onEditorHidden = (field: string) => {
    if (activeField.value === field) activeField.value = null
}

const chooseValue = (field: string, value: unknown) => {
    const item = activeItem.value
    popovers[field].value?.hide()
    if (item && value !== undefined && value !== item[field]) update(item, field, value)
}

const editableCellClass = "inline-flex items-center gap-1 rounded p-2 transition duration-200 hover:bg-gray-100 active:!bg-gray-200 disabled:cursor-wait disabled:opacity-60"
const readOnlyCellClass = "inline-flex items-center gap-1 p-2"

const askReporterItem = ref<any | null>(null)
const isAskReporterOpen = ref(false)
const statusNoteItem = ref<any | null>(null)
const statusNoteAction = ref<"resolved" | "cancelled">("resolved")
const isStatusNoteOpen = ref(false)

const chooseStatus = (status: string) => {
    const item = activeItem.value
    statusPopover.value?.hide()
    if (!item) return

    if (status === "waiting") {
        askReporterItem.value = item
        isAskReporterOpen.value = true
    } else if (status === "resolved" || status === "cancelled") {
        statusNoteItem.value = item
        statusNoteAction.value = status
        isStatusNoteOpen.value = true
    } else {
        update(item, "status", status)
    }
}

const quickLook = ref<any | null>(null)

const onTableClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null
    if (!target || target.closest("a, button, input, select, textarea, label, [role='listbox'], [role='option']")) return

    const ticketId = Number(target.closest("tr")?.querySelector<HTMLElement>("[data-ticket-id]")?.dataset.ticketId)
    if (!ticketId) return

    quickLook.value = (props.data?.data ?? []).find((item: { id: number }) => item.id === ticketId) ?? null
}

const closeQuickLook = () => {
    router.reload({ only: ["data"] })
}

const savedMineFilter = ref(props.mineFilter)

watch(
    () => usePage().url,
    (url) => {
        const mineFilter = new URLSearchParams(url.split("?")[1] ?? "").get("elements[mine]")
        if (mineFilter === null || mineFilter === savedMineFilter.value) return
        savedMineFilter.value = mineFilter
        axios.patch(route("grp.models.profile.update"), { tickets_list_mine: mineFilter })
    }
)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <TicketsCreatedInterval :options="createdIntervals" :selected="createdInterval" class="mx-4 mt-2" />
    <div class="mx-4 mt-1 flex flex-wrap items-center gap-1 text-xs text-gray-400">
        <span class="mr-1">{{ trans("Search tips") }}:</span>
        <code v-for="tip in searchHelp" :key="tip" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-500">{{ tip }}</code>
    </div>
    <div class="[&_tbody_tr]:cursor-pointer" @click="onTableClick">
        <Table :resource="data" class="mt-2">
            <template #cell(reference)="{ item }">
                <Link :href="route('grp.tickets.show', item.reference)" class="primaryLink" :data-ticket-id="item.id">{{ item.reference }}</Link>
            </template>
            <template #cell(status)="{ item }">
                <button
                    v-if="statusActionsFor(item).length"
                    type="button"
                    :class="[editableCellClass, isEditing('status', item) && '!bg-gray-200']"
                    :title="trans('Change status')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('status', item, $event)">
                    <Icon :data="item.status_icon" /> {{ item.status_label }}
                    <FontAwesomeIcon :icon="isSaving(item, 'status') ? 'fal fa-spinner' : 'fal fa-chevron-down'" :spin="isSaving(item, 'status')" class="text-[10px] text-gray-400" fixed-width />
                </button>
                <span v-else :class="readOnlyCellClass"><Icon :data="item.status_icon" /> {{ item.status_label }}</span>
            </template>
            <template #cell(subject)="{ item }">
                <span class="block truncate" :title="item.subject">{{ item.subject }}</span>
                <span v-if="item.search_snippet" class="block truncate text-xs text-gray-500 [&_mark]:rounded [&_mark]:bg-yellow-200 [&_mark]:px-0.5" v-html="item.search_snippet" />
            </template>
            <template #cell(priority)="{ item }">
                <button
                    v-if="canEditRow(item)"
                    type="button"
                    :class="[editableCellClass, 'min-w-9 justify-center', isEditing('priority', item) && '!bg-gray-200']"
                    :title="trans('Change priority')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('priority', item, $event)">
                    <FontAwesomeIcon v-if="isSaving(item, 'priority')" icon="fal fa-spinner" spin class="text-gray-400" fixed-width />
                    <Icon v-else :data="item.priority_icon" />
                </button>
                <span v-else :class="[readOnlyCellClass, 'min-w-9 justify-center']"><Icon :data="item.priority_icon" /></span>
            </template>
            <template #cell(kind)="{ item }">
                <button
                    v-if="canEditKind(item)"
                    type="button"
                    :class="[editableCellClass, 'text-gray-700', isEditing('kind', item) && '!bg-gray-200']"
                    :title="trans('Change kind')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('kind', item, $event)">
                    {{ item.kind_label || trans("No kind") }}
                    <FontAwesomeIcon :icon="isSaving(item, 'kind') ? 'fal fa-spinner' : 'fal fa-chevron-down'" :spin="isSaving(item, 'kind')" class="text-[10px] text-gray-400" fixed-width />
                </button>
                <span v-else :class="[readOnlyCellClass, 'text-gray-600']">{{ item.kind_label || "-" }}</span>
            </template>
            <template #cell(module)="{ item }">
                <button
                    v-if="canEditModule(item)"
                    type="button"
                    :class="[editableCellClass, 'text-gray-700', isEditing('module', item) && '!bg-gray-200']"
                    :title="trans('Change module')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('module', item, $event)">
                    {{ item.module_label || trans("No module") }}
                    <FontAwesomeIcon :icon="isSaving(item, 'module') ? 'fal fa-spinner' : 'fal fa-chevron-down'" :spin="isSaving(item, 'module')" class="text-[10px] text-gray-400" fixed-width />
                </button>
                <span v-else :class="[readOnlyCellClass, 'text-gray-600']">{{ item.module_label || "-" }}</span>
            </template>
            <template #cell(reporter)="{ item }">
                <div class="mx-auto flex w-20 flex-col items-center gap-0.5 p-2 text-center" :title="item.customer ? `${item.reporter} · ${item.customer}` : item.reporter">
                    <TicketUserAvatar :name="item.reporter" :avatar="item.reporter_avatar" />
                    <span class="w-full truncate text-[10px] leading-tight text-gray-600">{{ item.reporter_short || "-" }}</span>
                </div>
            </template>
            <template #cell(assignee)="{ item }">
                <component
                    :is="canEditRow(item) ? 'button' : 'div'"
                    :type="canEditRow(item) ? 'button' : undefined"
                    class="mx-auto flex w-20 flex-col items-center gap-0.5 rounded p-2 text-center"
                    :class="canEditRow(item) && ['relative transition duration-200 hover:bg-gray-100 active:!bg-gray-200 disabled:cursor-wait disabled:opacity-60', isEditing('assignee_id', item) && '!bg-gray-200']"
                    :disabled="canEditRow(item) ? isRowSaving(item) : undefined"
                    :title="canEditRow(item) ? trans('Change assignee') : item.assignee"
                    @click="canEditRow(item) && openEditor('assignee_id', item, $event)">
                    <TicketUserAvatar v-if="item.assignee" :name="item.assignee" :avatar="item.assignee_avatar" />
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-400"><FontAwesomeIcon icon="fal fa-user" fixed-width /></span>
                    <span class="w-full truncate text-[10px] leading-tight" :class="item.assignee ? 'text-gray-600' : 'text-gray-400'">{{ item.assignee_short || trans("Unassigned") }}</span>
                    <span v-if="item.collaborators?.length" class="text-[10px] leading-tight text-indigo-600" :title="item.collaborators.map((collaborator) => collaborator.name).join(', ')">
                        <FontAwesomeIcon icon="fal fa-users" class="mr-0.5" fixed-width />{{ item.collaborators.length }}
                    </span>
                    <FontAwesomeIcon v-if="isSaving(item, 'assignee_id')" icon="fal fa-spinner" spin class="absolute right-1 top-1 text-[10px] text-gray-400" fixed-width />
                </component>
            </template>
            <template #cell(created_at)="{ item }">
                <span :title="useFormatTime(item.created_at, { formatTime: 'hm' })">{{ useFormatTime(item.created_at, { formatTime: "d MMM HH:mm" }) }}</span>
            </template>
            <template #cell(updated_at)="{ item }">
                <span :title="useFormatTime(item.updated_at, { formatTime: 'hm' })">{{ useFormatTime(item.updated_at, { formatTime: "d MMM HH:mm" }) }}</span>
            </template>
        </Table>
    </div>

    <Popover ref="statusPopover" @show="onEditorShown('status')" @hide="onEditorHidden('status')">
        <div class="flex min-w-48 flex-col text-sm">
            <button
                v-for="action in activeItem ? statusActionsFor(activeItem) : []"
                :key="action.status"
                type="button"
                class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                @click="chooseStatus(action.status)">
                <FontAwesomeIcon :icon="action.icon" :class="action.class" fixed-width />
                {{ action.label }}
            </button>
        </div>
    </Popover>
    <Popover ref="priorityPopover" @show="onEditorShown('priority')" @hide="onEditorHidden('priority')">
        <Listbox :model-value="activeItem?.priority" :options="options.priorities" option-label="label" option-value="value" class="border-0" @update:model-value="chooseValue('priority', $event)">
            <template #option="{ option }">
                <Icon :data="option.icon" class="mr-2" />{{ option.label }}
            </template>
        </Listbox>
    </Popover>
    <Popover ref="assigneePopover" @show="onEditorShown('assignee_id')" @hide="onEditorHidden('assignee_id')">
        <div class="flex max-h-72 min-w-52 flex-col overflow-y-auto text-sm">
            <button
                v-for="engineer in options.assignees"
                :key="engineer.value"
                type="button"
                class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                :class="engineer.value === activeItem?.assignee_id && 'bg-indigo-50 text-indigo-700'"
                @click="chooseValue('assignee_id', engineer.value)">
                <TicketUserAvatar :name="engineer.label" :avatar="engineer.avatar" size="sm" />
                <span :class="engineer.value === myUserId && 'font-medium'">{{ engineer.label }}</span>
                <span v-if="engineer.value === myUserId" class="text-xs text-gray-400">{{ trans("me") }}</span>
            </button>
            <button
                v-if="can_assign && activeItem?.assignee_id"
                type="button"
                class="mt-1 rounded border-t border-gray-100 p-2 text-left text-gray-500 transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                @click="chooseValue('assignee_id', null)">
                {{ trans("Unassign") }}
            </button>
        </div>
    </Popover>
    <Popover ref="kindPopover" @show="onEditorShown('kind')" @hide="onEditorHidden('kind')">
        <Listbox :model-value="activeItem?.kind" :options="selectableKinds" option-label="label" option-value="value" class="border-0" @update:model-value="chooseValue('kind', $event)" />
    </Popover>
    <Popover ref="modulePopover" @show="onEditorShown('module')" @hide="onEditorHidden('module')">
        <Listbox :model-value="activeItem?.module" :options="options.modules" option-label="label" option-value="value" filter scroll-height="16rem" class="border-0" @update:model-value="chooseValue('module', $event)" />
    </Popover>

    <TicketAskReporterDialog
        v-if="askReporterItem"
        v-model:visible="isAskReporterOpen"
        :update-route="updateRouteFor(askReporterItem)"
        :default-waiting-hours="askReporterItem.default_waiting_hours" />
    <TicketStatusNoteDialog
        v-if="statusNoteItem"
        v-model:visible="isStatusNoteOpen"
        :status="statusNoteAction"
        :update-route="updateRouteFor(statusNoteItem)"
        :can-wait-for-deployment="statusNoteItem.status !== 'pending_deploy'" />
    <TicketQuickLook v-model:ticket="quickLook" @closed="closeQuickLook" />
</template>
