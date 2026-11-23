<script setup lang="ts">
import { computed, ref } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBan, faCheckCircle, faFlag, faExchangeAlt, faUserCheck, faInfoCircle, faPhone, faShare, faTruck, faExclamationTriangle, faEnvelope, faStore, faLifeRing } from "@far"
import { ctrans } from "@/Composables/useTrans"
import { formatChatAge } from "@/Composables/chatTime"
import AuraAvatar from "@/Components/Chat/Agent/AuraAvatar.vue"
import TicketStatusChip from "@/Components/Tickets/TicketStatusChip.vue"

const props = defineProps<{
    event: {
        id?: number
        event_type?: string | null
        description?: string
        note?: string | null
        note_links?: { ulid: string, shop: string | null, url: string }[]
        note_tickets?: { reference: string, subject: string, status_icon: { icon: string, class: string, tooltip: string }, url: string }[]
        note_answer_in?: { ulid: string, shop: string | null, url: string } | null
        note_follow_up?: { ulid: string, shop: string | null, url: string } | null
        payload?: Record<string, any> | null
        created_at?: string
    }
    date?: string
    beside?: boolean
    folded?: boolean
}>()

const ICONS: Record<string, any> = {
    spam: faBan,
    not_spam: faCheckCircle,
    priority: faFlag,
    transfer_request: faExchangeAlt,
    transfer_accept: faExchangeAlt,
    transfer_reject: faExchangeAlt,
    transfer_to_agent: faExchangeAlt,
    assignment_to_self: faUserCheck,
    phone_call: faPhone,
    forward: faShare,
    moved_to_couriers: faTruck,
    forwarded_by_email: faEnvelope,
    moved_to_shop: faStore,
    email_bounce: faExclamationTriangle,
    ticket: faLifeRing,
}

const icon = computed(() => ICONS[props.event.event_type ?? ""] ?? faInfoCircle)

const tone = computed(() => (["spam", "email_bounce"].includes(props.event.event_type ?? "") ? "text-red-500" : "text-gray-400"))

const isPhoneCall = computed(() => props.event.event_type === "phone_call")

// Pink is the colour of "for staff only": the note is read in the thread like a message, so it has to
// look like nothing the customer could have written or been sent.
const isInternalNote = computed(() => props.event.event_type === "internal_note")

const payload = computed(() => props.event.payload ?? {})

const unfolded = ref(false)
const age = computed(() => props.event.created_at ? formatChatAge(+new Date(props.event.created_at)) : "")

const isFyi = computed(() => !!(props.event.note_answer_in || props.event.note_follow_up))

const noteParts = computed(() => {
    const links = props.event.note_links ?? []
    const text = props.event.note ?? ""

    if (!links.length) return [{ text }]

    const pattern = new RegExp(`(${links.map((link) => link.ulid).join("|")})`)

    return text.split(pattern).map((part) => ({ text: part, link: links.find((link) => link.ulid === part) }))
})

const duration = computed(() => {
    const seconds = Number(payload.value.duration_seconds ?? 0)

    if (!seconds) return ""

    const minutes = Math.floor(seconds / 60)

    return minutes > 0 ? `${minutes}m ${seconds % 60}s` : `${seconds}s`
})

// The call is only worth putting in the thread because of what was said on it, so the headline
// names who spoke and the notes are shown in full underneath rather than hidden behind a hover.
const headline = computed(() => {
    // A forward chip with no names on it says nothing: the point of the chip is that the next
    // agent to open the thread sees it already went to somebody, and to whom.
    if (props.event.event_type === "forward") {
        const names = (payload.value.recipient_names ?? []).join(", ")

        return payload.value.also_emailed
            ? ctrans("Forwarded to :names, and by email", { names })
            : ctrans("Forwarded to :names", { names })
    }

    if (!isPhoneCall.value) {
        return props.event.description ?? ""
    }

    const parts = [
        ctrans("Phone call by :agent", { agent: payload.value.agent_name ?? ctrans("an agent") }),
    ]

    if (payload.value.contact_name) {
        parts.push(ctrans("with :contact", { contact: payload.value.contact_name }))
    }

    if (duration.value) {
        parts.push(`· ${duration.value}`)
    }

    return parts.join(" ")
})

const time = computed(() => props.event.created_at ? new Date(props.event.created_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : "")

const stamp = computed(() => {
    if (!props.event.created_at) return ""

    return new Date(props.event.created_at).toLocaleString([], {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    })
})
</script>

<template>
    <div v-if="isInternalNote && folded && !unfolded" class="flex justify-center py-0.5">
        <button type="button" class="flex max-w-xl items-center gap-1.5 truncate rounded-full border border-pink-200 bg-pink-50 px-2.5 py-0.5 text-[11px] text-pink-800 hover:bg-pink-100" @click="unfolded = true">
            <AuraAvatar class="h-3.5 w-3.5 shrink-0" />
            <span class="shrink-0 font-semibold">{{ ctrans("Old Aura note, :age old", { age: age }) }}</span>
            <span class="truncate text-pink-700">{{ event.note }}</span>
            <span class="shrink-0 font-semibold underline">{{ ctrans("Show here") }}</span>
        </button>
    </div>
    <div v-else-if="isInternalNote" class="flex justify-center py-2">
        <div class="w-full max-w-xl rounded-lg border border-pink-300 bg-pink-50 px-3 py-2 text-left shadow-sm">
            <div class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold text-pink-800">
                <FontAwesomeIcon v-if="beside" :icon="isFyi ? faInfoCircle : faExclamationTriangle" class="text-sm text-pink-700" fixed-width aria-hidden="true" />
                <AuraAvatar v-else class="h-4 w-4" />
                <span>{{ beside ? (isFyi ? ctrans("FYI") : ctrans("No draft: what to do")) : folded ? ctrans("Old Aura note, :age old", { age: age }) : headline }}</span>
                <span class="ml-auto flex flex-wrap justify-end gap-1 text-xs font-normal"><TicketStatusChip v-for="ticket in event.note_tickets ?? []" :key="ticket.reference" :ticket="ticket" /></span>
            </div>
            <div class="whitespace-pre-line text-xs leading-relaxed text-pink-950"><template v-for="(part, index) in noteParts" :key="index"><a v-if="part.link" :href="part.link.url" target="_blank" rel="noopener" class="font-semibold text-gray-700 underline hover:text-gray-900">{{ ctrans("Open the chat in :shop", { shop: part.link.shop ?? "" }) }}</a><template v-else>{{ part.text }}</template></template></div>
            <div class="mt-1 text-right text-[10px] text-pink-700">{{ stamp }}</div>
        </div>
    </div>
    <div v-else class="flex flex-col items-center py-0.5 text-center">
        <div class="flex max-w-full items-center gap-1.5 text-[11px] leading-snug" :class="event.event_type === 'email_bounce' ? 'text-red-600' : 'text-gray-500'">
            <span v-if="date" class="shrink-0 text-gray-400">{{ date }} ·</span>
            <FontAwesomeIcon :icon="icon" class="shrink-0 text-[10px]" :class="tone" fixed-width aria-hidden="true" />
            <span class="min-w-0">{{ headline }}</span>
            <TicketStatusChip v-for="ticket in event.note_tickets ?? []" :key="ticket.reference" :ticket="ticket" class="text-xs" />
            <span class="shrink-0 text-[10px] text-gray-400">· {{ time }}</span>
        </div>
        <div v-if="isPhoneCall && payload.notes"
            class="mt-1 max-w-md whitespace-pre-line rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-left text-[11px] leading-snug text-emerald-900">
            {{ payload.notes }}
        </div>
    </div>
</template>
