<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 16 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Head } from "@inertiajs/vue3"
import axios from "axios"
import draggable from "vuedraggable"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faColumns, faComments, faUser, faCalendar, faLock } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { PageHeadingTypes } from "@/types/PageHeading"
import Image from "@/Common/Components/Image.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faColumns, faComments, faUser, faCalendar, faLock)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    columns: { status: string; label: string; color: string; tasks: any[] }[]
    can_manage: boolean
    me: number
}>()

const columns = ref(props.columns)
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

const openThread = async (task: any) => {
    if (!task.conversation_ulid) return
    if (!store.conversations.length) await store.fetchConversations()
    store.openConversation(task.conversation_ulid)
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

    <div class="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-start">
        <div v-for="column in columns" :key="column.status" class="rounded-lg p-2 min-h-[12rem]" :class="columnClasses[column.color] ?? columnClasses.gray">
            <div class="flex items-center justify-between px-1 pb-2 text-sm font-semibold text-gray-700">
                <span>{{ column.label }}</span>
                <span class="text-xs font-normal text-gray-500">{{ column.tasks.length }}</span>
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
                    <div class="bg-white rounded-md border border-gray-200 shadow-sm p-2.5 text-sm" :class="can_manage ? 'cursor-grab' : ''">
                        <div class="flex items-center justify-between text-xxs text-gray-400">
                            <span class="font-mono">{{ task.reference }}</span>
                            <span v-if="task.priority !== 'normal'" class="px-1.5 rounded-full" :class="task.priority === 'low' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'">{{ task.priority }}</span>
                        </div>
                        <div class="text-gray-900 mt-0.5">{{ task.subject }}</div>
                        <div v-if="task.model_label" class="text-xxs text-indigo-600 mt-0.5">{{ task.model_label }}</div>
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
                                <button class="text-gray-400 hover:text-indigo-600" v-tooltip="trans('Open thread')" @click.stop="openThread(task)">
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
            <textarea v-model="cancelNote" rows="3" autofocus class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-indigo-500" />
            <div class="flex justify-end gap-x-2">
                <button class="px-3 py-1.5 text-sm text-gray-600" @click="abortCancel">{{ trans('Back') }}</button>
                <button :disabled="!cancelNote.trim()" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white disabled:opacity-40" @click="confirmCancel">{{ trans('Confirm') }}</button>
            </div>
        </div>
    </div>
</template>
