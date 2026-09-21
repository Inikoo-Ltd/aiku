<!--
 Author Louis Perez
 Created on 21-09-2026-15h-40m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { onMounted, ref } from "vue"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faComments, faPaperclip, faSpinner, faUser } from "@fal"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faChevronDown, faComments, faPaperclip, faSpinner, faUser)

const props = defineProps<{
    ticketId: number | string
    source?: {
        channel_icon?: { icon: any; class?: string } | null
        channel_label?: string
        contact?: string | null
        reference?: string | null
        url?: string | null
    } | null
}>()

type ChatLine = {
    id: number
    at: string
    sender_type: string
    sender: string | null
    text: string | null
    is_redacted: boolean
    attachments: number
}

const isOpen = ref(false)
const isLoading = ref(false)
const error = ref<string | null>(null)
const loaded = ref(false)
const customer = ref<{ name?: string | null; reference?: string | null; url?: string | null } | null>(null)
const session = ref<{ status?: string; contact?: string | null; agent?: string | null } | null>(null)
const messages = ref<ChatLine[]>([])
const truncated = ref(false)

// Who the customer is and who held the chat are wanted whether or not the conversation is
// unfolded, so this is fetched as soon as the ticket is shown; only the messages are hidden
// behind the chevron.
const load = async () => {
    if (loaded.value || isLoading.value) return

    isLoading.value = true
    error.value = null

    try {
        const { data } = await axios.get(route("grp.json.ticket.chat", { ticket: props.ticketId }))
        customer.value = data.customer
        session.value = data.session
        messages.value = data.messages ?? []
        truncated.value = !!data.truncated
        loaded.value = true
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? trans("Could not load the conversation")
    } finally {
        isLoading.value = false
    }
}

onMounted(load)

const toggle = () => {
    isOpen.value = !isOpen.value
}

const whoSaidIt = (line: ChatLine) => {
    if (line.sender) return line.sender
    if (line.sender_type === "agent") return trans("Agent")
    if (line.sender_type === "ai") return trans("Assistant")
    if (line.sender_type === "system" || line.sender_type === "system_campaign") return trans("System")

    return props.source?.contact || session.value?.contact || trans("Customer")
}

const isFromUs = (line: ChatLine) => ["agent", "ai", "system", "system_campaign"].includes(line.sender_type)
</script>

<template>
    <div class="rounded-md border border-gray-200 bg-white text-sm">
        <!-- The chevron hides the messages, nothing else. -->
        <button type="button"
            class="flex w-full items-center gap-2 px-3 py-2 text-left transition hover:bg-gray-50"
            :aria-expanded="isOpen" @click="toggle">
            <FontAwesomeIcon :icon="source?.channel_icon?.icon ?? ['fal', 'comments']"
                :class="source?.channel_icon?.class ?? 'text-gray-400'" fixed-width aria-hidden="true" />
            <span class="font-medium text-gray-800">{{ source?.channel_label || trans("Conversation") }}</span>
            <span v-if="source?.contact" class="truncate text-gray-500">· {{ source.contact }}</span>
            <span class="ml-auto flex shrink-0 items-center gap-2 text-xs text-gray-400">
                <span v-if="loaded && messages.length" class="tabular-nums">{{ messages.length }}</span>
                <FontAwesomeIcon :icon="['fal', 'chevron-down']" class="transition-transform" :class="isOpen ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
            </span>
        </button>

        <!-- Shown whether or not it is unfolded: who the customer is, who held the chat, and the
             two places to go from here. -->
        <div class="border-t border-gray-100 bg-gray-50 px-3 py-2">
            <dl class="space-y-0.5 text-xs text-gray-500">
                <div v-if="source?.contact" class="flex justify-between gap-2">
                    <dt>{{ trans("Contact") }}</dt>
                    <dd class="truncate text-gray-700">{{ source.contact }}</dd>
                </div>
                <div v-if="customer?.name" class="flex justify-between gap-2">
                    <dt>{{ trans("Customer") }}</dt>
                    <dd class="truncate">
                        <a v-if="customer.url" :href="customer.url" class="text-blue-600 hover:underline">{{ customer.name }}</a>
                        <span v-else class="text-gray-700">{{ customer.name }}</span>
                    </dd>
                </div>
                <div v-if="session?.agent" class="flex justify-between gap-2">
                    <dt>{{ trans("Chat assignee") }}</dt>
                    <dd class="truncate text-gray-700">{{ session.agent }}</dd>
                </div>
                <div v-if="customer?.reference || source?.reference" class="flex justify-between gap-2">
                    <dt>{{ trans("Reference") }}</dt>
                    <dd class="font-mono text-gray-700">{{ customer?.reference || source?.reference }}</dd>
                </div>
                <div v-if="session?.status" class="flex justify-between gap-2">
                    <dt>{{ trans("Status") }}</dt>
                    <dd class="text-gray-700">{{ session.status }}</dd>
                </div>
            </dl>

            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs">
                <a v-if="source?.url" :href="source.url" class="inline-flex items-center gap-1 text-blue-600 hover:underline">
                    <FontAwesomeIcon :icon="['fal', 'comments']" fixed-width aria-hidden="true" />
                    {{ trans("Open conversation") }}
                </a>
                <a v-if="customer?.url" :href="customer.url" class="inline-flex items-center gap-1 text-blue-600 hover:underline">
                    <FontAwesomeIcon :icon="['fal', 'user']" fixed-width aria-hidden="true" />
                    {{ trans("Open customer") }}
                </a>
            </div>
        </div>

        <div v-if="isOpen" class="border-t border-gray-200">
            <div v-if="isLoading" class="flex items-center gap-2 px-3 py-4 text-xs text-gray-400">
                <FontAwesomeIcon :icon="['fal', 'spinner']" spin fixed-width aria-hidden="true" />
                {{ trans("Loading the conversation") }}
            </div>

            <p v-else-if="error" class="px-3 py-4 text-xs text-red-600">{{ error }}</p>

            <template v-else>
                <p v-if="!messages.length" class="px-3 py-4 text-xs italic text-gray-400">
                    {{ trans("Nothing was said in this conversation") }}
                </p>

                <div v-else class="max-h-80 space-y-2 overflow-y-auto px-3 py-3">
                    <p v-if="truncated" class="text-center text-[11px] italic text-gray-400">
                        {{ trans("Only the most recent messages are shown") }}
                    </p>

                    <div v-for="line in messages" :key="line.id" class="flex flex-col" :class="isFromUs(line) ? 'items-end' : 'items-start'">
                        <div class="max-w-[85%] rounded-lg px-2.5 py-1.5"
                            :class="isFromUs(line) ? 'bg-indigo-50 text-gray-800' : 'bg-gray-100 text-gray-800'">
                            <div class="flex items-baseline gap-2 text-[10px] text-gray-500">
                                <span class="font-medium">{{ whoSaidIt(line) }}</span>
                                <span>{{ useFormatTime(line.at, { formatTime: "hm" }) }}</span>
                            </div>
                            <p v-if="line.is_redacted" class="mt-0.5 text-xs italic text-gray-400">{{ trans("Message withdrawn") }}</p>
                            <p v-else-if="line.text" class="mt-0.5 whitespace-pre-wrap break-words text-xs">{{ line.text }}</p>
                            <p v-if="line.attachments" class="mt-0.5 flex items-center gap-1 text-[10px] text-gray-400">
                                <FontAwesomeIcon :icon="['fal', 'paperclip']" fixed-width aria-hidden="true" />
                                {{ line.attachments }}
                            </p>
                        </div>
                    </div>
                </div>

                <p class="border-t border-gray-100 px-3 py-1.5 text-[10px] italic text-gray-400">
                    {{ trans("Read only. Answer the customer in Chat.") }}
                </p>
            </template>
        </div>
    </div>
</template>
