<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onMounted, ref, watch } from "vue"
import { Head, Link, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList, faPaperclip, faClock, faCalendarEdit } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import StaffTaskImportDialog from "@/Components/Tasks/StaffTaskImportDialog.vue"
import StaffTaskPeoplePicker from "@/Components/Tasks/StaffTaskPeoplePicker.vue"
import StaffTaskStatusActions from "@/Components/Tasks/StaffTaskStatusActions.vue"
import StaffTaskEtaDialog from "@/Components/Tasks/StaffTaskEtaDialog.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import StaffTasksSidebar from "@/Components/Tasks/StaffTasksSidebar.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { Skeleton } from "primevue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import StaffTaskDueBadge from "@/Components/Tasks/StaffTaskDueBadge.vue"

library.add(faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList, faPaperclip, faClock, faCalendarEdit)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    selected_task: string | null
}>()

const store = useStaffMessaging()
const layout = inject("layout", layoutStructure)
const myId = computed(() => usePage().props?.auth?.user?.id)

const views = [
    { key: "mine", label: ctrans("Assigned to me") },
    { key: "department", label: ctrans("My department") },
    { key: "requested", label: ctrans("I asked for") },
]
const view = ref("mine")
const showClosed = ref(false)
const tasks = ref<any[]>([])
const loading = ref(false)
const dialogOpen = ref(false)
const importOpen = ref(false)

const counts = ref<Record<string, number>>({})

const load = async ({ quietly = false } = {}) => {
    if (!quietly) loading.value = true
    try {
        const { data } = await axios.get(route("grp.tasks.list"), { params: { view: view.value, closed: showClosed.value ? 1 : 0 } })
        tasks.value = data.data
        counts.value = data.counts ?? {}
    } finally {
        loading.value = false
    }
}

watch([view, showClosed], () => load())

const quickLook = ref<{ id: number; reference: string } | null>(null)

const openQuickLook = (task: any, event: MouseEvent) => {
    if ((event.target as HTMLElement | null)?.closest("a, button, input, label, [data-picker]")) return
    quickLook.value = task
}

useLiveStaffTasks(() => {
    if (!savingCollaboratorsFor.value && !quickLook.value) load({ quietly: true })
})

const coworkers = ref<{ id: number; name: string; avatar: any }[]>([])

const loadCoworkers = async () => {
    const { data } = await axios.get(route("grp.chat.staff.coworkers.index"))
    coworkers.value = data.data
}

const collaboratorOptionsFor = (task: any) => {
    const byId = new Map<number, { id: number; name: string; avatar: any }>()
    for (const person of [...task.collaborators, ...coworkers.value]) byId.set(person.id, person)
    return [...byId.values()]
}

const statusStripClasses: Record<string, string> = {
    gray: "border-gray-200 bg-gray-50",
    blue: "border-blue-200 bg-blue-50/70",
    green: "border-green-200 bg-green-50/70",
    red: "border-red-200 bg-red-50/70",
}

const isTaskOpen = (task: { status: string }) => ["todo", "in_progress"].includes(task.status)

const canSuggestEtaFor = (task: any) => isTaskOpen(task) && isWorkingOn(task) && task.requester?.id !== myId.value && !task.eta_proposal

const etaTask = ref<any | null>(null)
const isEtaDialogOpen = ref(false)

const openEtaDialog = (task: any) => {
    etaTask.value = task
    isEtaDialogOpen.value = true
}

const isLeading = (task: any) => task.assignee?.id === myId.value || task.requester?.id === myId.value

const isWorkingOn = (task: any) => task.assignee?.id === myId.value || task.collaborators.some((person: { id: number }) => person.id === myId.value)

const draftCollaboratorIds = ref<Record<number, number[]>>({})
const savingCollaboratorsFor = ref<number | null>(null)

const collaboratorIdsOf = (task: any) => draftCollaboratorIds.value[task.id] ?? task.collaborators.map((person: { id: number }) => person.id)

const replaceTask = (updated: any) => {
    const index = tasks.value.findIndex((t) => t.id === updated.id)
    if (index === -1) return
    if (!showClosed.value && !isTaskOpen(updated)) {
        tasks.value.splice(index, 1)
        return
    }
    tasks.value[index] = updated
}

const syncCollaborators = async (task: any) => {
    const nextIds = draftCollaboratorIds.value[task.id]
    if (!nextIds) return
    const savedIds = task.collaborators.map((person: { id: number }) => person.id)
    if (nextIds.length === savedIds.length && nextIds.every((id) => savedIds.includes(id))) {
        delete draftCollaboratorIds.value[task.id]
        return
    }
    savingCollaboratorsFor.value = task.id
    try {
        const { data } = await axios.patch(route("grp.tasks.collaborators.update", task.reference), { collaborator_ids: nextIds })
        const index = tasks.value.findIndex((t) => t.id === task.id)
        if (index !== -1) tasks.value[index] = data.data
    } catch (error: any) {
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    } finally {
        delete draftCollaboratorIds.value[task.id]
        savingCollaboratorsFor.value = null
    }
}

const openThread = (task: any) => store.openTaskThread(task)

const toggleSubscription = async (task: any) => {
    const { data } = await axios.post(route("grp.tasks.subscription.toggle", task.reference))
    const index = tasks.value.findIndex((t) => t.id === task.id)
    if (index !== -1) tasks.value[index] = data.data
    await store.fetchConversations()
}

const onCreated = (task: any) => {
    if (view.value === "requested" || task.assignee?.id === myId.value) tasks.value.unshift(task)
    else view.value = "requested"
}

const onImported = () => {
    if (view.value === "requested") load()
    else view.value = "requested"
}

onMounted(async () => {
    loadCoworkers()
    await store.fetchConversations()
    if (props.selected_task) view.value = "requested"
    await load()
    if (props.selected_task) {
        const task = tasks.value.find((t) => t.reference === props.selected_task)
        if (task) quickLook.value = task
    }
})
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead">
        <template #other>
            <div class="flex items-center gap-x-2">
                <button class="flex items-center gap-x-1.5 px-3 py-1.5 text-sm rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50" @click="importOpen = true">
                    <FontAwesomeIcon icon="fal fa-list" fixed-width aria-hidden="true" />
                    {{ ctrans('From a list') }}
                </button>
                <button class="flex items-center gap-x-1.5 px-3 py-1.5 text-sm rounded-md bg-[--app-accent] text-[--app-accent-text] hover:bg-[--app-accent-strong]" @click="dialogOpen = true">
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    {{ ctrans('New task') }}
                </button>
            </div>
        </template>
    </PageHeading>

    <div class="flex items-start gap-6 px-4 py-4 md:px-6">
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button
                v-for="option in views"
                :key="option.key"
                class="flex items-center gap-x-1.5 px-3 py-1.5 text-sm rounded-full border"
                :class="view === option.key ? 'bg-[--app-accent] text-[--app-accent-text] border-[--app-accent]' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="view = option.key">
                {{ option.label }}
                <span
                    v-if="counts[option.key] !== undefined"
                    class="rounded-full px-1.5 text-xs tabular-nums"
                    :class="view === option.key ? 'bg-white/25' : 'bg-gray-100 text-gray-600'">{{ counts[option.key] }}</span>
            </button>
            <label class="ml-auto flex items-center gap-x-1.5 text-xs text-gray-500">
                <input v-model="showClosed" type="checkbox" class="rounded border-gray-300 text-[--app-accent] focus:ring-[--app-accent]" />
                {{ ctrans('Show closed') }}
            </label>
        </div>

        <ul v-if="loading" class="space-y-2.5" :aria-label="ctrans('Loading…')" aria-busy="true">
            <li v-for="row in 3" :key="row" class="flex items-stretch overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex w-11 shrink-0 justify-center border-r border-gray-200 bg-gray-50 pt-3.5">
                    <Skeleton shape="circle" size="1rem" />
                </div>
                <div class="flex min-w-0 flex-1 items-start gap-x-3 px-4 py-3">
                <div class="flex-1 min-w-0 space-y-2">
                    <Skeleton width="4rem" height="0.75rem" />
                    <Skeleton :width="row === 2 ? '40%' : '55%'" height="1rem" />
                    <Skeleton width="10rem" height="0.75rem" />
                    <div class="flex items-center gap-2">
                        <Skeleton v-for="chip in 2" :key="chip" width="4.5rem" height="1.75rem" border-radius="9999px" />
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-end justify-between gap-y-2 self-stretch">
                    <div class="flex items-center gap-x-1">
                        <Skeleton v-for="button in 3" :key="button" width="1.5rem" height="1.5rem" />
                    </div>
                    <div class="flex min-h-8 items-center gap-x-3">
                        <Skeleton width="6rem" height="0.75rem" />
                        <Skeleton width="7.5rem" height="1.25rem" border-radius="9999px" />
                    </div>
                </div>
                </div>
            </li>
        </ul>
        <div v-else-if="!tasks.length" class="py-10 text-center text-sm text-gray-400">{{ ctrans('Nothing here') }}</div>

        <ul v-else class="space-y-2.5">
            <li v-for="task in tasks" :key="task.id" class="flex cursor-pointer items-stretch overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition duration-200 hover:border-gray-300 hover:shadow" @click="openQuickLook(task, $event)">
                <div class="flex w-11 shrink-0 justify-center border-r pt-3.5" :class="statusStripClasses[task.status_icon.color] ?? statusStripClasses.gray">
                    <FontAwesomeIcon :icon="task.status_icon.icon" :class="task.status_icon.class" fixed-width v-tooltip="task.status_label" :aria-label="task.status_label" />
                </div>
                <div class="flex min-w-0 flex-1 items-start gap-x-3 px-4 py-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-x-2">
                        <Link :href="route('grp.tasks.show', task.reference)" class="primaryLink font-mono text-xs">{{ task.reference }}</Link>
                        <span v-if="task.priority !== 'normal'" class="text-xxs px-1.5 rounded-full" :class="task.priority === 'low' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'">{{ task.priority }}</span>
                        <a v-if="task.model_label" class="text-xxs text-[--app-accent]">{{ task.model_label }}</a>
                    </div>
                    <div class="text-sm text-gray-900">{{ task.subject }}</div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                        <span
                            v-tooltip="ctrans(':requester asked :assignee', { requester: task.requester?.name ?? '—', assignee: task.assignee?.name ?? task.department_label ?? ctrans('nobody yet') })"
                            class="flex min-w-0 cursor-default items-center gap-x-1">
                            <FontAwesomeIcon icon="fal fa-user" fixed-width aria-hidden="true" />
                            {{ task.requester?.name }}
                            <span class="text-gray-300">→</span>
                            {{ task.assignee?.name ?? task.department_label ?? '—' }}
                        </span>
                        <span v-if="task.attachments?.length" v-tooltip="ctrans(':count attachments', { count: String(task.attachments.length) })" class="flex cursor-default items-center gap-x-1">
                            <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                            {{ task.attachments.length }}
                        </span>
                    </div>
                    <StaffTaskPeoplePicker
                        v-if="task.collaborators.length || isWorkingOn(task)"
                        class="mt-2"
                        :modelValue="collaboratorIdsOf(task)"
                        :options="collaboratorOptionsFor(task)"
                        :excludeIds="task.assignee ? [task.assignee.id] : []"
                        :editable="isWorkingOn(task) || isLeading(task)"
                        :canRemove="(personId) => isLeading(task) || personId === myId"
                        :pending="savingCollaboratorsFor === task.id"
                        @update:modelValue="(ids) => (draftCollaboratorIds[task.id] = ids)"
                        @hide="syncCollaborators(task)" />
                </div>
                <div class="flex shrink-0 flex-col items-end justify-between gap-y-2 self-stretch">
                <div class="flex items-center gap-x-1" @click.stop>
                    <StaffTaskStatusActions
                        :task="task"
                        :canEdit="isWorkingOn(task)"
                        :canClaim="!task.assignee"
                        @updated="(updated) => replaceTask(updated)" />
                    <span v-if="isWorkingOn(task) || (!task.assignee && task.status === 'todo')" class="mx-0.5 h-4 w-px bg-gray-200" aria-hidden="true" />
                    <button v-tooltip="ctrans('Open thread')" class="p-1.5 text-gray-400 hover:text-[--app-accent]" @click="openThread(task)">
                        <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                    </button>
                    <button
                        v-if="task.requester?.id !== myId && task.assignee?.id !== myId"
                        v-tooltip="task.is_subscribed ? ctrans('Stop notifications') : ctrans('Notify me about this task')"
                        class="p-1.5"
                        :class="task.is_subscribed ? 'text-[--app-accent]' : 'text-gray-400 hover:text-[--app-accent]'"
                        @click="toggleSubscription(task)">
                        <FontAwesomeIcon :icon="task.is_subscribed ? 'fal fa-bell' : 'fal fa-bell-slash'" fixed-width aria-hidden="true" />
                    </button>
                </div>
                <div class="flex min-h-8 items-center gap-x-3">
                    <span v-tooltip="ctrans('Raised :date', { date: useFormatTime(task.created_at, { formatTime: 'hm' }) })" class="flex cursor-default items-center gap-x-1 text-xs tabular-nums text-gray-400">
                        <FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" />
                        {{ useFormatTime(task.created_at, { formatTime: 'hm' }) }}
                    </span>
                    <span
                        v-if="task.eta_proposal"
                        v-tooltip="ctrans(':name suggests :date: :reason', { name: task.eta_proposal.by_name, date: useFormatTime(task.eta_proposal.due_at, { formatTime: 'd MMM' }), reason: task.eta_proposal.reason })"
                        class="inline-flex cursor-default items-center gap-x-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 ring-1 ring-inset ring-amber-200">
                        <FontAwesomeIcon icon="fal fa-calendar-edit" fixed-width aria-hidden="true" />
                        {{ useFormatTime(task.eta_proposal.due_at, { formatTime: "d MMM" }) }}?
                    </span>
                    <StaffTaskDueBadge
                        v-if="task.due_at"
                        :dueAt="task.due_at"
                        :priority="task.priority"
                        :status="task.status"
                        :clickable="canSuggestEtaFor(task)"
                        :hint="canSuggestEtaFor(task) ? ctrans('click to suggest a new ETA') : null"
                        @click="openEtaDialog(task)" />
                    <button
                        v-else-if="canSuggestEtaFor(task)"
                        type="button"
                        class="rounded-full border border-dashed border-gray-300 px-2 py-0.5 text-xs text-gray-500 transition duration-200 hover:bg-gray-50 hover:text-gray-700 active:!bg-gray-100"
                        @click.stop="openEtaDialog(task)">
                        {{ ctrans("Suggest an ETA") }}
                    </button>
                    <span v-else class="cursor-default text-xs text-gray-400" v-tooltip="ctrans('Nobody set a due date')">{{ ctrans("No due date") }}</span>
                </div>
                </div>
                </div>
            </li>
        </ul>
    </div>

    <aside class="sticky top-[60px] hidden w-80 shrink-0 lg:block">
        <StaffTasksSidebar :badges="layout.task_badges ?? null" :tasks="tasks" @open="(task) => (quickLook = task)" />
    </aside>
    </div>

    <StaffTaskDialog :is-open="dialogOpen" @close="dialogOpen = false" @created="onCreated" />
    <StaffTaskImportDialog :is-open="importOpen" @close="importOpen = false" @created="onImported" />
    <StaffTaskQuickLook v-model:task="quickLook" @closed="load({ quietly: true })" />
    <StaffTaskEtaDialog v-if="etaTask" v-model:visible="isEtaDialogOpen" :task="etaTask" @suggested="replaceTask" />
</template>
