<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router, usePage } from "@inertiajs/vue3"
import { useMediaQuery } from "@vueuse/core"
import Chart from "primevue/chart"
import DatePicker from "primevue/datepicker"
import Select from "primevue/select"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import GoogleAdsMetric from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsMetric.vue"
import TableShoppingAssistantConversations from "@/Components/Tables/Grp/Org/Web/TableShoppingAssistantConversations.vue"
import TableShoppingAssistantWebsites from "@/Components/Tables/Grp/Org/Web/TableShoppingAssistantWebsites.vue"
import { capitalize } from "@/Composables/capitalize"
import { useCurrentTab } from "@/Composables/tab-change"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Navigation } from "@/types/Tabs"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faComments, faShoppingCart, faMoneyBillWave, faPercentage, faCoins, faUndo, faTimes, faRobot, faUserHeadset, faExclamationTriangle, faHeadSideBrain } from "@fal"

library.add(faComments, faShoppingCart, faMoneyBillWave, faPercentage, faCoins, faUndo, faTimes, faRobot, faUserHeadset, faExclamationTriangle, faHeadSideBrain)

type Metric = { value: number; previous: number | null }
type Option = { value: string | number; label: string }
type RouteRef = { name: string; parameters: Record<string, any> }

type Overview = {
    mature_until: string | null
    kpis: Record<string, Metric | null>
    cost_currency?: string
    funnel?: { key: string; value: number; previous: number | null }[]
    trend?: { granularity: "day" | "week"; points: { date: string; conversations: number; shown: number; clicked: number; added: number; ordered: number; complete: boolean }[] }
    drop_off?: { questions: string; conversations: number }[]
    families?: { id: number; code: string; name: string; shown: number; clicked: number; added: number; ordered: number; revenue: number }[]
    search?: {
        ignored_filters?: string[]
        searches?: Metric
        zero_results?: Metric
        zero_result_rate?: Metric
        suggestion_clicks?: Metric
        zero_rescued?: Metric
        seen?: Metric
        offered?: Metric
        by_kind?: { kind: string; shown?: number; clicks: number }[]
        top_queries?: { query: string; searches: number; clicks: number }[]
        top_zero_queries?: { query: string; searches: number; rescued: number }[]
    }
    assistant_reach?: { available?: Metric; offered?: Metric; asked?: Metric }
    refusals?: { rate_limit?: Metric; budget?: Metric; customer_cap?: Metric }
    general?: {
        questions: Metric
        answered: Metric
        by_source: { key: string; label: string; answers: number; general: string }[]
        offers: Metric
        by_reason: { reason: string; label: string; offered: number; handed_over: number }[]
        hand_overs: Metric
        by_route: { route: string; label: string; hand_overs: number }[]
        rate_per_question: { value: number | null; previous: number | null }
        rate_per_conversation: { value: number | null; previous: number | null }
        first_reply: Record<"in_hours" | "after_hours", { hand_overs: number; replied: number; median_minutes: number | null }>
        outcomes: { hand_overs: number; replied: number; closed: number; ordered: number; ordered_base: number }
        wrong_answers: Metric
        ai_cost: Metric
        gaps: { question: string; count: number; languages: string[]; last_asked_at: string; conversation: string }[]
    }
    feedback?: Record<"current" | "previous", { answers: number; up: number; down: number }>
    challenges?: {
        tagged: number
        untagged: number
        by_type: { type: string; label: string; conversations: number }[]
        top: { type: string; label: string; topic: string; conversations: number; quote: string | null; conversation: string }[]
    }
    gurus?: {
        asked: number
        answered: number
        answered_live: number
        closed: number
        open: number
        kept: number
        median_minutes: number | null
        routed: Record<string, number>
        people: { name: string; answered: number; median_minutes: number | null; declined: number; passed: number }[]
        latest: { reference: string; question: string; shop: string; status: "open" | "answered" | "closed"; by: string | null; minutes: number | null; url: string }[]
    }
}

type FilterKey = "website" | "language" | "entry_point" | "shop_type" | "country"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: { current: string; navigation: Navigation }
    filters: {
        from: string
        to: string
        previous_from?: string
        previous_to?: string
        website: number | null
        language: string | null
        entry_point: string | null
        shop_type: string | null
        country: string | null
        outcome: string | null
        general: string | null
        website_locked: boolean
        options?: {
            websites?: Option[]
            languages?: Option[]
            entry_points?: Option[]
            shop_types?: Option[]
            countries?: Option[]
        }
    }
    currency: string
    cost_currency?: string
    attribution_days: number
    budgets: { website: string; monthly: number; spent: number; paused: boolean; resumes_on: string }[]
    sales_leads?: {
        reference: string
        kind: "big_order" | "restock"
        product: string | null
        answer_channel: string | null
        customer: string | null
        website: string | null
        amount: number | null
        currency: string | null
        agreed_to_call: boolean
        status: string
        status_label: string
        assignee: string | null
        reminders: number
        created_at: string
        url: string
    }[]
    routes: { page: RouteRef; conversation: RouteRef }
    overview?: Overview
    conversations?: object
    shops?: object
}>()

const locale = useLocaleStore()
const currentTab = useCurrentTab(props.tabs.current ?? "overview")
const isLoading = ref(false)

const filterKeys: FilterKey[] = ["website", "language", "entry_point", "shop_type", "country"]

const page = usePage()
const urlQuery = computed(() => new URLSearchParams(page.url.split("?")[1] ?? ""))

const filterQuery = (overrides: Record<string, any> = {}) => {
    const query: Record<string, any> = {}
    if (urlQuery.value.has("from")) query.from = props.filters.from
    if (urlQuery.value.has("to")) query.to = props.filters.to
    filterKeys.forEach((key) => {
        if (key === "website" && props.filters.website_locked) return
        if (props.filters[key] !== null && props.filters[key] !== undefined) query[key] = props.filters[key]
    })
    Object.entries(overrides).forEach(([key, value]) => {
        if (value === null || value === undefined || value === "") delete query[key]
        else query[key] = value
    })
    return query
}

const pageUrl = (query: Record<string, any>) => route(props.routes.page.name, { ...props.routes.page.parameters, ...query })

const visit = (query: Record<string, any>, tab: string) =>
    router.get(pageUrl({ ...query, tab }), {}, {
        only: ["tabs", "filters", "budgets", "sales_leads", tab],
        preserveState: true,
        preserveScroll: true,
        onStart: () => (isLoading.value = true),
        onFinish: () => (isLoading.value = false),
    })

const keepOutcome = () => {
    if (currentTab.value !== "conversations") return {}
    return { ...(props.filters.outcome ? { outcome: props.filters.outcome } : {}), ...(props.filters.general ? { general: props.filters.general } : {}) }
}

const setFilter = (key: string, value: string | number | null) => visit(filterQuery({ ...keepOutcome(), [key]: value }), currentTab.value)

const handleTabUpdate = (tab: string) => {
    if (tab === currentTab.value) return
    visit(filterQuery(), tab)
}

const conversationsUrl = (outcome?: string, general?: string) =>
    pageUrl({ ...filterQuery(), tab: "conversations", ...(outcome ? { outcome } : {}), ...(general ? { general } : {}) })

const hasActiveFilters = computed(
    () => urlQuery.value.has("from") || urlQuery.value.has("to") || filterKeys.some((key) => !(key === "website" && props.filters.website_locked) && props.filters[key] !== null && props.filters[key] !== undefined)
)
const resetFilters = () => visit({}, currentTab.value)

const fromIsoDate = (iso: string | null | undefined): Date | null => {
    if (!iso) return null
    const [year, month, day] = iso.split("-").map(Number)
    return new Date(year, month - 1, day)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const dateModel = (key: "from" | "to") =>
    computed<Date | null>({
        get: () => fromIsoDate(props.filters[key]),
        set: (value) => {
            if (!value) return
            setFilter(key, toLocalIsoDate(value))
        },
    })
const rangeFrom = dateModel("from")
const rangeTo = dateModel("to")

const isNarrowScreen = useMediaQuery("(max-width: 639px)")
const dateFormat = computed(() => (isNarrowScreen.value ? "dd/mm/yy" : "d M yy"))
const datePickerPt = computed(() => ({ pcInputText: { root: { class: isNarrowScreen.value ? "!w-28 !py-1 !text-xs" : "!w-32 !py-1 !text-xs" } } }))
const filterSelectPt = { label: { class: "!py-1 !text-xs" } }
const fieldFocusClass = "[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]"

const selectFilters = computed(() =>
    [
        { key: "website" as const, placeholder: ctrans("All websites"), options: props.filters.options?.websites ?? [], hidden: props.filters.website_locked },
        { key: "language" as const, placeholder: ctrans("All languages"), options: props.filters.options?.languages ?? [] },
        { key: "entry_point" as const, placeholder: ctrans("All entry points"), options: props.filters.options?.entry_points ?? [] },
        { key: "shop_type" as const, placeholder: ctrans("All shop types"), options: props.filters.options?.shop_types ?? [] },
        { key: "country" as const, placeholder: ctrans("All countries"), options: props.filters.options?.countries ?? [] },
    ].filter((filter) => !filter.hidden)
)

const shortDate = (iso: string | null | undefined) => (iso ? useFormatTime(iso, { formatTime: "PP" }) : "")

const outcomeLabels: Record<string, string> = {
    shown: ctrans("shown products"),
    clicked: ctrans("clicked a product"),
    added: ctrans("added to basket"),
    ordered: ctrans("ordered"),
}

const transcriptUrl = (ulid: string) => route(props.routes.conversation.name, { ...props.routes.conversation.parameters, conversation: ulid })

const generalLabels: Record<string, string> = {
    questions: ctrans("general questions"),
    answered: ctrans("answered by the assistant"),
    offered: ctrans("a person was offered"),
    handed_over: ctrans("passed to a person"),
    wrong: ctrans("an answer marked wrong"),
    gap: ctrans("the assistant could not answer"),
    in_hours: ctrans("passed to a person in chat hours"),
    after_hours: ctrans("passed to a person outside chat hours"),
    replied: ctrans("a person replied after passing"),
    closed: ctrans("chat closed after passing"),
    ordered: ctrans("ordered after passing to a person"),
    thumbs_up: ctrans("an answer rated helpful"),
    thumbs_down: ctrans("an answer rated not helpful"),
    challenges: ctrans("a customer challenge"),
}

const generalFilterLabel = (value: string | null): string => {
    if (!value) return ""
    if (generalLabels[value]) return generalLabels[value]
    const [prefix, suffix] = value.split(":")
    const plain = (suffix ?? value).replace(/_/g, " ")
    const general = props.overview?.general
    if (prefix === "source" || prefix === "kind") return general?.by_source.find((row) => row.general === value)?.label ?? plain
    if (prefix === "reason") return ctrans("passed to a person: :reason", { reason: general?.by_reason.find((row) => row.reason === suffix)?.label ?? plain })
    if (prefix === "offer") return ctrans("offered a person: :reason", { reason: general?.by_reason.find((row) => row.reason === suffix)?.label ?? plain })
    if (prefix === "route") return ctrans("passed to a person by :route", { route: general?.by_route.find((row) => row.route === suffix)?.label ?? plain })
    if (prefix === "challenge") return ctrans("customer challenge: :type", { type: props.overview?.challenges?.by_type.find((row) => row.type === suffix)?.label ?? plain })
    return value
}

const budgetRows = computed(() =>
    (props.budgets ?? []).map((budget) => {
        const used = budget.monthly > 0 ? budget.spent / budget.monthly : 1
        return { ...budget, width: Math.min(100, used * 100), warning: !budget.paused && used >= 0.8 }
    })
)

const salesLeads = computed(() => props.sales_leads ?? [])
const leadContact = (lead: { kind: string; agreed_to_call: boolean; answer_channel: string | null }) =>
    lead.kind === "big_order"
        ? (lead.agreed_to_call ? ctrans("Asked for a call") : ctrans("Call not agreed yet"))
        : lead.answer_channel === "email"
            ? ctrans("Answer emailed")
            : lead.answer_channel === "customer_services"
                ? ctrans("Answer passed to customer services")
                : ctrans("Waiting for an answer")
const leadStatusClass = (lead: { status: string; assignee: string | null }) =>
    lead.status === "done" ? "text-green-700" : lead.status === "cancelled" ? "text-gray-500" : lead.assignee ? "text-gray-700" : "text-amber-700"

const kpis = computed(() => props.overview?.kpis ?? {})
const kpi = (key: string): Metric => kpis.value[key] ?? { value: 0, previous: null }
const costCurrency = computed(() => props.overview?.cost_currency ?? props.cost_currency ?? props.currency)
const money = (amount: number | null | undefined, code = props.currency) => locale.currencyFormat(code, amount ?? 0)
const percent = (fraction: number | null | undefined) => (fraction === null || fraction === undefined ? "—" : (fraction * 100).toFixed(1) + "%")

const change = (metric: Metric | null | undefined, better: "up" | "down" | "none" = "up", points = false) => {
    if (!metric || metric.previous === null || metric.previous === undefined) return null
    let text: string
    let direction: number
    if (points) {
        const delta = (metric.value - metric.previous) * 100
        text = (delta >= 0 ? "+" : "") + delta.toFixed(1) + " " + ctrans("pt")
        direction = Math.sign(delta)
    } else if (metric.previous === 0) {
        if (metric.value <= 0) return null
        text = ctrans("new")
        direction = 1
    } else {
        const delta = ((metric.value - metric.previous) / metric.previous) * 100
        text = (delta >= 0 ? "+" : "") + delta.toFixed(1) + "%"
        direction = Math.sign(delta)
    }
    const improved = better === "up" ? direction > 0 : direction < 0
    const cls = direction === 0 || better === "none" ? "text-gray-500" : improved ? "text-[#006300]" : "text-[#d03b3b]"
    return { text, cls }
}

const comparable = (metric: Metric | null | undefined) =>
    !metric || metric.previous === null || metric.previous === undefined || (!metric.previous && !metric.value) ? undefined : metric.previous

const hasConversations = computed(() => kpi("conversations").value > 0)

const kpiCards = computed(() => {
    const share = kpis.value.ai_cost_share
    return [
        {
            key: "conversations",
            label: ctrans("Conversations"),
            icon: faComments,
            metric: kpi("conversations"),
            kind: "count" as const,
            better: "up" as const,
            href: conversationsUrl(),
            lines: [
                ctrans(":count customers", { count: locale.number(kpi("customers").value) }),
                ctrans(":count returning customers", { count: locale.number(kpi("returning_customers").value) }),
            ],
        },
        {
            key: "orders",
            label: ctrans("Orders after being shown"),
            icon: faShoppingCart,
            metric: kpi("orders"),
            kind: "count" as const,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("strict: :count added from the assistant", { count: locale.number(kpi("strict_orders").value) })],
        },
        {
            key: "revenue",
            label: ctrans("Revenue of the products shown"),
            icon: faMoneyBillWave,
            metric: kpi("revenue"),
            kind: "money" as const,
            currency: props.currency,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("strict: :amount", { amount: money(kpi("strict_revenue").value) })],
        },
        {
            key: "conversion",
            label: ctrans("Conversion"),
            icon: faPercentage,
            metric: kpi("conversion_rate"),
            kind: "rate" as const,
            better: "up" as const,
            href: conversationsUrl("ordered"),
            lines: [ctrans("conversations older than :days days", { days: props.attribution_days ?? 7 })],
        },
        {
            key: "ai_cost",
            label: ctrans("AI cost"),
            icon: faCoins,
            metric: kpi("ai_cost"),
            kind: "money" as const,
            currency: costCurrency.value,
            better: "none" as const,
            href: null,
            lines: [
                ctrans(":amount per conversation", { amount: locale.currencyFormatUnitCost(costCurrency.value, kpi("ai_cost_per_conversation").value) }),
                ...(share && share.value !== null && share.value !== undefined ? [ctrans(":share of revenue", { share: percent(share.value) })] : []),
            ],
        },
    ]
})

const general = computed(() => props.overview?.general)
const hasGeneral = computed(() => !!general.value && (general.value.questions.value > 0 || general.value.hand_overs.value > 0))

const waitLabel = (minutes: number | null | undefined) => {
    if (minutes === null || minutes === undefined) return "–"
    const rounded = Math.round(minutes)
    if (rounded < 90) return ctrans(":minutes min", { minutes: rounded })
    return ctrans(":hours h :minutes min", { hours: Math.floor(rounded / 60), minutes: rounded % 60 })
}

const generalCards = computed(() => {
    const data = general.value
    if (!data) return []
    const rate = (metric: { value: number | null; previous: number | null }): Metric => ({ value: metric.value as number, previous: metric.value === null ? null : metric.previous })
    const perConversation = data.rate_per_conversation.value
    return [
        { key: "questions", label: ctrans("General questions"), icon: faComments, metric: data.questions, kind: "count" as const, better: "none" as const, href: conversationsUrl(undefined, "questions"), lines: [] as string[] },
        { key: "answered", label: ctrans("Answered by the assistant"), icon: faRobot, metric: data.answered, kind: "count" as const, better: "up" as const, href: conversationsUrl(undefined, "answered"), lines: [] as string[] },
        { key: "hand_overs", label: ctrans("Passed to a person"), icon: faUserHeadset, metric: data.hand_overs, kind: "count" as const, better: "none" as const, href: conversationsUrl(undefined, "handed_over"), lines: [] as string[] },
        {
            key: "rate",
            label: ctrans("Hand-over rate"),
            icon: faPercentage,
            metric: rate(data.rate_per_question),
            kind: "rate" as const,
            better: "none" as const,
            href: conversationsUrl(undefined, "handed_over"),
            lines: [
                ctrans("per question"),
                ...(perConversation === null ? [] : [ctrans(":share of conversations", { share: percent(perConversation) })]),
            ],
        },
        {
            key: "ai_cost",
            label: ctrans("AI cost of these answers"),
            icon: faCoins,
            metric: data.ai_cost,
            kind: "money" as const,
            currency: costCurrency.value,
            better: "none" as const,
            href: null as string | null,
            lines: [] as string[],
        },
        { key: "wrong", label: ctrans("Answers marked wrong"), icon: faExclamationTriangle, metric: data.wrong_answers, kind: "count" as const, better: "down" as const, href: conversationsUrl(undefined, "wrong"), lines: [] as string[] },
    ]
})

const showMaturityNote = computed(() => {
    const until = props.overview?.mature_until
    return !!until && until < props.filters.to
})

const funnelLabels: Record<string, string> = {
    conversations: ctrans("Conversations"),
    shown: ctrans("Products shown"),
    clicked: ctrans("Clicked"),
    added: ctrans("Added to basket"),
    ordered: ctrans("Ordered"),
}
const funnel = computed(() => {
    const steps = props.overview?.funnel ?? []
    const first = steps[0]?.value ?? 0
    return steps.map((step, index) => {
        const previousStep = index > 0 ? steps[index - 1].value : null
        return {
            ...step,
            label: funnelLabels[step.key] ?? step.key,
            width: first > 0 ? Math.max((step.value / first) * 100, step.value > 0 ? 1 : 0) : 0,
            ofPrevious: previousStep && step.value <= previousStep ? step.value / previousStep : null,
            ofFirst: index > 0 && first > 0 ? step.value / first : null,
            href: index > 0 ? conversationsUrl(step.key) : null,
        }
    })
})

const seriesColours = { conversations: "#2a78d6", clicked: "#eb6834", added: "#1baf7a", ordered: "#eda100" }
const trendPoints = computed(() => props.overview?.trend?.points ?? [])
const hasIncompletePoints = computed(() => trendPoints.value.some((point) => !point.complete))
const isWeekly = computed(() => props.overview?.trend?.granularity === "week")

const chartData = computed(() => {
    const points = trendPoints.value
    const dashed = (context: any) => (points[context.p1DataIndex] && !points[context.p1DataIndex].complete ? [5, 4] : undefined)
    const series = [
        { key: "conversations", label: ctrans("Conversations") },
        { key: "clicked", label: ctrans("Clicked") },
        { key: "added", label: ctrans("Added to basket") },
        { key: "ordered", label: ctrans("Ordered") },
    ] as const
    return {
        labels: points.map((point) => point.date),
        datasets: series.map((serie) => ({
            label: serie.label,
            data: points.map((point) => point[serie.key] ?? 0),
            borderColor: seriesColours[serie.key],
            backgroundColor: seriesColours[serie.key],
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
            segment: { borderDash: dashed },
        })),
    }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: "index", intersect: false },
    plugins: {
        legend: { position: "bottom", labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } } },
        tooltip: {
            callbacks: {
                title: (items: any[]) => {
                    const point = trendPoints.value[items[0].dataIndex]
                    const label = (isWeekly.value ? ctrans("Week of") + " " : "") + useFormatTime(items[0].label, { formatTime: "PP" })
                    return point && !point.complete ? `${label} (${ctrans("not final yet")})` : label
                },
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: {
                autoSkip: true,
                maxTicksLimit: isNarrowScreen.value ? 4 : 10,
                maxRotation: 0,
                font: { size: 10 },
                callback: function (this: any, value: number) {
                    return useFormatTime(this.getLabelForValue(value), { formatTime: "d MMM" })
                },
            },
        },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: "rgba(0,0,0,0.05)" } },
    },
}))

const dropOffLabels: Record<string, string> = {
    "1": ctrans("1 question"),
    "2": ctrans("2 questions"),
    "3": ctrans("3 questions"),
    "4+": ctrans("4 or more questions"),
}
const dropOff = computed(() => {
    const rows = props.overview?.drop_off ?? []
    const total = rows.reduce((sum, row) => sum + (row.conversations ?? 0), 0)
    return rows.map((row) => ({
        label: dropOffLabels[row.questions] ?? row.questions,
        count: row.conversations ?? 0,
        share: total > 0 ? (row.conversations ?? 0) / total : 0,
    }))
})

const aovDifference = computed(() => kpi("aov_assistant").value - kpi("aov_others").value)
const engagementRows = computed(() => [
    { label: ctrans("Average time"), value: kpi("avg_minutes").value.toFixed(1) + " " + ctrans("min"), change: change(kpis.value.avg_minutes, "none") },
    { label: ctrans("Average messages"), value: kpi("avg_messages").value.toFixed(1), change: change(kpis.value.avg_messages, "none") },
    { label: ctrans("Average order, assistant users"), value: money(kpi("aov_assistant").value), change: change(kpis.value.aov_assistant) },
    { label: ctrans("Average order, other customers"), value: money(kpi("aov_others").value), change: change(kpis.value.aov_others) },
    {
        label: ctrans("Difference"),
        value: (aovDifference.value > 0 ? "+" : "") + money(aovDifference.value),
        valueClass: aovDifference.value > 0 ? "text-[#006300]" : aovDifference.value < 0 ? "text-[#d03b3b]" : "",
        change: null,
    },
    { label: ctrans("Families ordered for the first time"), value: locale.number(kpi("new_families").value), change: change(kpis.value.new_families) },
])

const families = computed(() => props.overview?.families ?? [])

const search = computed(() => props.overview?.search)
const ignoredFilterLabels: Record<string, string> = {
    entry_point: ctrans("entry point"),
    shop_type: ctrans("shop type"),
    country: ctrans("country"),
    language: ctrans("language"),
    website: ctrans("website"),
}
const ignoredFilters = computed(() => (search.value?.ignored_filters ?? []).map((key) => ignoredFilterLabels[key] ?? key.replace(/_/g, " ")).join(", "))

const kindLabels: Record<string, string> = {
    did_you_mean: ctrans("Did you mean"),
    related_search: ctrans("Related searches"),
    popular_search: ctrans("Popular searches"),
    family: ctrans("Sells well with"),
    best_sellers: ctrans("Best sellers"),
    new_arrivals: ctrans("New arrivals"),
    forgotten: ctrans("Forgotten favourites"),
}
const clicksByKind = computed(() => {
    const rows = search.value?.by_kind ?? []
    const max = Math.max(0, ...rows.map((row) => row.clicks ?? 0))
    return rows.map((row) => ({
        label: kindLabels[row.kind] ?? row.kind,
        shown: row.shown ?? null,
        clicks: row.clicks ?? 0,
        rate: row.shown ? (row.clicks ?? 0) / row.shown : null,
        width: max > 0 ? ((row.clicks ?? 0) / max) * 100 : 0,
    }))
})

const seenShare = computed(() => {
    const offered = search.value?.offered?.value ?? 0
    return offered > 0 ? (search.value?.seen?.value ?? 0) / offered : null
})

const assistantReach = computed(() => {
    const reach = props.overview?.assistant_reach
    if (!reach) return []
    const steps = [
        { key: "available", label: ctrans("Searches with the Ask button"), metric: reach.available },
        { key: "offered", label: ctrans("Offer shown"), metric: reach.offered },
        { key: "asked", label: ctrans("Conversations opened from search"), metric: reach.asked },
    ]
    const first = steps[0].metric?.value ?? 0
    return steps.map((step, index) => {
        const value = step.metric?.value ?? 0
        const previousStep = index > 0 ? steps[index - 1].metric?.value ?? 0 : 0
        return {
            ...step,
            value,
            change: change(step.metric),
            width: first > 0 ? Math.min(Math.max((value / first) * 100, value > 0 ? 1 : 0), 100) : 0,
            ofPrevious: index > 0 && previousStep > 0 ? value / previousStep : null,
        }
    })
})

const feedback = computed(() => props.overview?.feedback ?? null)
const gurus = computed(() => (props.overview?.gurus?.asked ? props.overview.gurus : null))
const guruRouteLabels = computed<Record<string, string>>(() => ({
    guru: ctrans("the guru"),
    backup: ctrans("the backup guru"),
    customer_services: ctrans("customer services, gurus offline"),
    nobody_online: ctrans("waited, nobody online"),
}))
const guruStatusLabel = (status: string) => ({ open: ctrans("Waiting"), answered: ctrans("Answered"), closed: ctrans("Not answered") }[status] ?? status)
const guruStatusClass = (status: string) => ({ open: "text-amber-700", answered: "text-green-700", closed: "text-red-700" }[status] ?? "text-gray-600")
const challenges = computed(() => props.overview?.challenges ?? null)

const refusals = computed(() => ({
    rateLimit: props.overview?.refusals?.rate_limit?.value ?? 0,
    budget: props.overview?.refusals?.budget?.value ?? 0,
    customerCap: props.overview?.refusals?.customer_cap?.value ?? 0,
}))

const conversationsTable = computed(() => props.conversations)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="border-b border-gray-200 bg-white px-4 py-2">
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <div class="flex items-center gap-1.5">
                <DatePicker v-model="rangeFrom" :maxDate="rangeTo ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('From')" />
                <span class="text-gray-400">–</span>
                <DatePicker v-model="rangeTo" :minDate="rangeFrom ?? undefined" :dateFormat="dateFormat" :manualInput="false" showIcon iconDisplay="input" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('To')" />
            </div>
            <Select
                v-for="filter in selectFilters"
                :key="filter.key"
                :modelValue="filters[filter.key] ?? null"
                :options="filter.options"
                optionLabel="label"
                optionValue="value"
                :placeholder="filter.placeholder"
                :filter="filter.options.length > 10"
                showClear
                class="w-full sm:w-44"
                :class="fieldFocusClass"
                :pt="filterSelectPt"
                @update:modelValue="(value: any) => setFilter(filter.key, value ?? null)" />
            <Button v-if="hasActiveFilters" type="tertiary" size="xs" :label="ctrans('Reset')" @click="resetFilters">
                <template #icon>
                    <FontAwesomeIcon :icon="faUndo" class="text-gray-500" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <span v-if="isLoading" class="text-[var(--app-accent)]"><LoadingIcon /></span>
        </div>
        <p v-if="filters.previous_from && filters.previous_to" class="mt-1.5 text-xs text-gray-500">
            {{ ctrans("Compared with :from to :to", { from: shortDate(filters.previous_from), to: shortDate(filters.previous_to) }) }}
        </p>
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

    <div v-if="currentTab === 'overview'" class="space-y-4 p-4 transition-opacity" :class="isLoading ? 'opacity-60' : ''" :aria-busy="isLoading">
        <template v-if="!overview">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div v-for="index in 5" :key="index" class="skeleton h-28 rounded-lg" />
            </div>
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="skeleton h-72 rounded-lg" />
                <div class="skeleton h-72 rounded-lg" />
            </div>
            <div class="skeleton h-48 rounded-lg" />
        </template>

        <template v-else>
            <section>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    <div v-for="card in kpiCards" :key="card.key" class="min-w-0 rounded-lg border border-gray-200 bg-white px-4 py-3 max-lg:last:col-span-2">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500">
                            <FontAwesomeIcon :icon="card.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                            <span class="truncate">{{ card.label }}</span>
                        </div>
                        <component :is="card.href ? Link : 'div'" :href="card.href ?? undefined" class="mt-1 block text-2xl font-semibold" :class="card.href ? 'hover:underline decoration-gray-300 underline-offset-4' : ''">
                            <template v-if="card.kind === 'rate'">
                                <div class="tabular-nums text-gray-900">{{ percent(card.metric.value) }}</div>
                                <div v-if="change(card.metric, 'up', true)" class="text-xs font-normal tabular-nums" :class="change(card.metric, 'up', true)?.cls">
                                    {{ change(card.metric, "up", true)?.text }}
                                </div>
                            </template>
                            <GoogleAdsMetric
                                v-else
                                :value="card.metric.value ?? 0"
                                :kind="card.kind"
                                :currency="card.currency"
                                :previous="comparable(card.metric)"
                                :better="card.better"
                                strong
                                class="[&>div:first-child]:text-2xl [&>div:first-child]:text-gray-900" />
                        </component>
                        <p v-for="line in card.lines" :key="line" class="mt-0.5 truncate text-xs text-gray-500" :title="line">{{ line }}</p>
                    </div>
                </div>
                <div v-if="budgetRows.length && filters.website_locked" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("AI budget this month") }}</h3>
                    <table class="w-full text-sm">
                        <thead class="whitespace-nowrap text-xs text-gray-400">
                            <tr>
                                <th v-if="!(budgetRows.length === 1 && filters.website_locked)" class="pb-1 pr-3 text-left font-medium">{{ ctrans("Website") }}</th>
                                <th class="pb-1 font-medium" />
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Spent") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Budget") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="budget in budgetRows" :key="budget.website">
                                <td v-if="!(budgetRows.length === 1 && filters.website_locked)" class="whitespace-nowrap py-1 pr-3 text-gray-700">{{ budget.website }}</td>
                                <td class="w-full py-1">
                                    <div class="h-2 rounded-sm bg-gray-100">
                                        <div class="h-2 rounded-sm" :class="budget.paused ? 'bg-red-500' : budget.warning ? 'bg-amber-500' : 'bg-[var(--app-accent)]'" :style="{ width: budget.width + '%' }" />
                                    </div>
                                </td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ money(budget.spent, "USD") }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-500">{{ money(budget.monthly, "USD") }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                    <span v-if="budget.paused" class="text-red-600">{{ ctrans("Paused until :date", { date: shortDate(budget.resumes_on) }) }}</span>
                                    <span v-else-if="budget.warning" class="text-amber-700">{{ ctrans("80% used") }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="salesLeads.length" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Passed on to the team") }}</h3>
                    <table class="w-full text-sm">
                        <thead class="whitespace-nowrap text-xs text-gray-400">
                            <tr>
                                <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Task") }}</th>
                                <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Type") }}</th>
                                <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Customer") }}</th>
                                <th v-if="!filters.website_locked" class="pb-1 pr-3 text-left font-medium">{{ ctrans("Website") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("About") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("Customer contact") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("Status") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("Taken by") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Received") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="lead in salesLeads" :key="lead.reference">
                                <td class="whitespace-nowrap py-1 pr-3">
                                    <a :href="lead.url" class="text-gray-700 underline decoration-gray-300 underline-offset-4 hover:text-gray-900">{{ lead.reference }}</a>
                                </td>
                                <td class="whitespace-nowrap py-1 pr-3 text-xs text-gray-600">{{ lead.kind === "restock" ? ctrans("Restock question") : ctrans("Big order") }}</td>
                                <td class="max-w-[16rem] truncate py-1 pr-3 text-gray-700" :title="lead.customer ?? ''">{{ lead.customer }}</td>
                                <td v-if="!filters.website_locked" class="whitespace-nowrap py-1 pr-3 text-gray-500">{{ lead.website }}</td>
                                <td class="max-w-[16rem] truncate py-1 pl-4 text-gray-900" :class="lead.kind === 'big_order' ? 'tabular-nums' : ''" :title="lead.product ?? ''">
                                    {{ lead.kind === "big_order" ? (lead.amount === null ? "" : money(lead.amount, lead.currency ?? currency)) : lead.product }}
                                </td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs text-gray-600">{{ leadContact(lead) }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                    <span :class="leadStatusClass(lead)">{{ lead.status_label }}</span>
                                    <span v-if="lead.status === 'todo' && !lead.assignee && lead.reminders > 0" class="ml-1 text-amber-700">· {{ ctrans("reminded :count times", { count: lead.reminders }) }}</span>
                                </td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs text-gray-600">{{ lead.assignee ?? ctrans("Nobody yet") }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-500">{{ shortDate(lead.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="general && hasGeneral" class="mt-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("General questions and passing to a person") }}</h3>
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                        <div v-for="card in generalCards" :key="card.key" class="min-w-0 rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                <FontAwesomeIcon :icon="card.icon" class="text-gray-400" fixed-width aria-hidden="true" />
                                <span class="truncate" :title="card.label">{{ card.label }}</span>
                            </div>
                            <component :is="card.href ? Link : 'div'" :href="card.href ?? undefined" class="mt-1 block text-2xl font-semibold" :class="card.href ? 'hover:underline decoration-gray-300 underline-offset-4' : ''">
                                <template v-if="card.kind === 'rate'">
                                    <div class="tabular-nums text-gray-900">{{ percent(card.metric.value) }}</div>
                                    <div v-if="change(card.metric, 'none', true)" class="text-xs font-normal tabular-nums" :class="change(card.metric, 'none', true)?.cls">
                                        {{ change(card.metric, "none", true)?.text }}
                                    </div>
                                </template>
                                <GoogleAdsMetric
                                    v-else
                                    :value="card.metric.value ?? 0"
                                    :kind="card.kind"
                                    :currency="card.currency"
                                    :previous="comparable(card.metric)"
                                    :better="card.better"
                                    strong
                                    class="[&>div:first-child]:text-2xl [&>div:first-child]:text-gray-900" />
                            </component>
                            <p v-for="line in card.lines" :key="line" class="mt-0.5 truncate text-xs text-gray-500" :title="line">{{ line }}</p>
                        </div>
                    </div>

                    <div class="mt-3 grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Answered from") }}</h4>
                            <table v-if="general.by_source.length" class="w-full text-sm">
                                <thead class="whitespace-nowrap text-xs text-gray-400">
                                    <tr>
                                        <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Source") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Answers") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in general.by_source" :key="row.key">
                                        <td class="py-1 pr-3 text-gray-700">{{ row.label }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link :href="conversationsUrl(undefined, row.general)" class="primaryLink">{{ locale.number(row.answers) }}</Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-else class="py-2 text-xs text-gray-400">{{ ctrans("The assistant answered none itself in this period") }}</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Why a person was offered") }}</h4>
                            <table class="w-full text-sm">
                                <thead class="whitespace-nowrap text-xs text-gray-400">
                                    <tr>
                                        <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Reason") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Offered") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Passed to a person") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in general.by_reason" :key="row.reason">
                                        <td class="py-1 pr-3 text-gray-700">{{ row.label }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="row.offered" :href="conversationsUrl(undefined, 'offer:' + row.reason)" class="primaryLink">{{ locale.number(row.offered) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="row.handed_over" :href="conversationsUrl(undefined, 'reason:' + row.reason)" class="primaryLink">{{ locale.number(row.handed_over) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("How it reached the team") }}</h4>
                            <table class="w-full text-sm">
                                <thead class="whitespace-nowrap text-xs text-gray-400">
                                    <tr>
                                        <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Route") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Hand-overs") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="row in general.by_route" :key="row.route">
                                        <td class="py-1 pr-3 text-gray-700">{{ row.label }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="row.hand_overs" :href="conversationsUrl(undefined, 'route:' + row.route)" class="primaryLink">{{ locale.number(row.hand_overs) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("First reply from a person") }}</h4>
                            <table class="w-full text-sm">
                                <thead class="whitespace-nowrap text-xs text-gray-400">
                                    <tr>
                                        <th class="pb-1 pr-3 text-left font-medium" />
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Hand-overs") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Replied") }}</th>
                                        <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Median wait") }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="hours in (['in_hours', 'after_hours'] as const)" :key="hours">
                                        <td class="whitespace-nowrap py-1 pr-3 text-gray-700">{{ hours === "in_hours" ? ctrans("In chat hours") : ctrans("Outside chat hours") }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="general.first_reply[hours].hand_overs" :href="conversationsUrl(undefined, hours)" class="primaryLink">{{ locale.number(general.first_reply[hours].hand_overs) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                        </td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-700">{{ locale.number(general.first_reply[hours].replied) }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ waitLabel(general.first_reply[hours].median_minutes) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="mt-2 text-xs text-gray-500">{{ ctrans("Outside chat hours the wait includes the night.") }}</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                            <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("After passing to a person") }}</h4>
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-gray-100">
                                    <tr>
                                        <td class="py-1 pr-3 text-gray-700">{{ ctrans("A person replied") }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="general.outcomes.replied" :href="conversationsUrl(undefined, 'replied')" class="primaryLink">{{ locale.number(general.outcomes.replied) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                            <span class="text-xs text-gray-400"> {{ ctrans("of :count", { count: locale.number(general.outcomes.hand_overs) }) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 pr-3 text-gray-700">{{ ctrans("Chat closed") }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="general.outcomes.closed" :href="conversationsUrl(undefined, 'closed')" class="primaryLink">{{ locale.number(general.outcomes.closed) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                            <span class="text-xs text-gray-400"> {{ ctrans("of :count", { count: locale.number(general.outcomes.hand_overs) }) }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 pr-3 text-gray-700">{{ ctrans("Ordered within :days days", { days: attribution_days }) }}</td>
                                        <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums">
                                            <Link v-if="general.outcomes.ordered" :href="conversationsUrl(undefined, 'ordered')" class="primaryLink">{{ locale.number(general.outcomes.ordered) }}</Link>
                                            <span v-else class="text-gray-400">0</span>
                                            <span class="text-xs text-gray-400"> {{ ctrans("of :count old enough to judge", { count: locale.number(general.outcomes.ordered_base) }) }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <h4 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Questions the assistant could not answer") }}</h4>
                        <table v-if="general.gaps.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Question") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Times asked") }}</th>
                                    <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("Languages") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Last asked") }}</th>
                                    <th class="pb-1 pl-4 text-left font-medium" />
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="gap in general.gaps" :key="gap.question">
                                    <td class="break-words py-1 pr-3 text-gray-700">{{ gap.question }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ locale.number(gap.count) }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-xs uppercase text-gray-500">{{ gap.languages.join(", ") }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right text-xs tabular-nums text-gray-500">{{ shortDate(gap.last_asked_at) }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                        <Link :href="transcriptUrl(gap.conversation)" class="primaryLink">{{ ctrans("Open") }}</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-2 text-xs text-gray-400">{{ ctrans("No unanswered questions in this period") }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ ctrans("Add what answers these as notes in the shop's chat settings, and the assistant will answer them next time.") }}</p>
                    </div>
                </div>
                <p v-else-if="general" class="mt-3 text-xs text-gray-500">{{ ctrans("No general questions in this period") }}</p>
                <div v-if="assistantReach.length" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("From the search box to a question") }}</h3>
                    <table class="w-full text-sm">
                        <thead class="whitespace-nowrap text-xs text-gray-400">
                            <tr>
                                <th class="pb-1 text-left font-medium" />
                                <th class="pb-1 text-left font-medium" />
                                <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Count") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Change") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Of previous") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="step in assistantReach" :key="step.key">
                                <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">{{ step.label }}</td>
                                <td class="w-full py-1.5">
                                    <div class="h-3 rounded-sm bg-gray-100">
                                        <div class="h-3 rounded-sm bg-[var(--app-accent)]" :style="{ width: step.width + '%' }" />
                                    </div>
                                </td>
                                <td class="py-1.5 pl-3 text-right font-medium tabular-nums text-gray-900">{{ locale.number(step.value) }}</td>
                                <td class="whitespace-nowrap py-1.5 pl-4 text-right text-xs tabular-nums" :class="step.change?.cls">{{ step.change?.text ?? "" }}</td>
                                <td class="py-1.5 pl-4 text-right tabular-nums text-gray-500">{{ step.ofPrevious === null ? "" : percent(step.ofPrevious) }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="refusals.rateLimit > 0 || refusals.budget > 0 || refusals.customerCap > 0" class="mt-2 text-xs text-gray-500">
                        {{ ctrans("Questions refused: :rate_limit by the rate limit, :budget by the budget, :customer_cap by the new customer allowance", { rate_limit: locale.number(refusals.rateLimit), budget: locale.number(refusals.budget), customer_cap: locale.number(refusals.customerCap) }) }}
                    </p>
                </div>

                <div v-if="gurus" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">
                        <FontAwesomeIcon icon="fal fa-head-side-brain" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />
                        {{ ctrans("Questions for the gurus") }}
                    </h3>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <span><span class="text-gray-500">{{ ctrans("Asked") }}</span> <span class="ml-1 font-semibold tabular-nums">{{ locale.number(gurus.asked) }}</span></span>
                        <span><span class="text-gray-500">{{ ctrans("Answered") }}</span> <span class="ml-1 font-semibold tabular-nums">{{ locale.number(gurus.answered) }}</span>
                            <span class="ml-1 text-xs text-gray-400">{{ ctrans(":count while the customer waited", { count: locale.number(gurus.answered_live) }) }}</span></span>
                        <span><span class="text-gray-500">{{ ctrans("Typical answer time") }}</span> <span class="ml-1 font-semibold tabular-nums">{{ waitLabel(gurus.median_minutes) }}</span></span>
                        <span><span class="text-gray-500">{{ ctrans("Not answered") }}</span> <span class="ml-1 font-semibold tabular-nums" :class="gurus.closed ? 'text-red-700' : ''">{{ locale.number(gurus.closed) }}</span></span>
                        <span><span class="text-gray-500">{{ ctrans("Waiting") }}</span> <span class="ml-1 font-semibold tabular-nums" :class="gurus.open ? 'text-amber-700' : ''">{{ locale.number(gurus.open) }}</span></span>
                        <span><span class="text-gray-500">{{ ctrans("Kept for Merlin") }}</span> <span class="ml-1 font-semibold tabular-nums">{{ locale.number(gurus.kept) }}</span></span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ ctrans("First sent to:") }}
                        <span v-for="(count, route) in gurus.routed" :key="route" class="mr-3 whitespace-nowrap">{{ guruRouteLabels[route] ?? route }} {{ locale.number(count) }}</span>
                    </p>

                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        <table v-if="gurus.people.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="py-1 text-left font-normal">{{ ctrans("Person") }}</th>
                                    <th class="py-1 pl-4 text-right font-normal">{{ ctrans("Answered") }}</th>
                                    <th class="py-1 pl-4 text-right font-normal">{{ ctrans("Typical time") }}</th>
                                    <th class="py-1 pl-4 text-right font-normal">{{ ctrans("Did not want to") }}</th>
                                    <th class="py-1 pl-4 text-right font-normal">{{ ctrans("Passed on") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="person in gurus.people" :key="person.name">
                                    <td class="py-1 text-gray-900">{{ person.name }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ locale.number(person.answered) }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-700">{{ waitLabel(person.median_minutes) }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums" :class="person.declined ? 'text-red-700' : 'text-gray-400'">{{ locale.number(person.declined) }}</td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-700">{{ locale.number(person.passed) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="py-1 text-left font-normal">{{ ctrans("Latest questions") }}</th>
                                    <th class="py-1 pl-4 text-left font-normal">{{ ctrans("Status") }}</th>
                                    <th class="py-1 pl-4 text-right font-normal">{{ ctrans("Time") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="question in gurus.latest" :key="question.reference">
                                    <td class="py-1">
                                        <a :href="question.url" class="primaryLink">{{ question.question }}</a>
                                        <span class="ml-1 whitespace-nowrap text-xs text-gray-400">{{ question.shop }}</span>
                                    </td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                        <span :class="guruStatusClass(question.status)">{{ guruStatusLabel(question.status) }}</span>
                                        <span v-if="question.by" class="text-gray-500"> · {{ question.by }}</span>
                                    </td>
                                    <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-xs text-gray-500">{{ waitLabel(question.minutes) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="feedback" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ ctrans("Answer ratings by customers") }}</h3>
                    <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                        <span>
                            <span class="text-gray-500">{{ ctrans("Helpful") }}</span>
                            <Link v-if="feedback.current.up" :href="conversationsUrl(undefined, 'thumbs_up')" class="primaryLink ml-2 tabular-nums">{{ locale.number(feedback.current.up) }}</Link>
                            <span v-else class="ml-2 tabular-nums text-gray-400">0</span>
                            <span class="ml-1 text-xs text-gray-400">{{ ctrans("previous period :count", { count: locale.number(feedback.previous.up) }) }}</span>
                        </span>
                        <span>
                            <span class="text-gray-500">{{ ctrans("Not helpful") }}</span>
                            <Link v-if="feedback.current.down" :href="conversationsUrl(undefined, 'thumbs_down')" class="primaryLink ml-2 tabular-nums">{{ locale.number(feedback.current.down) }}</Link>
                            <span v-else class="ml-2 tabular-nums text-gray-400">0</span>
                            <span class="ml-1 text-xs text-gray-400">{{ ctrans("previous period :count", { count: locale.number(feedback.previous.down) }) }}</span>
                        </span>
                        <span class="text-xs text-gray-500">{{ ctrans(":count answers given", { count: locale.number(feedback.current.answers) }) }}</span>
                    </div>
                </div>

                <div v-if="challenges" class="mt-3 rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Customer challenges to act on") }}</h3>
                    <p class="mb-2 text-xs text-gray-500">
                        {{ ctrans("What stood between customers and an order, read by AI from each conversation once it went quiet. :tagged conversations read, :untagged waiting for tonight's run.", { tagged: locale.number(challenges.tagged), untagged: locale.number(challenges.untagged) }) }}
                    </p>
                    <div v-if="challenges.by_type.length" class="mb-3 flex flex-wrap gap-2">
                        <Link
                            v-for="row in challenges.by_type"
                            :key="row.type"
                            :href="conversationsUrl(undefined, `challenge:${row.type}`)"
                            class="rounded-full border border-gray-200 px-2.5 py-0.5 text-xs text-gray-700 hover:bg-gray-50"
                        >
                            {{ row.label }} <span class="tabular-nums text-gray-500">{{ locale.number(row.conversations) }}</span>
                        </Link>
                    </div>
                    <table v-if="challenges.top.length" class="w-full text-sm">
                        <thead class="whitespace-nowrap text-xs text-gray-400">
                            <tr>
                                <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Type") }}</th>
                                <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("What") }}</th>
                                <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Conversations") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium">{{ ctrans("Example") }}</th>
                                <th class="pb-1 pl-4 text-left font-medium" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in challenges.top" :key="`${row.type}-${row.topic}`">
                                <td class="whitespace-nowrap py-1 pr-3 text-xs text-gray-500">{{ row.label }}</td>
                                <td class="py-1 pr-3 text-gray-900">{{ row.topic }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-right tabular-nums text-gray-900">{{ locale.number(row.conversations) }}</td>
                                <td class="break-words py-1 pl-4 text-xs italic text-gray-600">{{ row.quote ? `“${row.quote}”` : "" }}</td>
                                <td class="whitespace-nowrap py-1 pl-4 text-xs">
                                    <Link :href="transcriptUrl(row.conversation)" class="primaryLink">{{ ctrans("Open") }}</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="py-2 text-xs text-gray-400">{{ ctrans("No challenges found in this period") }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ ctrans("Searches with no results are in the search section below.") }}</p>
                </div>
                <p v-if="showMaturityNote" class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
                    {{ ctrans("Conversations after :date can still turn into orders: orders, revenue and conversion for them are not final.", { date: shortDate(overview.mature_until) }) }}
                </p>
            </section>

            <section v-if="!hasConversations" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center">
                <FontAwesomeIcon :icon="faRobot" class="text-2xl text-gray-300" fixed-width aria-hidden="true" />
                <h3 class="mt-2 text-sm font-semibold text-gray-700">{{ ctrans("No conversations in this period") }}</h3>
                <p class="mt-1 text-xs text-gray-500">{{ ctrans("The AI assistant is switched on per website, in the website settings.") }}</p>
            </section>

            <template v-else>
                <div class="grid gap-4 xl:grid-cols-2">
                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Funnel") }}</h3>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium" />
                                    <th class="pb-1 text-left font-medium" />
                                    <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Count") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Of previous") }}</th>
                                    <th class="pb-1 pl-4 text-right font-medium">{{ ctrans("Of all") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="step in funnel" :key="step.key">
                                    <td class="whitespace-nowrap py-1.5 pr-3">
                                        <Link v-if="step.href" :href="step.href" class="primaryLink">{{ step.label }}</Link>
                                        <span v-else class="text-gray-700">{{ step.label }}</span>
                                    </td>
                                    <td class="w-full py-1.5">
                                        <div class="h-3 rounded-sm bg-gray-100">
                                            <div class="h-3 rounded-sm bg-[var(--app-accent)]" :style="{ width: step.width + '%' }" />
                                        </div>
                                    </td>
                                    <td class="py-1.5 pl-3 text-right font-medium tabular-nums text-gray-900">{{ locale.number(step.value) }}</td>
                                    <td class="py-1.5 pl-4 text-right tabular-nums text-gray-500">{{ step.ofPrevious === null ? "" : percent(step.ofPrevious) }}</td>
                                    <td class="py-1.5 pl-4 text-right tabular-nums text-gray-500">{{ step.ofFirst === null ? "" : percent(step.ofFirst) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="mt-3 text-xs text-gray-500">
                            {{ ctrans("Ordered counts any order of a product the assistant showed, within :days days, also when it reached the basket another way.", { days: attribution_days }) }}
                        </p>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ isWeekly ? ctrans("Conversations by week") : ctrans("Conversations by day") }}</h3>
                        <div class="h-60">
                            <Chart type="line" class="h-full" :data="chartData" :options="chartOptions" />
                        </div>
                        <p v-if="hasIncompletePoints" class="mt-1 flex items-center gap-1.5 text-xs text-gray-500">
                            <span class="inline-block w-5 border-t-2 border-dashed border-gray-400" aria-hidden="true" />
                            {{ ctrans("not final yet") }}
                        </p>
                    </section>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Where customers leave") }}</h3>
                        <p class="mb-3 text-xs text-gray-500">{{ ctrans("Conversations by the number of questions asked") }}</p>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Questions") }}</th>
                                    <th class="pb-1" />
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Count") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Share") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in dropOff" :key="row.label">
                                    <td class="whitespace-nowrap py-1.5 pr-3 text-gray-700">{{ row.label }}</td>
                                    <td class="w-full py-1.5">
                                        <div class="h-3 rounded-sm bg-gray-100">
                                            <div class="h-3 rounded-sm bg-[var(--app-accent)]" :style="{ width: row.share * 100 + '%' }" />
                                        </div>
                                    </td>
                                    <td class="py-1.5 text-right font-medium tabular-nums text-gray-900">{{ locale.number(row.count) }}</td>
                                    <td class="py-1.5 text-right tabular-nums text-gray-500">{{ percent(row.share) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-4">
                        <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Engagement and value") }}</h3>
                        <table class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in engagementRows" :key="row.label">
                                    <td class="py-1.5 pr-3 text-gray-600">{{ row.label }}</td>
                                    <td class="whitespace-nowrap py-1.5 text-right font-medium tabular-nums text-gray-900" :class="row.valueClass">{{ row.value }}</td>
                                    <td class="w-20 whitespace-nowrap py-1.5 text-right text-xs tabular-nums" :class="row.change?.cls">{{ row.change?.text ?? "" }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>

                <section v-if="families.length" class="rounded-lg border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700">{{ ctrans("Families suggested") }}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 pr-3 text-left font-medium">{{ ctrans("Family") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Shown") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Clicked") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Added") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Ordered") }}</th>
                                    <th class="w-28 pb-1 text-right font-medium">{{ ctrans("Revenue") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="family in families" :key="family.id">
                                    <td class="py-1.5 pr-3">
                                        <span class="whitespace-nowrap font-medium text-gray-900">{{ family.code }}</span>
                                        <span class="ml-2 text-gray-500">{{ family.name }}</span>
                                    </td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.shown) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.clicked) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.added) }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ locale.number(family.ordered) }}</td>
                                    <td class="whitespace-nowrap py-1.5 text-right tabular-nums">{{ money(family.revenue) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <section v-if="search" class="space-y-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">{{ ctrans("Search on the website") }}</h3>
                    <p v-if="ignoredFilters" class="text-xs text-gray-500">{{ ctrans("Search figures ignore: :filters", { filters: ignoredFilters }) }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 2xl:grid-cols-6">
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Searches") }}</div>
                        <GoogleAdsMetric :value="search.searches?.value ?? 0" kind="count" :previous="comparable(search.searches)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Searches with no results") }}</div>
                        <GoogleAdsMetric :value="search.zero_results?.value ?? 0" kind="count" :previous="comparable(search.zero_results)" better="down" size="lg" />
                        <div class="text-xs tabular-nums text-gray-500">
                            {{ ctrans(":rate of searches", { rate: percent(search.zero_result_rate?.value ?? 0) }) }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Clicks on Keep exploring") }}</div>
                        <GoogleAdsMetric :value="search.suggestion_clicks?.value ?? 0" kind="count" :previous="comparable(search.suggestion_clicks)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("No-result searches rescued") }}</div>
                        <GoogleAdsMetric :value="search.zero_rescued?.value ?? 0" kind="count" :previous="comparable(search.zero_rescued)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Suggestions offered") }}</div>
                        <GoogleAdsMetric :value="search.offered?.value ?? 0" kind="count" :previous="comparable(search.offered)" size="lg" />
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <div class="text-xs text-gray-500">{{ ctrans("Suggestions seen") }}</div>
                        <GoogleAdsMetric :value="search.seen?.value ?? 0" kind="count" :previous="comparable(search.seen)" size="lg" />
                        <div v-if="seenShare !== null" class="text-xs tabular-nums text-gray-500">
                            {{ ctrans(":rate of offered", { rate: percent(seenShare) }) }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Clicks by kind") }}</h4>
                        <table class="w-full text-sm">
                            <thead class="whitespace-nowrap text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium" />
                                    <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Offered") }}</th>
                                    <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Clicks") }}</th>
                                    <th class="pb-1 font-medium" />
                                    <th class="pb-1 pl-3 text-right font-medium">{{ ctrans("Rate") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in clicksByKind" :key="row.label">
                                    <td class="whitespace-nowrap py-1 pr-3 text-gray-700">{{ row.label }}</td>
                                    <td class="py-1 pl-3 text-right tabular-nums text-gray-500">{{ row.shown === null ? "" : locale.number(row.shown) }}</td>
                                    <td class="py-1 pl-3 text-right tabular-nums text-gray-900">{{ locale.number(row.clicks) }}</td>
                                    <td class="w-full py-1 pl-3">
                                        <div class="h-2.5 min-w-8 rounded-sm bg-gray-100">
                                            <div class="h-2.5 rounded-sm bg-[var(--app-accent)]" :style="{ width: row.width + '%' }" />
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap py-1 pl-3 text-right tabular-nums text-gray-500">{{ row.rate === null ? "" : percent(row.rate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Top searches") }}</h4>
                        <table v-if="search.top_queries?.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Search") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Searches") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Clicks") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in search.top_queries" :key="row.query">
                                    <td class="max-w-0 truncate py-1 pr-3 text-gray-700" :title="row.query">{{ row.query }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.searches) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.clicks) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-4 text-center text-xs text-gray-400">{{ ctrans("No searches in this period") }}</p>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4">
                        <h4 class="mb-3 text-xs font-medium text-gray-500">{{ ctrans("Searches with no results") }}</h4>
                        <table v-if="search.top_zero_queries?.length" class="w-full text-sm">
                            <thead class="whitespace-nowrap border-b border-gray-200 text-xs text-gray-400">
                                <tr>
                                    <th class="pb-1 text-left font-medium">{{ ctrans("Search") }}</th>
                                    <th class="w-20 pb-1 text-right font-medium">{{ ctrans("Searches") }}</th>
                                    <th class="w-16 pb-1 text-right font-medium">{{ ctrans("Rescued") }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in search.top_zero_queries" :key="row.query">
                                    <td class="max-w-0 truncate py-1 pr-3 text-gray-700" :title="row.query">{{ row.query }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.searches) }}</td>
                                    <td class="py-1 text-right tabular-nums">{{ locale.number(row.rescued) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="py-4 text-center text-xs text-gray-400">{{ ctrans("No searches without results in this period") }}</p>
                    </div>
                </div>
            </section>
        </template>
    </div>

    <div v-else-if="currentTab === 'conversations'">
        <div v-if="filters.outcome || filters.general" class="flex flex-wrap gap-2 px-4 pt-3">
            <span v-if="filters.outcome" class="inline-flex items-center gap-1.5 rounded-full border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] py-0.5 pl-3 pr-1 text-xs text-gray-700">
                {{ ctrans("Only conversations: :outcome", { outcome: outcomeLabels[filters.outcome] ?? filters.outcome }) }}
                <Button type="tertiary" size="xxs" :tooltip="ctrans('Clear')" @click="setFilter('outcome', null)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </span>
            <span v-if="filters.general" class="inline-flex items-center gap-1.5 rounded-full border border-[var(--app-accent-muted)] bg-[var(--app-accent-soft)] py-0.5 pl-3 pr-1 text-xs text-gray-700">
                {{ ctrans("Only conversations: :outcome", { outcome: generalFilterLabel(filters.general) }) }}
                <Button type="tertiary" size="xxs" :tooltip="ctrans('Clear')" @click="setFilter('general', null)">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </span>
        </div>
        <div v-if="!conversationsTable" class="space-y-2 p-4">
            <div v-for="index in 6" :key="index" class="skeleton h-10 rounded" />
        </div>
        <TableShoppingAssistantConversations
            v-else
            :data="conversationsTable"
            tab="conversations"
            :routes="routes"
            :currency="currency"
            :costCurrency="costCurrency" />
    </div>

    <div v-else-if="currentTab === 'shops'">
        <div v-if="!shops" class="space-y-2 p-4">
            <div v-for="index in 6" :key="index" class="skeleton h-10 rounded" />
        </div>
        <TableShoppingAssistantWebsites
            v-else
            :data="shops"
            tab="shops"
            :currency="currency"
            :conversationsUrl="(websiteId: number) => pageUrl({ ...filterQuery({ website: websiteId }), tab: 'conversations' })" />
    </div>
</template>
