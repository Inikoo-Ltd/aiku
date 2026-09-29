<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 01 Oct 2026 04:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faEnvelope, faEnvelopeOpenText, faComments, faCommentDots, faTicketAlt, faArchive } from "@fal"
import { ctrans } from "@/Composables/useTrans"

interface Thread {
    id: string
    kind: "email_archive" | "email" | "website" | "whatsapp" | "ticket"
    title: string
    summary?: string | null
    first_at: string | null
    last_at: string | null
    url: string | null
    messages: { from_us: boolean, at: string | null, text: string | null }[]
}

defineProps<{
    data?: { threads: Thread[], has_more: boolean }
}>()

const open = ref<Record<string, boolean>>({})

const KIND = {
    email_archive: { icon: faArchive, label: () => ctrans("Email (mailbox history)") },
    email: { icon: faEnvelopeOpenText, label: () => ctrans("Email") },
    website: { icon: faComments, label: () => ctrans("Website chat") },
    whatsapp: { icon: faCommentDots, label: () => ctrans("WhatsApp") },
    ticket: { icon: faTicketAlt, label: () => ctrans("Ticket") },
}

const when = (date: string | null) => date ? new Date(date).toLocaleString(undefined, { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" }) : ""
</script>

<template>
    <div class="max-w-5xl space-y-2 p-4">
        <p v-if="!data?.threads?.length" class="text-sm text-gray-500">
            <FontAwesomeIcon :icon="faEnvelope" fixed-width class="text-gray-400" />
            {{ ctrans("No emails, chats or tickets with this customer yet.") }}
        </p>

        <div v-for="thread in data?.threads ?? []" :key="thread.id" class="rounded-lg border border-gray-200">
            <button type="button" class="flex w-full items-start gap-3 px-4 py-2 text-left hover:bg-gray-50" @click="open[thread.id] = !open[thread.id]">
                <FontAwesomeIcon :icon="KIND[thread.kind]?.icon ?? faEnvelope" fixed-width class="mt-1 text-gray-500" />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="font-medium text-gray-800">{{ thread.title }}</span>
                        <span class="text-xs text-gray-400">{{ KIND[thread.kind]?.label() }} · {{ when(thread.last_at) }}</span>
                        <span v-if="thread.messages.length" class="text-xs text-gray-400">· {{ ctrans(":count messages", { count: thread.messages.length }) }}</span>
                    </div>
                    <p v-if="thread.summary" class="text-sm text-gray-600">{{ thread.summary }}</p>
                </div>
                <a v-if="thread.url" :href="thread.url" class="text-xs text-indigo-700 underline" @click.stop>{{ ctrans("Open") }}</a>
            </button>

            <div v-if="open[thread.id] && thread.messages.length" class="space-y-2 border-t border-gray-100 bg-gray-50 px-4 py-3">
                <div v-for="(message, index) in thread.messages" :key="index" class="flex" :class="message.from_us ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm" :class="message.from_us ? 'bg-indigo-50 text-gray-800' : 'bg-white text-gray-800 ring-1 ring-gray-200'">
                        <div class="text-[11px] text-gray-400">{{ message.from_us ? ctrans("Us") : ctrans("Customer") }} · {{ when(message.at) }}</div>
                        <p class="whitespace-pre-line">{{ message.text }}</p>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="data?.has_more" class="text-xs text-gray-500">{{ ctrans("Showing the latest 30.") }}</p>
    </div>
</template>
