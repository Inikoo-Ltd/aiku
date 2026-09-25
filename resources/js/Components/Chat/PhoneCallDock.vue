<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhoneVolume, faPhoneSlash, faChevronDown } from "@fas"
import PhoneCallModal from "@/Components/Chat/PhoneCallModal.vue"
import PhoneCallForm from "@/Components/Chat/PhoneCallForm.vue"
import { ctrans } from "@/Composables/useTrans"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"

const { state, modalOpen, closeModal, formattedElapsed, isRunningOut, fetchActive, warnIfRunningOut } =
    useChatPhoneCall()

// Only somebody who works chat can be on one of these calls, so nobody else pays for the request.
const isAgent = computed(() => usePage().props?.auth?.user?.is_agent === true)

const POSITION_KEY = "chat-phone-call-dock-x"

const wrapperRef = ref<HTMLElement | null>(null)
const popupOpen = ref(false)
const left = ref<number | null>(null)

let startPointerX = 0
let startLeft = 0
let dragging = false
let justDragged = false

const wrapperWidth = () => wrapperRef.value?.offsetWidth ?? 448

const clamp = (value: number) => {
    const limit = Math.max(0, window.innerWidth - wrapperWidth())

    return Math.min(Math.max(value, 0), limit)
}

const centred = () => clamp((window.innerWidth - wrapperWidth()) / 2)

const readStoredLeft = (): number | null => {
    try {
        const stored = window.localStorage.getItem(POSITION_KEY)

        return stored === null ? null : Number(stored)
    } catch (e) {
        return null
    }
}

const storeLeft = (value: number) => {
    try {
        window.localStorage.setItem(POSITION_KEY, String(Math.round(value)))
    } catch (e) {
        // A browser refusing storage costs the remembered position and nothing else.
    }
}

const placeInitially = () => {
    const stored = readStoredLeft()

    left.value = clamp(stored === null || Number.isNaN(stored) ? centred() : stored)
}

const onPointerMove = (event: PointerEvent) => {
    if (!dragging) return

    const delta = event.clientX - startPointerX

    if (Math.abs(delta) > 3) {
        justDragged = true
    }

    left.value = clamp(startLeft + delta)
}

const onPointerUp = () => {
    if (!dragging) return

    dragging = false
    window.removeEventListener("pointermove", onPointerMove)
    window.removeEventListener("pointerup", onPointerUp)

    if (justDragged && left.value !== null) {
        storeLeft(left.value)
    }

    // The click that follows this pointerup would otherwise open the popup at the end of a drag.
    setTimeout(() => {
        justDragged = false
    }, 0)
}

// Sideways only: the bar sits under the top bar where it belongs, and moves out of the way of
// whatever link it is covering rather than wandering over the page.
const beginDrag = (event: PointerEvent) => {
    if (event.button !== 0) return

    dragging = true
    justDragged = false
    startPointerX = event.clientX
    startLeft = left.value ?? centred()

    window.addEventListener("pointermove", onPointerMove)
    window.addEventListener("pointerup", onPointerUp)
}

// The popup and the modal are the same form, so only one of them is ever on screen: two would
// put two confirmation popovers behind one "Cancel call".
const togglePopup = () => {
    if (justDragged) return

    popupOpen.value = !popupOpen.value

    if (popupOpen.value) {
        closeModal()
    }
}

const openPopup = () => {
    if (justDragged) return

    popupOpen.value = true
    closeModal()
}

const onResize = () => {
    if (left.value !== null) {
        left.value = clamp(left.value)
    }
}

onMounted(() => {
    if (isAgent.value && !state.loaded) {
        fetchActive()
    }

    window.addEventListener("resize", onResize)
})

onBeforeUnmount(() => {
    window.removeEventListener("resize", onResize)
    window.removeEventListener("pointermove", onPointerMove)
    window.removeEventListener("pointerup", onPointerUp)
})

watch(isRunningOut, () => warnIfRunningOut())

watch(modalOpen, (isOpen) => {
    if (isOpen) {
        popupOpen.value = false
    }
})

watch(
    () => state.call,
    (call) => {
        if (call) {
            requestAnimationFrame(placeInitially)
        } else {
            popupOpen.value = false
        }
    },
    { immediate: true }
)
</script>

<template>
    <div v-if="state.call" ref="wrapperRef" class="fixed top-[42px] z-[95] w-full max-w-md px-3"
        :style="left === null ? { left: '50%', transform: 'translateX(-50%)' } : { left: `${left}px` }">
        <div @pointerdown="beginDrag" @click="togglePopup"
            class="w-full flex items-center gap-3 rounded-full px-4 py-2 shadow-lg ring-1 select-none cursor-grab active:cursor-grabbing transition-colors"
            :class="isRunningOut
                ? 'bg-amber-500 ring-amber-600 text-white hover:bg-amber-600'
                : 'bg-emerald-600 ring-emerald-700 text-white hover:bg-emerald-700'">
            <span class="relative flex h-2.5 w-2.5 shrink-0">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75" />
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white" />
            </span>
            <FontAwesomeIcon :icon="faPhoneVolume" class="text-sm shrink-0" fixed-width />
            <span class="flex-1 truncate text-left text-sm font-medium">
                {{ ctrans("You are on a phone call") }}
            </span>
            <span class="text-sm font-semibold tabular-nums">{{ formattedElapsed }}</span>

            <button type="button" @click.stop="openPopup" v-tooltip="ctrans('End the call')"
                class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full bg-white text-red-600 transition-colors hover:bg-red-50">
                <FontAwesomeIcon :icon="faPhoneSlash" class="text-[11px]" fixed-width />
            </button>

            <button type="button" @click.stop="togglePopup"
                v-tooltip="popupOpen ? ctrans('Hide the call') : ctrans('Open the call')"
                class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full transition-transform hover:bg-white/20"
                :class="popupOpen ? 'rotate-180' : ''">
                <FontAwesomeIcon :icon="faChevronDown" class="text-[11px]" fixed-width />
            </button>
        </div>

        <!-- The same call as the modal, opened where it is standing rather than over the page. -->
        <div v-if="popupOpen"
            class="mt-2 max-h-[70vh] overflow-y-auto rounded-xl border border-gray-200 bg-white px-4 pb-4 pt-1 shadow-2xl">
            <PhoneCallForm :open="popupOpen" compact @close="popupOpen = false" />
        </div>
    </div>

    <PhoneCallModal v-if="isAgent" />
</template>
