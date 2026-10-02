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
import { faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import StaffTaskImportDialog from "@/Components/Tasks/StaffTaskImportDialog.vue"
import StaffTaskPeoplePicker from "@/Components/Tasks/StaffTaskPeoplePicker.vue"
import StaffTaskQuickLook from "@/Components/Tasks/StaffTaskQuickLook.vue"
import StaffTasksSidebar from "@/Components/Tasks/StaffTasksSidebar.vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { Skeleton } from "primevue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"

library.add(faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList)

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

const isWorkingOn = (task: any) => task.assignee?.id === myId.value || task.collaborators.some((person: { id: number }) => person.id === myId.value)

const draftCollaboratorIds = ref<Record<number, number[]>>({})
const savingCollaboratorsFor = ref<number | null>(null)

const collaboratorIdsOf = (task: any) => draftCollaboratorIds.value[task.id] ?? task.collaborators.map((person: { id: number }) => person.id)

const update = async (task: any, payload: Record<string, unknown>) => {
    try {
        const { data } = await axios.patch(route("grp.tasks.update", task.reference), payload)
        const index = tasks.value.findIndex((t) => t.id === task.id)
        if (index !== -1) tasks.value[index] = data.data
        if (!showClosed.value && ["done", "cancelled"].includes(data.data.status)) {
            tasks.value.splice(index, 1)
        }
    } catch (error: any) {
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    }
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

const claim = (task: any) => update(task, { assignee_id: myId.value, status: "in_progress" })

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
        if (task) openThread(task)
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
    <div class="min-w-0 max-w-5xl flex-1">
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

        <ul v-if="loading" class="divide-y divide-gray-100 border border-gray-200 rounded-lg bg-white" :aria-label="ctrans('Loading…')" aria-busy="true">
            <li v-for="row in 3" :key="row" class="flex items-start gap-x-3 px-4 py-3">
                <Skeleton shape="circle" size="1rem" class="mt-1 shrink-0" />
                <div class="flex-1 min-w-0 space-y-2">
                    <Skeleton width="4rem" height="0.75rem" />
                    <Skeleton :width="row === 2 ? '40%' : '55%'" height="1rem" />
                    <div class="flex flex-wrap items-center gap-2 pt-0.5">
                        <Skeleton width="10rem" height="0.75rem" />
                        <Skeleton v-for="chip in 2" :key="chip" width="4.5rem" height="1.75rem" border-radius="9999px" />
                        <Skeleton width="6rem" height="0.75rem" />
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-x-2">
                    <Skeleton width="1.5rem" height="1.5rem" />
                    <Skeleton width="3.5rem" height="1.75rem" />
                    <Skeleton width="1.5rem" height="1.5rem" />
                </div>
            </li>
        </ul>
        <div v-else-if="!tasks.length" class="py-10 text-center text-sm text-gray-400">{{ ctrans('Nothing here') }}</div>

        <ul v-else class="divide-y divide-gray-100 border border-gray-200 rounded-lg bg-white">
            <li v-for="task in tasks" :key="task.id" class="flex cursor-pointer items-start gap-x-3 px-4 py-3 transition duration-200 hover:bg-gray-50" @click="openQuickLook(task, $event)">
                <FontAwesomeIcon :icon="task.status_icon.icon" :class="task.status_icon.class" class="mt-1" fixed-width v-tooltip="task.status_label" aria-hidden="true" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-x-2">
                        <Link :href="route('grp.tasks.show', task.reference)" class="primaryLink font-mono text-xs">{{ task.reference }}</Link>
                        <span v-if="task.priority !== 'normal'" class="text-xxs px-1.5 rounded-full" :class="task.priority === 'low' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'">{{ task.priority }}</span>
                        <a v-if="task.model_label" class="text-xxs text-[--app-accent]">{{ task.model_label }}</a>
                    </div>
                    <div class="text-sm text-gray-900">{{ task.subject }}</div>
                    <div class="flex flex-wrap items-center gap-x-3 mt-1 text-xs text-gray-500">
                        <span class="flex items-center gap-x-1">
                            <FontAwesomeIcon icon="fal fa-user" fixed-width aria-hidden="true" />
                            {{ task.requester?.name }}
                            <span class="text-gray-300">→</span>
                            {{ task.assignee?.name ?? task.department_label ?? '—' }}
                        </span>
                        <StaffTaskPeoplePicker
                            v-if="task.collaborators.length || isWorkingOn(task)"
                            :modelValue="collaboratorIdsOf(task)"
                            :options="collaboratorOptionsFor(task)"
                            :excludeIds="task.assignee ? [task.assignee.id] : []"
                            :editable="isWorkingOn(task)"
                            :pending="savingCollaboratorsFor === task.id"
                            @update:modelValue="(ids) => (draftCollaboratorIds[task.id] = ids)"
                            @hide="syncCollaborators(task)" />
                        <span v-if="task.due_at" class="flex items-center gap-x-1" :class="task.is_overdue ? 'text-red-600' : ''">
                            <FontAwesomeIcon icon="fal fa-calendar" fixed-width aria-hidden="true" />
                            {{ useFormatTime(task.due_at) }}
                        </span>
                        <span>{{ useFormatTime(task.created_at, { formatTime: 'hm' }) }}</span>
                    </div>
                </div>
                <div class="flex items-center gap-x-1 shrink-0">
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
                    <template v-if="['todo', 'in_progress'].includes(task.status)">
                        <button v-if="task.assignee?.id !== myId" class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50" @click="claim(task)">{{ ctrans('I will do it') }}</button>
                        <button v-else-if="task.status === 'todo'" class="px-2 py-1 text-xs rounded border border-gray-300 hover:bg-gray-50" @click="update(task, { status: 'in_progress' })">{{ ctrans('Working on it') }}</button>
                    </template>
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
</template>
