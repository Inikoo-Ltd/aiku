<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { Popover, Listbox, Dialog, Textarea, Button } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import StaffTasksSummary from "@/Components/Tasks/StaffTasksSummary.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFlag, faChevronDown, faSpinner, faUsers, faUser, faBuilding } from "@fal"
library.add(faFlag, faChevronDown, faSpinner, faUsers, faUser, faBuilding)

type Option = { label: string; value: string; icon: any }

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: any
    listSummary: { todo: number; in_progress: number; done: number; cancelled: number }
    options: {
        statuses: Option[]
        priorities: Option[]
    }
    showRoute: { name: string; parameters: string[] }
}>()

const myUserId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const canEditRow = (item: { assignee_id: number | null; collaborators: { id: number }[] }) =>
    myUserId.value !== null && (item.assignee_id === myUserId.value || item.collaborators.some((person) => person.id === myUserId.value))

const pendingEdit = ref<string | null>(null)
const isSaving = (item: { id: number }, field: string) => pendingEdit.value === `${item.id}:${field}`
const isRowSaving = (item: { id: number }) => pendingEdit.value?.startsWith(`${item.id}:`) ?? false

const update = async (item: any, field: string, payload: Record<string, unknown>) => {
    if (pendingEdit.value) return
    pendingEdit.value = `${item.id}:${field}`
    try {
        await axios.patch(route("grp.tasks.update", item.reference), payload)
        router.reload({ preserveScroll: true, onFinish: () => (pendingEdit.value = null) })
    } catch (error: any) {
        pendingEdit.value = null
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    }
}

const activeItem = ref<any | null>(null)
const activeField = ref<string | null>(null)
const statusPopover = ref()
const priorityPopover = ref()
const popovers: Record<string, typeof statusPopover> = { status: statusPopover, priority: priorityPopover }

const isEditing = (field: string, item: { id: number }) => activeField.value === field && activeItem.value?.id === item.id

const openEditor = (field: string, item: any, event: Event) => {
    if (isRowSaving(item)) return
    activeItem.value = item
    popovers[field].value?.toggle(event)
}

const onEditorShown = (field: string) => (activeField.value = field)
const onEditorHidden = (field: string) => {
    if (activeField.value === field) activeField.value = null
}

const statusChoices = computed(() => props.options.statuses.filter((status) => status.value !== activeItem.value?.status))

const cancelNoteItem = ref<any | null>(null)
const cancelNote = ref("")

const chooseStatus = (status: string) => {
    const item = activeItem.value
    statusPopover.value?.hide()
    if (!item || status === item.status) return
    if (status === "cancelled") {
        cancelNote.value = ""
        cancelNoteItem.value = item
        return
    }
    update(item, "status", { status })
}

const confirmCancel = () => {
    const item = cancelNoteItem.value
    if (!item || !cancelNote.value.trim()) return
    cancelNoteItem.value = null
    update(item, "status", { status: "cancelled", note: cancelNote.value.trim() })
}

const choosePriority = (priority: string) => {
    const item = activeItem.value
    priorityPopover.value?.hide()
    if (item && priority && priority !== item.priority) update(item, "priority", { priority })
}

const quickLook = ref<{ id: number; reference: string } | null>(null)

useLiveStaffTasks(() => {
    if (!pendingEdit.value && !quickLook.value) router.reload({ preserveScroll: true, preserveState: true })
})

const closeQuickLook = () => router.reload({ preserveScroll: true, preserveState: true })

const taskUrl =(item: { reference: string }) => route(props.showRoute.name, [...props.showRoute.parameters, item.reference])

const onTableClick = (event: MouseEvent) => {
    const target = event.target as HTMLElement | null
    if (!target || target.closest("a, button, input, select, textarea, label, [role='listbox'], [role='option']")) return

    const taskId = Number(target.closest("tr")?.querySelector<HTMLElement>("[data-task-id]")?.dataset.taskId)
    const item = (props.data?.data ?? []).find((task: { id: number }) => task.id === taskId)
    if (item) quickLook.value = item
}

const editableCellClass = "inline-flex items-center gap-1 rounded p-2 transition duration-200 hover:bg-gray-100 active:!bg-gray-200 disabled:cursor-wait disabled:opacity-60"
const readOnlyCellClass = "inline-flex items-center gap-1 p-2"
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <StaffTasksSummary :summary="listSummary" />
    <div class="[&_tbody_tr]:cursor-pointer" @click="onTableClick">
        <Table :resource="data" class="mt-4 max-md:[&_td.max-w-0]:max-w-none max-md:[&_th.max-w-0]:max-w-none">
            <template #cell(reference)="{ item }">
                <Link :href="taskUrl(item)" class="primaryLink" :data-task-id="item.id">{{ item.reference }}</Link>
            </template>
            <template #cell(subject)="{ item }">
                <span class="block w-56 truncate md:w-auto" :title="item.subject">{{ item.subject }}</span>
            </template>
            <template #cell(status)="{ item }">
                <button
                    v-if="canEditRow(item)"
                    type="button"
                    :class="[editableCellClass, isEditing('status', item) && '!bg-gray-200']"
                    :title="ctrans('Change status')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('status', item, $event)">
                    <Icon :data="item.status_icon" /> {{ item.status_label }}
                    <FontAwesomeIcon :icon="isSaving(item, 'status') ? 'fal fa-spinner' : 'fal fa-chevron-down'" :spin="isSaving(item, 'status')" class="text-[10px] text-gray-400" fixed-width />
                </button>
                <span v-else :class="readOnlyCellClass"><Icon :data="item.status_icon" /> {{ item.status_label }}</span>
            </template>
            <template #cell(priority)="{ item }">
                <button
                    v-if="canEditRow(item)"
                    type="button"
                    :class="[editableCellClass, 'min-w-9 justify-center', isEditing('priority', item) && '!bg-gray-200']"
                    :title="ctrans('Change priority')"
                    :disabled="isRowSaving(item)"
                    @click="openEditor('priority', item, $event)">
                    <FontAwesomeIcon v-if="isSaving(item, 'priority')" icon="fal fa-spinner" spin class="text-gray-400" fixed-width />
                    <Icon v-else :data="item.priority_icon" />
                </button>
                <span v-else :class="[readOnlyCellClass, 'min-w-9 justify-center']" :title="item.priority_label"><Icon :data="item.priority_icon" /></span>
            </template>
            <template #cell(requester)="{ item }">
                <div class="mx-auto flex w-20 flex-col items-center gap-0.5 p-2 text-center" :title="item.requester_name">
                    <TicketUserAvatar :name="item.requester_name" :avatar="item.requester_avatar" />
                    <span class="w-full truncate text-[10px] leading-tight text-gray-600">{{ item.requester_short || "-" }}</span>
                </div>
            </template>
            <template #cell(assignee)="{ item }">
                <div class="mx-auto flex w-20 flex-col items-center gap-0.5 p-2 text-center" :title="item.assignee_name || item.department_label || ctrans('Unassigned')">
                    <TicketUserAvatar v-if="item.assignee_name" :name="item.assignee_name" :avatar="item.assignee_avatar" />
                    <span v-else-if="item.department_label" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-gray-500"><FontAwesomeIcon icon="fal fa-building" fixed-width /></span>
                    <span v-else class="flex h-7 w-7 items-center justify-center rounded-full border border-dashed border-gray-300 text-gray-400"><FontAwesomeIcon icon="fal fa-user" fixed-width /></span>
                    <span class="w-full truncate text-[10px] leading-tight" :class="item.assignee_name || item.department_label ? 'text-gray-600' : 'text-gray-400'">{{ item.assignee_short || item.department_label || ctrans("Unassigned") }}</span>
                    <span v-if="item.collaborators.length" class="text-[10px] leading-tight text-[--app-accent-strong]" :title="item.collaborators.map((person) => person.name).join(', ')">
                        <FontAwesomeIcon icon="fal fa-users" class="mr-0.5" fixed-width />{{ item.collaborators.length }}
                    </span>
                </div>
            </template>
            <template #cell(due_at)="{ item }">
                <span :class="item.is_overdue && 'font-medium text-red-600'">{{ item.due_at ? useFormatTime(item.due_at, { formatTime: "mdy" }) : "-" }}</span>
            </template>
            <template #cell(created_at)="{ item }">
                <span :title="useFormatTime(item.created_at, { formatTime: 'hm' })">{{ useFormatTime(item.created_at, { formatTime: "d MMM HH:mm" }) }}</span>
            </template>
            <template #cell(closed_at)="{ item }">
                <span v-if="item.closed_at" :title="useFormatTime(item.closed_at, { formatTime: 'hm' })">{{ useFormatTime(item.closed_at, { formatTime: "d MMM HH:mm" }) }}</span>
                <span v-else class="text-gray-300">-</span>
            </template>
        </Table>
    </div>

    <Popover ref="statusPopover" @show="onEditorShown('status')" @hide="onEditorHidden('status')">
        <div class="flex min-w-48 flex-col text-sm">
            <button
                v-for="status in statusChoices"
                :key="status.value"
                type="button"
                class="flex items-center gap-2 rounded p-2 text-left transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                @click="chooseStatus(status.value)">
                <Icon :data="status.icon" />
                {{ status.label }}
            </button>
        </div>
    </Popover>
    <Popover ref="priorityPopover" @show="onEditorShown('priority')" @hide="onEditorHidden('priority')">
        <Listbox :model-value="activeItem?.priority" :options="options.priorities" option-label="label" option-value="value" class="border-0" @update:model-value="choosePriority">
            <template #option="{ option }">
                <Icon :data="option.icon" class="mr-2" />{{ option.label }}
            </template>
        </Listbox>
    </Popover>

    <Dialog
        :visible="!!cancelNoteItem"
        modal
        dismissableMask
        :header="ctrans(`Why can't :reference be done?`, { reference: cancelNoteItem?.reference ?? '' })"
        :style="{ width: '28rem' }"
        :breakpoints="{ '640px': '95vw' }"
        @update:visible="(visible) => !visible && (cancelNoteItem = null)">
        <form class="space-y-3" @submit.prevent="confirmCancel">
            <Textarea v-model="cancelNote" rows="3" maxlength="1000" autofocus autoResize fluid />
            <div class="flex justify-end gap-x-2">
                <Button type="button" text severity="secondary" :label="ctrans('Back')" @click="cancelNoteItem = null" />
                <Button type="submit" severity="danger" :label="ctrans('Confirm')" :disabled="!cancelNote.trim()" />
            </div>
        </form>
    </Dialog>
    <StaffTaskQuickLook v-model:task="quickLook" @closed="closeQuickLook" />
</template>
