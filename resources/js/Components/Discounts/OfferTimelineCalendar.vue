<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { trans } from "laravel-vue-i18n"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import Select from "primevue/select"
import InputText from "primevue/inputtext"
import Card from "primevue/card"
import Tag from "primevue/tag"

type OfferRoute = {
    name: string
    parameters: Record<string, unknown>
}

type OfferRange = {
    from: string
    to: string
    raw_from?: string | null
    raw_to?: string | null
    label: string
    offer_code?: string | null
    campaign_code?: string | null
    campaign_type?: string | null
    duration_label?: string | null
    shop_code?: string | null
    shop_name?: string | null
    state?: string | null
    status?: boolean
    details?: OfferDetailPayload | null
    route?: OfferRoute | null
}

type CampaignTypeLegend = {
    type: string
    label: string
    color: string
}

type SelectOption = {
    value: string
    label: string
}

type TimelineMode = "week" | "month" | "quarter" | "year"

type TimelineColumn = {
    key: string
    label: string
    start: Date
    end: Date
}

type TimelineItem = {
    id: string
    label: string
    labelHtml: string
    offerCode?: string | null
    campaignCode?: string | null
    campaignName?: string | null
    campaignType?: string | null
    durationLabel?: string | null
    shopCode?: string | null
    shopName?: string | null
    rawFrom?: string | null
    rawTo?: string | null
    from: string
    to: string
    fromDate: Date
    toDate: Date
    details?: OfferDetailPayload | null
    route?: OfferRoute | null
    color: string
}

type OfferDetailPayload = {
    offer?: {
        id?: number
        slug?: string | null
        code?: string | null
        name?: string | null
        type?: string | null
        state?: string | null
        status?: boolean | null
        duration?: string | null
        duration_label?: string | null
        trigger_type?: string | null
        trigger_sub_type?: string | null
        is_discretionary?: boolean | null
        is_locked?: boolean | null
        start_at?: string | null
        end_at?: string | null
        label?: string | null
    } | null
    campaign?: {
        slug?: string | null
        code?: string | null
        name?: string | null
        type?: string | null
        status?: boolean | null
        offers_state?: string | null
        start_at?: string | null
        finish_at?: string | null
    } | null
    shop?: {
        slug?: string | null
        code?: string | null
        name?: string | null
    } | null
    stats?: {
        offer?: {
            first_used_at?: string | null
            last_used_at?: string | null
            number_customers?: number
            number_orders?: number
            number_invoices?: number
            number_delivery_notes?: number
            amount?: string | number | null
            org_amount?: string | number | null
            grp_amount?: string | number | null
        } | null
    } | null
}

const props = defineProps<{
    calendar: {
        year?: number
        holidayRanges?: OfferRange[]
        campaignTypeLegend?: CampaignTypeLegend[]
        organisationSlug?: string
        filters?: {
            campaign_type?: string | null
            shop?: string | number | null
            limit?: number | null
            year?: number
            month?: number | null
            search?: string | null
        }
        filterOptions?: {
            campaignTypes?: SelectOption[]
            shops?: SelectOption[]
            years?: SelectOption[]
        }
        pagination?: {
            total?: number
            loaded?: number
            limit?: number | null
            hasMore?: boolean
        }
    }
}>()

const mode = ref<TimelineMode>("month")
const selectedItem = ref<TimelineItem | null>(null)
const selectedCampaignType = ref<string>(props.calendar.filters?.campaign_type ?? "")
const selectedShop = ref<string>(props.calendar.filters?.shop !== null && props.calendar.filters?.shop !== undefined ? String(props.calendar.filters.shop) : "")
const baseYear = Number(props.calendar.year ?? new Date().getFullYear())
const selectedYear = ref<number | null>(props.calendar.filters?.year ?? baseYear)
const searchTerm = ref<string>((props.calendar.filters as any)?.search ?? "")
const DEFAULT_LIMIT = 50
const currentLimit = ref<number>(Number(props.calendar.filters?.limit ?? DEFAULT_LIMIT))
const headerTimelineRef = ref<HTMLElement | null>(null)
const bodyTimelineRef = ref<HTMLElement | null>(null)
const bodyVerticalRef = ref<HTMLElement | null>(null)
const isSyncingHorizontal = ref(false)
const isLoadingMore = ref(false)
const hoverTooltip = ref<{
    visible: boolean
    text: string
    x: number
    y: number
}>({
    visible: false,
    text: "",
    x: 0,
    y: 0,
})

const activeYear = computed(() => Number(props.calendar.filters?.year ?? baseYear))
const previewStartYear = computed(() => (mode.value === "year" ? activeYear.value - 2 : activeYear.value - 1))
const previewEndYear = computed(() => (mode.value === "year" ? activeYear.value + 2 : activeYear.value + 1))
const timelineStart = computed(() => new Date(previewStartYear.value, 0, 1, 0, 0, 0, 0))
const timelineEnd = computed(() => new Date(previewEndYear.value, 11, 31, 23, 59, 59, 999))

const leftColumnWidth = 300
const modeColumnWidth: Record<TimelineMode, number> = {
    week: 92,
    month: 140,
    quarter: 220,
    year: 300,
}

const monthNames = [
    trans("January"),
    trans("February"),
    trans("March"),
    trans("April"),
    trans("May"),
    trans("June"),
    trans("July"),
    trans("August"),
    trans("September"),
    trans("October"),
    trans("November"),
    trans("December"),
]

const shortMonthNames = [
    trans("Jan"),
    trans("Feb"),
    trans("Mar"),
    trans("Apr"),
    trans("May"),
    trans("Jun"),
    trans("Jul"),
    trans("Aug"),
    trans("Sep"),
    trans("Oct"),
    trans("Nov"),
    trans("Dec"),
]

const campaignTypeColorMap = computed(() => {
    const legend = props.calendar.campaignTypeLegend ?? []
    const map = new Map<string, string>()
    legend.forEach((entry) => {
        map.set(entry.type, entry.color)
    })
    return map
})

const campaignTypeLabelMap = computed(() => {
    const legend = props.calendar.campaignTypeLegend ?? []
    const map = new Map<string, string>()
    legend.forEach((entry) => {
        map.set(entry.type, entry.label)
    })
    return map
})

const campaignTypeColor = (type?: string | null): string => {
    if (!type) {
        return "#94a3b8"
    }
    return campaignTypeColorMap.value.get(type) ?? "#94a3b8"
}

const campaignTypeLabel = (type?: string | null): string => {
    if (!type) {
        return "-"
    }
    return campaignTypeLabelMap.value.get(type) ?? type
}

const plainText = (value?: string | null): string => {
    if (!value) {
        return ""
    }

    return value.replace(/<[^>]*>/g, "").replace(/\s+/g, " ").trim()
}

const asNumber = (value?: string | number | null): number => {
    if (value === null || value === undefined || value === "") {
        return 0
    }
    const parsed = Number(value)
    return Number.isFinite(parsed) ? parsed : 0
}

const formatNumber = (value?: string | number | null): string => {
    return new Intl.NumberFormat().format(asNumber(value))
}

const formatAmount = (value?: string | number | null): string => {
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(asNumber(value))
}

const boolLabel = (value?: boolean | null): string => {
    return value ? trans("Yes") : trans("No")
}

const isAllowedColor = (value: string): boolean => {
    const color = value.trim()
    return /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/.test(color)
        || /^rgb(a)?\([0-9.,%\s]+\)$/.test(color)
        || /^[a-zA-Z]+$/.test(color)
}

const toSafeHtml = (value?: string | null): string => {
    if (!value) {
        return ""
    }

    const parser = new DOMParser()
    const doc = parser.parseFromString(value, "text/html")
    const allowedTags = new Set(["SPAN", "STRONG", "B", "EM", "I", "U", "BR"])

    const walk = (node: Node): void => {
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return
        }

        const element = node as HTMLElement

        Array.from(element.children).forEach((child) => walk(child))

        if (!allowedTags.has(element.tagName)) {
            const parent = element.parentNode
            if (!parent) {
                return
            }

            while (element.firstChild) {
                parent.insertBefore(element.firstChild, element)
            }
            parent.removeChild(element)
            return
        }

        const styleRaw = element.getAttribute("style") ?? ""
        Array.from(element.attributes).forEach((attr) => {
            if (attr.name !== "style") {
                element.removeAttribute(attr.name)
            }
        })

        if (styleRaw) {
            const colorMatch = styleRaw.match(/color\s*:\s*([^;]+)/i)
            if (colorMatch && isAllowedColor(colorMatch[1])) {
                element.setAttribute("style", `color: ${colorMatch[1].trim()}`)
            } else {
                element.removeAttribute("style")
            }
        }
    }

    Array.from(doc.body.children).forEach((child) => walk(child))
    return doc.body.innerHTML
}

const startOfWeek = (date: Date): Date => {
    const d = new Date(date)
    const day = (d.getDay() + 6) % 7
    d.setDate(d.getDate() - day)
    d.setHours(0, 0, 0, 0)
    return d
}

const endOfWeek = (date: Date): Date => {
    const d = startOfWeek(date)
    d.setDate(d.getDate() + 6)
    d.setHours(23, 59, 59, 999)
    return d
}

const formatTwoDigitYear = (year: number): string => String(year).slice(-2)

const columns = computed<TimelineColumn[]>(() => {
    const cols: TimelineColumn[] = []
    const start = timelineStart.value
    const end = timelineEnd.value

    if (mode.value === "year") {
        for (let y = previewStartYear.value; y <= previewEndYear.value; y += 1) {
            cols.push({
                key: `y-${y}`,
                label: String(y),
                start: new Date(y, 0, 1, 0, 0, 0, 0),
                end: new Date(y, 11, 31, 23, 59, 59, 999),
            })
        }
        return cols
    }

    if (mode.value === "quarter") {
        for (let y = activeYear.value - 1; y <= activeYear.value + 1; y += 1) {
            for (let q = 0; q < 4; q += 1) {
                const startMonth = q * 3
                const endMonth = startMonth + 2
                cols.push({
                    key: `q-${y}-${q + 1}`,
                    label: `${shortMonthNames[startMonth]} - ${shortMonthNames[endMonth]} '${formatTwoDigitYear(y)}`,
                    start: new Date(y, startMonth, 1, 0, 0, 0, 0),
                    end: new Date(y, endMonth + 1, 0, 23, 59, 59, 999),
                })
            }
        }
        return cols
    }

    if (mode.value === "month") {
        for (let y = activeYear.value - 1; y <= activeYear.value + 1; y += 1) {
            for (let m = 0; m < 12; m += 1) {
                cols.push({
                    key: `m-${y}-${m + 1}`,
                    label: `${monthNames[m]} '${formatTwoDigitYear(y)}`,
                    start: new Date(y, m, 1, 0, 0, 0, 0),
                    end: new Date(y, m + 1, 0, 23, 59, 59, 999),
                })
            }
        }
        return cols
    }

    let weekStart = startOfWeek(start)
    const maxEnd = endOfWeek(end)
    while (weekStart <= maxEnd) {
        const weekEnd = endOfWeek(weekStart)
        cols.push({
            key: `w-${weekStart.toISOString()}`,
            label: `${shortMonthNames[weekStart.getMonth()]} ${weekStart.getDate()}`,
            start: new Date(weekStart),
            end: weekEnd,
        })
        weekStart = new Date(weekStart)
        weekStart.setDate(weekStart.getDate() + 7)
    }

    return cols
})

const columnWidth = computed(() => modeColumnWidth[mode.value])
const timelineWidth = computed(() => columns.value.length * columnWidth.value)

const items = computed<TimelineItem[]>(() => {
    const ranges = props.calendar.holidayRanges ?? []
    const map = new Map<string, TimelineItem>()

    for (const [idx, r] of ranges.entries()) {
        const fromDate = new Date(`${r.from}T00:00:00`)
        const toDate = new Date(`${r.to}T23:59:59`)

        const key = `${r.label}|${r.offer_code ?? ""}|${r.campaign_code ?? ""}|${r.campaign_type ?? ""}|${r.from}|${r.to}`
        if (map.has(key)) {
            continue
        }

        map.set(key, {
            id: `${idx}-${key}`,
            label: plainText(r.details?.offer?.name ?? r.label ?? null),
            labelHtml: toSafeHtml(r.details?.offer?.name ?? r.label ?? null),
            offerCode: plainText(r.offer_code ?? null),
            campaignCode: plainText(r.campaign_code ?? null),
            campaignName: toSafeHtml(r.details?.campaign?.name ?? null),
            campaignType: plainText(r.campaign_type ?? null),
            durationLabel: plainText(r.duration_label ?? null),
            shopCode: plainText(r.shop_code ?? null),
            shopName: plainText(r.shop_name ?? null),
            rawFrom: plainText(r.raw_from ?? r.from ?? null),
            rawTo: plainText(r.raw_to ?? null),
            from: r.from,
            to: r.to,
            fromDate,
            toDate,
            route: r.route ?? null,
            details: r.details ?? null,
            color: campaignTypeColor(r.campaign_type),
        })
    }

    return Array.from(map.values())
})

const totalRangeMs = computed(() => timelineEnd.value.getTime() - timelineStart.value.getTime())

const barStyle = (item: TimelineItem) => {
    const clipStartMs = Math.max(item.fromDate.getTime(), timelineStart.value.getTime())
    const clipEndMs = Math.min(item.toDate.getTime(), timelineEnd.value.getTime())

    if (clipEndMs < timelineStart.value.getTime() || clipStartMs > timelineEnd.value.getTime()) {
        return { display: "none" }
    }

    const leftRatio = (clipStartMs - timelineStart.value.getTime()) / totalRangeMs.value
    const widthRatio = Math.max(0, (clipEndMs - clipStartMs) / totalRangeMs.value)

    const leftPx = leftRatio * timelineWidth.value
    const widthPx = Math.max(4, widthRatio * timelineWidth.value)

    return {
        left: `${leftPx}px`,
        width: `${widthPx}px`,
    }
}

const openItem = (item: TimelineItem): void => {
    selectedItem.value = item
}

const showTooltipAtCursor = (event: MouseEvent, item: TimelineItem): void => {
    hoverTooltip.value.visible = true
    const from = item.rawFrom ?? item.from
    const to = item.rawTo || trans("No end date")
    hoverTooltip.value.text = `${trans("From")}: ${from} • ${trans("To")}: ${to}`
    hoverTooltip.value.x = event.clientX + 12
    hoverTooltip.value.y = event.clientY + 12
}

const moveTooltipAtCursor = (event: MouseEvent): void => {
    if (!hoverTooltip.value.visible) {
        return
    }

    hoverTooltip.value.x = event.clientX + 12
    hoverTooltip.value.y = event.clientY + 12
}

const hideTooltip = (): void => {
    hoverTooltip.value.visible = false
}


const syncHorizontal = (source: "header" | "body"): void => {
    if (isSyncingHorizontal.value) {
        return
    }

    isSyncingHorizontal.value = true

    if (source === "header" && headerTimelineRef.value && bodyTimelineRef.value) {
        bodyTimelineRef.value.scrollLeft = headerTimelineRef.value.scrollLeft
    }

    if (source === "body" && headerTimelineRef.value && bodyTimelineRef.value) {
        headerTimelineRef.value.scrollLeft = bodyTimelineRef.value.scrollLeft
    }

    requestAnimationFrame(() => {
        isSyncingHorizontal.value = false
    })
}

const applyFilters = (resetLimit = false): void => {
    if (resetLimit) {
        currentLimit.value = DEFAULT_LIMIT
    }

    const params: Record<string, string | number> = {}

    if (props.calendar.organisationSlug) {
        params.organisation = props.calendar.organisationSlug
    }

    params.year = Number(selectedYear.value ?? baseYear)

    const month = props.calendar.filters?.month
    if (month) {
        params.month = month
    }

    const limit = currentLimit.value
    if (limit) {
        params.limit = limit
    }

    if (searchTerm.value) {
        params.search = searchTerm.value
    }

    if (selectedCampaignType.value) {
        params.campaign_type = selectedCampaignType.value
    }

    if (selectedShop.value) {
        params.shop = selectedShop.value
    }

    ;(router as any).get(window.location.pathname, params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            isLoadingMore.value = false
        },
    })
}

const onBodyVerticalScroll = (): void => {
    const el = bodyVerticalRef.value

    if (!el || isLoadingMore.value) {
        return
    }

    const reachedBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 40
    if (!reachedBottom) {
        return
    }

    const total = props.calendar.pagination?.total ?? items.value.length
    const loaded = items.value.length

    if (loaded >= total) {
        return
    }

    isLoadingMore.value = true
    currentLimit.value = Math.min(currentLimit.value + 50, total)
    applyFilters()
}

const timelineBackgroundStyle = computed(() => ({
    backgroundImage: "linear-gradient(to right, rgba(148,163,184,0.22) 1px, transparent 1px)",
    backgroundSize: `${columnWidth.value}px 100%`,
}))

const campaignTypeLegend = computed(() =>
    (props.calendar.campaignTypeLegend ?? []).map((entry) => ({
        ...entry,
        color: campaignTypeColor(entry.type),
    }))
)

const yearOptions = computed<SelectOption[]>(() => {
    const optionsFromBackend = props.calendar.filterOptions?.years ?? []
    if (optionsFromBackend.length > 0) {
        return optionsFromBackend
    }
    const currentYear = new Date().getFullYear()
    return [
        { value: String(currentYear), label: String(currentYear) },
        { value: String(currentYear + 1), label: String(currentYear + 1) },
        { value: String(currentYear + 2), label: String(currentYear + 2) },
    ]
})

const campaignTypeOptions = computed<SelectOption[]>(() => [
    { value: "", label: trans("All Campaign Types") },
    ...(props.calendar.filterOptions?.campaignTypes ?? []),
])

const shopOptions = computed<SelectOption[]>(() => [
    { value: "", label: trans("All Shops") },
    ...(props.calendar.filterOptions?.shops ?? []),
])

watch(
    () => props.calendar.filters?.limit,
    (value) => {
        currentLimit.value = Number(value ?? DEFAULT_LIMIT)
    }
)

watch(
    () => selectedYear.value,
    (value) => {
        if (value === null) {
            selectedYear.value = baseYear
            applyFilters(true)
        }
    }
)
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Button
                    :key="`mode-week-${mode === 'week'}`"
                    :label="trans('Week')"
                    size="md"
                    :type="mode === 'week' ? 'black' : 'transparent'"
                    @click="mode = 'week'"
                />
                <Button
                    :key="`mode-month-${mode === 'month'}`"
                    :label="trans('Month')"
                    size="md"
                    :type="mode === 'month' ? 'black' : 'transparent'"
                    @click="mode = 'month'"
                />
                <Button
                    :key="`mode-quarter-${mode === 'quarter'}`"
                    :label="trans('Quarter')"
                    size="md"
                    :type="mode === 'quarter' ? 'black' : 'transparent'"
                    @click="mode = 'quarter'"
                />
                <Button
                    :key="`mode-year-${mode === 'year'}`"
                    :label="trans('Year')"
                    size="md"
                    :type="mode === 'year' ? 'black' : 'transparent'"
                    @click="mode = 'year'"
                />
            </div>

            <div class="flex items-center gap-2">
                <InputText
                    v-model="searchTerm"
                    class="w-64 text-xs"
                    :placeholder="trans('Search offer code or name')"
                    @keyup.enter="applyFilters(true)"
                />

                <Select
                    v-model="selectedCampaignType"
                    :options="campaignTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-56 text-xs"
                    :placeholder="trans('All Campaign Types')"
                    @change="applyFilters(true)"
                />
                <Select
                    v-model="selectedShop"
                    :options="shopOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-56 text-xs"
                    :placeholder="trans('All Shops')"
                    @change="applyFilters(true)"
                />
                <Select
                    v-model="selectedYear"
                    :options="yearOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-32 text-xs"
                    :placeholder="trans('Year')"
                    :showClear="selectedYear !== null"
                    @change="applyFilters(true)"
                />
            </div>
        </div>

        <div class="relative rounded-lg border border-gray-200 bg-white shadow-sm">
            <div
                v-if="isLoadingMore"
                class="absolute inset-0 z-30 flex items-center justify-center bg-white/60 backdrop-blur-[1px]"
            >
                <div class="h-9 w-9 animate-spin rounded-full border-4 border-slate-400 border-t-transparent" />
            </div>

            <div class="flex border-b border-gray-200 bg-gray-50">
                <div class="shrink-0 flex h-10 items-center border-r border-gray-200 bg-gray-50 px-3 text-xs font-semibold text-gray-600" :style="{ width: `${leftColumnWidth}px` }">
                    {{ trans('Vouchers') }}
                </div>

                <div
                    ref="headerTimelineRef"
                    class="flex-1 overflow-x-auto scrollbar-hide"
                    @scroll="syncHorizontal('header')"
                >
                    <div
                        class="grid h-10"
                        :style="{ width: `${timelineWidth}px`, gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }"
                    >
                        <div
                            v-for="column in columns"
                            :key="column.key"
                            class="flex items-center justify-center border-l border-gray-200 px-1 text-[11px] font-medium text-gray-500"
                        >
                            {{ column.label }}
                        </div>
                    </div>
                </div>
            </div>

            <div
                ref="bodyVerticalRef"
                class="max-h-[58vh] overflow-y-auto scrollbar-hide"
                @scroll="onBodyVerticalScroll"
            >
                <div class="flex">
                    <div class="shrink-0" :style="{ width: `${leftColumnWidth}px` }">
                        <div
                            v-for="item in items"
                            :key="`left-${item.id}`"
                            class="h-16 border-b border-gray-100 bg-white px-3"
                        >
                            <div class="flex h-full items-center gap-2">
                                <span class="inline-block h-1/3 w-1.5 shrink-0 rounded-sm" :style="{ backgroundColor: item.color }" />
                                <div class="min-w-0 flex-1 flex flex-col justify-center gap-0.5">
                                    <span
                                        class="truncate text-[13px] font-medium leading-tight text-gray-800"
                                        v-tooltip.top="plainText(item.label)"
                                    >
                                        {{ plainText(item.label) }}
                                    </span>
                                    <div class="truncate text-[12px] leading-tight text-gray-500">
                                        <span v-if="item.campaignCode">{{ plainText(item.campaignCode) }}</span>
                                        <span v-if="item.offerCode"> • {{ plainText(item.offerCode) }}</span>
                                        <span v-tooltip.top="plainText(item.campaignName)" v-if="item.campaignName"> • {{ plainText(item.campaignName) }}</span>
                                    </div>
                                    <div class="truncate text-[12px] leading-tight text-gray-500">
                                        <span v-if="item.shopCode">{{ plainText(item.shopCode) }}</span>
                                        <span v-if="item.shopName"> • {{ plainText(item.shopName) }}</span>
                                        <span v-if="item.durationLabel"> • {{ plainText(item.durationLabel) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        ref="bodyTimelineRef"
                        class="flex-1 overflow-x-auto scrollbar-hide"
                        @scroll="syncHorizontal('body')"
                    >
                        <div :style="{ width: `${timelineWidth}px` }">
                            <div
                                v-for="item in items"
                                :key="`right-${item.id}`"
                                class="relative h-16 border-b border-gray-100"
                                :style="timelineBackgroundStyle"
                            >
                                <button
                                    class="absolute top-1/2 h-3 -translate-y-1/2 rounded-full"
                                    :style="{ ...barStyle(item), backgroundColor: item.color }"
                                    @mouseenter="showTooltipAtCursor($event, item)"
                                    @mousemove="moveTooltipAtCursor($event)"
                                    @mouseleave="hideTooltip"
                                    @click="openItem(item)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="hoverTooltip.visible"
            class="fixed z-[100] rounded bg-slate-900 px-3 py-1.5 text-sm font-medium text-white shadow-lg pointer-events-none"
            :style="{ left: `${hoverTooltip.x}px`, top: `${hoverTooltip.y}px` }"
        >
            {{ hoverTooltip.text }}
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs font-semibold text-gray-600">
                {{ trans('Campaign Type Colors') }}
            </div>
            <div class="mt-2 flex flex-wrap gap-3">
                <div
                    v-for="legend in campaignTypeLegend"
                    :key="legend.type"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-2 py-1 text-[12px] text-gray-700"
                >
                    <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: legend.color }" />
                    <span>{{ legend.label }}</span>
                </div>
            </div>
        </div>

        <Modal
            :is-open="Boolean(selectedItem)"
            width="w-[96vw] md:w-[90vw] lg:w-[82vw]"
            :close-button="true"
            @onClose="selectedItem = null"
        >
            <div v-if="selectedItem" class="flex max-h-[68vh] flex-col gap-5 overflow-y-auto pr-1">
                <div class="rounded-xl border border-slate-200 bg-gradient-to-r from-white to-slate-50 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="text-2xl font-semibold leading-tight text-slate-900">
                                <span v-html="selectedItem.labelHtml" />
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-base text-slate-600">
                                <span>{{ trans("From") }}: {{ selectedItem.rawFrom ?? selectedItem.from }}</span>
                                <span>{{ trans("To") }}: {{ selectedItem.rawTo || trans("No end date") }}</span>
                            </div>
                        </div>
                        <div class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700">
                            <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: selectedItem.color }" />
                            <span>{{ campaignTypeLabel(selectedItem.details?.campaign?.type ?? selectedItem.campaignType ?? null) }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                    <Card>
                        <template #title>
                            <div class="text-lg font-semibold text-slate-800">{{ trans("Offer") }}</div>
                        </template>
                        <template #content>
                            <div class="grid grid-cols-[130px_1fr] gap-y-2 text-[15px] text-slate-700">
                                <div class="text-slate-500">{{ trans("Code") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.offer?.code ?? selectedItem.offerCode ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Name") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.offer?.name ?? selectedItem.label ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Type") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.offer?.type ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("State") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.offer?.state ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Status") }}</div>
                                <div class="inline-flex items-center">
                                    <Tag :value="boolLabel(selectedItem.details?.offer?.status)" :severity="selectedItem.details?.offer?.status ? 'success' : 'secondary'" rounded />
                                </div>
                                <div class="text-slate-500">{{ trans("Duration") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.offer?.duration_label ?? selectedItem.durationLabel ?? "-" }}</div>
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <div class="text-lg font-semibold text-slate-800">{{ trans("Campaign") }}</div>
                        </template>
                        <template #content>
                            <div class="grid grid-cols-[130px_1fr] gap-y-2 text-[15px] text-slate-700">
                                <div class="text-slate-500">{{ trans("Code") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.campaign?.code ?? selectedItem.campaignCode ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Name") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.campaign?.name ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Type") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.campaign?.type ?? selectedItem.campaignType ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Offers State") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.campaign?.offers_state ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Status") }}</div>
                                <div class="inline-flex items-center">
                                    <Tag :value="boolLabel(selectedItem.details?.campaign?.status)" :severity="selectedItem.details?.campaign?.status ? 'success' : 'secondary'" rounded />
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <div class="text-lg font-semibold text-slate-800">{{ trans("Shop") }}</div>
                        </template>
                        <template #content>
                            <div class="grid grid-cols-[130px_1fr] gap-y-2 text-[15px] text-slate-700">
                                <div class="text-slate-500">{{ trans("Code") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.shop?.code ?? selectedItem.shopCode ?? "-" }}</div>
                                <div class="text-slate-500">{{ trans("Name") }}</div><div class="font-medium text-slate-900">{{ selectedItem.details?.shop?.name ?? selectedItem.shopName ?? "-" }}</div>
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>
                            <div class="text-lg font-semibold text-slate-800">{{ trans("Date Time") }}</div>
                        </template>
                        <template #content>
                            <div class="grid grid-cols-[130px_1fr] gap-y-2 text-[15px] text-slate-700">
                                <div class="text-slate-500">{{ trans("Offer Start") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.offer?.start_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                                <div class="text-slate-500">{{ trans("Offer End") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.offer?.end_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                                <div class="text-slate-500">{{ trans("Campaign Start") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.campaign?.start_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                                <div class="text-slate-500">{{ trans("Campaign End") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.campaign?.finish_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                            </div>
                        </template>
                    </Card>
                </div>

                <Card>
                    <template #title>
                        <div class="text-lg font-semibold text-slate-800">{{ trans("Offer Stats") }}</div>
                    </template>
                    <template #content>
                        <div class="grid grid-cols-1 gap-4 text-[15px] text-slate-700 xl:grid-cols-2">
                            <div class="grid grid-cols-[130px_1fr] gap-y-2">
                                <div class="text-slate-500">{{ trans("Customers") }}</div><div class="font-medium text-slate-900">{{ formatNumber(selectedItem.details?.stats?.offer?.number_customers) }}</div>
                                <div class="text-slate-500">{{ trans("Invoices") }}</div><div class="font-medium text-slate-900">{{ formatNumber(selectedItem.details?.stats?.offer?.number_invoices) }}</div>
                                <div class="text-slate-500">{{ trans("Amount") }}</div><div class="font-medium text-slate-900">{{ formatAmount(selectedItem.details?.stats?.offer?.amount) }}</div>
                                <div class="text-slate-500">{{ trans("Group Amount") }}</div><div class="font-medium text-slate-900">{{ formatAmount(selectedItem.details?.stats?.offer?.grp_amount) }}</div>
                                <div class="text-slate-500">{{ trans("Last Used") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.stats?.offer?.last_used_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                            </div>
                            <div class="grid grid-cols-[130px_1fr] gap-y-2">
                                <div class="text-slate-500">{{ trans("Orders") }}</div><div class="font-medium text-slate-900">{{ formatNumber(selectedItem.details?.stats?.offer?.number_orders) }}</div>
                                <div class="text-slate-500">{{ trans("Delivery Notes") }}</div><div class="font-medium text-slate-900">{{ formatNumber(selectedItem.details?.stats?.offer?.number_delivery_notes) }}</div>
                                <div class="text-slate-500">{{ trans("Org Amount") }}</div><div class="font-medium text-slate-900">{{ formatAmount(selectedItem.details?.stats?.offer?.org_amount) }}</div>
                                <div class="text-slate-500">{{ trans("First Used") }}</div><div class="font-medium text-slate-900">{{ useFormatTime(selectedItem.details?.stats?.offer?.first_used_at ?? undefined, { formatTime: "dd MMMM yyyy" }) }}</div>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
