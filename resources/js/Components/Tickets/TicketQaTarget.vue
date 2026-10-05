<!--
 Author Louis Perez
 Created on 25-09-2026-15h-40m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faQuestion } from "@fas"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"

library.add(faQuestion)

const props = withDefaults(defineProps<{
    name?: string | null
    avatar?: Record<string, string> | null
    size?: "xs" | "sm" | "md" | "lg"
}>(), { name: null, avatar: null, size: "sm" })

const sizeClasses: Record<string, string> = {
    xs: "h-5 w-5 text-[8px]",
    sm: "h-6 w-6 text-[9px]",
    md: "h-7 w-7 text-[10px]",
    lg: "h-9 w-9 text-xs",
}
</script>

<template>
    <TicketUserAvatar
        v-if="props.name"
        :name="props.name"
        :avatar="props.avatar"
        :size="props.size"
        class="ring-2 ring-white" />
    <span
        v-else
        :class="sizeClasses[props.size]"
        class="inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-gray-400 bg-white text-gray-500 ring-2 ring-white">
        <FontAwesomeIcon icon="fas fa-question" fixed-width class="text-[0.75em]" />
    </span>
</template>
