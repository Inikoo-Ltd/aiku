<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhoneVolume, faPhoneSlash, faVideo, faMicrophone } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { useWhatsappCall } from "@/Composables/useWhatsappCall"

const props = defineProps<{ organisation: string }>()

const { call, isRinging, isLive, busy, micReady, elapsedSeconds, answer, end } = useWhatsappCall()

const formattedElapsed = computed(() => {
    const total = elapsedSeconds.value
    const minutes = Math.floor(total / 60)
        .toString()
        .padStart(2, "0")
    const seconds = (total % 60).toString().padStart(2, "0")

    return `${minutes}:${seconds}`
})

const isOutgoing = computed(() => call.value?.direction === "business_initiated")

const label = computed(() => {
    if (isRinging.value) return isOutgoing.value ? ctrans("Calling…") : ctrans("Incoming WhatsApp call")

    return micReady.value ? ctrans("On the call") : ctrans("Connecting…")
})
</script>

<template>
    <div
        v-if="call"
        class="flex items-center gap-x-3 rounded-md border border-green-300 bg-green-50 px-3 py-2"
    >
        <FontAwesomeIcon
            :icon="faPhoneVolume"
            class="text-green-600"
            :class="{ 'animate-pulse': isRinging }"
            aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
            <div class="truncate text-sm font-medium text-green-900">{{ label }}</div>
            <div class="truncate text-xs text-green-700">
                {{ call.phone_number }}
                <span v-if="isLive"> · {{ formattedElapsed }}</span>
            </div>
        </div>

        <FontAwesomeIcon
            v-if="isLive && micReady"
            :icon="faMicrophone"
            class="text-xs text-green-600"
            :title="ctrans('Microphone is live')"
            aria-hidden="true"
        />

        <!-- WhatsApp has no video calling API yet; the control is shown so the console is
             ready for it, and says why it cannot be used rather than being silently absent. -->
        <button
            type="button"
            disabled
            :title="ctrans('WhatsApp does not support video calls yet')"
            class="cursor-not-allowed rounded px-2 py-1 text-gray-400"
        >
            <FontAwesomeIcon :icon="faVideo" aria-hidden="true" />
            <span class="sr-only">{{ ctrans("Video call") }}</span>
        </button>

        <button
            v-if="isRinging && !isOutgoing"
            type="button"
            :disabled="busy"
            class="rounded bg-green-600 px-3 py-1 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
            @click="answer(props.organisation)"
        >
            {{ ctrans("Answer") }}
        </button>

        <button
            type="button"
            :disabled="busy"
            class="rounded bg-red-600 px-3 py-1 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
            @click="end(props.organisation)"
        >
            <FontAwesomeIcon :icon="faPhoneSlash" aria-hidden="true" />
            <span class="ml-1">{{ isRinging ? (isOutgoing ? ctrans("Cancel") : ctrans("Decline")) : ctrans("Hang up") }}</span>
        </button>
    </div>
</template>
