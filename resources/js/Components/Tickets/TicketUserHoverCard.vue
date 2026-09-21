<!--
 Author Louis Perez
 Created on 21-09-2026-14h-05m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTicketAlt, faAt } from "@fal"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { ticketsRoute } from "@/Composables/useTicketsRoute"

library.add(faTicketAlt, faAt)

const props = withDefaults(defineProps<{
    name: string | null
    avatar?: Record<string, string> | null
    roles?: { key: string; label: string }[]
    username?: string | null
    reporterKey?: string | null
    size?: "xs" | "sm" | "md" | "lg"
    canMention?: boolean
}>(), { avatar: null, roles: () => [], username: null, reporterKey: null, size: "sm", canMention: true })

const emit = defineEmits<{
    (e: "mention", username: string): void
}>()

const roleClasses: Record<string, string> = {
    lead_engineer: "bg-teal-100 text-teal-700",
    engineer: "bg-blue-100 text-blue-700",
    qa: "bg-purple-100 text-purple-700",
    reporter: "bg-orange-100 text-orange-700",
    staff: "bg-gray-100 text-gray-600",
    bot: "bg-[--app-accent-muted] text-[--app-accent-strong]",
    customer: "bg-slate-200 text-slate-700",
}

const isOpen = ref(false)
let closeTimer: ReturnType<typeof setTimeout> | null = null

const open = () => {
    if (closeTimer) {
        clearTimeout(closeTimer)
        closeTimer = null
    }
    isOpen.value = true
}

const scheduleClose = () => {
    closeTimer = setTimeout(() => (isOpen.value = false), 120)
}

const isRetina = computed(() => (route().current() ?? "").startsWith("retina."))

const reportedWorkItemsUrl = computed(() =>
    props.reporterKey && !isRetina.value ? ticketsRoute("list", { filter: { reporter: props.reporterKey } }) : null
)

const onMention = () => {
    isOpen.value = false
    if (props.username) {
        emit("mention", props.username)
    }
}
</script>

<template>
    <span class="relative inline-flex items-center gap-1.5" @mouseenter="open" @mouseleave="scheduleClose">
        <TicketUserAvatar :name="name" :avatar="avatar" :size="size" />
        <span class="font-semibold text-gray-800">{{ name || ctrans("Unknown") }}</span>
        <span
            v-for="role in roles"
            :key="role.key"
            class="rounded px-1.5 py-0.5 text-[10px] font-medium"
            :class="roleClasses[role.key] ?? 'bg-gray-100 text-gray-600'"
            >{{ role.label }}</span
        >

        <transition
            enter-active-class="transition duration-100 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-75 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <span v-if="isOpen && (reportedWorkItemsUrl || (canMention && username))"
                class="absolute left-0 top-full z-30 mt-1 w-56 rounded-md border border-gray-200 bg-white py-1 text-left shadow-lg">
                <span class="block px-3 py-1.5 border-b border-gray-100">
                    <span class="block truncate text-sm font-semibold text-gray-800">{{ name || ctrans("Unknown") }}</span>
                    <span v-if="username" class="block truncate text-xs text-gray-500">@{{ username }}</span>
                </span>

                <Link v-if="reportedWorkItemsUrl" :href="reportedWorkItemsUrl"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-ticket-alt" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Reported work items") }}
                </Link>

                <button v-if="canMention && username" type="button" @click="onMention"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-at" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Mention in reply") }}
                </button>
            </span>
        </transition>
    </span>
</template>
