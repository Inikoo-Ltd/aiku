<script setup lang="ts">
import { hoverStaffProfile, leaveStaffProfile } from "@/Composables/useStaffProfileCard"

const props = defineProps<{
    userId?: number | null
}>()

const onEnter = (event: Event) => {
    if (props.userId) hoverStaffProfile(props.userId, event.currentTarget as HTMLElement)
}
</script>

<template>
    <span class="inline-flex max-w-full" :class="userId ? 'cursor-pointer' : ''" @mouseenter="onEnter" @mouseleave="leaveStaffProfile" @focusin="onEnter" @focusout="leaveStaffProfile">
        <slot />
    </span>
</template>
