<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBan, faCheckCircle, faFlag, faExchangeAlt, faUserCheck, faInfoCircle, faPhone } from "@far"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    event: {
        id?: number
        event_type?: string | null
        description?: string
        payload?: Record<string, any> | null
        created_at?: string
    }
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
}

const icon = computed(() => ICONS[props.event.event_type ?? ""] ?? faInfoCircle)

const tone = computed(() => (props.event.event_type === "spam" ? "text-red-500" : "text-gray-400"))

const isPhoneCall = computed(() => props.event.event_type === "phone_call")

const payload = computed(() => props.event.payload ?? {})

const duration = computed(() => {
    const seconds = Number(payload.value.duration_seconds ?? 0)

    if (!seconds) return ""

    const minutes = Math.floor(seconds / 60)

    return minutes > 0 ? `${minutes}m ${seconds % 60}s` : `${seconds}s`
})

// The call is only worth putting in the thread because of what was said on it, so the headline
// names who spoke and the notes are shown in full underneath rather than hidden behind a hover.
const headline = computed(() => {
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
    <div class="flex flex-col items-center gap-1 py-1 text-center">
        <span class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center">
            <FontAwesomeIcon :icon="icon" class="text-[9px]" :class="tone" />
        </span>
        <div class="text-[11px] leading-snug text-gray-500 max-w-xs">{{ headline }}</div>
        <div v-if="isPhoneCall && payload.notes"
            class="max-w-md whitespace-pre-line rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-left text-[11px] leading-snug text-emerald-900">
            {{ payload.notes }}
        </div>
        <div class="text-[10px] text-gray-400">{{ stamp }}</div>
    </div>
</template>
