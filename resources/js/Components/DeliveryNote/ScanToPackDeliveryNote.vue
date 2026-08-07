<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Tue, 28 July 2026 10:00:00 Bali, Indonesia
    -  Copyright (c) 2026, Vika Aqordi
-->

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faCheckCircle, faTimesCircle, faExclamationTriangle } from "@fal"
import { debounce } from "lodash-es"
import ToggleSwitch from "primevue/toggleswitch"
import { ctrans } from "@/Composables/useTrans"
import { playNotificationSound } from "@/Composables/useNotificationSound"
import { routeType } from "@/types/route"
import LoadingIcon from "../Utils/LoadingIcon.vue"
import Toggle from "../Pure/Toggle.vue"

library.add(faBarcodeRead, faCheckCircle, faTimesCircle, faExclamationTriangle)

const props = defineProps<{
    scanRoute: routeType
    tab?: string
}>()

const emits = defineEmits<{
    scanned: [payload: ScanOutcome]
}>()

type ScanStatus = "packed" | "already_packed" | "nothing_to_pack" | "not_found" | "wrong_state" | "error"

// delivery_note and picking_session_state only come back when the panel is packing a whole picking
// session, where the packer has to be told which box the scan just went into.
type ScanOutcome = {
    status: ScanStatus
    message: string
    scanned: string
    item: {
        id: number
        code: string
        name: string
        quantity_picked: number
        quantity_packed: number
        quantity_to_pack: number
    } | null
    delivery_note?: {
        id: number
        reference: string
        state: string
    } | null
    row: Record<string, any> | null
    delivery_note_state: string
    picking_session_state?: string
    remaining_to_pack: number
}

// quantity null means "everything still left on the item", which is what the pack-the-rest button
// sends. itemId pins that follow up to the line that was actually scanned.
type PendingScan = { code: string; quantity: number | null; itemId?: number }

type ScanLogEntry = ScanOutcome & { key: number }

// A keyboard wedge scanner types the whole code in a few milliseconds. Anything typed faster than
// this threshold is treated as machine input, which is what lets us auto submit codes coming from
// scanners configured without an Enter suffix without also submitting half typed manual entries.
const MACHINE_KEYSTROKE_INTERVAL_MS = 40
const IDLE_SUBMIT_DELAY_MS = 120
const MIN_MACHINE_CODE_LENGTH = 3
const MAX_LOG_ENTRIES = 8

const buffer = ref("")
const inputElement = ref<HTMLInputElement | null>(null)
const isListening = ref(true)
const isProcessing = ref(false)
const queuedCount = ref(0)
const packedCount = ref(0)
const remainingToPack = ref<number | null>(null)
const lastOutcome = ref<ScanOutcome | null>(null)
const scanLog = ref<ScanLogEntry[]>([])

let lastKeystrokeAt = 0
let looksLikeMachineInput = false
let logKey = 0
const pendingScans: PendingScan[] = []
let isDraining = false

const statusStyles: Record<ScanStatus, { wrapper: string; icon: string; iconClass: string }> = {
    packed: { wrapper: "border-green-500 bg-green-50 text-green-800", icon: "fal fa-check-circle", iconClass: "text-green-600" },
    already_packed: { wrapper: "border-sky-500 bg-sky-50 text-sky-800", icon: "fal fa-exclamation-triangle", iconClass: "text-sky-600" },
    nothing_to_pack: { wrapper: "border-amber-500 bg-amber-50 text-amber-800", icon: "fal fa-exclamation-triangle", iconClass: "text-amber-600" },
    not_found: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
    wrong_state: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
    error: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
}

const lastOutcomeStyle = computed(() => (lastOutcome.value ? statusStyles[lastOutcome.value.status] : statusStyles.error))

// Keystrokes are only hijacked while the packer is not interacting with something else, so the
// scanner works without ever clicking the field but the table search, modals and buttons still
// behave normally.
const shouldIgnoreKeydown = (element: EventTarget | null) => {
    const node = element as HTMLElement | null

    if (!node || node === inputElement.value) {
        return false
    }

    if (document.querySelector(".p-dialog, [role='dialog']")) {
        return true
    }

    return (
        node.tagName === "INPUT" ||
        node.tagName === "TEXTAREA" ||
        node.tagName === "SELECT" ||
        node.tagName === "BUTTON" ||
        node.tagName === "A" ||
        node.getAttribute("role") === "button" ||
        node.isContentEditable
    )
}

// A scanner configured without an Enter suffix never terminates the code, so the quiet gap right
// after a burst of machine speed keystrokes is what marks the code as complete.
const submitWhenScannerWentQuiet = debounce(() => {
    if (looksLikeMachineInput && buffer.value.trim().length >= MIN_MACHINE_CODE_LENGTH) {
        flushBuffer()
    }
}, IDLE_SUBMIT_DELAY_MS)

const registerKeystroke = () => {
    const now = performance.now()
    looksLikeMachineInput = now - lastKeystrokeAt < MACHINE_KEYSTROKE_INTERVAL_MS
    lastKeystrokeAt = now

    submitWhenScannerWentQuiet()
}

const clearBuffer = () => {
    submitWhenScannerWentQuiet.cancel()
    buffer.value = ""
    looksLikeMachineInput = false
}

const flushBuffer = () => {
    submitWhenScannerWentQuiet.cancel()

    const code = buffer.value.trim()
    buffer.value = ""
    looksLikeMachineInput = false

    if (!code) {
        return
    }

    // One scan is one physical item, which is what makes partial packing natural: scan as many times
    // as went into the box, and use the pack-the-rest button to finish the line in one go.
    pendingScans.push({ code, quantity: 1 })
    queuedCount.value = pendingScans.length
    drainQueue()
}

const packRestOfLastScannedItem = () => {
    const outcome = lastOutcome.value

    if (!outcome?.item || outcome.item.quantity_to_pack <= 0) {
        return
    }

    pendingScans.push({ code: outcome.scanned, quantity: null, itemId: outcome.item.id })
    queuedCount.value = pendingScans.length
    inputElement.value?.focus()
    drainQueue()
}

const drainQueue = async () => {
    if (isDraining) {
        return
    }

    isDraining = true

    while (pendingScans.length) {
        const scan = pendingScans.shift() as PendingScan
        queuedCount.value = pendingScans.length
        await submitScan(scan)
    }

    isDraining = false
}

const submitScan = async ({ code, quantity, itemId }: PendingScan) => {
    isProcessing.value = true

    try {
        const { data } = await axios.post(route(props.scanRoute.name, props.scanRoute.parameters), {
            barcode: code,
            tab: props.tab,
            ...(quantity === null ? {} : { quantity }),
            ...(itemId === undefined ? {} : { delivery_note_item_id: itemId }),
        })

        applyOutcome(data as ScanOutcome)
    } catch (error: any) {
        applyOutcome({
            status: "error",
            message:
                error?.response?.data?.message ||
                ctrans("Could not pack :code, try again", { code }),
            scanned: code,
            item: null,
            row: null,
            delivery_note_state: "",
            remaining_to_pack: remainingToPack.value ?? 0,
        })
    } finally {
        isProcessing.value = false
    }
}

const applyOutcome = (outcome: ScanOutcome) => {
    lastOutcome.value = outcome
    scanLog.value.unshift({ ...outcome, key: ++logKey })
    scanLog.value = scanLog.value.slice(0, MAX_LOG_ENTRIES)

    if (outcome.delivery_note_state) {
        remainingToPack.value = outcome.remaining_to_pack
    }

    if (outcome.status === "packed") {
        packedCount.value += 1
        // A partial pack is a success but the item is not finished, so it gets its own pitch to keep
        // the packer from walking away on a beep that only means "some of it went in the box".
        const isItemFinished = (outcome.item?.quantity_to_pack ?? 0) <= 0
        playNotificationSound({ frequency: isItemFinished ? 1180 : 880, duration: 90 })
    } else if (outcome.status === "already_packed") {
        playNotificationSound({ frequency: 660, duration: 110 })
    } else {
        playNotificationSound({ frequency: 200, duration: 280, type: "square" })
    }

    emits("scanned", outcome)
}

const onKeydown = (event: KeyboardEvent) => {
    if (!isListening.value) {
        return
    }

    if (event.target === inputElement.value) {
        return
    }

    if (shouldIgnoreKeydown(event.target) || event.ctrlKey || event.metaKey || event.altKey) {
        return
    }

    if (event.key === "Enter") {
        if (buffer.value) {
            event.preventDefault()
            flushBuffer()
        }
        return
    }

    if (event.key === "Escape") {
        clearBuffer()
        return
    }

    if (event.key.length !== 1) {
        return
    }

    event.preventDefault()
    buffer.value += event.key
    registerKeystroke()
    inputElement.value?.focus()
}

onMounted(() => {
    window.addEventListener("keydown", onKeydown)
    inputElement.value?.focus()
})

onBeforeUnmount(() => {
    window.removeEventListener("keydown", onKeydown)
    submitWhenScannerWentQuiet.cancel()
})
</script>

<template>
    <div class="p-2">
        <div class="rounded-lg border border-indigo-300 bg-indigo-50/60 px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div class="flex items-center gap-2 text-indigo-900">
                    <FontAwesomeIcon :icon="faBarcodeRead" class="text-2xl" fixed-width aria-hidden="true" />
                    <span class="font-semibold uppercase tracking-wider text-sm">{{ ctrans("Scan to pack") }}</span>
                </div>

                <div class="relative flex-1 min-w-64">
                    <input
                        ref="inputElement"
                        v-model="buffer"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :disabled="!isListening"
                        :placeholder="ctrans('Scan barcode or type the code then press Enter')"
                        class="w-full rounded-md border-indigo-300 bg-white py-2 pl-3 pr-10 font-mono text-lg tracking-wide focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100"
                        @keydown.enter.prevent="flushBuffer"
                        @keydown.esc.prevent="clearBuffer"
                        @input="registerKeystroke"
                    />
                    <span
                            v-if="isProcessing"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-indigo-500">
                        <LoadingIcon
                            fixed-width
                            aria-hidden="true" />
                    </span>
                </div>

                <div class="flex items-center gap-x-4">
                    <div v-if="queuedCount" class="text-sm text-indigo-700">
                        {{ ctrans(":queueCount queued", { queueCount: queuedCount }) }}
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold leading-none tabular-nums text-green-700">{{ packedCount }}</div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-500">{{ ctrans("Scanned") }}</div>
                    </div>
                    <div v-if="remainingToPack !== null" class="text-center">
                        <div class="text-xl font-bold leading-none tabular-nums text-amber-700">{{ remainingToPack }}</div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-500">{{ ctrans("Left") }}</div>
                    </div>
                    <div class="flex items-center gap-2" v-tooltip="ctrans('Capture scanner input anywhere on this page')">
                        <span class="text-xs text-gray-600">{{ ctrans("Listening") }}</span>
                        <!-- <ToggleSwitch v-model="isListening" /> -->
                        <Toggle v-model="isListening"  />
                    </div>
                </div>
            </div>

            <div
                v-if="lastOutcome"
                class="mt-3 flex items-center gap-3 rounded-md border-l-4 px-3 py-2"
                :class="lastOutcomeStyle.wrapper">
                <FontAwesomeIcon :icon="lastOutcomeStyle.icon" :class="lastOutcomeStyle.iconClass" class="text-xl" fixed-width aria-hidden="true" />
                <div
                    v-if="lastOutcome.delivery_note?.reference"
                    v-tooltip="ctrans('Put it in this box')"
                    class="whitespace-nowrap rounded bg-white/70 px-2 py-1 font-mono text-base font-bold">
                    {{ lastOutcome.delivery_note.reference }}
                </div>
                <div class="min-w-0">
                    <div class="font-semibold truncate">{{ lastOutcome.message }}</div>
                    <div v-if="lastOutcome.item?.name" class="text-xs opacity-80 truncate">
                        {{ lastOutcome.item.name }}
                    </div>
                </div>
                <div
                    v-if="lastOutcome.item && lastOutcome.item.quantity_to_pack > 0"
                    class="ml-auto flex items-center gap-x-2">
                    <span class="whitespace-nowrap rounded bg-amber-950 px-2 py-1 text-sm font-bold text-amber-50">
                        {{ ctrans(":remaining left on this item", { remaining: lastOutcome.item.quantity_to_pack }) }}
                    </span>

                    <!-- mousedown.prevent stops the button from taking focus at all, so the scanner
                         keeps feeding the scan field. The explicit focus() covers keyboard and touch
                         activation, where no mousedown fires. -->
                    <button
                        type="button"
                        :disabled="isProcessing || queuedCount > 0"
                        v-tooltip="ctrans('Pack the remaining :remaining without scanning them one by one', { remaining: lastOutcome.item.quantity_to_pack })"
                        class="whitespace-nowrap rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                        @mousedown.prevent
                        @click="packRestOfLastScannedItem">
                        {{ ctrans("Pack all :remaining", { remaining: lastOutcome.item.quantity_to_pack }) }}
                    </button>
                </div>
                <div class="font-mono text-xs opacity-70" :class="{ 'ml-auto': !(lastOutcome.item?.quantity_to_pack > 0) }">
                    {{ lastOutcome.scanned }}
                </div>
            </div>

            <div v-if="scanLog.length > 1" class="mt-2 flex flex-wrap gap-1.5">
                <span
                    v-for="entry in scanLog.slice(1)"
                    :key="entry.key"
                    v-tooltip="entry.message"
                    class="rounded border px-1.5 py-0.5 font-mono text-[11px]"
                    :class="statusStyles[entry.status].wrapper">
                    {{ entry.scanned }}
                </span>
            </div>
        </div>
    </div>
</template>
