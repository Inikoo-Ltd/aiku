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
import { faSpinner, faGlobe, faHeadset, faHourglassHalf, faCommentAlt, faCheckCircle, faEye, faMoon } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"

library.add(faSpinner, faGlobe, faHeadset, faHourglassHalf, faCommentAlt, faCheckCircle, faEye, faMoon)

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
    date?: string  // demo mode: "YYYY-MM-DD" for historical data, omit for live (last 24h)
}>()

const POLL_INTERVAL_MS = 15_000

const loading     = ref(true)
const websites    = ref<WebsiteRow[]>([])
const selectedIds = ref<number[]>([])
let pollTimer: ReturnType<typeof setInterval> | null = null

const STATUS: Record<VisitorStatus, { icon: string; barBg: string; chipBg: string; iconColor: string; textColor: string; label: string }> = {
    active_chat:  { icon: "fa-headset",        barBg: "bg-emerald-500", chipBg: "bg-emerald-50",  iconColor: "text-emerald-500", textColor: "text-emerald-700", label: "Active Chat"  },
    waiting_chat: { icon: "fa-hourglass-half",  barBg: "bg-amber-400",   chipBg: "bg-amber-50",    iconColor: "text-amber-500",   textColor: "text-amber-700",   label: "Waiting"      },
    new_session:  { icon: "fa-comment-alt",     barBg: "bg-sky-400",     chipBg: "bg-sky-50",      iconColor: "text-sky-500",     textColor: "text-sky-700",     label: "New Session"  },
    browsing:     { icon: "fa-eye",             barBg: "bg-blue-300",    chipBg: "bg-blue-50",     iconColor: "text-blue-400",    textColor: "text-blue-600",    label: "Browsing"     },
    idle:         { icon: "fa-moon",            barBg: "bg-gray-300",    chipBg: "bg-gray-50",     iconColor: "text-gray-400",    textColor: "text-gray-500",    label: "Idle"         },
    closed_chat:  { icon: "fa-check-circle",    barBg: "bg-gray-400",    chipBg: "bg-gray-100",    iconColor: "text-gray-400",    textColor: "text-gray-500",    label: "Closed"       },
}

const STATUSES = Object.keys(STATUS) as VisitorStatus[]

const regionNames = new Intl.DisplayNames(["en"], { type: "region" })

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
    if (code === "XX") return "Unknown"
    try {
        return regionNames.of(code) ?? code
    } catch {
        return code
    }
}

function flagUrl(code: string): string {
    return `/flags/${code.toLowerCase()}.png`
}

function statusChips(row: CountryRow): { status: VisitorStatus; count: number }[] {
    return STATUSES
        .map(s => ({ status: s, count: row[s] ?? 0 }))
        .filter(b => b.count > 0)
        .sort((a, b) => b.count - a.count)
}

async function fetchData(): Promise<void> {
    try {
        const params = props.date ? { date: props.date } : {}
        const { data } = await axios.get(props.route, { params })
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
    if (!props.date) {
        pollTimer = setInterval(fetchData, POLL_INTERVAL_MS)
    }
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
                <span v-if="date" class="rounded-full bg-amber-100 border border-amber-300 px-2 py-0.5 text-xs font-semibold text-amber-700">
                    Demo · {{ date }}
                </span>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <div v-for="s in STATUSES" :key="s" class="flex items-center gap-1">
                    <span v-if="s === 'browsing'" class="relative flex items-center justify-center w-3 h-3 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-300 opacity-60" />
                        <FontAwesomeIcon :icon="['fal', 'fa-eye']" class="relative text-[10px] text-blue-400" />
                    </span>
                    <FontAwesomeIcon v-else :icon="['fal', STATUS[s].icon]" class="text-xs" :class="STATUS[s].iconColor" />
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
                    <!-- <span class="text-xs font-bold text-gray-700">www.{{ site.domain }}</span> -->
                    <span class="text-xs font-bold text-gray-600">{{ site.website_name }}</span>
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

                        <!-- Status chips — icon + count -->
                        <div class="flex items-center gap-1 flex-wrap">
                            <div
                                v-for="chip in statusChips(row)"
                                :key="chip.status"
                                class="flex items-center gap-1 px-2 py-1.5 rounded"
                                :class="STATUS[chip.status].chipBg"
                                :title="`${STATUS[chip.status].label}: ${chip.count.toLocaleString()}`"
                            >
                                <span v-if="chip.status === 'browsing'" class="relative flex items-center justify-center w-[10px] h-[10px] shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-300 opacity-60" />
                                    <FontAwesomeIcon :icon="['fal', 'fa-eye']" class="relative text-[9px] text-blue-400" />
                                </span>
                                <FontAwesomeIcon
                                    v-else
                                    :icon="['fal', STATUS[chip.status].icon]"
                                    class="text-[12px]"
                                    :class="STATUS[chip.status].iconColor"
                                />
                                <span class="text-[12px] font-semibold tabular-nums leading-none" :class="STATUS[chip.status].textColor">
                                    {{ formatCount(chip.count) }}
                                </span>
                            </div>
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
            <template v-if="date">Historical data for {{ date }}</template>
            <template v-else>Auto-refreshes every {{ POLL_INTERVAL_MS / 1000 }}s</template>
        </div>
    </div>
</template>
