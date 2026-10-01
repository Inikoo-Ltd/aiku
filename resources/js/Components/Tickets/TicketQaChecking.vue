<!--
  - Author Louis Perez
  - Created on 29-09-2026-15h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import TicketMiniList from "@/Components/Tickets/TicketMiniList.vue"

const props = defineProps<{
    title: string
    tickets: any[]
}>()

type Checker = "all" | "me"

const checkers: { key: Checker; label: string }[] = [
    { key: "all", label: ctrans("All") },
    { key: "me", label: ctrans("Me") },
]

const myUserId = computed(() => (usePage().props.auth as { user?: { id: number } } | undefined)?.user?.id ?? null)

const checker = ref<Checker>("me")

const shownTickets = computed(() => (checker.value === "me" ? props.tickets.filter((ticket) => ticket.qa_user_id === myUserId.value) : props.tickets))

const checkerLabel = (ticket: any) => (ticket.qa_user_id === myUserId.value ? ctrans("Me") : ticket.qa_user)
</script>

<template>
    <TicketMiniList :title="title" :tickets="shownTickets" :empty="checker === 'me' ? ctrans('You are not checking anything') : ctrans('Nobody is checking anything')" date-key="updated_at">
        <template #filters>
            <span class="inline-flex rounded-full bg-gray-100 p-0.5 text-xs">
                <button
                    v-for="option in checkers"
                    :key="option.key"
                    type="button"
                    class="rounded-full px-2 py-0.5 transition duration-200"
                    :class="checker === option.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                    :aria-pressed="checker === option.key"
                    @click="checker = option.key"
                >
                    {{ option.label }}
                </button>
            </span>
        </template>
        <template #person="{ ticket }">
            <span v-tooltip="ctrans('QA checker')" class="text-xs whitespace-nowrap text-gray-600">{{ checkerLabel(ticket) }}</span>
        </template>
    </TicketMiniList>
</template>
