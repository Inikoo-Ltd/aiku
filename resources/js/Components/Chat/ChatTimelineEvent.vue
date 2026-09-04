<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBan, faCheckCircle, faFlag, faExchangeAlt, faUserCheck, faInfoCircle } from "@far"

const props = defineProps<{
    event: {
        id?: number
        event_type?: string | null
        description?: string
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
}

const icon = computed(() => ICONS[props.event.event_type ?? ""] ?? faInfoCircle)

const tone = computed(() => (props.event.event_type === "spam" ? "text-red-500" : "text-gray-400"))

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
        <div class="text-[11px] leading-snug text-gray-500 max-w-xs">{{ event.description }}</div>
        <div class="text-[10px] text-gray-400">{{ stamp }}</div>
    </div>
</template>
