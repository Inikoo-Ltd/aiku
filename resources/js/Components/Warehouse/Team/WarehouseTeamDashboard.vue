<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Oct 2026 18:00:00 British Summer Time, Sheffield, UK
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { formatInTimeZone } from "date-fns-tz"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import ToggleSwitch from "primevue/toggleswitch"
import { Chart as ChartJS, CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend } from "chart.js"
import { Bar, Line } from "vue-chartjs"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { hoursLabel, durationSince, timeIn, seriesColors } from "@/Components/Warehouse/Team/format"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
    faUserCheck, faSignOutAlt, faUserClock, faUserTimes, faUmbrellaBeach, faBed, faPlus,
    faHandHoldingBox, faBoxCheck, faBoxOpen, faExclamationTriangle, faShippingFast,
    faArrowUp, faArrowDown, faMinus, faClock, faTachometerAlt, faAlarmExclamation, faInfoCircle,
} from "@fal"

library.add(faUserCheck, faSignOutAlt, faUserClock, faUserTimes, faUmbrellaBeach, faBed, faPlus, faHandHoldingBox, faBoxCheck, faBoxOpen, faExclamationTriangle, faShippingFast, faArrowUp, faArrowDown, faMinus, faClock, faTachometerAlt, faAlarmExclamation, faInfoCircle)
ChartJS.register(CategoryScale, LinearScale, BarElement, PointElement, LineElement, Tooltip, Legend)

type FloorStatus = "on_site" | "clocked_out" | "expected" | "absent" | "on_leave" | "day_off"

interface Person {
    id: number
    slug: string
    name: string
    positions: string[]
    status: FloorStatus
    since: string | null
    worked_seconds: number
    leave_type: string | null
    expected_end: string | null
}

interface Kpi {
    value: number | null
    previous: number | null
}

export interface LeaderboardRow {
    id: number
    slug: string
    name: string
    positions: string[]
    has_user: boolean
    days_worked: number
    worked_seconds: number
    first_in: string | null
    last_out: string | null
    late: number
    picked_dns: number
    picked_items: number
    packed_dns: number
    packed_items: number
    pick_lines: number
    short_picks: number
    short_rate: number | null
    items_per_hour: number | null
}

export interface DashboardData {
    period: string
    from: string
    to: string
    previous_from: string
    previous_to: string
    timezone: string
    now: string
    team_size: number
    floor: {
        people: Person[]
        counts: Partial<Record<FloorStatus, number>>
        open_previous_days: { employee_id: number; name: string; started_at: string }[]
    }
    backlog: Record<"to_pick" | "blocked" | "to_pack" | "packed", { count: number; items: number; oldest_at: string | null }>
    kpis: Record<"picked_dns" | "packed_dns" | "items" | "worked_seconds" | "people_worked" | "items_per_hour" | "short_picks" | "short_rate" | "late", Kpi>
    hourly: { hours: number[]; today_picked: number[]; today_packed: number[]; usual_picked: number[]; usual_packed: number[] }
    daily: { days: string[]; picked: number[]; packed: number[]; worked_hours: number[]; people: number[]; items_per_hour: (number | null)[] }
    leaderboard: LeaderboardRow[]
    last_activity_at: string | null
}

const props = defineProps<{
    data?: DashboardData
    backlogRoute: { name: string; parameters: Record<string, string | number> }
}>()

const emit = defineEmits<{
    (e: "add-clocking", employeeId: number | null, day?: string): void
}>()

const periodOptions = [
    { value: "today", label: ctrans("Today") },
    { value: "yesterday", label: ctrans("Yesterday") },
    { value: "week", label: ctrans("7 days") },
    { value: "month", label: ctrans("30 days") },
]
const period = ref(props.data?.period ?? "today")
watch(() => props.data?.period, (value) => { if (value) period.value = value })
watch(period, (value) => {
    if (value !== props.data?.period) {
        router.reload({ data: { period: value }, only: ["dashboard"] })
    }
})

const tz = computed(() => props.data?.timezone ?? "UTC")
const now = computed(() => (props.data ? new Date(props.data.now) : new Date()))
const isSingleDay = computed(() => props.data?.from === props.data?.to)
const periodLabel = computed(() => {
    if (!props.data) return ""
    const from = formatInTimeZone(`${props.data.from}T12:00:00Z`, "UTC", "EEE d MMM")
    const to = formatInTimeZone(`${props.data.to}T12:00:00Z`, "UTC", "EEE d MMM")
    return isSingleDay.value ? from : `${from} → ${to}`
})
const previousLabel = computed(() => {
    switch (props.data?.period) {
        case "today": return ctrans("vs yesterday")
        case "yesterday": return ctrans("vs day before")
        case "week": return ctrans("vs prev. 7 days")
        default: return ctrans("vs prev. 30 days")
    }
})

const quietToday = computed(() => {
    if (!props.data) return false
    const todayWork = props.data.hourly.today_picked.reduce((a, b) => a + b, 0) + props.data.hourly.today_packed.reduce((a, b) => a + b, 0)
    return todayWork === 0
})

const statusMeta: Record<FloorStatus, { label: string; icon: any; dot: string; pill: string }> = {
    on_site: { label: ctrans("On site"), icon: faUserCheck, dot: "bg-green-500", pill: "bg-green-50 text-green-700 border-green-200" },
    clocked_out: { label: ctrans("Clocked out"), icon: faSignOutAlt, dot: "bg-gray-400", pill: "bg-gray-50 text-gray-700 border-gray-200" },
    expected: { label: ctrans("Not in yet"), icon: faUserClock, dot: "bg-sky-400", pill: "bg-sky-50 text-sky-700 border-sky-200" },
    absent: { label: ctrans("Did not clock in"), icon: faUserTimes, dot: "bg-red-500", pill: "bg-red-50 text-red-700 border-red-200" },
    on_leave: { label: ctrans("On leave"), icon: faUmbrellaBeach, dot: "bg-amber-400", pill: "bg-amber-50 text-amber-700 border-amber-200" },
    day_off: { label: ctrans("Day off"), icon: faBed, dot: "bg-gray-300", pill: "bg-gray-50 text-gray-500 border-gray-200" },
}
const statusOrder: FloorStatus[] = ["on_site", "clocked_out", "expected", "absent", "on_leave", "day_off"]
const statusFilter = ref<FloorStatus | null>(null)
const floorPeople = computed(() => {
    const people = props.data?.floor.people ?? []
    const filtered = statusFilter.value ? people.filter((person) => person.status === statusFilter.value) : people
    return [...filtered].sort((a, b) => statusOrder.indexOf(a.status) - statusOrder.indexOf(b.status) || a.name.localeCompare(b.name))
})
const sinceText = (person: Person) => {
    if (!person.since) return person.leave_type ? person.leave_type.replace(/-/g, " ") : ""
    const at = timeIn(person.since, tz.value)
    switch (person.status) {
        case "on_site": return ctrans("in since :time (:ago)", { time: at, ago: durationSince(person.since, now.value) })
        case "clocked_out": return ctrans("out at :time, worked :hours", { time: at, hours: hoursLabel(person.worked_seconds, "0m") })
        case "expected": return ctrans("starts at :time", { time: at })
        case "absent": return ctrans("was due at :time", { time: at })
        default: return ""
    }
}

const backlogTiles = computed(() => {
    const backlog = props.data?.backlog
    if (!backlog) return []
    return [
        { key: "to_pick", label: ctrans("To pick"), icon: faHandHoldingBox, ...backlog.to_pick, tone: "" },
        { key: "blocked", label: ctrans("Blocked"), icon: faExclamationTriangle, ...backlog.blocked, tone: backlog.blocked.count ? "text-amber-600" : "" },
        { key: "to_pack", label: ctrans("To pack"), icon: faBoxOpen, ...backlog.to_pack, tone: "" },
        { key: "packed", label: ctrans("Packed, waiting dispatch"), icon: faShippingFast, ...backlog.packed, tone: "" },
    ]
})

const kpiTiles = computed(() => {
    const kpis = props.data?.kpis
    if (!kpis) return []
    const tile = (key: keyof DashboardData["kpis"], label: string, icon: any, format: (v: number | null) => string, lowerIsBetter = false, hint?: string) => ({
        key, label, icon, hint,
        value: format(kpis[key].value),
        previous: format(kpis[key].previous),
        delta: delta(kpis[key].value, kpis[key].previous, lowerIsBetter),
    })
    const n = (v: number | null) => (v === null ? "–" : v.toLocaleString())
    return [
        tile("picked_dns", ctrans("Delivery notes picked"), faHandHoldingBox, n),
        tile("packed_dns", ctrans("Delivery notes packed"), faBoxCheck, n),
        tile("items", ctrans("Items handled"), faBoxOpen, n, false, ctrans("Items picked plus items packed")),
        tile("worked_seconds", ctrans("Hours worked"), faClock, (v) => hoursLabel(v, "0m"), false, ctrans(":n people clocked in", { n: kpis.people_worked.value ?? 0 })),
        tile("items_per_hour", ctrans("Items per worked hour"), faTachometerAlt, n, false, ctrans("Items handled divided by hours on the clock, breaks excluded")),
        tile("short_picks", ctrans("Short picks"), faExclamationTriangle, n, true, kpis.short_rate.value === null ? undefined : ctrans(":rate% of pick lines", { rate: kpis.short_rate.value })),
        tile("late", ctrans("Late clock-ins"), faAlarmExclamation, n, true),
    ]
})
const delta = (value: number | null, previous: number | null, lowerIsBetter: boolean) => {
    if (value === null || previous === null) return null
    if (previous === 0) return value === 0 ? { text: "=", tone: "text-gray-400", icon: faMinus } : { text: ctrans("new"), tone: lowerIsBetter ? "text-red-600" : "text-green-600", icon: faArrowUp }
    const change = Math.round(((value - previous) / previous) * 100)
    if (change === 0) return { text: "0%", tone: "text-gray-400", icon: faMinus }
    const good = lowerIsBetter ? change < 0 : change > 0
    return { text: `${Math.abs(change)}%`, tone: good ? "text-green-600" : "text-red-600", icon: change > 0 ? faArrowUp : faArrowDown }
}

const baseChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index" as const, intersect: false },
    plugins: { legend: { display: true, labels: { boxWidth: 10, boxHeight: 10, font: { size: 10 }, usePointStyle: true } } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkipPadding: 16 } },
        y: { beginAtZero: true, grid: { color: "rgba(0,0,0,0.05)" }, ticks: { font: { size: 10 }, precision: 0 } },
    },
}

const weekdayLabel = computed(() => (props.data ? formatInTimeZone(props.data.now, tz.value, "EEEE") : ""))
const hourlyRange = computed(() => {
    const h = props.data?.hourly
    if (!h) return [6, 20]
    const active = h.hours.filter((hour) => h.today_picked[hour] || h.today_packed[hour] || h.usual_picked[hour] || h.usual_packed[hour])
    return active.length ? [Math.max(0, Math.min(...active) - 1), Math.min(23, Math.max(...active) + 1)] : [6, 20]
})
const hourlyChart = computed(() => {
    const h = props.data!.hourly
    const [start, end] = hourlyRange.value
    const slice = (values: number[]) => values.slice(start, end + 1)
    return {
        labels: h.hours.slice(start, end + 1).map((hour) => `${String(hour).padStart(2, "0")}:00`),
        datasets: [
            { type: "bar" as const, label: ctrans("Picked today"), data: slice(h.today_picked), backgroundColor: seriesColors.picked, borderRadius: 3, barPercentage: 0.8, categoryPercentage: 0.7, order: 2 },
            { type: "bar" as const, label: ctrans("Packed today"), data: slice(h.today_packed), backgroundColor: seriesColors.packed, borderRadius: 3, barPercentage: 0.8, categoryPercentage: 0.7, order: 2 },
            { type: "line" as const, label: ctrans("Usual picked (:day)", { day: weekdayLabel.value }), data: slice(h.usual_picked), borderColor: seriesColors.picked, backgroundColor: seriesColors.picked, borderDash: [4, 3], borderWidth: 2, pointRadius: 0, tension: 0.3, order: 1 },
            { type: "line" as const, label: ctrans("Usual packed (:day)", { day: weekdayLabel.value }), data: slice(h.usual_packed), borderColor: seriesColors.packed, backgroundColor: seriesColors.packed, borderDash: [4, 3], borderWidth: 2, pointRadius: 0, tension: 0.3, order: 1 },
        ],
    }
})

const dayLabels = computed(() => props.data!.daily.days.map((day) => formatInTimeZone(`${day}T12:00:00Z`, "UTC", "d MMM")))
const dailyChart = computed(() => ({
    labels: dayLabels.value,
    datasets: [
        { label: ctrans("Picked"), data: props.data!.daily.picked, backgroundColor: seriesColors.picked, borderRadius: 2, barPercentage: 0.9, categoryPercentage: 0.8 },
        { label: ctrans("Packed"), data: props.data!.daily.packed, backgroundColor: seriesColors.packed, borderRadius: 2, barPercentage: 0.9, categoryPercentage: 0.8 },
    ],
}))
const productivityChart = computed(() => ({
    labels: dayLabels.value,
    datasets: [
        { label: ctrans("Items per worked hour"), data: props.data!.daily.items_per_hour, borderColor: seriesColors.productivity, backgroundColor: seriesColors.productivity, borderWidth: 2, pointRadius: 2, pointHoverRadius: 4, tension: 0.3, spanGaps: true },
    ],
}))
const hoursChart = computed(() => ({
    labels: dayLabels.value,
    datasets: [
        { label: ctrans("Hours worked"), data: props.data!.daily.worked_hours, backgroundColor: "#6da7ec", borderRadius: 2, barPercentage: 0.9, categoryPercentage: 0.8 },
    ],
}))
const singleSeriesOptions = { ...baseChartOptions, plugins: { legend: { display: false } } }

const maxItemsPerHour = computed(() => Math.max(1, ...(props.data?.leaderboard.map((row) => row.items_per_hour ?? 0) ?? [0])))
const showIdlePeople = ref(false)
const hasActivity = (row: LeaderboardRow) => row.worked_seconds || row.first_in || row.picked_dns || row.packed_dns || row.pick_lines || row.short_picks || row.late
const idlePeople = computed(() => (props.data?.leaderboard ?? []).filter((row) => !hasActivity(row)).length)
const leaderboardRows = computed(() =>
    (props.data?.leaderboard ?? [])
        .map((row, index) => ({ ...row, rank: index + 1 }))
        .filter((row) => showIdlePeople.value || hasActivity(row))
)
</script>

<template>
    <div v-if="!data" class="px-4 py-4 space-y-4 animate-pulse" aria-busy="true">
        <div class="h-8 w-72 rounded bg-gray-200" />
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"><div v-for="i in 6" :key="i" class="h-16 rounded-lg bg-gray-200" /></div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7"><div v-for="i in 7" :key="i" class="h-24 rounded-lg bg-gray-200" /></div>
        <div class="grid gap-3 xl:grid-cols-3"><div v-for="i in 3" :key="i" class="h-56 rounded-lg bg-gray-200" /></div>
        <div class="h-80 rounded-lg bg-gray-200" />
    </div>

    <div v-else class="px-4 py-4 space-y-6">
        <div v-if="data.floor.open_previous_days.length" class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">
            <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
            <span class="font-medium">{{ ctrans(":n clock-ins from earlier days were never closed", { n: data.floor.open_previous_days.length }) }}</span>
            <button
                v-for="open in data.floor.open_previous_days"
                :key="open.employee_id + open.started_at"
                type="button"
                class="rounded-full border border-amber-300 bg-white px-2 py-0.5 text-xs hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                v-tooltip="ctrans('Clocked in :when and never clocked out. Press to add the missing clock-out.', { when: timeIn(open.started_at, tz, 'EEE d MMM HH:mm') })"
                @click="emit('add-clocking', open.employee_id, timeIn(open.started_at, tz, 'yyyy-MM-dd'))"
            >
                {{ open.name }} · {{ timeIn(open.started_at, tz, "EEE HH:mm") }}
            </button>
        </div>

        <section>
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ ctrans("Floor now") }} <span class="ml-1 text-sm font-normal text-gray-500">{{ timeIn(data.now, tz, "EEE d MMM, HH:mm") }}</span></h2>
                <span class="text-xs text-gray-500">{{ ctrans(":n people in the team", { n: data.team_size }) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-6">
                <button
                    v-for="status in statusOrder"
                    :key="status"
                    type="button"
                    class="flex items-center gap-3 rounded-lg border bg-white px-3 py-2 text-left transition focus:outline-none focus:ring-2 focus:ring-[--app-accent]"
                    :class="statusFilter === status ? 'border-[--app-accent] ring-1 ring-[--app-accent]' : 'border-gray-200 hover:border-gray-300'"
                    :aria-pressed="statusFilter === status"
                    @click="statusFilter = statusFilter === status ? null : status"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border" :class="statusMeta[status].pill">
                        <FontAwesomeIcon :icon="statusMeta[status].icon" fixed-width aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block text-2xl font-semibold leading-none tabular-nums">{{ data.floor.counts[status] ?? 0 }}</span>
                        <span class="block text-xs text-gray-500">{{ statusMeta[status].label }}</span>
                    </span>
                </button>
            </div>
            <ul class="mt-2 grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2 xl:grid-cols-3">
                <li v-for="person in floorPeople" :key="person.id" class="flex items-center gap-2 rounded px-2 py-1 text-sm hover:bg-gray-50">
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="statusMeta[person.status].dot" aria-hidden="true" />
                    <span class="truncate font-medium">{{ person.name }}</span>
                    <span class="truncate text-xs text-gray-400">{{ person.positions.join(", ") }}</span>
                    <span class="ml-auto shrink-0 text-xs text-gray-500 tabular-nums">{{ sinceText(person) }}</span>
                    <Button type="transparent" size="xs" :icon="faPlus" v-tooltip="ctrans('Add clocking')" @click="emit('add-clocking', person.id)" />
                </li>
                <li v-if="!floorPeople.length" class="col-span-full py-3 text-center text-sm text-gray-500">{{ ctrans("Nobody in this state") }}</li>
            </ul>
        </section>

        <section>
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-semibold">{{ ctrans("Backlog the team faces") }}</h2>
                <Link :href="route(backlogRoute.name, backlogRoute.parameters)" class="text-xs text-[--app-accent-strong] hover:underline">{{ ctrans("Open goods out") }}</Link>
            </div>
            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div v-for="tile in backlogTiles" :key="tile.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <FontAwesomeIcon :icon="tile.icon" fixed-width aria-hidden="true" class="text-[--app-accent]" />
                        {{ tile.label }}
                    </div>
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-2xl font-semibold tabular-nums" :class="tile.tone">{{ tile.count.toLocaleString() }}</span>
                        <span class="text-xs text-gray-500">{{ ctrans(":n items", { n: tile.items.toLocaleString() }) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">{{ ctrans("Throughput") }} <span class="ml-1 text-sm font-normal text-gray-500">{{ periodLabel }}</span></h2>
                <SegmentedToggle v-model="period" :options="periodOptions" :ariaLabel="ctrans('Period')" />
            </div>
            <div v-if="quietToday && data.period === 'today'" class="mb-3 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-600">
                <FontAwesomeIcon :icon="faInfoCircle" fixed-width aria-hidden="true" />
                <span v-if="data.last_activity_at">{{ ctrans("Nothing picked or packed yet today. Last activity: :when.", { when: timeIn(data.last_activity_at, tz, "EEE d MMM HH:mm") }) }}</span>
                <span v-else>{{ ctrans("Nothing picked or packed by the team in the last 30 days.") }}</span>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                <div v-for="tile in kpiTiles" :key="tile.key" class="rounded-lg border border-gray-200 bg-white px-4 py-3" v-tooltip="tile.hint">
                    <div class="flex items-center gap-2 text-xs text-gray-500">
                        <FontAwesomeIcon :icon="tile.icon" fixed-width aria-hidden="true" class="text-[--app-accent]" />
                        <span class="truncate">{{ tile.label }}</span>
                    </div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums">{{ tile.value }}</div>
                    <div class="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                        <template v-if="tile.delta">
                            <span class="inline-flex items-center gap-0.5 font-medium" :class="tile.delta.tone">
                                <FontAwesomeIcon :icon="tile.delta.icon" aria-hidden="true" class="text-[10px]" />{{ tile.delta.text }}
                            </span>
                        </template>
                        <span class="truncate">{{ tile.previous }} {{ previousLabel }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="text-sm font-semibold">{{ ctrans("Today by hour") }}</h3>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Delivery notes finished each hour, bars today, dashed lines the average :day of the last 4 weeks", { day: weekdayLabel }) }}</p>
                <div class="h-52"><Bar :data="hourlyChart" :options="baseChartOptions" /></div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="text-sm font-semibold">{{ ctrans("Last 30 days") }}</h3>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Delivery notes picked and packed by the team per day") }}</p>
                <div class="h-52"><Bar :data="dailyChart" :options="baseChartOptions" /></div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4">
                <h3 class="text-sm font-semibold">{{ ctrans("Items per worked hour") }}</h3>
                <p class="mb-2 text-xs text-gray-500">{{ ctrans("Items handled per hour on the clock, per day; hours worked underneath") }}</p>
                <div class="h-32"><Line :data="productivityChart" :options="singleSeriesOptions" /></div>
                <div class="mt-1 h-20"><Bar :data="hoursChart" :options="singleSeriesOptions" /></div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white overflow-hidden">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-gray-200 px-4 py-2.5">
                <h2 class="font-semibold">{{ ctrans("People") }} <span class="ml-1 text-sm font-normal text-gray-500">{{ periodLabel }}</span></h2>
                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                    <span>{{ ctrans("Ranked by items per worked hour. Picking and packing are matched through the person's user account.") }}</span>
                    <label v-if="idlePeople" class="flex items-center gap-2">
                        <ToggleSwitch v-model="showIdlePeople" />
                        {{ ctrans("Show :n without activity", { n: idlePeople }) }}
                    </label>
                </div>
            </div>
            <DataTable :value="leaderboardRows" dataKey="id" size="small" scrollable removableSort>
                <template #empty>
                    <div class="py-8 text-center text-gray-500">
                        {{ data.team_size ? ctrans("Nobody in the team has clocked, picked or packed in this period") : ctrans("Nobody has a warehouse job position for this warehouse") }}
                    </div>
                </template>
                <Column field="rank" header="#" bodyClass="text-gray-400 tabular-nums w-8" />
                <Column field="name" :header="ctrans('Employee')" sortable>
                    <template #body="{ data: row }">
                        <div class="font-medium">{{ row.name }}</div>
                        <div class="text-xs text-gray-500">{{ row.positions.join(", ") }}<span v-if="!row.has_user" class="ml-1 text-amber-600" v-tooltip="ctrans('No user account, so picking and packing cannot be matched')">· {{ ctrans("no user") }}</span></div>
                    </template>
                </Column>
                <Column v-if="isSingleDay" field="first_in" :header="ctrans('In')" sortable>
                    <template #body="{ data: row }"><span class="tabular-nums">{{ timeIn(row.first_in, tz) }}</span></template>
                </Column>
                <Column v-if="isSingleDay" field="last_out" :header="ctrans('Out')" sortable>
                    <template #body="{ data: row }"><span class="tabular-nums">{{ row.first_in && !row.last_out ? "…" : timeIn(row.last_out, tz) }}</span></template>
                </Column>
                <Column v-else field="days_worked" :header="ctrans('Days')" sortable bodyClass="text-right tabular-nums" />
                <Column field="worked_seconds" :header="ctrans('Worked')" sortable bodyClass="text-right tabular-nums">
                    <template #body="{ data: row }">{{ hoursLabel(row.worked_seconds) }}</template>
                </Column>
                <Column field="picked_items" :header="ctrans('Picked')" sortable bodyClass="text-right tabular-nums">
                    <template #body="{ data: row }">
                        <span v-if="row.picked_dns" v-tooltip="ctrans(':dns delivery notes', { dns: row.picked_dns })">{{ row.picked_items.toLocaleString() }}</span>
                        <span v-else class="text-gray-300">–</span>
                    </template>
                </Column>
                <Column field="packed_items" :header="ctrans('Packed')" sortable bodyClass="text-right tabular-nums">
                    <template #body="{ data: row }">
                        <span v-if="row.packed_dns" v-tooltip="ctrans(':dns delivery notes', { dns: row.packed_dns })">{{ row.packed_items.toLocaleString() }}</span>
                        <span v-else class="text-gray-300">–</span>
                    </template>
                </Column>
                <Column field="items_per_hour" :header="ctrans('Items/h')" sortable>
                    <template #body="{ data: row }">
                        <div v-if="row.items_per_hour !== null" class="flex items-center gap-2">
                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                                <div class="h-full rounded-full bg-[--app-accent]" :style="{ width: `${Math.round((row.items_per_hour / maxItemsPerHour) * 100)}%` }" />
                            </div>
                            <span class="tabular-nums">{{ row.items_per_hour }}</span>
                        </div>
                        <span v-else class="text-gray-300">–</span>
                    </template>
                </Column>
                <Column field="short_picks" :header="ctrans('Short picks')" sortable bodyClass="text-right">
                    <template #body="{ data: row }">
                        <span v-if="row.short_picks" class="rounded bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700" v-tooltip="ctrans(':rate% of :lines pick lines', { rate: row.short_rate, lines: row.pick_lines + row.short_picks })">{{ row.short_picks }}</span>
                        <span v-else-if="row.pick_lines" class="text-xs text-green-600">0</span>
                        <span v-else class="text-gray-300">–</span>
                    </template>
                </Column>
                <Column field="late" :header="ctrans('Late')" sortable bodyClass="text-right">
                    <template #body="{ data: row }">
                        <span v-if="row.late" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">{{ row.late }}</span>
                        <span v-else class="text-gray-300">–</span>
                    </template>
                </Column>
                <Column bodyClass="text-right w-10">
                    <template #body="{ data: row }">
                        <Button type="transparent" size="xs" :icon="faPlus" v-tooltip="ctrans('Add clocking')" @click="emit('add-clocking', row.id)" />
                    </template>
                </Column>
            </DataTable>
        </section>
    </div>
</template>
