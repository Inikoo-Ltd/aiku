<!--
  - Author Louis Perez
  - Created on 24-11-2026-17h-20m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed } from "vue"
import { useMediaQuery } from "@vueuse/core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faDesktop, faMobile } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { desktopAlerts, enableDesktopAlerts } from "@/Composables/useNotificationSound"

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false })

const isTouchDevice = useMediaQuery("(pointer: coarse)")

const icon = computed(() => isTouchDevice.value ? faMobile : faDesktop)

const looks = {
    granted: "text-green-400 bg-green-400/15 ring-1 ring-green-400/60 shadow-[0_0_10px_rgba(74,222,128,0.45)]",
    default: "text-[var(--chat-muted)] ring-1 ring-[var(--chat-line)] hover:text-white",
    denied: "text-[var(--chat-muted)] ring-1 ring-[var(--chat-line)] cursor-help",
}

const look = computed(() => looks[desktopAlerts.value as keyof typeof looks] ?? looks.default)

const tooltip = computed(() => {
    if (desktopAlerts.value === "granted") {
        return ctrans("Desktop alerts are on")
    }

    return desktopAlerts.value === "denied"
        ? ctrans("Desktop alerts are blocked in this browser. Allow them in the browser settings")
        : ctrans("Turn on desktop alerts")
})

const onClick = () => {
    if (desktopAlerts.value === "default") {
        enableDesktopAlerts()
    }
}
</script>

<template>
    <button
        v-if="desktopAlerts !== 'unsupported'"
        v-tooltip="tooltip"
        type="button"
        data-rail="desktop_alerts"
        :aria-label="tooltip"
        class="relative flex shrink-0 items-center justify-center rounded-full transition-colors"
        :class="[look, compact ? 'h-6 w-6 text-[11px]' : 'h-7 w-7 text-xs']"
        @click="onClick">
        <FontAwesomeIcon :icon="icon" fixed-width aria-hidden="true" />
        <span v-if="desktopAlerts === 'denied'" class="absolute h-px w-4 rotate-45 bg-current" aria-hidden="true" />
    </button>
</template>
