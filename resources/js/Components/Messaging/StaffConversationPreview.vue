<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { usePage } from "@inertiajs/vue3"
import StaffMessageTicks from "@/Components/Messaging/StaffMessageTicks.vue"
import { tickStateOf, useStaffMessaging, type StaffConversation } from "@/Stores/staff-messaging"

const props = defineProps<{
    conversation: StaffConversation
    text: string
}>()

const store = useStaffMessaging()
const myId = computed(() => usePage().props?.auth?.user?.id)
const typing = computed(() => store.typingText(props.conversation.ulid))
const tick = computed(() => {
    if (props.conversation.last_message_user_id !== myId.value || !props.conversation.last_message_at) return null
    return tickStateOf(props.conversation.participants.filter((participant) => participant.id !== myId.value), props.conversation.last_message_at)
})
</script>

<template>
    <span v-if="typing" class="flex min-w-0 items-center gap-1 text-[var(--app-accent-strong)]">
        <span class="truncate">{{ typing }}</span>
        <span class="typing-dots shrink-0" aria-hidden="true"><span /><span /><span /></span>
    </span>
    <span v-else class="flex min-w-0 items-center gap-0.5">
        <StaffMessageTicks v-if="tick" :state="tick" class="shrink-0 text-xxs" />
        <span class="truncate">{{ text }}</span>
    </span>
</template>

<style scoped>
.typing-dots {
    display: inline-flex;
    gap: 2px;
}
.typing-dots span {
    width: 3px;
    height: 3px;
    border-radius: 9999px;
    background: currentColor;
    animation: typing-dot 1.2s infinite ease-in-out;
}
.typing-dots span:nth-child(2) {
    animation-delay: 0.15s;
}
.typing-dots span:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes typing-dot {
    0%, 60%, 100% { opacity: 0.3; transform: translateY(0); }
    30% { opacity: 1; transform: translateY(-2px); }
}
</style>
