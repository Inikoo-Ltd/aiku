<!--
 Author Louis Perez
 Created on 15-09-2026-10h-09m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ticketRoute } from "@/Composables/useTicketsRoute"
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import axios from "axios"
import Icon from "@/Components/Icon.vue"
import TicketControls from "@/Components/Tickets/TicketControls.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import TicketSimilar from "@/Components/Tickets/TicketSimilar.vue"
import TicketLinks from "@/Components/Tickets/TicketLinks.vue"
import TicketForm from "@/Components/Tickets/TicketForm.vue"
import TicketBody from "@/Components/Tickets/TicketBody.vue"
import TicketUserHoverCard from "@/Components/Tickets/TicketUserHoverCard.vue"
import TicketControlPanel from "@/Components/Tickets/TicketControlPanel.vue"
import TicketPullRequest from "@/Components/Tickets/TicketPullRequest.vue"
import TicketChatDropdown from "@/Components/Tickets/TicketChatDropdown.vue"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faSpinner, faChevronDown, faLink, faCheck, faLifeRing, faToolbox, faUserHeadset, faComment, faComments, faEnvelope, faCommentDots, faCircle, faUserCheck, faClock, faRocket, faCheckCircle, faBan, faPencil, faArrowLeft } from "@fal"
import { editContentUrl } from "@/Composables/useEditContentUrl"
import { faWhatsapp } from "@fortawesome/free-brands-svg-icons"

library.add(faArrowLeft, faPencil, faLifeRing,faToolbox, faUserHeadset, faTimes, faSpinner, faChevronDown, faLink, faCheck, faWhatsapp, faComment, faComments, faEnvelope, faCommentDots, faCircle, faUserCheck, faClock, faRocket, faCheckCircle, faBan)

const emit = defineEmits<{
    (e: "closed"): void
}>()

const ticket = defineModel<any | null>("ticket", { default: null })

const controls = ref<any | null>(null)
const overlay = ref<HTMLElement | null>(null)

useModalFocusTrap(computed(() => Boolean(ticket.value)), overlay)
let stopReloadingAfterSaves: (() => void) | null = null
const isControlsUnavailable = ref(false)
const displayTicket = computed(() => controls.value?.ticket ?? ticket.value)

const shortDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString([], { day: "numeric", month: "short" }) : ""

const isLinkCopied = ref(false)

const copyTicketLink = async () => {
    await navigator.clipboard.writeText(route("grp.tickets.show", displayTicket.value.reference))
    isLinkCopied.value = true
    setTimeout(() => (isLinkCopied.value = false), 2000)
}

const thread = ref<InstanceType<typeof TicketThread> | null>(null)

const isTicketClosed = computed(() => ["resolved", "cancelled"].includes(displayTicket.value?.status))

const loadControls = async (ticketId: number) => {
    isControlsUnavailable.value = false
    try {
        const { data } = await axios.get(route("grp.json.ticket.controls", { ticket: ticketId }))
        if (ticket.value?.id === ticketId) controls.value = data
    } catch {
        if (ticket.value?.id === ticketId) isControlsUnavailable.value = true
    }
}

const LIVE_RELOAD_DELAY_MS = 800

let stopListeningForChanges: (() => void) | null = null

const listenForChanges = (ticketId: number) => {
    if (!window.Echo) {
        return
    }

    const channelName = `grp.ticket.${ticketId}`
    let reloadTimer: ReturnType<typeof setTimeout> | undefined

    const onTicketUpdated = () => {
        clearTimeout(reloadTimer)
        reloadTimer = setTimeout(() => {
            if (ticket.value?.id === ticketId) loadControls(ticketId)
        }, LIVE_RELOAD_DELAY_MS)
    }

    window.Echo.private(channelName).listen(".ticket-updated", onTicketUpdated)

    stopListeningForChanges = () => {
        clearTimeout(reloadTimer)
        window.Echo.private(channelName).stopListening(".ticket-updated", onTicketUpdated)
    }
}

watch(
    () => ticket.value?.id,
    (ticketId) => {
        controls.value = null
        stopReloadingAfterSaves?.()
        stopReloadingAfterSaves = null
        stopListeningForChanges?.()
        stopListeningForChanges = null
        if (!ticketId) return
        loadControls(ticketId)
        stopReloadingAfterSaves = router.on("success", () => {
            if (ticket.value?.id === ticketId) loadControls(ticketId)
        })
        listenForChanges(ticketId)
    },
    { immediate: true }
)

const desktopQuery = globalThis.window?.matchMedia?.("(min-width: 1024px)")
const isDesktop = ref(desktopQuery?.matches ?? true)
const onDesktopQueryChange = (event: MediaQueryListEvent) => (isDesktop.value = event.matches)

onMounted(() => desktopQuery?.addEventListener("change", onDesktopQueryChange))

onBeforeUnmount(() => {
    stopReloadingAfterSaves?.()
    stopListeningForChanges?.()
    desktopQuery?.removeEventListener("change", onDesktopQueryChange)
})

const previousTickets = ref<any[]>([])
const creatingLinked = ref(false)

const openSimilar = (similar: { id: number; reference: string }) => {
    creatingLinked.value = false
    previousTickets.value.push(displayTicket.value)
    ticket.value = similar
}

const goBack = () => {
    creatingLinked.value = false
    ticket.value = previousTickets.value.pop() ?? null
}

const onLinkedCreated = () => {
    creatingLinked.value = false
    if (ticket.value?.id) loadControls(ticket.value.id)
}

const close = () => {
    creatingLinked.value = false
    previousTickets.value = []
    ticket.value = null
    emit("closed")
}
</script>

<template>
        <div
            v-if="ticket"
            ref="overlay"
            tabindex="-1"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-x-hidden overscroll-contain bg-black/40 p-2 outline-none sm:p-4"
            @click.self="close">
            <div class="relative w-full min-w-0 max-w-6xl rounded-2xl bg-white p-4 shadow-xl sm:p-6">
                <button
                    type="button"
                    class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow transition duration-200 hover:bg-[--app-accent-soft] hover:text-[--app-accent-strong]"
                    @click="close">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
                <div v-if="!creatingLinked || !controls" class="grid max-h-[80vh] min-w-0 gap-6 overflow-y-auto overflow-x-hidden lg:h-[80vh] lg:grid-cols-3 lg:overflow-hidden [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
                    <div class="flex min-w-0 flex-col lg:col-span-2 lg:min-h-0">
                        <div class="shrink-0 border-b border-gray-100 pb-3">
                            <button
                                v-if="previousTickets.length"
                                type="button"
                                class="mb-2 inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-xs font-medium text-[--app-accent-strong] transition duration-200 hover:bg-[--app-accent-soft]"
                                @click="goBack">
                                <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                                {{ ctrans("Back to :reference", { reference: previousTickets[previousTickets.length - 1].reference }) }}
                            </button>
                            <div class="flex items-center gap-2 text-xs mb-2">
                        <Icon v-if="displayTicket.type_icon" :data="displayTicket.type_icon" class="text-gray-400" />
                        <Link
                            :href="ticketRoute(displayTicket.reference)"
                            class="rounded-full bg-[--app-accent-soft] px-2 py-0.5 font-medium text-[--app-accent-strong] hover:underline"
                            >{{ displayTicket.reference }}</Link
                        >
                        <button
                            v-tooltip="isLinkCopied ? ctrans('Copied') : ctrans('Copy link')"
                            type="button"
                            class="text-gray-400 transition duration-200 hover:text-gray-600 focus:!text-gray-700"
                            @click="copyTicketLink">
                            <FontAwesomeIcon :icon="isLinkCopied ? 'fal fa-check' : 'fal fa-link'" :class="isLinkCopied && 'text-green-500'" fixed-width aria-hidden="true" />
                        </button>
                        <Icon v-if="displayTicket.status_icon" :data="displayTicket.status_icon" />
                        <span class="text-gray-600">{{ displayTicket.status_label }}</span>
                        <Icon v-if="displayTicket.priority_icon" :data="displayTicket.priority_icon" />
                        <span class="text-gray-600">{{ displayTicket.priority_label }}</span>
                        <span v-if="displayTicket.kind_label" class="text-gray-400"
                            >· {{ displayTicket.kind_label }}</span
                        >
                        <span v-if="displayTicket.module_label" class="text-gray-400"
                            >· {{ displayTicket.module_label }}</span
                        >
                    </div>
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <h2 class="text-lg font-semibold leading-snug">{{ displayTicket.subject }}</h2>
                        <Link
                            v-if="controls?.can_edit_content"
                            v-tooltip="ctrans('Edit subject, description and files')"
                            :href="editContentUrl(ticketRoute(displayTicket.reference))"
                            class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-600">
                            <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                        </Link>
                    </div>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 mb-4">
                        <span class="flex flex-wrap items-center gap-1"
                            >{{ ctrans("Raised") }}: {{ shortDate(displayTicket.created_at) }}
                            <template v-if="displayTicket.reporter">
                                ·
                                <TicketUserHoverCard
                                    :name="displayTicket.reporter"
                                    :avatar="displayTicket.reporter_avatar ?? null"
                                    :roles="displayTicket.reporter_roles ?? []"
                                    :username="displayTicket.reporter_username ?? null"
                                    :reporterKey="displayTicket.reporter_key ?? null"
                                    :profileUrl="displayTicket.reporter_profile_url ?? null"
                                    :canMention="!!thread"
                                    size="xs"
                                    class="font-normal"
                                    @mention="(username) => thread?.mentionInReply(username)" />
                            </template></span
                        >
                        <span v-if="displayTicket.assignee"
                            >{{ ctrans("Assignee") }}: {{ displayTicket.assignee }}</span
                        >
                        <span v-if="displayTicket.assigned_at"
                            >{{ ctrans("Assigned") }}: {{ shortDate(displayTicket.assigned_at) }}</span
                        >
                        <span v-if="displayTicket.started_at"
                            >{{ ctrans("Started") }}: {{ shortDate(displayTicket.started_at) }}</span
                        >
                        <span v-if="displayTicket.waiting_at"
                            >{{ ctrans("Waiting since") }}: {{ shortDate(displayTicket.waiting_at) }}</span
                        >
                        <span v-if="isTicketClosed && displayTicket.closed_at"
                            >{{ ctrans("Closed") }}: {{ shortDate(displayTicket.closed_at) }}</span
                        >
                        <span v-if="displayTicket.customer"
                            >{{ ctrans("Customer") }}: {{ displayTicket.customer }}</span
                        >
                        <span v-if="displayTicket.shop">{{ ctrans("Shop") }}: {{ displayTicket.shop }}</span>                    </div>
                    <template v-if="!isDesktop">
                        <TicketControlPanel v-if="controls" :ticket="displayTicket" storage-key="ticket_quick_look_controls_open" :default-open="false">
                            <TicketControls v-bind="controls" @updated="loadControls(ticket.id)">
                                <template #after-qa>
                                    <TicketPullRequest :ticket="controls.ticket" :routes="controls.routes" :can-edit="controls.can_contribute" compact @updated="loadControls(ticket.id)" />
                                </template>
                            </TicketControls>
                        </TicketControlPanel>
                        <p v-else-if="isControlsUnavailable" class="text-sm text-gray-500">{{ ctrans("Controls are unavailable") }}</p>
                        <p v-else class="text-sm text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin class="mr-1" fixed-width />{{ ctrans("Loading") }}</p>
                    </template>
                        </div>
                        <div class="min-w-0 break-words pr-1 pt-3 lg:min-h-0 lg:flex-1 lg:overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
                            <div class="flex min-h-full flex-col">
                                <a v-if="displayTicket.reference_url" :href="displayTicket.reference_url" target="_blank" rel="noopener" class="mb-3 block truncate text-sm text-[--app-accent-strong] hover:underline">{{ displayTicket.reference_url }}</a>
                                <TicketBody v-if="displayTicket.description || displayTicket.images?.length" :text="displayTicket.description" :images="displayTicket.images" />
                                <TicketChatDropdown v-if="displayTicket.source?.has_conversation" :ticketId="displayTicket.id" :source="displayTicket.source" class="mt-3" />
                    <div v-if="controls" class="mt-auto space-y-3 pb-2.5 pt-4">
                        <TicketAttachmentList v-if="controls.attachment_gallery?.length" :files="controls.attachment_gallery" :preview-blocked="controls.can_preview_attachments === false" compact />
                        <TicketLinks
                            v-if="!isDesktop"
                            :links="controls.links ?? []"
                            :link-types="controls.link_types ?? []"
                            :can-link="controls.can_link ?? false"
                            :store-route="controls.routes.link_store"
                            :search-route="controls.routes.link_search"
                            @preview="openSimilar"
                            @create-linked="creatingLinked = true" />
                        <TicketSimilar v-if="!isDesktop" :ticket-id="displayTicket.id" :limit="3" @preview="openSimilar" />
                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-800">{{ ctrans("Comments") }}</span>
                                <span class="rounded bg-gray-100 px-1.5 text-[11px] font-medium tabular-nums text-gray-600">{{ controls.comments?.length ?? 0 }}</span>
                            </div>
                            <div class="mt-2">
                                <TicketThread
                                    ref="thread"
                                    :ticket="controls.ticket"
                                    :comments="controls.comments ?? []"
                                    :comment-route="controls.routes.comment"
                                    :can-comment-internally="controls.can_comment_internally ?? false"
                                    :mentionable="controls.options?.mentionable"
                                    :comments-newest-first="controls.comments_newest_first ?? true"
                                    :show-description="false" />
                            </div>
                        </div>
                    </div>
                            </div>
                        </div>
                    </div>
                    <aside v-if="isDesktop" class="min-w-0 text-sm lg:border-l lg:border-gray-200 lg:pl-6 lg:pr-2 lg:min-h-0 lg:overflow-y-auto [scrollbar-width:thin] [scrollbar-color:theme(colors.gray.300)_transparent]">
                        <TicketControls v-if="controls" v-bind="controls" @updated="loadControls(ticket.id)">
                            <template #after-qa>
                                <TicketPullRequest :ticket="controls.ticket" :routes="controls.routes" :can-edit="controls.can_contribute" compact @updated="loadControls(ticket.id)" />
                            </template>
                        </TicketControls>
                        <p v-else-if="isControlsUnavailable" class="text-gray-500">{{ ctrans("Controls are unavailable") }}</p>
                        <p v-else class="text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin class="mr-1" fixed-width />{{ ctrans("Loading") }}</p>
                        <TicketLinks
                            v-if="controls"
                            flat
                            class="mt-4 border-t border-gray-100 pt-3"
                            :links="controls.links ?? []"
                            :link-types="controls.link_types ?? []"
                            :can-link="controls.can_link ?? false"
                            :store-route="controls.routes.link_store"
                            :search-route="controls.routes.link_search"
                            @preview="openSimilar"
                            @create-linked="creatingLinked = true" />
                        <TicketSimilar flat :ticket-id="displayTicket.id" :limit="3" class="mt-3 border-t border-gray-100 pt-3" @preview="openSimilar" />
                    </aside>
                </div>
                <div v-else class="max-h-[80vh] overflow-y-auto">
                    <button
                        type="button"
                        class="mb-3 inline-flex items-center gap-1.5 rounded px-1.5 py-0.5 text-xs font-medium text-[--app-accent-strong] transition duration-200 hover:bg-[--app-accent-soft]"
                        @click="creatingLinked = false">
                        <FontAwesomeIcon icon="fal fa-arrow-left" fixed-width aria-hidden="true" />
                        {{ ctrans("Back to :reference", { reference: displayTicket.reference }) }}
                    </button>
                    <h2 class="mb-4 text-lg font-semibold">{{ ctrans("New ticket linked to :reference", { reference: displayTicket.reference }) }}</h2>
                    <TicketForm
                        stay
                        :store-route="{ name: 'grp.models.ticket.store' }"
                        :priorities="controls.options.priorities"
                        :kinds="controls.options.kinds"
                        :modules="controls.options.modules"
                        :types="controls.linked_ticket_types"
                        :link-from="{ id: displayTicket.id, reference: displayTicket.reference, subject: displayTicket.subject, types: controls.link_types }"
                        @created="onLinkedCreated" />
                </div>
            </div>
        </div>
</template>
