<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPhone, faPhoneSlash, faVideo, faTimes } from "@fal"
import { useStaffCalls, type StaffCall } from "@/Stores/staff-calls"
import { alertOnce, alertWayIsOn, chosenAlertSound, playChosenSound, soundBlocked } from "@/Composables/useNotificationSound"
import { isFocusOn } from "@/Composables/useFocusMode"

library.add(faPhone, faPhoneSlash, faVideo, faTimes)

const StaffCallPanel = defineAsyncComponent(() => import("@/Components/Messaging/Call/StaffCallPanel.vue"))

const store = useStaffCalls()
const now = ref(Date.now())
let clock: ReturnType<typeof setInterval> | null = null
let ringSoundTimer: ReturnType<typeof setInterval> | null = null
let titleTimer: ReturnType<typeof setInterval> | null = null
let originalTitle: string | null = null

const isRingTimeLeft = (call: StaffCall) => call.conversation_type !== "dm" || now.value - (call.local_started_at ?? now.value) < store.ringSeconds * 1000

const incoming = computed(() => Object.values(store.calls).filter((call) => store.isRingingMe(call) && isRingTimeLeft(call) && store.active?.call.ulid !== call.ulid))
const ringing = computed(() => incoming.value.filter((call) => call.conversation_type === "dm" && !store.active))

const stopRingAlerts = () => {
    if (ringSoundTimer) clearInterval(ringSoundTimer)
    if (titleTimer) clearInterval(titleTimer)
    ringSoundTimer = titleTimer = null
    if (originalTitle !== null) document.title = originalTitle
    originalTitle = null
}

watch(() => ringing.value.map((call) => call.ulid).join(","), (ulids) => {
    stopRingAlerts()
    const call = ringing.value[0]
    if (!ulids || !call) return
    const callingTitle = (call.kind === "screen" ? ctrans(":name wants to share their screen", { name: call.started_by_name }) : ctrans(":name is calling you", { name: call.started_by_name }))
    alertOnce({
        event: "colleague",
        key: `staff-call:${call.ulid}`,
        title: callingTitle,
        body: ctrans("Open aiku to answer"),
        tag: `staff-call-${call.ulid}`,
        sound: chosenAlertSound("colleague"),
    })
    ringSoundTimer = setInterval(() => {
        if (document.visibilityState === "visible" && alertWayIsOn("colleague", "sound") && !isFocusOn()) {
            playChosenSound(chosenAlertSound("colleague"))
        }
    }, 3000)
    originalTitle = document.title
    titleTimer = setInterval(() => (document.title = document.title === callingTitle ? originalTitle! : callingTitle), 1000)
})

onMounted(() => {
    store.init()
    clock = setInterval(() => (now.value = Date.now()), 1000)
})

onBeforeUnmount(() => {
    if (clock) clearInterval(clock)
    stopRingAlerts()
})
</script>

<template>
    <div v-if="incoming.length" class="fixed right-3 top-14 z-[80] flex w-[calc(100vw-1.5rem)] max-w-xs flex-col gap-2">
        <div
            v-for="call in incoming"
            :key="call.ulid"
            class="rounded-lg bg-white p-3 text-sm text-gray-900 shadow-xl ring-1 ring-gray-200"
            :class="call.conversation_type === 'dm' && !store.active && 'ring-2 ring-[var(--app-accent)]'"
            role="alertdialog"
            :aria-label="ctrans('Incoming call')">
            <div class="flex items-start gap-2">
                <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--app-accent-soft)] text-[var(--app-accent-strong)]" :class="call.conversation_type === 'dm' && !store.active && 'animate-pulse'">
                    <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">
                        {{ call.conversation_type === "dm" ? (call.kind === "screen" ? ctrans(":name wants to share their screen", { name: call.started_by_name }) : ctrans(":name is calling you", { name: call.started_by_name })) : ctrans(":name started a call", { name: call.started_by_name }) }}
                    </p>
                    <p v-if="call.conversation_type !== 'dm' && call.conversation_name" class="truncate text-xs text-gray-500">{{ call.conversation_name }}</p>
                    <p v-if="store.active" class="text-xs text-gray-500">{{ ctrans("Answering leaves your current call.") }}</p>
                    <p v-else-if="soundBlocked && call.conversation_type === 'dm'" class="text-xs text-gray-500">{{ ctrans("Click anywhere in aiku once to hear the ring.") }}</p>
                </div>
                <button v-if="call.conversation_type !== 'dm'" type="button" :aria-label="ctrans('Dismiss')" class="flex h-7 w-7 items-center justify-center rounded text-gray-400 hover:bg-gray-100" @click="store.decline(call)">
                    <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                </button>
            </div>
            <p v-if="store.error && store.error.conversationUlid === call.conversation_ulid" class="mt-2 text-xs text-red-600">{{ store.error.text }}</p>
            <div class="mt-3 flex gap-2">
                <button
                    v-if="call.conversation_type === 'dm'"
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-md bg-red-600 px-3 py-1.5 font-medium text-white transition duration-200 hover:bg-red-700"
                    @click="store.decline(call)">
                    <FontAwesomeIcon icon="fal fa-phone-slash" fixed-width aria-hidden="true" />
                    {{ ctrans("Decline") }}
                </button>
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-md bg-green-600 px-3 py-1.5 font-medium text-white transition duration-200 hover:bg-green-700 disabled:opacity-50"
                    :disabled="!!store.joining"
                    @click="store.accept(call, false)">
                    <FontAwesomeIcon icon="fal fa-phone" fixed-width aria-hidden="true" />
                    {{ call.conversation_type === "dm" ? ctrans("Accept") : ctrans("Join") }}
                </button>
                <button
                    type="button"
                    v-tooltip="ctrans('Answer with camera on')"
                    :aria-label="ctrans('Answer with camera on')"
                    class="flex items-center justify-center rounded-md bg-green-600 px-3 py-1.5 text-white transition duration-200 hover:bg-green-700 disabled:opacity-50"
                    :disabled="!!store.joining"
                    @click="store.accept(call, true)">
                    <FontAwesomeIcon icon="fal fa-video" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>

    <StaffCallPanel v-if="store.active" :key="store.active.call.ulid" :join="store.active" />
</template>
