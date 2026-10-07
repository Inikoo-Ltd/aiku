<!--
 Author Louis Perez
 Created on 17-09-2026-13h-23m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { ticketRoute } from "@/Composables/useTicketsRoute"
import Icon from "@/Components/Icon.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faSyncAlt } from "@fal"

library.add(faChevronDown, faSyncAlt)

const props = withDefaults(defineProps<{
    ticketId: number
    collapsible?: boolean
    limit?: number | null
    flat?: boolean
}>(), { collapsible: true, limit: null, flat: false })

type SimilarTicket = { id: number; reference: string; subject: string; status_label: string; status_icon: any; type_icon: any }

const emit = defineEmits<{
    (e: "preview", ticket: SimilarTicket): void
}>()

const isOpen = ref(!props.collapsible)
const similarTickets = ref<SimilarTicket[]>([])
const isLoading = ref(false)
const hasFailed = ref(false)
const hasLoaded = ref(false)

const load = async (ticketId: number, isRefresh = false) => {
    isLoading.value = true
    hasFailed.value = false
    try {
        const { data } = await axios.get(route("grp.json.ticket.similar", { ticket: ticketId, ...(isRefresh ? { refresh: 1 } : {}) }))
        if (ticketId !== props.ticketId) return
        similarTickets.value = data ?? []
        hasLoaded.value = true
    } catch {
        if (ticketId === props.ticketId) hasFailed.value = true
    } finally {
        if (ticketId === props.ticketId) isLoading.value = false
    }
}

const loadCached = async (ticketId: number) => {
    try {
        const { data } = await axios.get(route("grp.json.ticket.similar", { ticket: ticketId, cached_only: 1 }))
        if (ticketId !== props.ticketId || !Array.isArray(data)) return
        similarTickets.value = data
        hasLoaded.value = true
        if (data.length) isOpen.value = true
    } catch {}
}

const toggle = () => {
    isOpen.value = !isOpen.value
    if (isOpen.value && !hasLoaded.value && !isLoading.value) load(props.ticketId)
}

watch(() => props.ticketId, (ticketId) => {
    similarTickets.value = []
    hasLoaded.value = false
    hasFailed.value = false
    isOpen.value = !props.collapsible
    if (!ticketId) return
    if (props.collapsible) loadCached(ticketId)
    else load(ticketId)
}, { immediate: true })

const refresh = () => {
    isOpen.value = true
    load(props.ticketId, true)
}

const shown = () => (props.limit ? similarTickets.value.slice(0, props.limit) : similarTickets.value)
</script>

<template>
    <div class="text-sm" :class="!flat && 'overflow-hidden rounded-lg border border-gray-300 bg-white'">
        <div
            :role="collapsible ? 'button' : undefined"
            :tabindex="collapsible ? 0 : undefined"
            :aria-expanded="collapsible ? isOpen : undefined"
            class="flex w-full items-center justify-between gap-3 text-left text-xs text-gray-500"
            :class="[
                flat ? 'py-1' : 'rounded-t-lg p-4',
                collapsible && 'cursor-pointer transition duration-200',
                collapsible && (flat ? 'hover:text-gray-700' : 'hover:bg-gray-50'),
            ]"
            @click="collapsible && toggle()"
            @keydown.enter.prevent="collapsible && toggle()">
            <span class="font-medium uppercase tracking-wide text-gray-400">
                {{ ctrans("Similar tickets") }}
                <span v-if="!isLoading && similarTickets.length" class="ml-1 rounded bg-gray-100 px-1.5 text-[11px] normal-case tabular-nums text-gray-600">{{ similarTickets.length }}</span>
            </span>
            <span class="flex shrink-0 items-center gap-2">
                <button
                    type="button"
                    v-tooltip="ctrans('Look for similar tickets again')"
                    :aria-label="ctrans('Look for similar tickets again')"
                    :disabled="isLoading"
                    class="rounded p-0.5 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-wait"
                    @click.stop="refresh"
                    @keydown.enter.stop>
                    <FontAwesomeIcon icon="fal fa-sync-alt" fixed-width :spin="isLoading" />
                </button>
                <FontAwesomeIcon v-if="collapsible" icon="fal fa-chevron-down" fixed-width class="text-gray-400 transition-transform duration-200" :class="!isOpen && '-rotate-90'" />
            </span>
        </div>

        <div v-show="isOpen" :class="flat ? '-mx-2 pt-1' : 'px-2 pb-3 pt-2'">
            <div v-if="isLoading" class="space-y-2 px-2">
                <div v-for="placeholder in 3" :key="placeholder" class="h-7 animate-pulse rounded bg-gray-100" />
            </div>
            <p v-else-if="hasFailed" class="px-2 text-xs text-gray-400">{{ ctrans("Similar tickets could not be loaded") }}</p>
            <p v-else-if="!similarTickets.length" class="px-2 text-xs text-gray-400">{{ ctrans("No similar tickets found") }}</p>
            <ul v-else class="space-y-0.5">
                <li v-for="similar in shown()" :key="similar.id">
                    <div
                        role="button"
                        tabindex="0"
                        class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 transition duration-200 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                        @click="emit('preview', similar)"
                        @keydown.enter.prevent="emit('preview', similar)">
                        <Icon v-if="similar.type_icon" :data="similar.type_icon" class="shrink-0 text-gray-400" />
                        <a :href="ticketRoute(similar.reference)" target="_blank" rel="noopener" class="shrink-0 font-medium text-[--app-accent-strong] hover:underline" @click.stop>{{ similar.reference }}</a>
                        <span class="min-w-0 flex-1 truncate text-gray-700">{{ similar.subject }}</span>
                        <span v-tooltip="similar.status_label" class="shrink-0"><Icon :data="similar.status_icon" /></span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>
