<!--
 Author Louis Perez
 Created on 07-10-2026-13h-00m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { Link, router } from "@inertiajs/vue3"
import { AutoComplete, Select } from "primevue"
import { notify } from "@kyvg/vue3-notification"
import { ctrans } from "@/Composables/useTrans"
import { ticketRoute } from "@/Composables/useTicketsRoute"
import Icon from "@/Components/Icon.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faLink, faPlus, faTimes, faSpinner } from "@fal"

library.add(faChevronDown, faLink, faPlus, faTimes, faSpinner)

type RouteRef = { name: string; parameters: Record<string, unknown> }
type TicketLinkRow = { id: number; label: string; ticket_id: number; reference: string; subject: string; status_label: string; status_icon: any; type_icon: any; delete_route: RouteRef }
type FoundTicket = { id: number; reference: string; subject: string; status_label: string; status_icon: any; type_icon: any }

const props = defineProps<{
    links: TicketLinkRow[]
    linkTypes: { value: string; label: string }[]
    canLink: boolean
    storeRoute: RouteRef
    searchRoute: RouteRef
    flat?: boolean
}>()

const emit = defineEmits<{
    (e: "preview", ticket: { id: number; reference: string }): void
    (e: "create-linked"): void
}>()

const STORAGE_KEY = "ticket_links_open"
const readOpenState = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) !== "closed"
    } catch {
        return true
    }
}

const isOpen = ref(readOpenState())
const toggle = () => {
    isOpen.value = !isOpen.value
    try {
        localStorage.setItem(STORAGE_KEY, isOpen.value ? "open" : "closed")
    } catch {}
}

const groups = computed(() => {
    const byLabel = new Map<string, TicketLinkRow[]>()
    props.links.forEach((link) => byLabel.set(link.label, [...(byLabel.get(link.label) ?? []), link]))
    return [...byLabel.entries()].map(([label, rows]) => ({ label, rows }))
})

const isAdding = ref(false)
const linkType = ref("relates")
const selected = ref<FoundTicket | null>(null)
const suggestions = ref<FoundTicket[]>([])
const isSaving = ref(false)
const removingId = ref<number | null>(null)

const searchTickets = async (event: { query: string }) => {
    try {
        const { data } = await axios.get(route(props.searchRoute.name, { ...props.searchRoute.parameters, search: event.query }))
        suggestions.value = data ?? []
    } catch {
        suggestions.value = []
    }
}

const openAdd = () => {
    isAdding.value = true
    if (!isOpen.value) toggle()
}

const cancelAdd = () => {
    isAdding.value = false
    selected.value = null
    linkType.value = "relates"
}

const saveLink = () => {
    if (!selected.value || typeof selected.value !== "object") return
    router.post(route(props.storeRoute.name, props.storeRoute.parameters), { linked_ticket_id: selected.value.id, type: linkType.value }, {
        preserveScroll: true,
        onStart: () => (isSaving.value = true),
        onSuccess: () => {
            notify({ title: ctrans("Linked"), text: selected.value?.reference, type: "success" })
            cancelAdd()
        },
        onError: (errors) => notify({ title: ctrans("Could not link the ticket"), text: Object.values(errors)[0] as string, type: "error" }),
        onFinish: () => (isSaving.value = false),
    })
}

const removeLink = (link: TicketLinkRow) => {
    router.delete(route(link.delete_route.name, link.delete_route.parameters), {
        preserveScroll: true,
        onStart: () => (removingId.value = link.id),
        onError: () => notify({ title: ctrans("Could not remove the link"), type: "error" }),
        onFinish: () => (removingId.value = null),
    })
}
</script>

<template>
    <div v-if="links.length || canLink" class="text-sm" :class="!flat && 'overflow-hidden rounded-lg border border-gray-300 bg-white'">
        <div
            role="button"
            tabindex="0"
            :aria-expanded="isOpen"
            class="flex w-full cursor-pointer items-center justify-between gap-3 text-left text-xs text-gray-500" :class="flat ? 'py-1' : 'rounded-t-lg p-4 transition duration-200 hover:bg-gray-50'"
            @click="toggle"
            @keydown.enter.prevent="toggle">
            <span class="font-medium uppercase tracking-wide text-gray-400">
                {{ ctrans("Linked tickets") }}
                <span v-if="links.length" class="ml-1 rounded bg-gray-100 px-1.5 text-[11px] normal-case tabular-nums text-gray-600">{{ links.length }}</span>
            </span>
            <span class="flex shrink-0 items-center gap-2">
                <button
                    v-if="canLink"
                    type="button"
                    v-tooltip="ctrans('Link a ticket')"
                    :aria-label="ctrans('Link a ticket')"
                    class="rounded p-0.5 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-700"
                    @click.stop="openAdd"
                    @keydown.enter.stop>
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width />
                </button>
                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isOpen && '-rotate-90'" />
            </span>
        </div>

        <div v-show="isOpen" class="space-y-3" :class="flat ? '-mx-2 pt-1' : 'px-2 pb-3 pt-2'">
            <div v-if="isAdding" class="space-y-2 rounded-md border border-gray-200 bg-gray-50 p-2">
                <div class="flex gap-2">
                    <Select v-model="linkType" :options="linkTypes" option-label="label" option-value="value" size="small" class="w-36 shrink-0" />
                    <AutoComplete
                        v-model="selected"
                        :suggestions="suggestions"
                        option-label="reference"
                        :delay="300"
                        :placeholder="ctrans('Reference or words')"
                        size="small"
                        class="min-w-0 flex-1"
                        input-class="w-full"
                        @complete="searchTickets">
                        <template #option="{ option }">
                            <span class="flex min-w-0 items-center gap-2 text-sm">
                                <Icon v-if="option.type_icon" :data="option.type_icon" class="shrink-0 text-gray-400" />
                                <span class="shrink-0 font-medium">{{ option.reference }}</span>
                                <span class="min-w-0 max-w-[16rem] truncate text-gray-600">{{ option.subject }}</span>
                            </span>
                        </template>
                    </AutoComplete>
                </div>
                <div class="flex items-center justify-between gap-2">
                    <button type="button" class="text-xs text-[--app-accent-strong] hover:underline" @click="emit('create-linked')">
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width class="mr-0.5" />{{ ctrans("Create linked ticket") }}
                    </button>
                    <span class="flex gap-2">
                        <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="cancelAdd" />
                        <Button type="primary" size="xs" :label="ctrans('Link')" :loading="isSaving" :disabled="!selected || typeof selected !== 'object'" @click="saveLink" />
                    </span>
                </div>
            </div>

            <p v-if="!links.length && !isAdding" class="px-2 text-xs text-gray-400">{{ ctrans("No linked tickets") }}</p>

            <div v-for="group in groups" :key="group.label">
                <p class="px-2 pb-1 text-xs text-gray-500">{{ group.label }}</p>
                <ul class="space-y-0.5">
                    <li v-for="link in group.rows" :key="link.id">
                        <div
                            role="button"
                            tabindex="0"
                            class="group/link flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 transition duration-200 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                            :class="removingId === link.id && 'opacity-50'"
                            @click="emit('preview', { id: link.ticket_id, reference: link.reference })"
                            @keydown.enter.prevent="emit('preview', { id: link.ticket_id, reference: link.reference })">
                            <Icon v-if="link.type_icon" :data="link.type_icon" class="shrink-0 text-gray-400" />
                            <a :href="ticketRoute(link.reference)" target="_blank" rel="noopener" class="shrink-0 font-medium text-[--app-accent-strong] hover:underline" @click.stop>{{ link.reference }}</a>
                            <span class="min-w-0 flex-1 truncate text-gray-700">{{ link.subject }}</span>
                            <span v-tooltip="link.status_label" class="shrink-0"><Icon :data="link.status_icon" /></span>
                            <button
                                v-if="canLink"
                                type="button"
                                v-tooltip="ctrans('Remove link')"
                                :aria-label="ctrans('Remove link')"
                                :disabled="removingId === link.id"
                                class="shrink-0 rounded p-0.5 text-gray-300 opacity-0 transition duration-200 hover:bg-red-50 hover:text-red-600 focus:opacity-100 group-hover/link:opacity-100"
                                @click.stop="removeLink(link)">
                                <FontAwesomeIcon :icon="removingId === link.id ? 'fal fa-spinner' : 'fal fa-times'" :spin="removingId === link.id" fixed-width />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
