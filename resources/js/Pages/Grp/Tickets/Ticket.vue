<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketRoute } from "@/Composables/useTicketsRoute"
import { ref, computed, onMounted, onBeforeUnmount } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import Icon from "@/Components/Icon.vue"
import TicketControlPanel from "@/Components/Tickets/TicketControlPanel.vue"
import TicketChatDropdown from "@/Components/Tickets/TicketChatDropdown.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import TicketRating from "@/Components/Tickets/TicketRating.vue"
import TicketControls from "@/Components/Tickets/TicketControls.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import TicketPullRequest from "@/Components/Tickets/TicketPullRequest.vue"
import TicketSimilar from "@/Components/Tickets/TicketSimilar.vue"
import TicketLinks from "@/Components/Tickets/TicketLinks.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import { Dialog } from "primevue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import HistoryChangeModal from "@/Components/Tickets/HistoryChangeModal.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus, faPlusCircle, faExchange, faHourglassHalf, faVial, faShieldCheck, faForward, faShield, faRocket, faUsers, faLink, faLifeRing, faToolbox, faUserHeadset, faBooks, faDatabase, faTasks, faChevronDown, faComment, faComments, faEnvelope, faCommentDots, faCodeBranch, faBell, faBellSlash } from "@fal"

library.add(faBell, faBellSlash, faCodeBranch,faWhatsapp,faComment, faComments, faEnvelope, faBooks, faDatabase, faTasks, faChevronDown, faLifeRing, faToolbox, faUserHeadset, faLink, faUsers, faRocket, faVial, faShieldCheck, faForward, faShield, faHourglassHalf, faPlusCircle, faExchange, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus,faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faCommentDots)

const desktopQuery = window.matchMedia("(min-width: 1024px)")
const isDesktop = ref(desktopQuery.matches)
const onBreakpointChange = (event: MediaQueryListEvent) => {
    isDesktop.value = event.matches
}
onMounted(() => desktopQuery.addEventListener("change", onBreakpointChange))
onBeforeUnmount(() => desktopQuery.removeEventListener("change", onBreakpointChange))

const isLinkCopied = ref(false)
const copyTicketLink = async () => {
    await navigator.clipboard.writeText(route("grp.tickets.show", props.ticket.reference))
    isLinkCopied.value = true
    setTimeout(() => { isLinkCopied.value = false }, 2000)
}

const props = defineProps<{
    pageHead: any
    title: string
    ticket: any
    comments: any[]
    timeline: { at: string; icon: string; text: string; by: string | null; change?: { label: string; from: string; to: string } | null }[]
    can_rate: boolean
    can_manage: boolean
    can_assign: boolean
    can_flag_confidential: boolean
    can_qa: boolean
    can_claim_qa?: boolean
    qa_held_by_another?: boolean
    can_request_qa: boolean
    is_reporter: boolean
    can_comment_internally: boolean
    can_edit_content?: boolean
    can_change_kind_module: boolean
    can_change_project?: boolean
    can_update: boolean
    can_cancel_as_reporter: boolean
    can_reopen_as_reporter: boolean
    can_contribute: boolean
    can_manage_collaborators: boolean
    can_preview_attachments: boolean
    comments_newest_first: boolean
    history_newest_first: boolean
    attachment_gallery: any[]
    options: {
        statuses: { label: string; value: string }[]
        priorities: { label: string; value: string }[]
        assignees: { label: string; value: number }[]
        qa_users: { label: string; value: number; avatar: any }[]
        mentionable: { username: string; name: string | null; suggested?: boolean; is_customer?: boolean }[]
        tags: string[]
        kinds: { label: string; value: string }[]
        modules: { label: string; value: string }[]
    }
    routes: {
        update: { name: string; parameters: Record<string, unknown> }
        content?: { name: string; parameters: Record<string, unknown> }
        comment: { name: string; parameters: Record<string, unknown> }
        rate: { name: string; parameters: Record<string, unknown> }
        project?: { name: string; parameters: Record<string, unknown> }
        pull_request: { name: string; parameters: Record<string, unknown> }
        pull_request_update: { name: string; parameters: Record<string, unknown> }
        link_store: { name: string; parameters: Record<string, unknown> }
        link_search: { name: string; parameters: Record<string, unknown> }
    }
    can_link: boolean
    links: any[]
    link_types: { value: string; label: string }[]
    linked_ticket_types: { value: string; label: string }[]
}>()

useLiveTickets([], props.ticket.reference, undefined, props.ticket.id)

const saveTicketOrderSetting = (setting: "ticket_comments_newest_first" | "ticket_history_newest_first", isNewestFirst: boolean) => {
    axios.patch(route("grp.models.profile.update"), { [setting]: isNewestFirst })
}

const readPanelState = (key: string) => {
    try {
        return localStorage.getItem(key) !== "closed"
    } catch {
        return true
    }
}

const isHistoryOpen = ref(readPanelState("ticket_history_open"))
const similarQuickLook = ref<{ id: number; reference: string } | null>(null)
const isCreatingLinked = ref(false)

const rememberPanelState = (key: string, isOpen: boolean) => {
    try {
        localStorage.setItem(key, isOpen ? "open" : "closed")
    } catch {}
}

const toggleHistory = () => {
    isHistoryOpen.value = !isHistoryOpen.value
    rememberPanelState("ticket_history_open", isHistoryOpen.value)
}


const isHistoryNewestFirst = ref(props.history_newest_first)

const toggleHistoryOrder = () => {
    isHistoryNewestFirst.value = !isHistoryNewestFirst.value
    saveTicketOrderSetting("ticket_history_newest_first", isHistoryNewestFirst.value)
}
const mobileTab = ref<"comments" | "history">("comments")

const mobileTabs = computed(() => [
    { key: "comments" as const, label: ctrans("Comments"), count: props.comments.length },
    { key: "history" as const, label: ctrans("History"), count: props.timeline.length },
])

const sortedTimeline = computed(() => (isHistoryNewestFirst.value ? props.timeline : [...props.timeline].reverse()))

const historyChangeEvent = ref<any | null>(null)




const update = (field: string, value: unknown) => {
    router.patch(route(props.routes.update.name, props.routes.update.parameters), { [field]: value }, { preserveScroll: true })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle>
            <button type="button" v-tooltip="isLinkCopied ? ctrans('Copied') : ctrans('Copy link')" class="text-sm text-gray-400 hover:text-gray-600" @click="copyTicketLink">
                <FontAwesomeIcon :icon="isLinkCopied ? ['fal', 'fa-check'] : ['fal', 'fa-link']" :class="{ 'text-green-500': isLinkCopied }" fixed-width aria-hidden="true" />
            </button>
        </template>
        <template #wrapped-delete>
            <div class="flex w-80 flex-col gap-3 whitespace-nowrap">
                <label v-if="can_flag_confidential" class="flex items-center gap-x-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" :checked="ticket.is_confidential" class="rounded border-gray-300" @change="update('is_confidential', ($event.target as HTMLInputElement).checked)" />
                    {{ ctrans("Confidential") }} <span class="text-xs text-gray-400">({{ ctrans("only reporter and lead engineers") }})</span>
                </label>
                <ModalConfirmationDelete
                    :title="ctrans('Delete :reference?', { reference: ticket.reference })"
                    :description="ctrans('The ticket and its comments will be removed for good.')"
                    :noLabel="ctrans('Yes, delete')"
                    :routeDelete="routes.delete"
                    class="w-full">
                    <template #default="{ changeModel }">
                        <Button type="negative" icon="fal fa-trash-alt" :label="ctrans('Delete ticket')" full @click="changeModel" />
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </PageHeading>
    <div class="p-4 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="min-w-0 lg:col-span-2 space-y-4 pb-[26px] lg:border-r-2 lg:border-gray-300 lg:pr-6">
            <TicketRating :rating="ticket.rating" :rating-comment="ticket.rating_comment" :can-rate="can_rate" :rate-route="routes.rate" />
            <TicketThread tinted-header :ticket="ticket" :content-route="can_edit_content ? routes.content : null" label-reporter-on-mobile:show-comments="isDesktop || mobileTab === 'comments'" :comments="comments" :comment-route="routes.comment" :translate-routes="{ ticket: 'grp.models.ticket.translate', comment: 'grp.models.ticket.comment.translate' }" :can-comment-internally="can_comment_internally" :mentionable="options.mentionable" :comments-newest-first="comments_newest_first" @update:comments-newest-first="saveTicketOrderSetting('ticket_comments_newest_first', $event)">
                <template #card-header-footer>
                    <div id="ticket-card-controls" />
                </template>
                <template v-if="is_reporter" #subject-actions>
                    <button
                        type="button"
                        v-tooltip="ticket.reporter_muted ? ctrans('Muted: no sound, mini-modal or email for you on this ticket') : ctrans('Mute this ticket for me')"
                        class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-600"
                        :class="ticket.reporter_muted && '!text-amber-600'"
                        :aria-pressed="ticket.reporter_muted"
                        @click="update('reporter_muted', !ticket.reporter_muted)">
                        <FontAwesomeIcon :icon="ticket.reporter_muted ? 'fal fa-bell-slash' : 'fal fa-bell'" fixed-width aria-hidden="true" />
                    </button>
                </template>
                <template #after-description>
                    <div id="ticket-mobile-pull-request" class="lg:hidden" />
                    <TicketChatDropdown v-if="ticket.source?.has_conversation" :ticketId="ticket.id" :source="ticket.source" />
                    <TicketAttachmentList :files="attachment_gallery" :preview-blocked="can_preview_attachments === false" />
                    <div id="ticket-mobile-similar" class="lg:hidden" />
                </template>
                <template #before-comments>
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
                            <span class="rounded bg-gray-100 px-1.5 text-[11px] tabular-nums text-gray-600">{{ tab.count }}</span>
                        </button>
                    </div>
                    <div v-show="mobileTab === 'history'" id="ticket-mobile-history" class="lg:hidden" />
                </template>
            </TicketThread>
        </div>
        <div class="min-w-0 space-y-4 self-start max-lg:hidden lg:sticky lg:top-[60px] lg:max-h-[calc(100vh-60px-26px-2rem)] lg:overflow-y-auto lg:pb-12 [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
        <Teleport defer to="#ticket-card-controls" :disabled="isDesktop">
        <TicketControlPanel :ticket="ticket" :storage-key="isDesktop ? 'ticket_controls_open' : 'ticket_controls_open_mobile'" :default-open="isDesktop" :embedded="!isDesktop">
            <TicketControls :ticket="ticket" :options="options" :can_manage="can_manage" :can_assign="can_assign" :can_flag_confidential="can_flag_confidential" :can_qa="can_qa" :can_claim_qa="can_claim_qa" :qa_held_by_another="qa_held_by_another" :can_request_qa="can_request_qa" :is_reporter="is_reporter" :can_cancel_as_reporter="can_cancel_as_reporter" :can_reopen_as_reporter="can_reopen_as_reporter" :can_change_kind_module="can_change_kind_module" :can_change_project="can_change_project" :can_update="can_update" :can_contribute="can_contribute" :can_manage_collaborators="can_manage_collaborators" :routes="routes" hide-confidential />
            <dl class="space-y-1 text-gray-600">
                <div v-if="ticket.parent" class="flex justify-between"><dt>{{ ctrans("Escalated from") }}</dt><dd><Link :href="ticketRoute(ticket.parent)" class="text-blue-600 hover:underline">{{ ticket.parent }}</Link></dd></div>
                <div v-if="ticket.escalations.length" class="flex justify-between"><dt>{{ ctrans("Escalated to") }}</dt><dd class="space-x-1"><Link v-for="ref in ticket.escalations" :key="ref" :href="ticketRoute(ref)" class="text-blue-600 hover:underline">{{ ref }}</Link></dd></div>
                <div v-if="ticket.customer" class="flex justify-between"><dt>{{ ctrans("Customer") }}</dt><dd>{{ ticket.customer }}</dd></div>
                <div v-if="ticket.shop" class="flex justify-between"><dt>{{ ctrans("Shop") }}</dt><dd>{{ ticket.shop }}</dd></div>
            </dl>
        </TicketControlPanel>
        </Teleport>
        <Teleport defer to="#ticket-mobile-pull-request" :disabled="isDesktop">
            <TicketPullRequest :ticket="ticket" :routes="routes" :can-edit="can_contribute" />
        </Teleport>
        <Teleport defer to="#ticket-mobile-similar" :disabled="isDesktop">
            <div class="space-y-4">
                <TicketLinks
                    :links="links"
                    :link-types="link_types"
                    :can-link="can_link"
                    :store-route="routes.link_store"
                    :search-route="routes.link_search"
                    @preview="(linked) => (similarQuickLook = linked)"
                    @create-linked="isCreatingLinked = true" />
                <TicketSimilar :ticket-id="ticket.id" @preview="(similar) => (similarQuickLook = similar)" />
            </div>
        </Teleport>
        <Teleport defer to="#ticket-mobile-history" :disabled="isDesktop">
        <div class="overflow-hidden bg-white rounded-lg border border-gray-300 text-sm">
            <button type="button" class="flex w-full items-center justify-between gap-3 p-4 text-left text-xs text-gray-500 transition duration-200 hover:bg-gray-50" @click="toggleHistory">
                <span class="font-medium uppercase tracking-wide text-gray-400">{{ ctrans("History") }}</span>
                <span class="flex shrink-0 items-center gap-3">
                    <span v-if="isHistoryOpen && timeline.length > 1" class="px-1 py-0.5 hover:text-gray-900" :title="ctrans('Sort history')" @click.stop="toggleHistoryOrder">
                        {{ isHistoryNewestFirst ? "↓" : "↑" }} {{ isHistoryNewestFirst ? ctrans("Newest first") : ctrans("Oldest first") }}
                    </span>
                    <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isHistoryOpen && '-rotate-90'" />
                </span>
            </button>
            <ol v-show="isHistoryOpen" class="relative ml-6 mr-4 mb-4 border-l border-gray-200">
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
        </div>
        <HistoryChangeModal :event="historyChangeEvent" @close="historyChangeEvent = null" />
        </Teleport>
        </div>
    </div>
    <TicketQuickLook v-model:ticket="similarQuickLook" />
    <Dialog
        :visible="isCreatingLinked"
        modal
        :header="ctrans('New ticket linked to :reference', { reference: ticket.reference })"
        :style="{ width: '64rem' }"
        :breakpoints="{ '1024px': '95vw' }"
        @update:visible="(visible) => !visible && (isCreatingLinked = false)">
        <TicketForm
            stay
            :store-route="{ name: 'grp.models.ticket.store' }"
            :priorities="options.priorities"
            :kinds="options.kinds"
            :modules="options.modules"
            :types="linked_ticket_types"
            :link-from="{ id: ticket.id, reference: ticket.reference, subject: ticket.subject, types: link_types }"
            @created="isCreatingLinked = false" />
    </Dialog>
</template>
