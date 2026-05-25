<!--
  - Author: andiferdiawan (https://github.com/andiferdiawan)
  - Created: Thursday, 22 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2026, andiferdiawan
  -->

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue"
import axios from "axios"
import MultiSelect from "primevue/multiselect"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSpinner, faGlobe } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faSpinner, faGlobe)

type VisitorStatus = "browsing" | "idle" | "new_session" | "waiting_chat" | "active_chat" | "closed_chat"

interface CountryRow {
    country_code: string
    total: number
    browsing: number
    idle: number
    new_session: number
    waiting_chat: number
    active_chat: number
    closed_chat: number
}

interface WebsiteRow {
    website_id: number
    domain: string
    website_name: string
    total: number
    countries: CountryRow[]
}

const props = defineProps<{
    route: string
}>()

const POLL_INTERVAL_MS = 15_000
const BUBBLE_SIZE      = 26

const loading     = ref(true)
const websites    = ref<WebsiteRow[]>([])
const selectedIds = ref<number[]>([])
let pollTimer: ReturnType<typeof setInterval> | null = null

const STATUS: Record<VisitorStatus, { bg: string; border: string; label: string; textColor: string }> = {
    active_chat:  { bg: "bg-emerald-500", border: "border-emerald-600", label: "Active Chat",   textColor: "text-emerald-700" },
    waiting_chat: { bg: "bg-amber-400",   border: "border-amber-500",   label: "Waiting",       textColor: "text-amber-700"   },
    new_session:  { bg: "bg-sky-400",     border: "border-sky-500",     label: "New Session",   textColor: "text-sky-700"     },
    browsing:     { bg: "bg-blue-300",    border: "border-blue-400",    label: "Browsing",      textColor: "text-blue-700"    },
    idle:         { bg: "bg-gray-300",    border: "border-gray-400",    label: "Idle",          textColor: "text-gray-600"    },
    closed_chat:  { bg: "bg-gray-400",    border: "border-gray-500",    label: "Closed",        textColor: "text-gray-600"    },
}

const STATUSES = Object.keys(STATUS) as VisitorStatus[]

const countryNames: Record<string, string> = {
    ID: "Indonesia",    US: "United States",  GB: "United Kingdom", DE: "Germany",
    FR: "France",       NL: "Netherlands",    ES: "Spain",          AU: "Australia",
    SG: "Singapore",    MY: "Malaysia",       TH: "Thailand",       JP: "Japan",
    CN: "China",        IN: "India",          BR: "Brazil",         CA: "Canada",
    IT: "Italy",        KR: "South Korea",    XX: "Unknown",        TR: "Turkey",
    PH: "Philippines",  VN: "Vietnam",        PL: "Poland",         SE: "Sweden",
    HK: "Hong Kong",    CZ: "Czech Rep.",     PT: "Portugal",       RO: "Romania",
    HU: "Hungary",      SK: "Slovakia",       PK: "Pakistan",       MX: "Mexico",
    BE: "Belgium",      AT: "Austria",        CH: "Switzerland",    AR: "Argentina",
    CO: "Colombia",     ZA: "South Africa",   EG: "Egypt",          SA: "Saudi Arabia",
    AE: "UAE",          BG: "Bulgaria",       HR: "Croatia",        RS: "Serbia",
}

const websiteOptions = computed(() =>
    websites.value.map(w => ({
        website_id: w.website_id,
        label: w.domain,
        sublabel: w.website_name,
        total: w.total,
    }))
)

const visibleWebsites = computed(() =>
    selectedIds.value.length
        ? websites.value.filter(w => selectedIds.value.includes(w.website_id))
        : websites.value
)

const totals = computed(() => {
    const acc = Object.fromEntries(STATUSES.map(s => [s, 0])) as Record<VisitorStatus, number>
    for (const site of visibleWebsites.value) {
        for (const row of site.countries) {
            for (const s of STATUSES) acc[s] += row[s] ?? 0
        }
    }
    return acc
})

const grandTotal = computed(() => visibleWebsites.value.reduce((s, w) => s + w.total, 0))

function formatCount(n: number): string {
    if (n >= 10000) return Math.round(n / 1000) + "k"
    if (n >= 1000)  return (n / 1000).toFixed(1).replace(/\.0$/, "") + "k"
    return String(n)
}

function countryLabel(code: string): string {
    return countryNames[code] ?? code
}

function flagUrl(code: string): string {
    return `/flags/${code.toLowerCase()}.png`
}

function statusBubbles(row: CountryRow): { status: VisitorStatus; count: number }[] {
    return STATUSES
        .map(s => ({ status: s, count: row[s] ?? 0 }))
        .filter(b => b.count > 0)
        .sort((a, b) => b.count - a.count)
}

async function fetchData(): Promise<void> {
    try {
        const { data } = await axios.get(props.route)
        websites.value = data
        if (!selectedIds.value.length && data.length) {
            selectedIds.value = [data[0].website_id]
        }
    } catch {
        // keep existing data on error
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchData()
    pollTimer = setInterval(fetchData, POLL_INTERVAL_MS)
})

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
    <div class="rounded-2xl border border-gray-100 bg-white overflow-hidden shadow-sm">

        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon :icon="['fal', 'fa-globe']" class="text-gray-400" />
                <h3 class="text-sm font-semibold text-gray-700">Live Visitors</h3>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                    {{ grandTotal.toLocaleString() }}
                </span>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <div v-for="s in STATUSES" :key="s" class="flex items-center gap-1">
                    <span class="w-2.5 h-2.5 rounded-full inline-block border" :class="[STATUS[s].bg, STATUS[s].border]" />
                    <span class="text-xs text-gray-500">
                        {{ STATUS[s].label }}
                        <b class="font-semibold" :class="STATUS[s].textColor">{{ totals[s].toLocaleString() }}</b>
                    </span>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center h-56 text-gray-400">
            <FontAwesomeIcon :icon="['fal', 'fa-spinner']" class="animate-spin text-2xl" />
        </div>

        <template v-else-if="websites.length">

            <!-- Website filter -->
            <div class="flex items-center gap-3 px-4 py-2.5 border-b border-gray-100 bg-gray-50">
                <span class="text-xs text-gray-400 shrink-0">Website:</span>
                <MultiSelect
                    v-model="selectedIds"
                    :options="websiteOptions"
                    option-label="label"
                    option-value="website_id"
                    :placeholder="'All websites'"
                    :max-selected-labels="3"
                    :selected-items-label="'{0} websites selected'"
                    filter
                    class="w-72 text-xs"
                >
                    <template #option="{ option }">
                        <div class="flex items-center justify-between gap-3 w-full">
                            <div class="flex flex-col">
                                <span class="text-xs font-medium">{{ option.label }}</span>
                                <span class="text-xs text-gray-400">{{ option.sublabel }}</span>
                            </div>
                            <span class="text-xs text-gray-400 tabular-nums shrink-0">{{ option.total.toLocaleString() }}</span>
                        </div>
                    </template>
                </MultiSelect>
                <span class="text-xs text-gray-400">
                    showing {{ visibleWebsites.length }} of {{ websites.length }} websites
                </span>
            </div>

            <!-- Empty filter result -->
            <div v-if="!visibleWebsites.length" class="flex flex-col items-center justify-center h-32 text-gray-400">
                <p class="text-xs">No websites selected</p>
            </div>

            <!-- Per-website sections -->
            <div v-for="site in visibleWebsites" :key="site.website_id">

                <!-- Website header row -->
                <div class="flex items-center gap-2 px-5 py-2 bg-gray-50 border-b border-gray-100">
                    <span class="text-xs font-bold text-gray-700">{{ site.domain }}</span>
                    <span class="text-xs text-gray-400">{{ site.website_name }}</span>
                    <span class="ml-auto rounded-full bg-white border border-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">
                        {{ site.total.toLocaleString() }}
                    </span>
                </div>

                <!-- Country grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-px bg-gray-100/80">
                    <div
                        v-for="row in site.countries"
                        :key="row.country_code"
                        class="bg-white px-3 py-2.5 flex flex-col gap-1.5"
                    >
                        <!-- Country header -->
                        <div class="flex items-center gap-1.5">
                            <img
                                :src="flagUrl(row.country_code)"
                                :alt="row.country_code"
                                class="h-3 w-auto rounded-[2px] shrink-0"
                                loading="lazy"
                                @error="($event.target as HTMLImageElement).style.display = 'none'"
                            />
                            <span class="text-[11px] font-semibold text-gray-600 truncate leading-none">{{ countryLabel(row.country_code) }}</span>
                            <span class="ml-auto text-[11px] font-bold text-gray-400 shrink-0 tabular-nums">{{ row.total.toLocaleString() }}</span>
                        </div>

                        <!-- Status bubbles — uniform size -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <div
                                v-for="bubble in statusBubbles(row)"
                                :key="bubble.status"
                                class="rounded-full flex items-center justify-center font-semibold text-white cursor-default shrink-0 transition-opacity hover:opacity-80"
                                :class="STATUS[bubble.status].bg"
                                :style="{ width: BUBBLE_SIZE + 'px', height: BUBBLE_SIZE + 'px', fontSize: '9px' }"
                                :title="`${STATUS[bubble.status].label}: ${bubble.count.toLocaleString()}`"
                            >
                                {{ formatCount(bubble.count) }}
                            </div>
                        </div>

                        <!-- Mini bar -->
                        <div class="flex h-[3px] rounded-full overflow-hidden w-full">
                            <div
                                v-for="s in STATUSES"
                                :key="s"
                                :class="STATUS[s].bg"
                                :style="{ width: (row[s] / row.total * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>

            </div>

        </template>

        <!-- Empty data -->
        <div v-else class="flex flex-col items-center justify-center h-56 text-gray-400">
            <FontAwesomeIcon :icon="['fal', 'fa-globe']" class="text-4xl mb-2 opacity-30" />
            <p class="text-xs">No visitor data available</p>
        </div>

        <!-- Footer -->
        <div v-if="!loading && websites.length" class="px-5 py-2 border-t border-gray-100 text-xs text-gray-400 text-right">
            Auto-refreshes every {{ POLL_INTERVAL_MS / 1000 }}s
        </div>
    </div>
</template>
