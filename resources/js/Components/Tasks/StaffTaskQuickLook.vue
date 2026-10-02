<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import { taskRoute } from "@/Composables/useTasksRoute"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import Icon from "@/Components/Icon.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketControlPanel from "@/Components/Tickets/TicketControlPanel.vue"
import StaffTaskControls from "@/Components/Tasks/StaffTaskControls.vue"
import StaffTaskSubtasks from "@/Components/Tasks/StaffTaskSubtasks.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faSpinner, faLink, faCheck, faComments, faExternalLink, faImage, faBuilding, faCalendar } from "@fal"
library.add(faTimes, faSpinner, faLink, faCheck, faComments, faExternalLink, faImage, faBuilding, faCalendar)

const emit = defineEmits<{
    closed: []
}>()

const task = defineModel<{ id: number; reference: string } | null>("task", { default: null })

type QuickLookData = {
    task: any
    linked_url: string | null
    can_edit: boolean
    options: { statuses: any[]; priorities: any[] }
    messages: { id: number; user_id: number; user_name: string; body: string | null; gif_url: string | null; image: any; created_at: string }[] | null
    message_count: number
}

const data = ref<QuickLookData | null>(null)
const isUnavailable = ref(false)
const overlay = ref<HTMLElement | null>(null)
const controls = ref<InstanceType<typeof StaffTaskControls> | null>(null)
const subtasksBox = ref<InstanceType<typeof StaffTaskSubtasks> | null>(null)

useModalFocusTrap(computed(() => Boolean(task.value)), overlay)

const load = async (reference: string) => {
    isUnavailable.value = false
    try {
        const { data: response } = await axios.get(route("grp.tasks.quick_look", reference))
        if (task.value?.reference === reference) data.value = response
    } catch {
        if (task.value?.reference === reference) isUnavailable.value = true
    }
}

const reload = () => {
    if (task.value) load(task.value.reference)
}

watch(() => task.value?.reference, (reference) => {
    data.value = null
    if (reference) load(reference)
}, { immediate: true })

useLiveStaffTasks(reload, (event) => event.id === task.value?.id && !controls.value?.isBusy && !subtasksBox.value?.isSaving)

const shown = computed(() => data.value?.task ?? null)

const isLinkCopied = ref(false)
const copyTaskLink = async () => {
    if (!shown.value) return
    await navigator.clipboard.writeText(route("grp.tasks.show", shown.value.reference))
    isLinkCopied.value = true
    setTimeout(() => (isLinkCopied.value = false), 2000)
}

const store = useStaffMessaging()

const close = () => {
    task.value = null
    emit("closed")
}

const openChat = () => {
    if (!shown.value) return
    store.openTaskThread({ reference: shown.value.reference, conversation_ulid: shown.value.conversation_ulid })
    close()
}

const panelSummary = computed(() => shown.value ? {
    assignee: shown.value.assignee?.name ?? shown.value.department_label,
    assignee_avatar: shown.value.assignee?.avatar ?? null,
    assignee_short: shown.value.assignee ? shown.value.assignee.name.split(" ")[0] : shown.value.department_label,
    collaborators: shown.value.collaborators.map((person: { name: string }) => ({ ...person, short: person.name.split(" ")[0] })),
    status_icon: shown.value.status_icon,
    status_label: shown.value.status_label,
} : null)

const shortDate = (value: string | null) => value ? new Date(value).toLocaleDateString([], { day: "numeric", month: "short" }) : ""

const desktopQuery = globalThis.window?.matchMedia?.("(min-width: 1024px)")
const isDesktop = ref(desktopQuery?.matches ?? true)
const onDesktopQueryChange = (event: MediaQueryListEvent) => (isDesktop.value = event.matches)

onMounted(() => desktopQuery?.addEventListener("change", onDesktopQueryChange))
onBeforeUnmount(() => desktopQuery?.removeEventListener("change", onDesktopQueryChange))
</script>

<template>
    <div
        v-if="task"
        ref="overlay"
        tabindex="-1"
        class="fixed inset-0 z-50 flex items-center justify-center overscroll-contain bg-black/40 p-4 outline-none"
        @click.self="close">
        <div class="relative w-full max-w-6xl rounded-2xl bg-white p-6 shadow-xl">
            <button
                type="button"
                :aria-label="ctrans('Close')"
                class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow hover:text-gray-800"
                @click="close">
                <FontAwesomeIcon icon="fal fa-times" fixed-width />
            </button>

            <div v-if="!shown" class="flex h-40 items-center justify-center text-sm text-gray-400">
                <template v-if="isUnavailable">{{ ctrans("This task is unavailable") }}</template>
                <template v-else><FontAwesomeIcon icon="fal fa-spinner" spin class="mr-1" fixed-width />{{ ctrans("Loading") }}</template>
            </div>

            <div v-else class="grid max-h-[80vh] gap-6 overflow-y-auto lg:h-[80vh] lg:grid-cols-3 lg:overflow-hidden">
                <div class="flex flex-col lg:col-span-2 lg:min-h-0">
                    <div class="shrink-0 border-b border-gray-100 pb-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                            <Link :href="taskRoute(shown.reference)" class="primaryLink font-medium">{{ shown.reference }}</Link>
                            <button
                                v-tooltip="isLinkCopied ? ctrans('Copied') : ctrans('Copy link')"
                                type="button"
                                class="text-gray-400 transition duration-200 hover:text-gray-600 focus:!text-gray-700"
                                @click="copyTaskLink">
                                <FontAwesomeIcon :icon="isLinkCopied ? 'fal fa-check' : 'fal fa-link'" :class="isLinkCopied && 'text-green-500'" fixed-width aria-hidden="true" />
                            </button>
                            <Icon :data="shown.status_icon" />
                            <span class="text-gray-600">{{ shown.status_label }}</span>
                            <Icon v-if="shown.priority_icon" :data="shown.priority_icon" />
                            <span class="text-gray-600">{{ shown.priority_label }}</span>
                            <span v-if="shown.is_overdue" class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-700">{{ ctrans("Overdue") }}</span>
                        </div>
                        <h2 class="mb-3 text-lg font-semibold leading-snug">{{ shown.subject }}</h2>
                        <div class="mb-4 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600">
                            <span>{{ ctrans("Raised") }}: {{ shortDate(shown.created_at) }}{{ shown.requester ? " · " + shown.requester.name : "" }}</span>
                            <span>{{ ctrans("Assignee") }}: {{ shown.assignee?.name ?? shown.department_label ?? ctrans("Unassigned") }}</span>
                            <span v-if="shown.started_at">{{ ctrans("Started") }}: {{ shortDate(shown.started_at) }}</span>
                            <span v-if="shown.due_at" :class="shown.is_overdue && 'font-medium text-red-600'">{{ ctrans("Due") }}: {{ shortDate(shown.due_at) }}</span>
                            <span v-if="shown.closed_at">{{ ctrans("Closed") }}: {{ shortDate(shown.closed_at) }}</span>
                            <span v-if="shown.model_label" class="flex min-w-0 items-center gap-1">
                                {{ ctrans("About") }}:
                                <Link v-if="data?.linked_url" :href="data.linked_url" class="truncate text-[--app-accent-strong] hover:underline">{{ shown.model_label }}</Link>
                                <span v-else class="truncate">{{ shown.model_label }}</span>
                            </span>
                        </div>
                        <TicketControlPanel v-if="!isDesktop && panelSummary" :ticket="panelSummary" storage-key="staff_task_quick_look_controls_open" :default-open="false">
                            <StaffTaskControls ref="controls" :task="shown" :can-edit="data!.can_edit" :options="data!.options" @updated="reload" />
                        </TicketControlPanel>
                    </div>

                    <div class="pr-1 pt-3 lg:min-h-0 lg:flex-1 lg:overflow-y-auto">
                        <div class="flex min-h-full flex-col">
                            <TicketBody v-if="shown.description" :text="shown.description" />
                            <p v-else class="text-sm text-gray-400">{{ ctrans("No description") }}</p>
                            <TicketAttachmentList v-if="shown.attachments?.length" :files="shown.attachments" compact />

                            <StaffTaskSubtasks ref="subtasksBox" :reference="shown.reference" :subtasks="shown.subtasks" :can-edit="data!.can_edit" />

                            <div class="mt-auto pb-2.5 pt-4">
                                <div class="rounded-lg border border-gray-200 bg-white">
                                    <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2">
                                        <FontAwesomeIcon icon="fal fa-comments" class="text-gray-400" fixed-width />
                                        <span class="text-sm font-semibold text-gray-800">{{ ctrans("Chat") }}</span>
                                        <span class="rounded bg-gray-100 px-1.5 text-[11px] font-medium tabular-nums text-gray-600">{{ data!.message_count }}</span>
                                        <span class="ml-auto flex items-center gap-1">
                                            <button
                                                v-if="data!.messages && shown.conversation_ulid"
                                                type="button"
                                                class="rounded px-2 py-1 text-xs font-medium text-[--app-accent-strong] transition duration-200 hover:bg-gray-100 active:!bg-gray-200"
                                                @click="openChat">
                                                {{ ctrans("Open chat") }}
                                            </button>
                                            <Link :href="taskRoute(shown.reference)" class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition duration-200 hover:bg-gray-100 hover:text-gray-700">
                                                {{ ctrans("Task page") }}
                                                <FontAwesomeIcon icon="fal fa-external-link" fixed-width class="text-[10px]" />
                                            </Link>
                                        </span>
                                    </div>
                                    <p v-if="data!.messages === null" class="px-3 py-3 text-sm text-gray-400">{{ ctrans("You are not in this task's chat.") }}</p>
                                    <p v-else-if="!data!.messages.length" class="px-3 py-3 text-sm text-gray-400">{{ ctrans("No messages yet") }}</p>
                                    <template v-else>
                                        <p v-if="data!.message_count > data!.messages.length" class="px-3 pt-2 text-[11px] text-gray-400">
                                            {{ ctrans("Last :count of :total messages", { count: data!.messages.length, total: data!.message_count }) }}
                                        </p>
                                        <ul class="divide-y divide-gray-50">
                                            <li v-for="message in data!.messages" :key="message.id" class="flex items-start gap-2 px-3 py-2">
                                                <TicketUserAvatar :name="message.user_name" size="sm" />
                                                <div class="min-w-0 flex-1">
                                                    <p class="flex items-baseline gap-2 text-xs">
                                                        <span class="font-medium text-gray-800">{{ message.user_name }}</span>
                                                        <span class="text-gray-400">{{ useFormatTime(message.created_at, { formatTime: "hm" }) }}</span>
                                                    </p>
                                                    <p v-if="message.gif_url" class="text-xs italic text-gray-400">GIF</p>
                                                    <p v-else-if="message.image && !message.body" class="text-xs italic text-gray-400"><FontAwesomeIcon icon="fal fa-image" fixed-width /> {{ ctrans("Image") }}</p>
                                                    <p v-else class="line-clamp-2 whitespace-pre-wrap break-words text-sm text-gray-700">{{ message.body }}</p>
                                                </div>
                                            </li>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <aside v-if="isDesktop" class="text-sm lg:min-h-0 lg:overflow-y-auto lg:border-l lg:border-gray-200 lg:pl-6 lg:pr-1">
                    <StaffTaskControls ref="controls" :task="shown" :can-edit="data!.can_edit" :options="data!.options" @updated="reload" />
                </aside>
            </div>
        </div>
    </div>
</template>
