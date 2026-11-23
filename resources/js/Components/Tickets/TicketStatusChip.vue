<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan } from "@fal"
import Icon from "@/Components/Icon.vue"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { ctrans } from "@/Composables/useTrans"
import { formatChatTime } from "@/Composables/chatTime"

library.add(faCircle, faUserCheck, faSpinner, faClock, faCommentDots, faRocket, faCheckCircle, faBan)

const props = defineProps<{
    ticket: {
        reference: string
        subject: string
        status_icon: { icon: string, class: string, tooltip: string }
        progress?: "fixed" | "fixing" | "not_fixed" | "closed"
        type_icon?: { icon: string, tooltip?: string } | null
        kind_label?: string | null
        priority_label?: string
        assignee?: string | null
        assignee_avatar?: Record<string, string> | null
        resolved_at?: string | null
        url: string
    }
    compact?: boolean
}>()

const PROGRESS = {
    fixed: { label: () => ctrans("Fixed"), icon: faCheckCircle, chip: "bg-emerald-50 text-emerald-700 ring-emerald-300" },
    fixing: { label: () => ctrans("Fixing"), icon: faSpinner, chip: "bg-amber-50 text-amber-700 ring-amber-300" },
    not_fixed: { label: () => ctrans("Not fixed yet"), icon: faClock, chip: "bg-white text-gray-700 ring-gray-300" },
    closed: { label: () => ctrans("Closed"), icon: faBan, chip: "bg-white text-gray-500 ring-gray-200" },
}

const progress = computed(() => PROGRESS[props.ticket.progress ?? "not_fixed"])
</script>

<template>
    <span class="group relative inline-flex items-center gap-1 align-middle" :class="compact ? 'flex-col' : ''">
        <a :href="ticket.url" target="_blank" rel="noopener" data-aura="ticket_chip" class="inline-flex items-center gap-1 whitespace-nowrap rounded-full py-0.5 font-semibold ring-1 ring-inset" :class="[progress.chip, compact ? 'px-0.5' : 'px-2']" :aria-label="`${progress.label()} · ${ticket.reference}`"><FontAwesomeIcon :icon="progress.icon" fixed-width /><template v-if="!compact">{{ progress.label() }}<span class="font-normal opacity-70">· {{ ticket.reference }}</span></template></a>
        <TicketUserAvatar v-if="ticket.assignee" :name="ticket.assignee" :avatar="ticket.assignee_avatar" size="xs" />
        <span class="pointer-events-none absolute right-0 top-full z-30 mt-1 hidden w-64 whitespace-normal rounded-md border border-gray-200 bg-white px-3 py-2 text-left shadow-lg group-hover:block">
            <span class="flex items-center gap-1.5 text-xs font-semibold text-gray-800">
                <FontAwesomeIcon :icon="ticket.status_icon.icon" :class="ticket.status_icon.class" fixed-width />{{ ticket.status_icon.tooltip }}
                <span v-if="ticket.resolved_at" class="font-normal text-gray-500">{{ formatChatTime(Date.parse(ticket.resolved_at)) }}</span>
            </span>
            <span class="mt-1 block text-xs text-gray-600">{{ ticket.subject }}</span>
            <span class="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-500">
                <Icon v-if="ticket.type_icon" :data="ticket.type_icon" class="text-gray-400" />
                {{ [ticket.reference, ticket.kind_label, ticket.priority_label].filter(Boolean).join(" · ") }}
            </span>
            <span v-if="ticket.assignee" class="mt-1.5 flex items-center gap-1.5 text-[11px] text-gray-600">
                <TicketUserAvatar :name="ticket.assignee" :avatar="ticket.assignee_avatar" size="xs" />
                {{ ticket.resolved_at ? ctrans("Fixed by :name", { name: ticket.assignee }) : ctrans("Assigned to :name", { name: ticket.assignee }) }}
            </span>
        </span>
    </span>
</template>
