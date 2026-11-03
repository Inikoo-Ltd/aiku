<!--
  - Author Louis Perez
  - Created on 03-11-2026-14h-50m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from "vue"
import axios from "axios"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRobot, faSpinner, faCheck, faTimes, faQuestion, faExclamationTriangle, faRedo, faMinus } from "@fal"
library.add(faRobot, faSpinner, faCheck, faTimes, faQuestion, faExclamationTriangle, faRedo, faMinus)

export interface ArtworkAiCheckResult {
    carries_label_text: boolean
    summary: string
    items: { key: string, group: "text" | "pictogram" | "mark", label: string, expected: string | null, status: "matches" | "differs" | "missing" | "unclear" | "not_on_artwork", note: string | null }[]
    issues: { kind: "spelling" | "inconsistency" | "legibility" | "other", text: string, quote: string | null }[]
    barcodes: { level: string | null, label: string, value: string, status: "found" | "read_by_ai" | "not_found" | "unknown" }[]
    text_available: boolean
}

export interface ArtworkAiCheckPayload {
    version: number
    state: "checking" | "done" | "failed"
    result: ArtworkAiCheckResult | null
    error: string | null
    requested_by: string | null
    checked_at: string | null
}

export interface ArtworkAiCheck {
    can_run: boolean
    check: ArtworkAiCheckPayload | null
    routes: { run: routeType, show: routeType }
}

const props = defineProps<{
    aiCheck: ArtworkAiCheck
    version: number
}>()

const check = ref<ArtworkAiCheckPayload | null>(props.aiCheck.check)
const starting = ref(false)
const startError = ref<string | null>(null)
let pollTimer: ReturnType<typeof setTimeout> | null = null

watch(() => props.aiCheck.check, (value) => {
    check.value = value
    poll()
})

function stopPolling() {
    if (pollTimer) {
        clearTimeout(pollTimer)
        pollTimer = null
    }
}

function poll() {
    stopPolling()
    if (check.value?.state !== "checking") {
        return
    }
    pollTimer = setTimeout(async () => {
        try {
            const { data } = await axios.get(route(props.aiCheck.routes.show.name, props.aiCheck.routes.show.parameters))
            check.value = data.check
        } finally {
            poll()
        }
    }, 4000)
}

async function run() {
    starting.value = true
    startError.value = null
    try {
        const { data } = await axios.post(route(props.aiCheck.routes.run.name, props.aiCheck.routes.run.parameters))
        check.value = data
        poll()
    } catch (error: any) {
        startError.value = error?.response?.data?.errors ? String(Object.values(error.response.data.errors)[0]) : ctrans("The check could not be started, please try again")
    } finally {
        starting.value = false
    }
}

poll()
onBeforeUnmount(stopPolling)

const result = computed(() => check.value?.state === "done" ? check.value.result : null)
const problems = computed(() => result.value
    ? result.value.items.filter((item) => item.status === "differs" || item.status === "missing").length
        + result.value.issues.length
        + result.value.barcodes.filter((barcode) => barcode.status === "not_found" || barcode.status === "unknown").length
    : 0)

const itemStatus: Record<string, { icon: string, class: string, label: string }> = {
    matches: { icon: "fal fa-check", class: "text-green-600", label: ctrans("Matches") },
    differs: { icon: "fal fa-times", class: "text-red-600", label: ctrans("Different") },
    missing: { icon: "fal fa-exclamation-triangle", class: "text-amber-600", label: ctrans("Not found") },
    unclear: { icon: "fal fa-question", class: "text-gray-500", label: ctrans("Could not read") },
    not_on_artwork: { icon: "fal fa-minus", class: "text-gray-400", label: ctrans("Not on this artwork") },
}

const barcodeStatus: Record<string, { icon: string, class: string, label: string }> = {
    found: { icon: "fal fa-check", class: "text-green-600", label: ctrans("Number printed") },
    read_by_ai: { icon: "fal fa-check", class: "text-green-600", label: ctrans("Number printed (read by AI)") },
    not_found: { icon: "fal fa-exclamation-triangle", class: "text-amber-600", label: ctrans("Not found") },
    unknown: { icon: "fal fa-times", class: "text-red-600", label: ctrans("Not one of this SKU's barcodes") },
}

const issueLabels: Record<string, string> = {
    spelling: ctrans("Spelling"),
    inconsistency: ctrans("Inconsistent"),
    legibility: ctrans("Hard to read"),
    other: ctrans("Other"),
}

const groupLabels: Record<string, string> = {
    text: ctrans("Pack text"),
    pictogram: ctrans("Pictograms"),
    mark: ctrans("Marks"),
}

const groups = computed(() => {
    if (!result.value) {
        return []
    }
    return (["text", "pictogram", "mark"] as const)
        .map((group) => ({ group, label: groupLabels[group], items: result.value!.items.filter((item) => item.group === group) }))
        .filter((group) => group.items.length)
})
</script>

<template>
    <section class="space-y-2 rounded-md border border-gray-200 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
                <FontAwesomeIcon icon="fal fa-robot" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
                {{ ctrans("AI pre-check") }}
            </h4>
            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">{{ ctrans("Advice only, a person approves") }}</span>
        </div>

        <template v-if="!check || check.version !== version">
            <p class="text-xs text-gray-500">{{ ctrans("AI compares V:version with the approved label information and points out what may be missing, different or misspelt. It can miss mistakes: always check the artwork yourself.", { version: String(version) }) }}</p>
            <Button v-if="aiCheck.can_run" type="tertiary" size="xs" :label="ctrans('Run AI check')" :loading="starting" @click="run">
                <template #icon><FontAwesomeIcon icon="fal fa-robot" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
            </Button>
        </template>

        <p v-else-if="check.state === 'checking'" class="flex items-center gap-2 text-xs text-gray-500">
            <FontAwesomeIcon icon="fal fa-spinner" spin fixed-width aria-hidden="true" />
            {{ ctrans("Checking V:version, this can take a minute…", { version: String(check.version) }) }}
        </p>

        <template v-else-if="check.state === 'failed'">
            <p class="flex items-center gap-1.5 text-xs text-red-600">
                <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width aria-hidden="true" />{{ check.error ?? ctrans("The artwork could not be checked.") }}
            </p>
            <Button v-if="aiCheck.can_run" type="tertiary" size="xs" :label="ctrans('Try again')" :loading="starting" @click="run">
                <template #icon><FontAwesomeIcon icon="fal fa-redo" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
            </Button>
        </template>

        <template v-else-if="result">
            <p class="text-sm" :class="problems ? 'text-amber-800' : 'text-gray-700'">
                <FontAwesomeIcon :icon="problems ? 'fal fa-exclamation-triangle' : 'fal fa-check'" :class="problems ? 'text-amber-600' : 'text-green-600'" fixed-width aria-hidden="true" />
                {{ problems ? ctrans(":count things to look at", { count: String(problems) }) : ctrans("Nothing found") }}<template v-if="result.summary">: {{ result.summary }}</template>
            </p>

            <div v-for="group in groups" :key="group.group" class="space-y-1">
                <h5 class="text-xs font-medium text-gray-500">{{ group.label }}</h5>
                <ul class="space-y-1">
                    <li v-for="item in group.items" :key="item.key" class="flex gap-2 text-xs">
                        <FontAwesomeIcon :icon="itemStatus[item.status].icon" :class="itemStatus[item.status].class" class="mt-0.5 shrink-0" fixed-width :aria-label="itemStatus[item.status].label" />
                        <div class="min-w-0">
                            <span class="font-medium text-gray-800">{{ item.label }}</span>
                            <span class="text-gray-500"> · {{ itemStatus[item.status].label }}</span>
                            <p v-if="item.note" class="text-gray-600">{{ item.note }}</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-if="result.barcodes.length" class="space-y-1">
                <h5 class="text-xs font-medium text-gray-500">{{ ctrans("Barcodes") }}</h5>
                <ul class="space-y-1">
                    <li v-for="barcode in result.barcodes" :key="barcode.label + barcode.value" class="flex gap-2 text-xs">
                        <FontAwesomeIcon :icon="barcodeStatus[barcode.status].icon" :class="barcodeStatus[barcode.status].class" class="mt-0.5 shrink-0" fixed-width :aria-label="barcodeStatus[barcode.status].label" />
                        <span class="text-gray-800"><span class="font-medium">{{ barcode.label }}</span> <span class="tabular-nums">{{ barcode.value }}</span></span>
                        <span class="text-gray-500">· {{ barcodeStatus[barcode.status].label }}</span>
                    </li>
                </ul>
                <p class="text-[11px] text-gray-400">{{ ctrans("Only the printed number is compared; the bars are not scanned.") }}</p>
            </div>

            <div v-if="result.issues.length" class="space-y-1">
                <h5 class="text-xs font-medium text-gray-500">{{ ctrans("Other things spotted") }}</h5>
                <ul class="space-y-1">
                    <li v-for="(issue, index) in result.issues" :key="index" class="flex gap-2 text-xs">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 shrink-0 text-amber-600" fixed-width aria-hidden="true" />
                        <div class="min-w-0">
                            <span class="font-medium text-gray-800">{{ issueLabels[issue.kind] }}:</span> <span class="text-gray-700">{{ issue.text }}</span>
                            <p v-if="issue.quote" class="italic text-gray-500">“{{ issue.quote }}”</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <p class="text-[11px] text-gray-400">{{ ctrans("Checked :date by :name", { date: check.checked_at ? useFormatTime(check.checked_at, { formatTime: "d MMM yyyy, HH:mm" }) : "", name: check.requested_by ?? "" }) }}</p>
                <Button v-if="aiCheck.can_run" type="tertiary" size="xs" :label="ctrans('Run again')" :loading="starting" @click="run">
                    <template #icon><FontAwesomeIcon icon="fal fa-redo" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>

        <p v-if="startError" class="text-xs text-red-600">{{ startError }}</p>
    </section>
</template>
