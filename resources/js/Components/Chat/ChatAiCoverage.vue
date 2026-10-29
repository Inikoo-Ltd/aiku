<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 23 Oct 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { computed, ref, watch } from "vue"
import Chart from "primevue/chart"
import DatePicker from "primevue/datepicker"
import { useMediaQuery } from "@vueuse/core"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import DashboardWidgetBox from "@/Components/DataDisplay/Dashboard/Widget/DashboardWidgetBox.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faRadar } from "@fal"
import { ctrans } from "@/Composables/useTrans"

type Channel = "email" | "website" | "whatsapp"

interface Coverage {
    window: { key: string, segments: { label: string, from: string, to: string }[] }
    matrix: Record<string, Record<Channel | "total", number>>
    shops: { shop: string, organisation: string, buckets: Record<string, number>, received: number }[]
    trend: { unit: "hour" | "day", points: { at: string, received: number, covered: number }[] }
}

const props = defineProps<{
    coverage: Coverage
}>()

const WINDOWS = [
    { value: "last_hour", label: ctrans("Last hour") },
    { value: "today", label: ctrans("Today") },
    { value: "overnight", label: ctrans("Overnight") },
    { value: "team_hours", label: ctrans("Team hours") },
    { value: "last_24h", label: ctrans("Last 24h") },
    { value: "last_7_days", label: ctrans("Last 7 days") },
    { value: "custom", label: ctrans("Custom") },
]

const ROWS: { key: string, label: string, short?: string, sub?: boolean, strong?: boolean }[] = [
    { key: "received", label: ctrans("Chats received"), short: ctrans("Received"), strong: true },
    { key: "answered_by_staff", label: ctrans("Already answered by staff"), short: ctrans("By staff") },
    { key: "suggestion_already_waiting", label: ctrans("Already had a suggestion waiting"), short: ctrans("Waiting") },
    { key: "worked", label: ctrans("Worked"), strong: true },
    { key: "draft", label: "✅ " + ctrans("Suggestion"), sub: true },
    { key: "note", label: "📝 " + ctrans("Note for staff, no suggestion"), sub: true },
    { key: "review_failed_note", label: "🔍 " + ctrans("Note, suggestion failed review"), sub: true },
    { key: "spam", label: "🗑️ " + ctrans("Spam or marketing"), sub: true },
    { key: "held", label: "👤 " + ctrans("Held by a colleague"), sub: true },
    { key: "opened", label: "👀 " + ctrans("Opened by a colleague, no suggestion"), sub: true },
    { key: "whatsapp_window_closed", label: "⌛ " + ctrans("WhatsApp 24-hour window closed: only an approved template can be sent"), sub: true },
    { key: "thanks", label: "🙏 " + ctrans("Thank-you only"), sub: true },
    { key: "notice", label: "🔔 " + ctrans("System notice"), sub: true },
    { key: "answered_elsewhere", label: "✔️ " + ctrans("Answered in another chat"), sub: true },
    { key: "not_worked", label: "⏳ " + ctrans("Not worked yet"), strong: true },
]

const WORKED = ["draft", "note", "review_failed_note", "spam", "held", "opened", "whatsapp_window_closed", "thanks", "notice", "answered_elsewhere"]

const CHANNELS: { key: Channel | "total", label: string }[] = [
    { key: "email", label: ctrans("Email") },
    { key: "website", label: ctrans("Website chat") },
    { key: "whatsapp", label: ctrans("WhatsApp") },
    { key: "total", label: ctrans("Total") },
]

const VIEWS = [
    { value: "channel", label: ctrans("By channel") },
    { value: "shop", label: ctrans("By shop") },
]

const query = new URLSearchParams(typeof window === "undefined" ? "" : window.location.search)
const selectedWindow = ref<string>(props.coverage.window.key)
const range = ref<{ from: string | null, to: string | null }>({ from: query.get("from"), to: query.get("to") })
const view = ref<string>("channel")

const params = computed(() => selectedWindow.value === "custom"
    ? { window: "custom", from: range.value.from, to: range.value.to ?? range.value.from }
    : { window: selectedWindow.value })

const reload = () => {
    if (selectedWindow.value === "custom" && !range.value.from) return
    router.get(route("grp.chat.ai.dashboard"), params.value, { only: ["coverage"], preserveState: true, preserveScroll: true, replace: true })
}

watch(selectedWindow, reload)

const fromIsoDate = (iso: string): Date => {
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (key: "from" | "to") =>
    computed<Date | null>({
        get: () => (range.value[key] ? fromIsoDate(range.value[key] as string) : null),
        set: (value) => {
            if (!value) return
            range.value = { ...range.value, [key]: toLocalIsoDate(value) }
            reload()
        },
    })
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const isNarrowScreen = useMediaQuery("(max-width: 639px)")
const dateFormat = computed(() => (isNarrowScreen.value ? "dd/mm/yy" : "d M yy"))
const datePickerPt = computed(() => ({ pcInputText: { root: { class: isNarrowScreen.value ? "!w-28 !py-1 !text-xs" : "!w-32 !py-1 !text-xs" } } }))
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]"

const drillDown = (row: string, filters: { channel?: string, shop?: string } = {}) =>
    route("grp.chat.ai.coverage", { ...params.value, row, ...filters })

const channelFilter = (channel: Channel | "total") => (channel === "total" ? {} : { channel })

const shopCount = (shop: Coverage["shops"][number], row: string) => {
    if (row === "received") return shop.received
    if (row === "worked") return WORKED.reduce((sum, bucket) => sum + (shop.buckets[bucket] ?? 0), 0)
    return shop.buckets[row] ?? 0
}

const covered = computed(() => props.coverage.matrix.received.total - props.coverage.matrix.not_worked.total)

const timeFormat = (iso: string) => new Date(iso).toLocaleString(undefined, { weekday: "short", day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" })

const trendLabel = (iso: string) => props.coverage.trend.unit === "hour"
    ? new Date(iso).toLocaleString(undefined, { weekday: "short", hour: "2-digit", minute: "2-digit" })
    : new Date(iso).toLocaleDateString(undefined, { day: "numeric", month: "short" })

const trendChart = computed(() => ({
    labels: props.coverage.trend.points.map((point) => trendLabel(point.at)),
    datasets: [
        { type: "bar", label: ctrans("Came in"), data: props.coverage.trend.points.map((point) => point.received), backgroundColor: "#cbd5e1", borderRadius: 2 },
        { type: "bar", label: ctrans("Covered"), data: props.coverage.trend.points.map((point) => point.covered), backgroundColor: "#1f845a", borderRadius: 2 },
    ],
}))

const trendOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: { legend: { position: "bottom", labels: { boxWidth: 12 } } },
    scales: {
        x: { grid: { display: false }, ticks: { autoSkip: true, maxTicksLimit: 12, maxRotation: 0 } },
        y: { beginAtZero: true, ticks: { precision: 0 } },
    },
}
</script>

<template>
    <DashboardWidgetBox storageKey="chat_ai_dashboard_coverage_collapsed">
        <template #header>
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                <FontAwesomeIcon :icon="faRadar" class="text-[--app-accent]" fixed-width />
                {{ ctrans("What the AI did with every chat") }}
            </span>
            <span class="text-xs text-gray-400">
                {{ ctrans(":covered of :received covered", { covered: covered, received: coverage.matrix.received.total }) }}
            </span>
        </template>

        <div class="flex flex-wrap items-center gap-2">
            <SegmentedToggle v-model="selectedWindow" :options="WINDOWS" :ariaLabel="ctrans('Window')" />
            <template v-if="selectedWindow === 'custom'">
                <DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
                <span class="text-xs text-gray-400">–</span>
                <DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
            </template>
            <SegmentedToggle v-model="view" :options="VIEWS" :ariaLabel="ctrans('Breakdown')" soft class="sm:ml-auto" />
        </div>

        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500">
            <li v-for="segment in coverage.window.segments" :key="segment.label">
                <span class="font-medium text-gray-700">{{ segment.label }}</span>
                {{ timeFormat(segment.from) }} → {{ timeFormat(segment.to) }}
            </li>
        </ul>

        <div class="mt-3 overflow-x-auto">
            <table v-if="view === 'channel'" class="w-full text-sm tabular-nums">
                <thead class="whitespace-nowrap">
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="py-1.5 font-normal"></th>
                        <th v-for="channel in CHANNELS" :key="channel.key" class="py-1.5 pl-4 text-right font-normal">{{ channel.label }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in ROWS" :key="row.key" class="border-b border-gray-100 last:border-0">
                        <td class="py-1.5" :class="[row.sub ? 'pl-5 text-gray-600' : 'text-gray-800', row.strong ? 'font-semibold' : '']">{{ row.label }}</td>
                        <td v-for="channel in CHANNELS" :key="channel.key" class="py-1.5 pl-4 text-right" :class="row.strong ? 'font-semibold' : ''">
                            <Link v-if="coverage.matrix[row.key][channel.key]" :href="drillDown(row.key, channelFilter(channel.key))" class="text-gray-900 hover:underline">
                                {{ coverage.matrix[row.key][channel.key] }}
                            </Link>
                            <span v-else class="text-gray-300">0</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <table v-else class="w-full text-sm tabular-nums">
                <thead class="whitespace-nowrap">
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="py-1.5 font-normal">{{ ctrans("Shop") }}</th>
                        <th v-for="row in ROWS" :key="row.key" class="py-1.5 pl-3 text-right font-normal" v-tooltip="row.label">
                            {{ row.short ?? (row.sub || row.key === "not_worked" ? row.label.split(" ")[0] : row.label) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="shop in coverage.shops" :key="shop.shop" class="border-b border-gray-100 last:border-0">
                        <td class="whitespace-nowrap py-1.5 text-gray-800">
                            {{ shop.shop }} <span class="text-xs text-gray-400">{{ shop.organisation }}</span>
                        </td>
                        <td v-for="row in ROWS" :key="row.key" class="py-1.5 pl-3 text-right" :class="row.strong ? 'font-semibold' : ''">
                            <Link v-if="shopCount(shop, row.key)" :href="drillDown(row.key, { shop: shop.shop })" class="text-gray-900 hover:underline">
                                {{ shopCount(shop, row.key) }}
                            </Link>
                            <span v-else class="text-gray-300">0</span>
                        </td>
                    </tr>
                    <tr v-if="!coverage.shops.length">
                        <td :colspan="ROWS.length + 1" class="py-3 text-center text-gray-500">{{ ctrans("No customer wrote in this window") }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 h-56">
            <Chart type="bar" :data="trendChart" :options="trendOptions" class="h-full" />
        </div>
    </DashboardWidgetBox>
</template>
