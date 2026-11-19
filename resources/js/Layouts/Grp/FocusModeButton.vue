<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue"
import Menu from "primevue/menu"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMoon } from "@fal"
import { faMoon as fasMoon } from "@fas"
import { ctrans } from "@/Composables/useTrans"
import { FOCUS_MINUTES, focusUntil, isFocusOn, setFocus } from "@/Composables/useFocusMode"

library.add(faMoon, fasMoon)

const menu = ref()
const now = ref(Date.now())
const errorMessage = ref("")
let ticker: ReturnType<typeof setInterval> | undefined

onMounted(() => {
    isFocusOn()
    ticker = setInterval(() => (now.value = Date.now()), 30_000)
})
onUnmounted(() => clearInterval(ticker))

const isOn = computed(() => !!focusUntil.value && Date.parse(focusUntil.value) > now.value)
const endsAt = computed(() => (focusUntil.value ? new Date(focusUntil.value).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }) : ""))

const durationLabel = (minutes: number) =>
    minutes < 60 ? ctrans(":minutes minutes", { minutes: String(minutes) }) : ctrans(":hours h", { hours: String(minutes / 60) })

const choose = async (minutes: number) => {
    errorMessage.value = ""
    try {
        await setFocus(minutes)
        now.value = Date.now()
    } catch {
        errorMessage.value = ctrans("Focus mode could not be changed")
    }
}

const items = computed(() => [
    {
        label: isOn.value ? ctrans("Focus until :time", { time: endsAt.value }) : ctrans("Focus: no pop-ups, sounds, Slack or phone alerts"),
        items: [
            ...FOCUS_MINUTES.map((minutes) => ({ label: durationLabel(minutes), command: () => choose(minutes) })),
            ...(isOn.value ? [{ label: ctrans("Turn off focus"), command: () => choose(0) }] : []),
        ],
    },
])
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="h-7 flex items-center gap-x-1.5 rounded-md px-2 text-xs ring-1 focus:outline-none focus:ring-2 focus:ring-[var(--app-accent)]"
            :class="isOn ? 'ring-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] text-[var(--app-accent-deep)]' : 'ring-gray-300 text-gray-500 hover:bg-gray-200'"
            :title="errorMessage || (isOn ? ctrans('Focus until :time', { time: endsAt }) : ctrans('Focus mode'))"
            aria-haspopup="true"
            @click="(event) => menu.toggle(event)">
            <FontAwesomeIcon :icon="isOn ? 'fas fa-moon' : 'fal fa-moon'" fixed-width aria-hidden="true" />
            <span v-if="isOn" class="whitespace-nowrap">{{ endsAt }}</span>
            <span v-else class="sr-only">{{ ctrans("Focus mode") }}</span>
        </button>
        <Menu ref="menu" :model="items" popup />
        <p v-if="errorMessage" class="absolute right-0 top-full mt-1 whitespace-nowrap text-xs text-red-600">{{ errorMessage }}</p>
    </div>
</template>
