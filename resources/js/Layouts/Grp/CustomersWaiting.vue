<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onMounted, onUnmounted, ref, watch } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faUserClock, faBell, faBellSlash } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { chatsWaiting, emailsWaiting, customerPeek, desktopAlerts, enableDesktopAlerts, waitedFor } from "@/Composables/useNotificationSound"
import { chatPaneUrl, closeChatPane, openChatPane } from "@/Composables/useChatPane"

library.add(faUserClock, faBell, faBellSlash)

defineProps<{ micro?: boolean }>()

const layout = inject("layout", layoutStructure)

const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | null = null
onMounted(() => { clock = setInterval(() => { now.value = Date.now() }, 15000) })
onUnmounted(() => { if (clock) clearInterval(clock) })

const live = computed(() => chatsWaiting.value.live)
const liveWait = computed(() => waitedFor(live.value.oldest_at, now.value))
const backlog = computed(() => chatsWaiting.value.sessions + emailsWaiting.value.sessions)
const backlogUrl = computed(() => chatsWaiting.value.url ?? emailsWaiting.value.url)
const MORE_WAITING = 10
const MOST_WAITING = 30

const levelStyles = {
    green: { box: "border-emerald-400 bg-emerald-400/10 shadow-[0_0_12px_rgba(52,211,153,0.5)]", badge: "bg-emerald-400 text-emerald-950", text: "text-emerald-400" },
    yellow: { box: "border-amber-300 bg-amber-300/10 shadow-[0_0_12px_rgba(252,211,77,0.6)]", badge: "bg-amber-300 text-amber-900", text: "text-amber-300" },
    orange: { box: "border-orange-400 bg-orange-400/15 shadow-[0_0_14px_rgba(251,146,60,0.7)]", badge: "bg-orange-400 text-orange-950", text: "text-orange-400" },
    red: { box: "border-red-500 bg-red-500/15 shadow-[0_0_16px_rgba(239,68,68,0.8)]", badge: "bg-red-500 text-white", text: "text-red-400" },
}

const backlogLevel = computed(() => {
    if (backlog.value >= MOST_WAITING) return levelStyles.red
    if (backlog.value >= MORE_WAITING) return levelStyles.orange
    return backlog.value ? levelStyles.yellow : levelStyles.green
})

const boxClass = computed(() => {
    if (!layout.user?.is_agent) return "border-[var(--chat-line)]"
    return live.value.sessions ? levelStyles.red.box : backlogLevel.value.box
})

const inboxSlug = computed(() => layout.currentParams?.organisation ?? layout.organisations?.data?.[0]?.slug)
const open = (url: string | null) => openChatPane(url ?? (inboxSlug.value ? route("grp.org.chat.inbox", [inboxSlug.value]) : null))
const toggle = (url: string | null) => chatPaneUrl.value ? closeChatPane() : open(url)

const peekShownFor = computed(() => (layout.user?.settings?.alert_preview_seconds ?? 6) * 1000)
const PEEK_GROW_TIME = 300
const peek = ref<typeof customerPeek.value>(null)
let peekTimer: ReturnType<typeof setTimeout> | null = null
const holdPeek = () => {
    if (peekTimer) clearTimeout(peekTimer)
}
const releasePeek = (after: number) => {
    holdPeek()
    peekTimer = setTimeout(() => { peek.value = null }, after)
}
watch(customerPeek, (latest) => {
    if (!latest) return
    peek.value = latest
    releasePeek(PEEK_GROW_TIME + peekShownFor.value)
})
const openPeek = () => {
    const url = peek.value?.url ?? null
    peek.value = null
    open(url)
}
onUnmounted(holdPeek)
</script>

<template>
    <div class="relative w-full flex flex-col items-center">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="scale-x-0 opacity-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="scale-x-0 opacity-0">
            <div
                v-if="peek"
                :key="peek.key"
                class="absolute right-full top-1 z-30 w-72 max-w-[70vw] origin-right cursor-pointer rounded-l-2xl rounded-r-md px-3 py-2 leading-snug"
                :class="peek.isEmail ? 'bg-amber-300 text-amber-950 shadow-[0_0_14px_rgba(252,211,77,0.6)]' : 'bg-red-500 text-white shadow-[0_0_16px_rgba(239,68,68,0.8)]'"
                @mouseenter="holdPeek"
                @mouseleave="releasePeek(800)"
                @click.stop="openPeek">
                <div class="text-xs font-semibold truncate">{{ peek.channel }} · {{ peek.sender }}</div>
                <div v-if="peek.subject" class="text-xs font-semibold truncate">{{ peek.subject }}</div>
                <div class="text-xs line-clamp-2">{{ peek.text }}</div>
            </div>
        </Transition>

        <div v-if="micro" class="flex flex-col items-center gap-y-1 rounded-full border pt-1 pb-2 px-px" :class="boxClass">
            <span
                v-if="live.sessions"
                class="text-white bg-[var(--chat-red)] rounded-full px-0.5 py-0.5 -mx-1 animate-pulse"
                @click.stop="toggle(live.url)">{{ live.sessions > 99 ? 99 : live.sessions }}</span>
            <span v-if="live.sessions" class="text-[var(--chat-red)]">{{ liveWait }}</span>
            <span
                :class="backlogLevel.text"
                @click.stop="toggle(backlogUrl)">{{ backlog > 99 ? 99 : backlog }}</span>
        </div>

        <div v-else-if="layout.user?.is_agent" class="w-full mb-1 cursor-pointer" @click="toggle(live.url ?? backlogUrl)">
            <div class="border-2 mb-6" :class="[boxClass, layout.messagingSidebar.show ? 'flex flex-wrap items-center gap-2 rounded-xl px-2 pt-2 pb-3' : 'w-fit mx-auto flex flex-col items-center gap-y-2 rounded-2xl px-0.5 pt-2 pb-3']">
                <FontAwesomeIcon icon="fal fa-user-clock" class="w-4 shrink-0 text-center text-sm" :class="live.sessions ? levelStyles.red.text : backlogLevel.text" fixed-width aria-hidden="true" />
                <span v-if="layout.messagingSidebar.show" class="flex-1 text-xs font-semibold text-white">{{ ctrans('Customers waiting') }}</span>

                <div v-if="live.sessions" class="h-9 min-w-[2.25rem] px-1.5 rounded-xl flex flex-col items-center justify-center bg-red-500 text-white font-bold tabular-nums leading-none animate-pulse">
                    <span class="text-sm">{{ live.sessions > 99 ? '99+' : live.sessions }}</span>
                    <span class="text-[9px] mt-0.5">{{ liveWait }}</span>
                </div>

                <div class="h-9 min-w-[2.25rem] px-1.5 rounded-xl flex items-center justify-center text-sm font-bold tabular-nums" :class="backlogLevel.badge">
                    {{ backlog > 99 ? '99+' : backlog }}
                </div>

                <button
                    v-if="desktopAlerts === 'default' || desktopAlerts === 'denied'"
                    class="h-8 w-8 flex items-center justify-center text-xs"
                    :class="desktopAlerts === 'default' ? 'text-amber-300 hover:text-white' : 'text-[var(--chat-muted)]'"
                    @click.stop="enableDesktopAlerts">
                    <FontAwesomeIcon :icon="desktopAlerts === 'default' ? 'fal fa-bell' : 'fal fa-bell-slash'" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <button
            v-else-if="desktopAlerts === 'default' || desktopAlerts === 'denied'"
            class="h-8 w-8 flex items-center justify-center text-xs"
            :class="desktopAlerts === 'default' ? 'text-amber-300 hover:text-white' : 'text-[var(--chat-muted)]'"
            @click="enableDesktopAlerts">
            <FontAwesomeIcon :icon="desktopAlerts === 'default' ? 'fal fa-bell' : 'fal fa-bell-slash'" fixed-width aria-hidden="true" />
        </button>
    </div>
</template>
