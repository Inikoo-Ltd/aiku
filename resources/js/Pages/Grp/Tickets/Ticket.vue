<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 03 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ticketRoute } from "@/Composables/useTicketsRoute"
import { ref, computed } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import Icon from "@/Components/Icon.vue"
import TicketControlPanel from "@/Components/Tickets/TicketControlPanel.vue"
import TicketSourceCard from "@/Components/Tickets/TicketSourceCard.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import TicketRating from "@/Components/Tickets/TicketRating.vue"
import TicketControls from "@/Components/Tickets/TicketControls.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useLiveTickets } from "@/Composables/useLiveTickets"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus, faPlusCircle, faExchange, faHourglassHalf, faVial, faShieldCheck, faShield, faRocket, faUsers, faLink, faLifeRing, faToolbox, faUserHeadset, faBooks, faDatabase, faTasks, faChevronDown, faComment, faComments, faEnvelope, faCommentDots } from "@fal"

library.add(faWhatsapp, faComment, faComments, faEnvelope, faBooks, faDatabase, faTasks, faChevronDown, faLifeRing, faToolbox, faUserHeadset, faLink, faUsers, faRocket, faVial, faShieldCheck, faShield, faHourglassHalf, faPlusCircle, faExchange, faEllipsisV, faTrashAlt, faUser, faPencil, faTimes, faPlus,faPaperclip, faCircle, faUserCheck, faSpinner, faClock, faCheckCircle, faBan, faPlay, faPause, faStop, faCheck, faUndo, faBug, faLightbulb, faLevelUp, faCube, faQuestionCircle, faCommentDots)

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
    timeline: { at: string; icon: string; text: string; by: string | null }[]
    can_rate: boolean
    can_manage: boolean
    can_assign: boolean
    can_flag_confidential: boolean
    can_qa: boolean
    is_reporter: boolean
    can_comment_internally: boolean
    can_change_kind_module: boolean
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
        comment: { name: string; parameters: Record<string, unknown> }
        rate: { name: string; parameters: Record<string, unknown> }
    }
}>()

useLiveTickets(["ticket", "comments", "timeline", "can_rate", "can_manage", "can_assign", "can_flag_confidential", "can_qa", "is_reporter", "can_cancel_as_reporter", "can_reopen_as_reporter", "can_comment_internally", "can_change_kind_module", "can_update", "can_contribute", "can_manage_collaborators", "can_preview_attachments", "attachment_gallery"], props.ticket.reference)

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
const sortedTimeline = computed(() => (isHistoryNewestFirst.value ? props.timeline : [...props.timeline].reverse()))




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
    <div class="p-4 grid gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            <TicketRating :rating="ticket.rating" :rating-comment="ticket.rating_comment" :can-rate="can_rate" :rate-route="routes.rate" />
            <TicketThread :ticket="ticket" :comments="comments" :comment-route="routes.comment" :can-comment-internally="can_comment_internally" :mentionable="options.mentionable" :comments-newest-first="comments_newest_first" @update:comments-newest-first="saveTicketOrderSetting('ticket_comments_newest_first', $event)">
                <template #after-description>
                    <TicketAttachmentList :files="attachment_gallery" :preview-blocked="can_preview_attachments === false" />
                </template>
            </TicketThread>
        </div>
        <div class="space-y-4 self-start lg:sticky lg:top-[60px] lg:max-h-[calc(100vh-60px-2rem)] lg:overflow-y-auto">
        <TicketControlPanel :ticket="ticket" storage-key="ticket_controls_open">
            <TicketControls :ticket="ticket" :options="options" :can_manage="can_manage" :can_assign="can_assign" :can_flag_confidential="can_flag_confidential" :can_qa="can_qa" :is_reporter="is_reporter" :can_cancel_as_reporter="can_cancel_as_reporter" :can_reopen_as_reporter="can_reopen_as_reporter" :can_change_kind_module="can_change_kind_module" :can_update="can_update" :can_contribute="can_contribute" :can_manage_collaborators="can_manage_collaborators" :routes="routes" hide-confidential />
            <div v-if="ticket.commits?.length">
                <p class="text-xs text-gray-500 mb-1">{{ ctrans("Commits") }}</p>
                <ul class="space-y-1 text-xs">
                    <li v-for="commit in ticket.commits" :key="commit.hash">
                        <a v-if="commit.url" :href="commit.url" target="_blank" class="font-mono text-blue-600 hover:underline">{{ commit.hash.slice(0, 8) }}</a>
                        <span v-else class="font-mono">{{ commit.hash.slice(0, 8) }}</span>
                        <span class="text-gray-600"> {{ commit.subject }}</span>
                        <span v-if="commit.version || commit.deployed_at" class="text-gray-400"> · {{ commit.version || ctrans("deployed") }} {{ commit.deployed_at ? new Date(commit.deployed_at).toLocaleDateString() : "" }}</span>
                    </li>
                </ul>
            </div>
            <TicketSourceCard v-if="ticket.source" :source="ticket.source" />
            <dl class="space-y-1 text-gray-600">
                <div v-if="ticket.parent" class="flex justify-between"><dt>{{ ctrans("Escalated from") }}</dt><dd><Link :href="ticketRoute(ticket.parent)" class="text-blue-600 hover:underline">{{ ticket.parent }}</Link></dd></div>
                <div v-if="ticket.escalations.length" class="flex justify-between"><dt>{{ ctrans("Escalated to") }}</dt><dd class="space-x-1"><Link v-for="ref in ticket.escalations" :key="ref" :href="ticketRoute(ref)" class="text-blue-600 hover:underline">{{ ref }}</Link></dd></div>
                <div v-if="ticket.customer" class="flex justify-between"><dt>{{ ctrans("Customer") }}</dt><dd>{{ ticket.customer }}</dd></div>
                <div v-if="ticket.shop" class="flex justify-between"><dt>{{ ctrans("Shop") }}</dt><dd>{{ ticket.shop }}</dd></div>
            </dl>
        </TicketControlPanel>
        <div class="bg-white rounded-lg border border-gray-300 text-sm">
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
                    <p class="text-gray-800">{{ event.text }}</p>
                    <p class="text-xs text-gray-400">
                        {{ useFormatTime(event.at, { formatTime: "hm" }) }}<template v-if="event.by"> · {{ event.by }}</template>
                    </p>
                </li>
            </ol>
        </div>
        </div>
    </div>
</template>
