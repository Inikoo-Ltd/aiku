<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { useStaffTaskActions } from "@/Composables/useStaffTaskActions"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import StaffTaskCollaborators from "@/Components/Tasks/StaffTaskCollaborators.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faLink, faCheck, faBan, faBell, faBellSlash, faCalendar } from "@fal"

library.add(faTimes, faLink, faCheck, faBan, faBell, faBellSlash, faCalendar)

const task = defineModel<any | null>("task", { default: null })

const emit = defineEmits<{
    (e: "closed"): void
    (e: "updated", task: any): void
}>()

const myId = computed(() => usePage().props?.auth?.user?.id)
const overlay = ref<HTMLElement | null>(null)
const isLinkCopied = ref(false)
const isAskingCancelNote = ref(false)
const cancelNote = ref("")

const { update, claim, syncCollaborators, toggleSubscription } = useStaffTaskActions((updated) => {
    if (task.value?.id === updated.id) task.value = updated
    emit("updated", updated)
})

const askCancel = () => {
    cancelNote.value = ""
    isAskingCancelNote.value = true
}

const confirmCancel = async () => {
    if (!cancelNote.value.trim()) return
    await update(task.value, { status: "cancelled", note: cancelNote.value.trim() })
    isAskingCancelNote.value = false
}

useModalFocusTrap(computed(() => Boolean(task.value)), overlay)

const isOpen = computed(() => ["todo", "in_progress"].includes(task.value?.status))

watch(
    () => task.value?.reference,
    () => (isAskingCancelNote.value = false)
)

const copyTaskLink = async () => {
    await navigator.clipboard.writeText(route("grp.tasks.index", { task: task.value.reference }))
    isLinkCopied.value = true
    setTimeout(() => (isLinkCopied.value = false), 2000)
}

const close = () => {
    task.value = null
    emit("closed")
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="task"
            ref="overlay"
            tabindex="-1"
            class="fixed inset-0 z-50 flex items-center justify-center overscroll-contain bg-black/40 p-4 outline-none"
            @click.self="close"
            @keydown.esc="isAskingCancelNote ? (isAskingCancelNote = false) : close()">
            <div class="relative w-full max-w-3xl rounded-2xl bg-white p-6 shadow-xl">
                <button
                    type="button"
                    class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow hover:text-gray-800"
                    @click="close">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
                <div class="max-h-[80vh] overflow-y-auto">
                    <div class="flex flex-col">
                        <div class="shrink-0 border-b border-gray-100 pb-3">
                            <div class="flex flex-wrap items-center gap-2 text-xs mb-2">
                                <span class="font-mono font-medium text-gray-500">{{ task.reference }}</span>
                                <button
                                    v-tooltip="isLinkCopied ? ctrans('Copied') : ctrans('Copy link')"
                                    type="button"
                                    class="text-gray-400 transition duration-200 hover:text-gray-600 focus:!text-gray-700"
                                    @click="copyTaskLink">
                                    <FontAwesomeIcon :icon="isLinkCopied ? 'fal fa-check' : 'fal fa-link'" :class="isLinkCopied && 'text-green-500'" fixed-width aria-hidden="true" />
                                </button>
                                <FontAwesomeIcon :icon="task.status_icon.icon" :class="task.status_icon.class" fixed-width aria-hidden="true" />
                                <span class="text-gray-600">{{ task.status_label }}</span>
                                <span v-if="task.priority !== 'normal'" class="px-1.5 rounded-full" :class="task.priority === 'low' ? 'bg-gray-100 text-gray-500' : 'bg-orange-100 text-orange-700'">{{ task.priority }}</span>
                                <span v-if="task.model_label" class="text-gray-400">· {{ task.model_label }}</span>
                            </div>
                            <h2 class="text-lg font-semibold leading-snug mb-3">{{ task.subject }}</h2>
                            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 mb-4">
                                <span>{{ ctrans("Raised") }}: {{ useFormatTime(task.created_at, { formatTime: 'hm' }) }} · {{ task.requester?.name }}</span>
                                <span>{{ ctrans("Assignee") }}: {{ task.assignee?.name ?? task.department_label ?? '—' }}</span>
                                <span v-if="task.due_at" class="flex items-center gap-x-1" :class="task.is_overdue ? 'text-red-600' : ''">
                                    <FontAwesomeIcon icon="fal fa-calendar" fixed-width aria-hidden="true" />
                                    {{ ctrans("Due") }}: {{ useFormatTime(task.due_at) }}
                                </span>
                                <span v-if="task.closed_at">{{ ctrans("Closed") }}: {{ useFormatTime(task.closed_at, { formatTime: 'hm' }) }}</span>
                            </div>
                            <div v-if="task.is_partial" class="flex gap-2">
                                <div v-for="width in ['w-24', 'w-16', 'w-28']" :key="width" class="h-7 animate-pulse rounded-md bg-gray-100" :class="width" />
                            </div>
                            <div v-else class="flex flex-wrap items-center gap-2">
                                <template v-if="isOpen">
                                    <button v-if="task.assignee?.id !== myId" class="px-3 py-1.5 text-xs rounded-md border border-gray-300 hover:bg-gray-50" @click="claim(task)">{{ ctrans('I will do it') }}</button>
                                    <button v-else-if="task.status === 'todo'" class="px-3 py-1.5 text-xs rounded-md border border-gray-300 hover:bg-gray-50" @click="update(task, { status: 'in_progress' })">{{ ctrans('Working on it') }}</button>
                                    <button class="px-3 py-1.5 text-xs rounded-md bg-green-600 text-white hover:bg-green-700" @click="update(task, { status: 'done' })">{{ ctrans('Done') }}</button>
                                    <button class="flex items-center gap-x-1 px-3 py-1.5 text-xs rounded-md border border-gray-300 text-gray-600 hover:text-red-600 hover:border-red-300" @click="askCancel">
                                        <FontAwesomeIcon icon="fal fa-ban" fixed-width aria-hidden="true" />
                                        {{ ctrans(`Can't be done`) }}
                                    </button>
                                </template>
                                <button
                                    v-if="task.requester?.id !== myId && task.assignee?.id !== myId"
                                    class="flex items-center gap-x-1 px-3 py-1.5 text-xs rounded-md border border-gray-300 hover:bg-gray-50"
                                    :class="task.is_subscribed ? 'text-[--app-accent]' : 'text-gray-600'"
                                    @click="toggleSubscription(task)">
                                    <FontAwesomeIcon :icon="task.is_subscribed ? 'fal fa-bell' : 'fal fa-bell-slash'" fixed-width aria-hidden="true" />
                                    {{ task.is_subscribed ? ctrans('Stop notifications') : ctrans('Notify me about this task') }}
                                </button>
                            </div>
                        </div>
                        <div class="pr-1 pt-3 space-y-4">
                            <div v-if="task.is_partial" class="space-y-2">
                                <div class="h-4 w-3/4 animate-pulse rounded bg-gray-100" />
                                <div class="h-4 w-1/2 animate-pulse rounded bg-gray-100" />
                                <div class="h-4 w-2/3 animate-pulse rounded bg-gray-100" />
                            </div>
                            <template v-else>
                            <TicketBody v-if="task.description" :text="task.description" />
                            <p v-else class="text-sm text-gray-400">{{ ctrans("No details") }}</p>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">{{ ctrans('Working on it too') }}</label>
                                <StaffTaskCollaborators
                                    :model-value="task.collaborators"
                                    :exclude-ids="task.assignee ? [task.assignee.id] : []"
                                    @update:model-value="(people) => syncCollaborators(task, people)" />
                            </div>
                            <TicketAttachmentList v-if="task.attachments?.length" :files="task.attachments" compact />
                            </template>
                        </div>
                    </div>
                </div>
                <div v-if="isAskingCancelNote" class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-black/30 p-4" @click.self="isAskingCancelNote = false">
                    <div class="bg-white rounded-xl p-5 w-full max-w-md space-y-3 shadow-xl">
                        <h3 class="text-sm font-semibold text-gray-900">{{ ctrans(`Why can't :reference be done?`, { reference: task.reference }) }}</h3>
                        <textarea v-model="cancelNote" rows="3" autofocus class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-[--app-accent]" />
                        <div class="flex justify-end gap-x-2">
                            <button class="px-3 py-1.5 text-sm text-gray-600" @click="isAskingCancelNote = false">{{ ctrans('Back') }}</button>
                            <button :disabled="!cancelNote.trim()" class="px-3 py-1.5 text-sm rounded-md bg-red-600 text-white disabled:opacity-40" @click="confirmCancel">{{ ctrans('Confirm') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
