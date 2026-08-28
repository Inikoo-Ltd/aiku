<!--
    -  Author: Raul Perusquia <raul@inikoo.com>
    -  Created: Thu, 27 Aug 2026, Kuala Lumpur, Malaysia
    -  Copyright (c) 2026, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBarcodeRead, faCheckCircle, faTimesCircle, faExclamationTriangle, faMapMarkerAlt } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { playNotificationSound } from "@/Composables/useNotificationSound"
import { useBarcodeScanner, useScanQueue } from "@/Composables/useBarcodeScanner"
import { routeType } from "@/types/route"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import Toggle from "@/Components/Pure/Toggle.vue"

library.add(faBarcodeRead, faCheckCircle, faTimesCircle, faExclamationTriangle, faMapMarkerAlt)

const props = defineProps<{
    scanRoute: routeType
    reloadProps?: string[]
}>()

type ScanStatus = "picked" | "already_picked" | "not_found" | "wrong_state" | "error"

type ScanOutcome = {
    status: ScanStatus
    message: string
    scanned: string
    item: {
        id: number
        type: string
        reference: string
        quantity_ordered: number
        quantity_picked: number
        quantity_to_pick: number
        location_code: string | null
    } | null
    pallet_return_state: string
    remaining_to_pick: number
}

type ScanLogEntry = ScanOutcome & { key: number }

const MAX_LOG_ENTRIES = 8

const isProcessing = ref(false)
const pickedCount = ref(0)
const remainingToPick = ref<number | null>(null)
const lastOutcome = ref<ScanOutcome | null>(null)
const scanLog = ref<ScanLogEntry[]>([])

let logKey = 0

const statusStyles: Record<ScanStatus, { wrapper: string; icon: string; iconClass: string }> = {
    picked: { wrapper: "border-green-500 bg-green-50 text-green-800", icon: "fal fa-check-circle", iconClass: "text-green-600" },
    already_picked: { wrapper: "border-sky-500 bg-sky-50 text-sky-800", icon: "fal fa-exclamation-triangle", iconClass: "text-sky-600" },
    not_found: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
    wrong_state: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
    error: { wrapper: "border-red-500 bg-red-50 text-red-800", icon: "fal fa-times-circle", iconClass: "text-red-600" },
}

const lastOutcomeStyle = computed(() => (lastOutcome.value ? statusStyles[lastOutcome.value.status] : statusStyles.error))

const { queuedCount, enqueueScan } = useScanQueue<string>((code) => submitScan(code))

const { buffer, inputElement, isListening, registerKeystroke, clearBuffer, flushBuffer } = useBarcodeScanner(
    (code) => enqueueScan(code)
)

const submitScan = async (code: string) => {
    isProcessing.value = true

    try {
        const { data } = await axios.post(route(props.scanRoute.name, props.scanRoute.parameters), {
            barcode: code,
        })

        applyOutcome(data as ScanOutcome)
    } catch (error: any) {
        applyOutcome({
            status: "error",
            message: error?.response?.data?.message || ctrans("Could not pick :code, try again", { code }),
            scanned: code,
            item: null,
            pallet_return_state: "",
            remaining_to_pick: remainingToPick.value ?? 0,
        })
    } finally {
        isProcessing.value = false
    }
}

const applyOutcome = (outcome: ScanOutcome) => {
    lastOutcome.value = outcome
    scanLog.value.unshift({ ...outcome, key: ++logKey })
    scanLog.value = scanLog.value.slice(0, MAX_LOG_ENTRIES)

    if (outcome.pallet_return_state) {
        remainingToPick.value = outcome.remaining_to_pick
    }

    if (outcome.status === "picked") {
        pickedCount.value += 1
        const isItemFinished = (outcome.item?.quantity_to_pick ?? 0) <= 0
        playNotificationSound({ frequency: isItemFinished ? 1180 : 880, duration: 90 })
        // The pallet return tables read their rows from Inertia props, so a partial reload is how
        // a pick lands on screen; there is no per-row fetch endpoint to update them in place.
        router.reload({ only: props.reloadProps ?? ["pallets", "stored_items", "box_stats", "pageHead", "data"] })
    } else {
        playNotificationSound({ frequency: 200, duration: 280, type: "square" })
    }
}
</script>

<template>
    <div class="p-2">
        <div class="rounded-lg border border-emerald-300 bg-emerald-50/60 px-4 py-3">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <div class="flex items-center gap-2 text-emerald-900">
                    <FontAwesomeIcon :icon="faBarcodeRead" class="text-2xl" fixed-width aria-hidden="true" />
                    <span class="font-semibold uppercase tracking-wider text-sm">{{ ctrans("Scan to pick") }}</span>
                </div>

                <div class="relative flex-1 min-w-64">
                    <input
                        ref="inputElement"
                        v-model="buffer"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :disabled="!isListening"
                        :placeholder="ctrans('Scan the pallet or item label, or type its reference then press Enter')"
                        class="w-full rounded-md border-emerald-300 bg-white py-2 pl-3 pr-10 font-mono text-lg tracking-wide focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100"
                        @keydown.enter.prevent="flushBuffer"
                        @keydown.esc.prevent="clearBuffer"
                        @input="registerKeystroke"
                    />
                    <span v-if="isProcessing" class="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-500">
                        <LoadingIcon fixed-width aria-hidden="true" />
                    </span>
                </div>

                <div class="flex items-center gap-x-4">
                    <div v-if="queuedCount" class="text-sm text-emerald-700">
                        {{ ctrans(":queueCount queued", { queueCount: queuedCount }) }}
                    </div>
                    <div class="text-center">
                        <div class="text-xl font-bold leading-none tabular-nums text-green-700">{{ pickedCount }}</div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-500">{{ ctrans("Scanned") }}</div>
                    </div>
                    <div v-if="remainingToPick !== null" class="text-center">
                        <div class="text-xl font-bold leading-none tabular-nums text-amber-700">{{ remainingToPick }}</div>
                        <div class="text-[11px] uppercase tracking-wide text-gray-500">{{ ctrans("Left") }}</div>
                    </div>
                    <div class="flex items-center gap-2" v-tooltip="ctrans('Capture scanner input anywhere on this page')">
                        <span class="text-xs text-gray-600">{{ ctrans("Listening") }}</span>
                        <Toggle v-model="isListening" />
                    </div>
                </div>
            </div>

            <div
                v-if="lastOutcome"
                class="mt-3 flex items-center gap-3 rounded-md border-l-4 px-3 py-2"
                :class="lastOutcomeStyle.wrapper">
                <FontAwesomeIcon :icon="lastOutcomeStyle.icon" :class="lastOutcomeStyle.iconClass" class="text-xl" fixed-width aria-hidden="true" />
                <div
                    v-if="lastOutcome.item?.location_code"
                    v-tooltip="ctrans('Take it from this location')"
                    class="flex items-center gap-1.5 whitespace-nowrap rounded bg-white/70 px-2 py-1 font-mono text-base font-bold">
                    <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-sm" fixed-width aria-hidden="true" />
                    {{ lastOutcome.item.location_code }}
                </div>
                <div class="min-w-0">
                    <div class="font-semibold truncate">{{ lastOutcome.message }}</div>
                </div>
                <div class="ml-auto font-mono text-xs opacity-70">
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
