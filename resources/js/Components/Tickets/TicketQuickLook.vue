<!--
 Author Louis Perez
 Created on 15-09-2026-10h-09m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import axios from "axios"
import Icon from "@/Components/Icon.vue"
import TicketControls from "@/Components/Tickets/TicketControls.vue"
import TicketAttachmentList from "@/Components/Tickets/TicketAttachmentList.vue"
import TicketThread from "@/Components/Tickets/TicketThread.vue"
import { useModalFocusTrap } from "@/Composables/useModalFocusTrap"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTimes, faSpinner, faChevronDown, faLink, faCheck, faLifeRing, faCode, faUserHeadset } from "@fal"

library.add(faLifeRing, faCode, faUserHeadset, faTimes, faSpinner, faChevronDown, faLink, faCheck)

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

const roleClasses: Record<string, string> = {
    lead_engineer: "bg-red-100 text-red-700",
    engineer: "bg-blue-100 text-blue-700",
    qa: "bg-purple-100 text-purple-700",
    staff: "bg-gray-100 text-gray-600",
    customer: "bg-slate-200 text-slate-700",
}

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

watch(
    () => ticket.value?.id,
    (ticketId) => {
        controls.value = null
        stopReloadingAfterSaves?.()
        stopReloadingAfterSaves = null
        if (!ticketId) return
        loadControls(ticketId)
        stopReloadingAfterSaves = router.on("success", () => {
            if (ticket.value?.id === ticketId) loadControls(ticketId)
        })
    },
    { immediate: true }
)

onBeforeUnmount(() => stopReloadingAfterSaves?.())

const close = () => {
    ticket.value = null
    emit("closed")
}
</script>

<template>
        <div
            v-if="ticket"
            ref="overlay"
            tabindex="-1"
            class="fixed inset-0 z-50 flex items-center justify-center overscroll-contain bg-black/40 p-4 outline-none"
            @click.self="close">
            <div class="relative w-full max-w-6xl rounded-2xl bg-white p-6 shadow-xl">
                <button
                    type="button"
                    class="absolute -right-3 -top-3 z-10 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-500 shadow hover:text-gray-800"
                    @click="close">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width />
                </button>
                <div class="grid max-h-[80vh] gap-6 overflow-y-auto lg:h-[80vh] lg:grid-cols-3 lg:overflow-hidden">
                    <div class="flex flex-col lg:col-span-2 lg:min-h-0">
                        <div class="shrink-0 border-b border-gray-100 pb-3">
                            <div class="flex items-center gap-2 text-xs mb-2">
                        <Icon v-if="displayTicket.type_icon" :data="displayTicket.type_icon" class="text-gray-400" />
                        <Link
                            :href="route('grp.tickets.show', displayTicket.reference)"
                            class="primaryLink font-medium"
                            >{{ displayTicket.reference }}</Link
                        >
                        <button
                            v-tooltip="isLinkCopied ? trans('Copied') : trans('Copy link')"
                            type="button"
                            class="text-gray-400 transition duration-200 hover:text-gray-600 focus:!text-gray-700"
                            @click="copyTicketLink">
                            <FontAwesomeIcon :icon="isLinkCopied ? 'fal fa-check' : 'fal fa-link'" :class="isLinkCopied && 'text-green-500'" fixed-width aria-hidden="true" />
                        </button>
                        <Icon :data="displayTicket.status_icon" />
                        <span class="text-gray-600">{{ displayTicket.status_label }}</span>
                        <Icon :data="displayTicket.priority_icon" />
                        <span class="text-gray-600">{{ displayTicket.priority_label }}</span>
                        <span v-if="displayTicket.kind_label" class="text-gray-400"
                            >· {{ displayTicket.kind_label }}</span
                        >
                        <span v-if="displayTicket.module_label" class="text-gray-400"
                            >· {{ displayTicket.module_label }}</span
                        >
                    </div>
                    <h2 class="text-lg font-semibold leading-snug mb-3">{{ displayTicket.subject }}</h2>
                    <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600 mb-4">
                        <span class="flex flex-wrap items-center gap-1"
                            >{{ trans("Raised") }}: {{ shortDate(displayTicket.created_at) }}
                            {{ displayTicket.reporter ? "· " + displayTicket.reporter : "" }}
                            <span
                                v-for="role in displayTicket.reporter_roles ?? []"
                                :key="role.key"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="roleClasses[role.key] ?? 'bg-gray-100 text-gray-600'"
                                >{{ role.label }}</span
                            ></span
                        >
                        <span v-if="displayTicket.assignee"
                            >{{ trans("Assignee") }}: {{ displayTicket.assignee }}</span
                        >
                        <span v-if="displayTicket.assigned_at"
                            >{{ trans("Assigned") }}: {{ shortDate(displayTicket.assigned_at) }}</span
                        >
                        <span v-if="displayTicket.started_at"
                            >{{ trans("Started") }}: {{ shortDate(displayTicket.started_at) }}</span
                        >
                        <span v-if="displayTicket.waiting_at"
                            >{{ trans("Waiting since") }}: {{ shortDate(displayTicket.waiting_at) }}</span
                        >
                        <span v-if="isTicketClosed && displayTicket.closed_at"
                            >{{ trans("Closed") }}: {{ shortDate(displayTicket.closed_at) }}</span
                        >
                        <span v-if="displayTicket.customer"
                            >{{ trans("Customer") }}: {{ displayTicket.customer }}</span
                        >
                        <span v-if="displayTicket.shop">{{ trans("Shop") }}: {{ displayTicket.shop }}</span>
                    </div>
                        </div>
                        <div class="lg:min-h-0 lg:flex-1 lg:overflow-y-auto pr-1 pt-3">
                            <div class="flex min-h-full flex-col">
                                <p class="text-sm whitespace-pre-wrap break-words">{{ displayTicket.description }}</p>
                    <div v-if="controls" class="mt-auto space-y-3 pb-2.5 pt-4">
                        <TicketAttachmentList v-if="controls.attachment_gallery?.length" :files="controls.attachment_gallery" :preview-blocked="controls.can_preview_attachments === false" compact />
                        <div class="rounded-lg border border-gray-200 bg-white p-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-800">{{ trans("Comments") }}</span>
                                <span class="rounded bg-gray-100 px-1.5 text-[11px] font-medium tabular-nums text-gray-600">{{ controls.comments?.length ?? 0 }}</span>
                            </div>
                            <div class="mt-2">
                                <TicketThread
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
                    <aside class="text-sm lg:min-h-0 lg:overflow-y-auto lg:border-l lg:border-gray-200 lg:pl-6 lg:pr-1">
                        <TicketControls v-if="controls" v-bind="controls" @updated="loadControls(ticket.id)" />
                        <p v-else-if="isControlsUnavailable" class="text-gray-500">{{ trans("Controls are unavailable") }}</p>
                        <p v-else class="text-gray-400"><FontAwesomeIcon icon="fal fa-spinner" spin class="mr-1" />{{ trans("Loading") }}</p>
                    </aside>
                </div>
            </div>
        </div>
</template>
