<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, reactive, ref } from "vue"
import TicketsCreatedInterval from "@/Components/Tickets/TicketsCreatedInterval.vue"
import { Head } from "@inertiajs/vue3"
import axios from "axios"
import draggable from "vuedraggable"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faColumns, faComments, faUser, faCalendar, faLock, faBell, faBellSlash } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faColumns, faComments, faUser, faCalendar, faLock, faBell, faBellSlash)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    columns: { status: string; label: string; color: string; tasks: any[] }[]
    can_manage: boolean
    me: number
    createdIntervals: Record<string, string>
    createdInterval: string
}>()

const columns = ref(props.columns)

type FilterKey = "department_label" | "priority" | "assignee"
const filters = reactive<Record<FilterKey, string[]>>({ department_label: [], priority: [], assignee: [] })
const filterLabels: Record<FilterKey, string> = { department_label: trans("Department"), priority: trans("Urgency"), assignee: trans("Assignee") }
const assigneeOf = (task: any) => (task.assignee ? (task.assignee.id === props.me ? "me" : "others") : "unassigned")
const assigneeLabels: Record<string, string> = { me: trans("Me"), others: trans("Everybody else"), unassigned: trans("Nobody yet") }
const valueOf = (task: any, key: FilterKey) => (key === "assignee" ? assigneeOf(task) : task[key])
const allTasks = computed(() => columns.value.flatMap((c) => c.tasks))
const filterOptions = computed(() =>
    (Object.keys(filters) as FilterKey[]).map((key) => {
        const counts: Record<string, number> = {}
        allTasks.value.forEach((task) => {
            const value = valueOf(task, key)
            if (value) counts[value] = (counts[value] ?? 0) + 1
        })
        return { key, label: filterLabels[key], options: Object.entries(counts).map(([value, count]) => ({ value, count, label: key === "assignee" ? assigneeLabels[value] : value })) }
    }).filter((group) => group.options.length > 1 || group.key === "assignee")
)
const toggleFilter = (key: FilterKey, value: string) => {
    const index = filters[key].indexOf(value)
    index === -1 ? filters[key].push(value) : filters[key].splice(index, 1)
}
const matches = (task: any) => (Object.keys(filters) as FilterKey[]).every((key) => !filters[key].length || filters[key].includes(valueOf(task, key)))
const visibleCount = (column: { tasks: any[] }) => column.tasks.filter(matches).length
const store = useStaffMessaging()
const cancelFor = ref<{ task: any; from: string } | null>(null)
const cancelNote = ref("")

const columnClasses: Record<string, string> = {
    gray: "bg-gray-100 border-t-4 border-gray-400",
    blue: "bg-blue-50 border-t-4 border-blue-400",
    green: "bg-green-50 border-t-4 border-green-500",
    red: "bg-red-50 border-t-4 border-red-400",
}

const moveBack = (task: any, from: string, to: string) => {
    const source = columns.value.find((c) => c.status === to)
    const target = columns.value.find((c) => c.status === from)
    if (!source || !target) return
    source.tasks = source.tasks.filter((t) => t.id !== task.id)
    target.tasks.unshift(task)
}

const patch = async (task: any, from: string, to: string, extra: Record<string, unknown> = {}) => {
    try {
        const { data } = await axios.patch(route("grp.tasks.update", task.reference), { status: to, ...extra })
        const column = columns.value.find((c) => c.status === to)
        const index = column?.tasks.findIndex((t) => t.id === task.id) ?? -1
        if (column && index !== -1) column.tasks[index] = data.data
    } catch (error: any) {
        notify({ title: trans("Could not move task"), text: error.response?.data?.message, type: "error" })
        moveBack(task, from, to)
    }
}

const onMoved = (column: { status: string }, event: { added?: { element: any }; removed?: { element: any } }) => {
    if (!event.added) return
    const task = event.added.element
    const from = task.status
    if (column.status === "cancelled") {
        cancelFor.value = { task, from }
        cancelNote.value = ""
        return
    }
    patch(task, from, column.status)
}

const confirmCancel = async () => {
    if (!cancelFor.value || !cancelNote.value.trim()) return
    await patch(cancelFor.value.task, cancelFor.value.from, "cancelled", { note: cancelNote.value.trim() })
    cancelFor.value = null
}

const abortCancel = () => {
    if (cancelFor.value) moveBack(cancelFor.value.task, cancelFor.value.from, "cancelled")
    cancelFor.value = null
}

const openThread = (task: any) => store.openTaskThread(task)

const toggleSubscription = async (task: any) => {
    const { data } = await axios.post(route("grp.tasks.subscription.toggle", task.reference))
    task.is_subscribed = data.data.is_subscribed
    await store.fetchConversations()
}
</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead">
        <template #other>
            <span v-if="!can_manage" class="flex items-center gap-x-1 text-xs text-gray-500" v-tooltip="trans('Only supervisors can move cards')">
                <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />
                {{ trans('Read only') }}
            </span>
        </template>
    </PageHeading>

    <div class="px-4 pt-4 space-y-3">
        <TicketsCreatedInterval :options="createdIntervals" :selected="createdInterval" />
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
            <div v-for="group in filterOptions" :key="group.key" class="flex flex-wrap items-center gap-1.5">
                <span class="mr-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ group.label }}</span>
                <button
                    v-for="option in group.options"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 transition"
                    :class="filters[group.key].includes(option.value) ? 'border-[--app-accent] bg-[--app-accent] text-[--app-accent-text] shadow-sm' : option.value === 'urgent' ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100' : 'border-gray-200 bg-gray-50 text-gray-600 hover:border-gray-300 hover:bg-white'"
                    @click="toggleFilter(group.key, option.value)">
                    <span class="capitalize">{{ option.label }}</span>
                    <span class="rounded-full px-1.5 text-xs tabular-nums" :class="filters[group.key].includes(option.value) ? 'bg-white/20' : 'bg-white text-gray-500'">{{ option.count }}</span>
                </button>
            </div>
        </div>
    </div>

    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
        <div v-for="column in columns" :key="column.status" class="rounded-lg p-2 min-h-[12rem]" :class="columnClasses[column.color] ?? columnClasses.gray">
            <div class="flex items-center justify-between px-1 pb-2 text-sm font-semibold text-gray-700">
                <span>{{ column.label }}</span>
                <span class="text-xs font-normal text-gray-500">{{ visibleCount(column) }}</span>
            </div>
            <draggable
                v-model="column.tasks"
                item-key="id"
                group="staff-tasks"
                :disabled="!can_manage"
                class="space-y-2 min-h-[8rem]"
                ghost-class="opacity-40"
                @change="onMoved(column, $event)">
                <template #item="{ element: task }">
                    <div v-show="matches(task)" class="bg-white rounded-md border border-gray-200 shadow-sm p-2.5 text-sm" :class="can_manage ? 'cursor-grab' : ''">
                        <div class="flex items-center justify-between text-xxs text-gray-400">
                            <span class="font-mono">{{ task.reference }}</span>
                            <span v-if="task.priority !== 'normal'" class="px-1.5 rounded-full" :class="task.priority === 'low' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'">{{ task.priority }}</span>
                        </div>
                        <div class="text-gray-900 mt-0.5">{{ task.subject }}</div>
                        <div v-if="task.model_label" class="text-xxs text-[--app-accent] mt-0.5">{{ task.model_label }}</div>
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-x-1 min-w-0">
                                <span class="h-5 w-5 rounded-full overflow-hidden bg-gray-200 shrink-0">
                                    <Image v-if="task.assignee?.avatar" :src="task.assignee.avatar" :alt="task.assignee.name" image-cover />
                                    <FontAwesomeIcon v-else icon="fal fa-user" class="h-full w-full p-0.5 text-gray-400" aria-hidden="true" />
                                </span>
                                <span class="truncate">{{ task.assignee?.name ?? task.department_label ?? '—' }}</span>
                            </span>
                            <span class="flex items-center gap-x-2 shrink-0">
                                <span v-if="task.due_at" :class="task.is_overdue ? 'text-red-600' : ''">
                                    <FontAwesomeIcon icon="fal fa-calendar" fixed-width aria-hidden="true" />
                                    {{ useFormatTime(task.due_at) }}
                                </span>
                                <button
                                    v-if="can_manage && task.requester?.id !== me && task.assignee?.id !== me"
                                    v-tooltip="task.is_subscribed ? trans('Stop notifications') : trans('Notify me about this task')"
                                    :class="task.is_subscribed ? 'text-[--app-accent]' : 'text-gray-400 hover:text-[--app-accent]'"
                                    @click.stop="toggleSubscription(task)">
                                    <FontAwesomeIcon :icon="task.is_subscribed ? 'fal fa-bell' : 'fal fa-bell-slash'" fixed-width aria-hidden="true" />
                                </button>
                                <button class="text-gray-400 hover:text-[--app-accent]" v-tooltip="trans('Open thread')" @click.stop="openThread(task)">
                                    <FontAwesomeIcon icon="fal fa-comments" fixed-width aria-hidden="true" />
                                </button>
                            </span>
                        </div>
                    </div>
                </template>
            </draggable>
        </div>
    </div>

    <div v-if="cancelFor" class="fixed inset-0 z-30 flex items-center justify-center bg-black/40" @click.self="abortCancel">
        <div class="bg-white rounded-xl p-5 w-full max-w-md space-y-3">
            <h3 class="text-sm font-semibold text-gray-900">{{ trans(`Why can't :reference be done?`, { reference: cancelFor.task.reference }) }}</h3>
            <textarea v-model="cancelNote" rows="3" autofocus class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
            <div class="flex justify-end gap-x-2">
                <button class="px-3 py-1.5 text-sm text-gray-600" @click="abortCancel">{{ trans('Back') }}</button>
                <button :disabled="!cancelNote.trim()" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white disabled:opacity-40" @click="confirmCancel">{{ trans('Confirm') }}</button>
            </div>
        </div>
    </div>
</template>
