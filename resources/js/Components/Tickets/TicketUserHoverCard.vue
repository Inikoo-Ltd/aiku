<!--
 Author Louis Perez
 Created on 21-09-2026-14h-05m
 GitHub: https://github.com/louis-perez
 Copyright 2026
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTicketAlt, faAt, faIdCard } from "@fal"
import TicketUserAvatar from "@/Components/Tickets/TicketUserAvatar.vue"
import { ticketsRoute } from "@/Composables/useTicketsRoute"

library.add(faTicketAlt, faAt, faIdCard)

const props = withDefaults(defineProps<{
    name: string | null
    avatar?: Record<string, string> | null
    roles?: { key: string; label: string }[]
    username?: string | null
    reporterKey?: string | null
    profileUrl?: string | null
    size?: "xs" | "sm" | "md" | "lg"
    canMention?: boolean
    avatarOnly?: boolean
    menuRole?: { key: string; label: string } | null
}>(), { avatar: null, roles: () => [], username: null, reporterKey: null, profileUrl: null, size: "sm", canMention: true, avatarOnly: false, menuRole: null })

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

const menuWidth = 224
const trigger = ref<HTMLElement | null>(null)
const floatingMenuPosition = ref({ top: 0, left: 0 })

const placeFloatingMenu = () => {
    if (!props.avatarOnly || !trigger.value) {
        return
    }
    const triggerBox = trigger.value.getBoundingClientRect()
    floatingMenuPosition.value = {
        top: triggerBox.bottom + 4,
        left: Math.max(8, Math.min(triggerBox.left, window.innerWidth - menuWidth - 8)),
    }
}

const open = () => {
    if (closeTimer) {
        clearTimeout(closeTimer)
        closeTimer = null
    }
    if (!isOpen.value) {
        placeFloatingMenu()
    }
    isOpen.value = true
}

const close = () => (isOpen.value = false)

const scheduleClose = () => {
    closeTimer = setTimeout(close, 120)
}

watch(isOpen, (isNowOpen) => {
    if (!props.avatarOnly) {
        return
    }
    if (isNowOpen) {
        window.addEventListener("scroll", close, true)
    } else {
        window.removeEventListener("scroll", close, true)
    }
})

onBeforeUnmount(() => {
    if (closeTimer) {
        clearTimeout(closeTimer)
    }
    window.removeEventListener("scroll", close, true)
})

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
    <span ref="trigger" class="relative inline-flex items-center gap-1.5" @mouseenter="open" @mouseleave="scheduleClose">
        <TicketUserAvatar :name="name" :avatar="avatar" :size="size" />
        <span v-if="!avatarOnly" class="font-semibold text-gray-800">{{ name || ctrans("Unknown") }}</span>
        <span
            v-for="role in avatarOnly ? [] : roles"
            :key="role.key"
            class="rounded px-1.5 py-0.5 text-[10px] font-medium"
            :class="roleClasses[role.key] ?? 'bg-gray-100 text-gray-600'"
            >{{ role.label }}</span
        >

        <Teleport to="body" :disabled="!avatarOnly">
        <transition
            enter-active-class="transition duration-100 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition duration-75 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <span v-if="isOpen && (reportedWorkItemsUrl || profileUrl || (canMention && username))"
                class="w-56 rounded-md border border-gray-200 bg-white py-1 text-left shadow-lg"
                :class="avatarOnly ? 'fixed z-[60] block' : 'absolute left-0 top-full z-30 mt-1'"
                :style="avatarOnly ? { top: `${floatingMenuPosition.top}px`, left: `${floatingMenuPosition.left}px` } : undefined"
                @mouseenter="open"
                @mouseleave="scheduleClose"
                @click.stop>
                <span class="block px-3 py-1.5 border-b border-gray-100">
                    <span class="block truncate text-sm font-semibold text-gray-800">{{ name || ctrans("Unknown") }}</span>
                    <span v-if="username" class="block truncate text-xs text-gray-500">@{{ username }}</span>
                    <span
                        v-if="menuRole"
                        class="mt-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-medium"
                        :class="roleClasses[menuRole.key] ?? 'bg-gray-100 text-gray-600'"
                        >{{ menuRole.label }}</span
                    >
                </span>

                <Link v-if="reportedWorkItemsUrl" :href="reportedWorkItemsUrl"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-ticket-alt" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Reported work items") }}
                </Link>

                <!-- Only sent by the server to somebody who may open it, so there is no link here
                     that answers with a 403. -->
                <a v-if="profileUrl" :href="profileUrl"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-id-card" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Their account") }}
                </a>

                <button v-if="canMention && username" type="button" @click="onMention"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm text-gray-700 hover:bg-gray-50">
                    <FontAwesomeIcon icon="fal fa-at" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
                    {{ ctrans("Mention in reply") }}
                </button>
            </span>
        </transition>
        </Teleport>
    </span>
</template>
