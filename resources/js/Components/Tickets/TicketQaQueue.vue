<!--
 Author Louis Perez
 Created on 18-09-2026-10h-19m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { usePage } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"

const props = defineProps<{
    title: string
    tickets: any[]
}>()

type Checker = "all" | "anyone" | "me"

const checkers: { key: Checker; label: string }[] = [
    { key: "all", label: trans("All") },
    { key: "anyone", label: trans("Anyone") },
    { key: "me", label: trans("Me") },
]

const myUserId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const checker = ref<Checker>("all")
const filteredTickets = ref<any[] | null>(null)
const isLoading = ref(false)

const loadQueue = async () => {
    const requested = checker.value
    if (requested === "all") {
        filteredTickets.value = null
        return
    }
    isLoading.value = true
    try {
        const { data } = await axios.get(route("grp.json.ticket.qa_queue", { checker: requested }))
        if (requested === checker.value) filteredTickets.value = data
    } finally {
        if (requested === checker.value) isLoading.value = false
    }
}

watch(checker, loadQueue)
watch(() => props.tickets, loadQueue)

const shownTickets = computed(() => filteredTickets.value ?? props.tickets)

const checkerLabel = (ticket: any) => {
    if (!ticket.qa_user_id) return trans("Anyone")
    if (ticket.qa_user_id === myUserId.value) return trans("Me")
    return ticket.qa_user
}
</script>

<template>
    <TicketMiniList :title="title" :tickets="shownTickets" :empty="trans('Nothing to check')" date-key="qa_requested_at" :class="isLoading && 'opacity-60 transition-opacity duration-200'">
        <template #filters>
            <span class="inline-flex rounded-full bg-gray-100 p-0.5 text-xs">
                <button
                    v-for="option in checkers"
                    :key="option.key"
                    type="button"
                    class="rounded-full px-2 py-0.5 transition duration-200"
                    :class="checker === option.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                    @click="checker = option.key"
                >
                    {{ option.label }}
                </button>
            </span>
        </template>
        <template #person="{ ticket }">
            <span v-tooltip="trans('QA checker')" class="text-xs whitespace-nowrap" :class="ticket.qa_user_id ? 'text-gray-600' : 'italic text-gray-400'">{{ checkerLabel(ticket) }}</span>
        </template>
    </TicketMiniList>
</template>
