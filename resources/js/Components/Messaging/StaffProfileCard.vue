<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { formatDistanceToNow } from "date-fns"
import { onClickOutside, useEventListener } from "@vueuse/core"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faClock, faComment, faPhone, faVideo, faChevronDown } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Image from "@/Common/Components/Image.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { isFocusingUntil } from "@/Composables/useFocusMode"
import { closeStaffProfile, keepStaffProfile, leaveStaffProfile, loadStaffProfileCard, staffProfileCard, type StaffProfileCardData } from "@/Composables/useStaffProfileCard"
import { useLayoutStore } from "@/Stores/layout"
import { useLiveUsers } from "@/Stores/active-users"
import { useStaffMessaging } from "@/Stores/staff-messaging"
import { useStaffCalls, type StaffCallKind } from "@/Stores/staff-calls"

library.add(faClock, faComment, faPhone, faVideo, faChevronDown)

const CARD_WIDTH = 304
const CARD_HEIGHT = 230
const GAP = 6
const ACTIVE_WINDOW_SECONDS = 15 * 60

const layout = useLayoutStore()
const liveUsers = useLiveUsers()
const store = useStaffMessaging()
const calls = useStaffCalls()

const card = ref<StaffProfileCardData | null>(null)
const cardEl = ref<HTMLElement | null>(null)
const isCallMenuOpen = ref(false)
const isWorking = ref(false)

watch(() => staffProfileCard.userId, async (userId) => {
    isCallMenuOpen.value = false
    card.value = null
    if (!userId) return
    try {
        const data = await loadStaffProfileCard(userId)
        if (staffProfileCard.userId === userId) card.value = data
    } catch {
        closeStaffProfile()
    }
})

const position = computed(() => {
    const anchor = staffProfileCard.anchor
    if (!anchor) return {}
    const left = Math.max(8, Math.min(anchor.left, window.innerWidth - CARD_WIDTH - 8))
    const fitsBelow = anchor.bottom + GAP + CARD_HEIGHT < window.innerHeight

    return fitsBelow
        ? { left: `${left}px`, top: `${anchor.bottom + GAP}px` }
        : { left: `${left}px`, bottom: `${window.innerHeight - anchor.top + GAP}px` }
})

const live = computed(() => card.value ? liveUsers.liveUsers[card.value.id] : undefined)
const isOnline = computed(() => live.value?.action === "navigate")
const isFocusing = computed(() => isFocusingUntil(live.value?.focus_until))
const isRecentlyActive = computed(() => !!card.value?.last_active_at && Date.now() / 1000 - card.value.last_active_at < ACTIVE_WINDOW_SECONDS)

const presence = computed<"active" | "idle" | "focus" | "offline">(() => {
    if (card.value?.is_me) return "active"
    if (isFocusing.value) return "focus"
    if (!isOnline.value) return "offline"
    return isRecentlyActive.value ? "active" : "idle"
})

const statusText = computed(() => {
    if (presence.value === "focus") {
        return ctrans("Focus mode until :time, please do not disturb", { time: useFormatTime(live.value?.focus_until ?? undefined, { formatTime: "HH:mm" }) })
    }
    if (presence.value === "active") return ctrans("Active")
    if (presence.value === "idle") return ctrans("Idle")
    return card.value?.last_active_at
        ? ctrans("Offline · last seen :ago", { ago: formatDistanceToNow(new Date(card.value.last_active_at * 1000), { addSuffix: true }) })
        : ctrans("Offline")
})

const localTime = computed(() => new Intl.DateTimeFormat(undefined, { timeStyle: "short" }).format(new Date()))

const organisationColour = (slug: string) => (layout.app as { organisation_colours?: Record<string, string> }).organisation_colours?.[slug] || "var(--chat-accent)"

const isLocal = computed(() => layout.app.environment === "local")

const openDirectChat = async () => {
    if (!card.value || isWorking.value) return
    isWorking.value = true
    try {
        const conversation = await store.openWithUser(card.value.id, { openWindow: false })
        closeStaffProfile()
        if (conversation) router.visit(route("grp.chat.staff.show", conversation.ulid))
    } finally {
        isWorking.value = false
    }
}

const call = async (kind: StaffCallKind) => {
    if (!card.value || isWorking.value) return
    isWorking.value = true
    try {
        const conversation = await store.openWithUser(card.value.id, { openWindow: false, forCall: true })
        closeStaffProfile()
        if (conversation) calls.start(conversation.ulid, kind)
    } finally {
        isWorking.value = false
    }
}

onClickOutside(cardEl, () => isCallMenuOpen.value = false)
useEventListener(window, ["wheel", "touchmove"], (event: Event) => !cardEl.value?.contains(event.target as Node) && closeStaffProfile(), { passive: true })
useEventListener(window, "keydown", (event: KeyboardEvent) => event.key === "Escape" && closeStaffProfile())
</script>

<template>
    <Teleport to="body">
        <div
            v-if="staffProfileCard.userId && staffProfileCard.anchor"
            ref="cardEl"
            class="staff-profile-card fixed z-[60] w-[19rem] rounded-xl border border-[var(--chat-line)] bg-[var(--chat-bg)] font-desk text-[var(--chat-text)] shadow-xl"
            :style="position"
            role="dialog"
            :aria-label="card?.name ?? ctrans('Loading…')"
            @mouseenter="keepStaffProfile"
            @mouseleave="leaveStaffProfile">
            <div class="flex items-center gap-x-3.5 p-4">
                <div class="h-[72px] w-[72px] shrink-0 overflow-hidden rounded-xl bg-[var(--chat-line)]">
                    <Image v-if="card?.avatar" :src="card.avatar" :alt="card.name" image-cover />
                    <span v-else-if="card" class="flex h-full w-full items-center justify-center text-2xl font-bold">{{ card.name[0] }}</span>
                    <span v-else class="skeleton block h-full w-full" />
                </div>
                <div v-if="card" class="min-w-0 flex-1">
                    <div class="flex items-center gap-x-1.5 text-[15px] font-bold">
                        <span class="truncate">{{ card.is_me ? ctrans(":name (you)", { name: card.name }) : card.name }}</span>
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="{
                                'bg-[var(--chat-green)]': presence === 'active',
                                'bg-[var(--chat-yellow)]': presence === 'idle',
                                'bg-violet-400': presence === 'focus',
                                'ring-[1.5px] ring-inset ring-[var(--chat-muted)]': presence === 'offline',
                            }"
                            :aria-label="statusText" />
                    </div>
                    <div v-for="organisation in card.organisations" :key="organisation.slug" class="mt-1 flex min-w-0 items-center gap-x-1.5 text-[13px] text-[var(--chat-muted)]">
                        <span class="flex h-4 shrink-0 items-center rounded px-1 text-[9px] font-bold uppercase text-white" :style="{ backgroundColor: organisationColour(organisation.slug) }">{{ organisation.code }}</span>
                        <span class="truncate">{{ organisation.name }}</span>
                    </div>
                    <div v-if="!card.is_me" class="mt-1 text-[12.5px] leading-snug text-[var(--chat-muted)]">{{ statusText }}</div>
                </div>
                <div v-else class="flex-1 space-y-2">
                    <span class="skeleton block h-4 w-28 rounded" />
                    <span class="skeleton block h-3 w-36 rounded" />
                </div>
            </div>

            <div v-if="card" class="profile-card-actions relative flex flex-col gap-y-2.5 rounded-b-xl bg-[var(--card-low)] px-4 pb-4 pt-3">
                <template v-if="card.is_me">
                    <div class="flex items-center gap-x-2 text-sm">
                        <FontAwesomeIcon icon="fal fa-clock" fixed-width aria-hidden="true" />
                        {{ ctrans(":time local time", { time: localTime }) }}
                    </div>
                    <!-- Placeholder only on local until Raul adds custom statuses; then drop the v-if and wire the dialog. -->
                    <Button v-if="isLocal" type="dashed" full :label="ctrans('Set a status')" v-tooltip="ctrans('Coming soon')" class="cursor-default" />
                </template>
                <div v-else class="flex gap-x-2">
                    <Button type="tertiary" class="flex-1 justify-center" :label="ctrans('Message')" :disabled="isWorking" @click="openDirectChat">
                        <template #icon>
                            <FontAwesomeIcon icon="fal fa-comment" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <div class="flex flex-1">
                        <Button type="tertiary" class="flex-1 justify-center rounded-r-none" :label="ctrans('Call')" :disabled="isWorking || presence === 'offline'" @click="call('voice')">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-phone" class="text-[var(--chat-green)]" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button
                            type="tertiary"
                            class="-ml-px rounded-l-none !px-2"
                            :tooltip="ctrans('More call options')"
                            :disabled="isWorking || presence === 'offline'"
                            :aria-expanded="isCallMenuOpen"
                            @click="isCallMenuOpen = !isCallMenuOpen">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-chevron-down" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </div>
                    <ul v-if="isCallMenuOpen" class="absolute left-[45%] top-[calc(100%-0.5rem)] z-10 w-48 rounded-lg border border-[var(--chat-line)] bg-[var(--card-low)] p-1 text-sm shadow-lg" role="menu">
                        <li>
                            <button type="button" role="menuitem" class="flex w-full items-center gap-x-2.5 rounded-md px-2.5 py-1.5 text-left hover:bg-[var(--card-hover)]" @click="call('voice')">
                                <FontAwesomeIcon icon="fal fa-phone" class="text-[var(--chat-green)]" fixed-width aria-hidden="true" />
                                {{ ctrans("Voice call") }}
                            </button>
                        </li>
                        <li>
                            <button type="button" role="menuitem" class="flex w-full items-center gap-x-2.5 rounded-md px-2.5 py-1.5 text-left hover:bg-[var(--card-hover)]" @click="call('video')">
                                <FontAwesomeIcon icon="fal fa-video" class="text-[var(--chat-green)]" fixed-width aria-hidden="true" />
                                {{ ctrans("Video call") }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.staff-profile-card {
    --card-low: color-mix(in srgb, var(--chat-bg) 90%, var(--chat-text));
    --card-hover: color-mix(in srgb, var(--chat-bg) 80%, var(--chat-text));
    --card-button-line: color-mix(in srgb, var(--chat-bg) 70%, var(--chat-text));
}

.profile-card-actions :deep(button.border) {
    border-color: var(--card-button-line);
    color: var(--chat-text);
}

.profile-card-actions :deep(button.border:hover:not(:disabled)) {
    background-color: var(--card-hover);
}

.profile-card-actions :deep(button.border:disabled) {
    background-color: transparent;
    opacity: 0.45;
}
</style>
