<!--
  - Author: aqordeon <dev@aw-advantage.com>
  - Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, onUnmounted, ref } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { Drawer } from "primevue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import TicketControlPanel from "@/Components/Tickets/TicketControlPanel.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import TicketComposer from "@/Components/Tickets/TicketComposer.vue"
import TicketKeptFiles from "@/Components/Tickets/TicketKeptFiles.vue"
import HistoryChangeModal from "@/Components/Tickets/HistoryChangeModal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import axios from "axios"
import type { StaffTaskDueAccess, StaffTaskEtaProposal } from "@/types/StaffTaskEta"
import StaffTaskControls from "@/Components/Tasks/StaffTaskControls.vue"
import StaffTaskSubtasks from "@/Components/Tasks/StaffTaskSubtasks.vue"
import StaffTaskChatMembers from "@/Components/Tasks/StaffTaskChatMembers.vue"
import VerticalScrollFade from "@/Components/Utils/VerticalScrollFade.vue"
import MessagingConversation from "@/Components/Messaging/MessagingConversation.vue"
import { useStaffMessaging, type StaffConversation } from "@/Stores/staff-messaging"
import { useStaffTaskMembers } from "@/Composables/useStaffTaskMembers"
import { useLiveStaffTasks } from "@/Composables/useLiveStaffTasks"
import { PageHeadingTypes } from "@/types/PageHeading"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faLink, faComments, faChevronDown, faPlusCircle, faUserCheck, faFlag, faPencil, faExchange, faCalendar, faCircle, faSpinner, faCheckCircle, faBan } from "@fal"
library.add(faLink, faComments, faChevronDown, faPlusCircle, faUserCheck, faFlag, faPencil, faExchange, faCalendar, faCircle, faSpinner, faCheckCircle, faBan)

type Person = { id: number; name: string; avatar: any }
type Option = { label: string; value: string; icon: any }
type Subtask = { title: string; status: "todo" | "in_progress" | "done" }

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    task: {
        id: number
        reference: string
        subject: string
        description: string | null
        status: string
        status_label: string
        status_icon: any
        priority: string
        priority_icon: any
        department_label: string | null
        requester: Person | null
        assignee: Person | null
        collaborators: Person[]
        due_at: string | null
        is_overdue: boolean
        model_type: string | null
        model_id: number | null
        model_label: string | null
        is_subscribed: boolean | null
        subtasks: Subtask[]
        eta_proposal: StaffTaskEtaProposal | null
        ticket_project_id: number | null
        ticket_project_milestone_id: number | null
        project: { name: string; slug: string } | null
        milestone: string | null
        closed_at: string | null
        created_at: string
        attachments?: { ulid?: string; name: string; url: string; mime?: string | null; thumbnail?: Record<string, string> | null }[]
    }
    linked_url: string | null
    conversation: StaffConversation | null
    can_edit: boolean
    due_access: StaffTaskDueAccess
    can_remove_collaborators: boolean
    can_reassign: boolean
    can_ask_for_help: boolean
    can_edit_content?: boolean
    timeline: { at: string; icon: string; text: string; by: string | null; change?: { label: string; from: string; to: string } | null }[]
    options: { statuses: Option[]; priorities: Option[] }
    project_options?: { label: string; value: number }[]
    milestone_options?: { label: string; value: number }[]
    can_change_project?: boolean
    project_route?: { name: string; parameters: Record<string, unknown> }
}>()

const store = useStaffMessaging()
const myId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const firstName = (name: string) => name.split(" ")[0]

const desktopQuery = window.matchMedia("(min-width: 1024px)")
const isDesktop = ref(desktopQuery.matches)
const onBreakpointChange = (event: MediaQueryListEvent) => {
    isDesktop.value = event.matches
}
onMounted(() => desktopQuery.addEventListener("change", onBreakpointChange))
onBeforeUnmount(() => desktopQuery.removeEventListener("change", onBreakpointChange))

const daysAgo = (date: string) => {
    const days = Math.floor((Date.now() - new Date(date).getTime()) / 86_400_000)
    return days === 0 ? ctrans("today") : days === 1 ? ctrans("1 day ago") : ctrans(":days days ago", { days: String(days) })
}

const controls = ref<InstanceType<typeof StaffTaskControls> | null>(null)
const subtasksBox = ref<InstanceType<typeof StaffTaskSubtasks> | null>(null)

const reloadTask = () => router.reload({ only: ["task", "conversation", "can_edit", "due_access", "can_remove_collaborators", "can_reassign", "can_ask_for_help", "can_edit_content", "timeline", "project_options", "milestone_options", "can_change_project", "project_route"], preserveScroll: true })

const historyChangeEvent = ref<any | null>(null)

const isEditingContent = ref(false)
const isSavingContent = ref(false)
const contentSubject = ref("")
const contentDescription = ref("")
const contentRemovedMedia = ref<string[]>([])
const contentImages = ref<File[]>([])
const contentErrors = ref<Record<string, string>>({})

const startContentEdit = () => {
    contentSubject.value = props.task.subject
    contentDescription.value = props.task.description ?? ""
    contentRemovedMedia.value = []
    contentImages.value = []
    contentErrors.value = {}
    isEditingContent.value = true
}

onMounted(() => {
    const url = new URL(window.location.href)
    if (url.searchParams.get("edit") !== "content") return

    url.searchParams.delete("edit")
    window.history.replaceState(window.history.state, "", url.toString())
    if (props.can_edit_content) startContentEdit()
})

const keptTaskFiles = computed(() => (props.task.attachments ?? []).filter((file) => !file.ulid || !contentRemovedMedia.value.includes(file.ulid)))
const keptContentImages = computed(() => keptTaskFiles.value.filter((file) => file.thumbnail).map((file) => ({ ...file.thumbnail, name: file.name, ulid: file.ulid })))
const keptContentAttachments = computed(() => keptTaskFiles.value.filter((file) => !file.thumbnail))

const saveContent = async () => {
    const formData = new FormData()
    formData.append("_method", "patch")
    formData.append("subject", contentSubject.value)
    formData.append("description", contentDescription.value)
    contentRemovedMedia.value.forEach((ulid) => formData.append("remove_media[]", ulid))
    contentImages.value.forEach((file) => formData.append("images[]", file))

    isSavingContent.value = true
    contentErrors.value = {}
    try {
        await axios.post(route("grp.tasks.content.update", props.task.reference), formData)
        isEditingContent.value = false
        reloadTask()
    } catch (error: any) {
        contentErrors.value = Object.fromEntries(Object.entries(error.response?.data?.errors ?? { subject: [ctrans("Could not save, please try again")] }).map(([field, messages]) => [field.split(".")[0], (messages as string[])[0]]))
    } finally {
        isSavingContent.value = false
    }
}

const panelSummary = computed(() => ({
    assignee: props.task.assignee?.name ?? props.task.department_label,
    assignee_avatar: props.task.assignee?.avatar ?? null,
    assignee_short: props.task.assignee ? firstName(props.task.assignee.name) : props.task.department_label,
    collaborators: props.task.collaborators.map((person) => ({ ...person, short: firstName(person.name) })),
    status_icon: props.task.status_icon,
    status_label: props.task.status_label,
}))

const chatConversation = computed<StaffConversation | null>(() =>
    props.conversation ? store.conversationByUlid(props.conversation.ulid) ?? props.conversation : null
)

const { members, onlineCount } = useStaffTaskMembers(
    () => chatConversation.value?.participants ?? [],
    () => ({
        requesterId: props.task.requester?.id ?? null,
        assigneeId: props.task.assignee?.id ?? null,
        collaboratorIds: props.task.collaborators.map((person) => person.id),
    })
)

const isMemberDrawerOpen = ref(false)

const mobileTab = ref<"chat" | "history">("chat")

const mobileTabs = computed(() => [
    { key: "chat" as const, label: ctrans("Chatroom"), count: null },
    { key: "history" as const, label: ctrans("History"), count: props.timeline.length },
])

const readPanelState = (key: string) => {
    try {
        return localStorage.getItem(key) !== "closed"
    } catch {
        return true
    }
}

const rememberPanelState = (key: string, isOpen: boolean) => {
    try {
        localStorage.setItem(key, isOpen ? "open" : "closed")
    } catch {
        return
    }
}

const isMembersOpen = ref(readPanelState("staff-task-members"))
const toggleMembers = () => {
    isMembersOpen.value = !isMembersOpen.value
    rememberPanelState("staff-task-members", isMembersOpen.value)
}

const isHistoryOpen = ref(readPanelState("staff-task-history"))
const toggleHistory = () => {
    isHistoryOpen.value = !isHistoryOpen.value
    rememberPanelState("staff-task-history", isHistoryOpen.value)
}

const isHistoryNewestFirst = ref(true)
const sortedTimeline = computed(() => isHistoryNewestFirst.value ? props.timeline : [...props.timeline].reverse())

useLiveStaffTasks(
    reloadTask,
    (event) => event.id === props.task.id && !controls.value?.isBusy && !subtasksBox.value?.isSaving && !isEditingContent.value
)

onMounted(async () => {
    if (!props.conversation) return
    await store.fetchConversations()
    if (!store.conversationByUlid(props.conversation.ulid)) store.visitingConversations.push(props.conversation)
    store.fullViewUlid = props.conversation.ulid
    store.loadMessages(props.conversation.ulid)
    store.markRead(props.conversation.ulid)
})

onUnmounted(() => {
    store.fullViewUlid = null
})

</script>

<template>
    <Head :title="title" />
    <PageHeading :data="pageHead" />

    <div class="flex flex-col lg:flex-row lg:items-start">
        <div class="min-w-0 flex-1 space-y-4 p-4 lg:border-r lg:border-gray-200">
                <section class="rounded-lg border-2 border-[--app-accent-muted] bg-white p-5 shadow-sm">
                    <div class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-gray-200 pb-2 text-xs text-gray-500 max-lg:-mx-5 max-lg:px-5">
                        <span class="w-full text-[10px] font-medium uppercase tracking-wide text-gray-400 lg:hidden">{{ ctrans("Requester") }}</span>
                        <TicketUserAvatar :name="task.requester?.name ?? null" :avatar="task.requester?.avatar" size="sm" />
                        <span class="font-semibold text-gray-800">{{ task.requester?.name ?? "-" }}</span>
                        <span>· {{ useFormatTime(task.created_at, { formatTime: "PP, HH:mm:ss zzz" }) }}</span>
                        <span class="text-gray-400">({{ daysAgo(task.created_at) }})</span>
                        <span v-if="task.is_overdue" class="rounded-full bg-red-100 px-2 py-0.5 font-medium text-red-700">{{ ctrans("Overdue") }}</span>
                        <span v-if="task.closed_at" class="text-gray-400">· {{ ctrans("Closed :date", { date: useFormatTime(task.closed_at, { formatTime: "hm" }) }) }}</span>
                    </div>
                    <div id="task-card-controls" />
                    <form v-if="isEditingContent" class="mb-3 space-y-3" :aria-busy="isSavingContent" @submit.prevent="saveContent">
                        <div>
                            <label for="task-content-subject" class="mb-1 block text-xs text-gray-500">{{ ctrans("Subject") }}</label>
                            <input id="task-content-subject" v-model="contentSubject" type="text" maxlength="255" class="w-full rounded-md border-gray-300 text-sm font-semibold focus:border-[--app-accent] focus:ring-[--app-accent]" />
                            <p v-if="contentErrors.subject" class="mt-1 text-xs text-red-600">{{ contentErrors.subject }}</p>
                        </div>
                        <div>
                            <p class="mb-1 text-xs text-gray-500">{{ ctrans("Description") }}</p>
                            <TicketComposer v-model:body="contentDescription" v-model:images="contentImages" :rows="6" :placeholder="ctrans('What needs doing, and anything that helps')" :max-images="Math.max(0, 5 - keptTaskFiles.length)" />
                            <p v-if="contentErrors.description || contentErrors.images" class="mt-1 text-xs text-red-600">{{ contentErrors.description || contentErrors.images }}</p>
                        </div>
                        <TicketKeptFiles :images="keptContentImages" :attachments="keptContentAttachments" @remove="(ulid) => contentRemovedMedia.push(ulid)" />
                        <p class="text-xs text-gray-400">{{ ctrans("Edits are written to the task history.") }}</p>
                        <div class="flex justify-end gap-2">
                            <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isSavingContent" @click="isEditingContent = false" />
                            <Button :label="ctrans('Save')" :loading="isSavingContent" :disabled="!contentSubject.trim()" @click="saveContent" />
                        </div>
                    </form>
                    <template v-else>
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <h2 class="text-lg font-semibold">{{ task.subject }}</h2>
                            <button v-if="can_edit_content" v-tooltip="ctrans('Edit subject, description and files')" type="button" class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-600" @click="startContentEdit">
                                <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                            </button>
                        </div>
                    </template>
                    <div v-if="task.model_label" class="mb-3 flex items-center gap-1.5 text-sm">
                        <FontAwesomeIcon icon="fal fa-link" class="text-gray-400" fixed-width />
                        <Link v-if="linked_url" :href="linked_url" class="truncate text-[--app-accent-strong] hover:underline">{{ task.model_label }}</Link>
                        <span v-else class="text-gray-700">{{ task.model_label }}</span>
                        <span class="text-xs text-gray-400">{{ task.model_type }}</span>
                    </div>
                    <template v-if="!isEditingContent">
                        <TicketBody v-if="task.description" :text="task.description" />
                        <p v-else class="text-sm text-gray-400">{{ ctrans("No description") }}</p>
                        <TicketAttachmentList v-if="task.attachments?.length" :files="task.attachments" compact class="mt-3" />
                    </template>

                    <StaffTaskSubtasks ref="subtasksBox" :reference="task.reference" :subtasks="task.subtasks" :can-edit="can_edit" />
                </section>

            <div class="flex border-b border-gray-200 lg:hidden" role="tablist">
                <button
                    v-for="tab in mobileTabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="mobileTab === tab.key"
                    class="-mb-px flex flex-1 items-center justify-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition duration-200"
                    :class="mobileTab === tab.key ? 'border-[--app-accent] text-[--app-accent-strong]' : 'border-transparent text-gray-500 hover:text-gray-700'"
                    @click="mobileTab = tab.key">
                    {{ tab.label }}
                    <span v-if="tab.count !== null" class="rounded bg-gray-100 px-1.5 text-[11px] tabular-nums text-gray-600">{{ tab.count }}</span>
                </button>
            </div>
            <div v-show="mobileTab === 'history'" id="task-mobile-history" class="lg:hidden" />

            <section v-if="chatConversation" v-show="isDesktop || mobileTab === 'chat'" class="h-[70vh] min-h-[28rem] overflow-hidden rounded-lg border border-gray-200 bg-white">
                <MessagingConversation :conversation="chatConversation" full-screen embedded>
                    <template #header-actions>
                        <button
                            v-if="!isDesktop"
                            type="button"
                            class="flex shrink-0 items-center gap-1.5 rounded-full bg-white px-2.5 py-1 text-xs text-gray-600 ring-1 ring-gray-200 transition duration-200 hover:bg-gray-50 active:!bg-gray-100"
                            @click="isMemberDrawerOpen = true">
                            <span class="h-2 w-2 rounded-full" :class="onlineCount ? 'bg-green-500' : 'bg-gray-300'" />
                            {{ ctrans(":online of :total online", { online: onlineCount, total: members.length }) }}
                        </button>
                    </template>
                </MessagingConversation>
            </section>
            <section v-else v-show="isDesktop || mobileTab === 'chat'" class="flex items-center gap-2 rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">
                <FontAwesomeIcon icon="fal fa-comments" fixed-width />
                {{ ctrans("You are not in this task's chat.") }}
            </section>
        </div>

        <div class="w-full shrink-0 space-y-4 p-4 max-lg:hidden lg:sticky lg:top-[60px] lg:max-h-[calc(100vh-60px)] lg:w-[26rem] lg:overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
            <Teleport defer to="#task-card-controls" :disabled="isDesktop">
            <TicketControlPanel :ticket="panelSummary" :storage-key="isDesktop ? 'staff-task-control-panel' : 'staff-task-control-panel-mobile'" :default-open="isDesktop" :embedded="!isDesktop">
                <StaffTaskControls ref="controls" :task="task" :can-edit="can_edit" :due-access="due_access" :can-remove-collaborators="can_remove_collaborators" :can-reassign="can_reassign" :can-ask-for-help="can_ask_for_help" :options="options" :project-options="project_options" :milestone-options="milestone_options" :can-change-project="can_change_project" :project-route="project_route" @updated="reloadTask" />
            </TicketControlPanel>
            </Teleport>

            <section v-if="chatConversation" class="overflow-hidden rounded-lg border border-gray-300 bg-white text-sm">
                <button type="button" class="flex w-full items-center justify-between gap-3 p-4 text-left transition duration-200 hover:bg-gray-50" :aria-expanded="isMembersOpen" @click="toggleMembers">
                    <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("In this chat") }}</span>
                    <span class="flex shrink-0 items-center gap-3">
                        <span class="flex items-center -space-x-1.5">
                            <span
                                v-for="member in members.slice(0, 3)"
                                :key="member.id"
                                v-tooltip="`${member.name} · ${member.presence.isOnline ? ctrans('Online') : ctrans('Offline')}`"
                                class="rounded-full ring-2"
                                :class="member.presence.isOnline ? 'ring-green-500' : 'ring-gray-300'">
                                <TicketUserAvatar :name="member.name" :avatar="member.avatar" size="sm" class="ring-2 ring-white" />
                            </span>
                            <span
                                v-if="members.length > 3"
                                v-tooltip="members.slice(3).map((member) => `${member.name} · ${member.presence.isOnline ? ctrans('Online') : ctrans('Offline')}`).join('\n')"
                                class="flex h-6 min-w-6 items-center justify-center rounded-full bg-gray-100 px-1 text-[10px] font-medium text-gray-600 ring-2 ring-white">
                                +{{ members.length - 3 }}
                            </span>
                        </span>
                        <span class="text-xs tabular-nums text-gray-500">{{ onlineCount }}/{{ members.length }}</span>
                        <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isMembersOpen && '-rotate-90'" />
                    </span>
                </button>
                <VerticalScrollFade v-show="isMembersOpen" max-height-class="max-h-[20rem]" class="border-t border-gray-200">
                    <StaffTaskChatMembers :members="members" :my-id="myId" />
                </VerticalScrollFade>
            </section>

            <Teleport defer to="#task-mobile-history" :disabled="isDesktop">
            <section class="rounded-lg border border-gray-300 bg-white text-sm">
                <button type="button" class="flex w-full items-center justify-between gap-3 p-4 text-left text-xs text-gray-500 transition duration-200 hover:bg-gray-50" :aria-expanded="isHistoryOpen || !isDesktop" @click="isDesktop && toggleHistory()">
                    <span class="font-medium uppercase tracking-wide text-gray-400">{{ ctrans("History") }}</span>
                    <span class="flex shrink-0 items-center gap-3">
                        <span v-if="(isHistoryOpen || !isDesktop) && timeline.length > 1" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort history')" @click.stop="isHistoryNewestFirst = !isHistoryNewestFirst">
                            {{ isHistoryNewestFirst ? "↓" : "↑" }} {{ isHistoryNewestFirst ? ctrans("Newest first") : ctrans("Oldest first") }}
                        </span>
                        <FontAwesomeIcon v-if="isDesktop" icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isHistoryOpen && '-rotate-90'" />
                    </span>
                </button>
                <ol v-show="isHistoryOpen || !isDesktop" class="relative mb-4 ml-6 mr-4 border-l border-gray-200">
                    <li v-for="(event, index) in sortedTimeline" :key="index" class="mb-4 ml-5 last:mb-0">
                        <span class="absolute -left-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-gray-500 ring-1 ring-gray-200">
                            <FontAwesomeIcon :icon="event.icon" fixed-width class="text-[10px]" />
                        </span>
                        <button v-if="event.change" v-tooltip="ctrans('See what changed')" type="button" class="text-left text-gray-800 underline decoration-gray-300 decoration-dotted underline-offset-2 transition duration-200 hover:text-[--app-accent-strong] hover:decoration-current" @click="historyChangeEvent = event">{{ event.text }}</button>
                        <p v-else class="text-gray-800">{{ event.text }}</p>
                        <p class="text-xs text-gray-400">
                            {{ useFormatTime(event.at, { formatTime: "hm" }) }}<template v-if="event.by"> · {{ event.by }}</template>
                        </p>
                    </li>
                </ol>
                <HistoryChangeModal :event="historyChangeEvent" @close="historyChangeEvent = null" />
            </section>
            </Teleport>
        </div>
    </div>

    <Drawer v-model:visible="isMemberDrawerOpen" position="right" :header="ctrans('In this chat')" class="!w-80 !max-w-[85vw]" :pt="{ content: { class: '!p-0' } }">
        <p class="border-b border-gray-200 px-3 pb-2 text-xs text-gray-500">{{ ctrans(":online of :total online", { online: onlineCount, total: members.length }) }}</p>
        <StaffTaskChatMembers :members="members" :my-id="myId" />
    </Drawer>

</template>
