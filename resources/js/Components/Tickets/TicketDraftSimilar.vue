<!--
 Author Louis Perez
 Created on 07-10-2026-15h-00m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { ticketRoute } from "@/Composables/useTicketsRoute"
import Icon from "@/Components/Icon.vue"
import TicketQuickLook from "@/Components/Tickets/TicketQuickLook.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSyncAlt, faSpinner, faExternalLink } from "@fal"

library.add(faSyncAlt, faSpinner, faExternalLink)

const props = defineProps<{
    subject: string
    description: string
}>()

type SimilarTicket = { id: number; reference: string; subject: string; status_label: string; status_icon: any; type_icon: any }

const DEBOUNCE_MS = 1000
const MIN_CHARACTERS = 8

const similarTickets = ref<SimilarTicket[]>([])
const quickLook = ref<SimilarTicket | null>(null)
const isSearching = ref(false)
const hasFailed = ref(false)
const hasSearched = ref(false)

const draftText = computed(() => `${props.subject}\n${props.description}`.trim())
const isLongEnough = computed(() => draftText.value.replace(/\s+/g, " ").length >= MIN_CHARACTERS)

let debounceTimer: ReturnType<typeof setTimeout> | undefined
let controller: AbortController | null = null

const search = async (refresh = false) => {
    clearTimeout(debounceTimer)
    controller?.abort()

    if (!isLongEnough.value) {
        similarTickets.value = []
        hasSearched.value = false
        isSearching.value = false
        return
    }

    controller = new AbortController()
    const signal = controller.signal
    isSearching.value = true
    hasFailed.value = false

    try {
        const { data } = await axios.post(
            route("grp.json.ticket.similar_draft"),
            { subject: props.subject, description: props.description, ...(refresh ? { refresh: true } : {}) },
            { signal }
        )
        similarTickets.value = data ?? []
        hasSearched.value = true
    } catch (error) {
        if (axios.isCancel(error)) return
        hasFailed.value = true
    } finally {
        if (!signal.aborted) isSearching.value = false
    }
}

watch(draftText, () => {
    clearTimeout(debounceTimer)
    controller?.abort()
    isSearching.value = false
    if (isLongEnough.value) {
        debounceTimer = setTimeout(() => search(), DEBOUNCE_MS)
    } else {
        similarTickets.value = []
        hasSearched.value = false
    }
})

onBeforeUnmount(() => {
    clearTimeout(debounceTimer)
    controller?.abort()
})
</script>

<template>
    <div>
        <div class="mb-1 flex items-center justify-between">
            <label class="block text-xs text-gray-500">
                {{ ctrans("Similar tickets") }}
                <span v-if="!isSearching && similarTickets.length" class="ml-1 rounded bg-gray-100 px-1.5 text-[11px] tabular-nums text-gray-600">{{ similarTickets.length }}</span>
            </label>
            <button
                type="button"
                v-tooltip="ctrans('Look for similar tickets again')"
                :aria-label="ctrans('Look for similar tickets again')"
                :disabled="isSearching || !isLongEnough"
                class="rounded p-0.5 text-gray-400 transition duration-200 hover:bg-gray-100 hover:text-gray-700 disabled:cursor-not-allowed disabled:opacity-40"
                @click="search(true)">
                <FontAwesomeIcon icon="fal fa-sync-alt" fixed-width :spin="isSearching" />
            </button>
        </div>

        <div v-if="isSearching" aria-live="polite">
            <p class="mb-2 flex items-center gap-1.5 text-xs text-[--app-accent-strong]">
                <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width />
                {{ ctrans("Looking for related tickets…") }}
            </p>
            <div class="space-y-1.5">
                <div v-for="placeholder in 3" :key="placeholder" class="h-7 animate-pulse rounded bg-gray-100" />
            </div>
        </div>
        <p v-else-if="!isLongEnough" class="text-xs text-gray-400">{{ ctrans("Start writing the subject or details to see tickets that may already cover it.") }}</p>
        <p v-else-if="hasFailed" class="text-xs text-gray-400">{{ ctrans("Similar tickets could not be loaded") }}</p>
        <p v-else-if="hasSearched && !similarTickets.length" class="text-xs text-gray-400">{{ ctrans("No similar tickets found") }}</p>
        <p v-else-if="!hasSearched" class="text-xs text-gray-400">{{ ctrans("Searching when you stop typing…") }}</p>
        <ul v-else class="-mx-1.5 space-y-0.5 text-sm">
            <li v-for="similar in similarTickets" :key="similar.id">
                <div
                    role="button"
                    tabindex="0"
                    class="flex cursor-pointer items-center gap-2 rounded px-1.5 py-1.5 transition duration-200 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[--app-accent]"
                    @click="quickLook = similar"
                    @keydown.enter.prevent="quickLook = similar">
                    <Icon v-if="similar.type_icon" :data="similar.type_icon" class="shrink-0 text-gray-400" />
                    <a :href="ticketRoute(similar.reference)" target="_blank" rel="noopener" class="shrink-0 font-medium text-[--app-accent-strong] hover:underline" @click.stop>{{ similar.reference }}</a>
                    <span class="min-w-0 flex-1 truncate text-gray-700">{{ similar.subject }}</span>
                    <span v-tooltip="similar.status_label" class="shrink-0"><Icon :data="similar.status_icon" /></span>
                </div>
            </li>
        </ul>
        <TicketQuickLook v-model:ticket="quickLook" />
    </div>
</template>
