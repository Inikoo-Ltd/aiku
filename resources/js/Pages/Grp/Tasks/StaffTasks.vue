<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue"
import { Head, usePage } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import StaffTaskDialog from "@/Components/Tasks/StaffTaskDialog.vue"
import StaffTaskImportDialog from "@/Components/Tasks/StaffTaskImportDialog.vue"
import StaffTaskCollaborators from "@/Components/Tasks/StaffTaskCollaborators.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faTasks, faPlus, faComments, faCircle, faSpinner, faCheckCircle, faBan, faCalendar, faUser, faBell, faBellSlash, faList)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    selected_task: string | null
}>()

const store = useStaffMessaging()
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
const cancelNoteFor = ref<any | null>(null)
const cancelNote = ref("")

const load = async () => {
    loading.value = true
    const { data } = await axios.get(route("grp.tasks.list"), { params: { view: view.value, closed: showClosed.value ? 1 : 0 } })
    tasks.value = data.data
    loading.value = false
}

watch([view, showClosed], load)

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

const syncCollaborators = async (task: any, people: any[]) => {
    try {
        const { data } = await axios.patch(route("grp.tasks.collaborators.update", task.reference), { collaborator_ids: people.map((person) => person.id) })
        const index = tasks.value.findIndex((t) => t.id === task.id)
        if (index !== -1) tasks.value[index] = data.data
    } catch (error: any) {
        notify({ title: ctrans("Could not update task"), text: error.response?.data?.message, type: "error" })
    }
}

const claim = (task: any) => update(task, { assignee_id: myId.value, status: "in_progress" })
const done = (task: any) => update(task, { status: "done" })
const askCancel = (task: any) => { cancelNoteFor.value = task; cancelNote.value = "" }
const confirmCancel = async () => {
    if (!cancelNoteFor.value || !cancelNote.value.trim()) return
    await update(cancelNoteFor.value, { status: "cancelled", note: cancelNote.value.trim() })
    cancelNoteFor.value = null
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

    <div class="px-4 md:px-6 py-4 max-w-5xl">
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <button
                v-for="option in views"
                :key="option.key"
                class="px-3 py-1.5 text-sm rounded-full border"
                :class="view === option.key ? 'bg-[--app-accent] text-[--app-accent-text] border-[--app-accent]' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="view = option.key">
                {{ option.label }}
            </button>
            <label class="ml-auto flex items-center gap-x-1.5 text-xs text-gray-500">
                <input v-model="showClosed" type="checkbox" class="rounded border-gray-300 text-[--app-accent] focus:ring-[--app-accent]" />
                {{ ctrans('Show closed') }}
            </label>
        </div>

        <div v-if="loading" class="py-10 text-center text-sm text-gray-400">{{ ctrans('Loading…') }}</div>
        <div v-else-if="!tasks.length" class="py-10 text-center text-sm text-gray-400">{{ ctrans('Nothing here') }}</div>

        <ul v-else class="divide-y divide-gray-100 border border-gray-200 rounded-lg bg-white">
            <li v-for="task in tasks" :key="task.id" class="flex items-start gap-x-3 px-4 py-3">
                <FontAwesomeIcon :icon="task.status_icon.icon" :class="task.status_icon.class" class="mt-1" fixed-width v-tooltip="task.status_label" aria-hidden="true" />
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-x-2">
                        <span class="text-xs text-gray-400 font-mono">{{ task.reference }}</span>
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
                        <StaffTaskCollaborators
                            :model-value="task.collaborators"
                            :exclude-ids="task.assignee ? [task.assignee.id] : []"
                            compact
                            @update:model-value="(people) => syncCollaborators(task, people)" />
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
                        <button class="px-2 py-1 text-xs rounded bg-green-600 text-white hover:bg-green-700" @click="done(task)">{{ ctrans('Done') }}</button>
                        <button v-tooltip="ctrans(`Can't be done`)" class="p-1.5 text-gray-400 hover:text-red-600" @click="askCancel(task)">
                            <FontAwesomeIcon icon="fal fa-ban" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </div>
            </li>
        </ul>
    </div>

    <StaffTaskDialog :is-open="dialogOpen" @close="dialogOpen = false" @created="onCreated" />
    <StaffTaskImportDialog :is-open="importOpen" @close="importOpen = false" @created="onImported" />

    <div v-if="cancelNoteFor" class="fixed inset-0 z-30 flex items-center justify-center bg-black/40" @click.self="cancelNoteFor = null">
        <div class="bg-white rounded-xl p-5 w-full max-w-md space-y-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ ctrans(`Why can't :reference be done?`, { reference: cancelNoteFor.reference }) }}</h3>
            <textarea v-model="cancelNote" rows="3" autofocus class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
            <div class="flex justify-end gap-x-2">
                <button class="px-3 py-1.5 text-sm text-gray-600" @click="cancelNoteFor = null">{{ ctrans('Back') }}</button>
                <button :disabled="!cancelNote.trim()" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white disabled:opacity-40" @click="confirmCancel">{{ ctrans('Confirm') }}</button>
            </div>
        </div>
    </div>
</template>
