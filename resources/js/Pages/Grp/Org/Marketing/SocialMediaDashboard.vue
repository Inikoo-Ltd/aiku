<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 19 Oct 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { Deferred, Head, router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import DashboardSettings from '@/Components/DataDisplay/Dashboard/DashboardSettings.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SegmentedToggle from '@/Components/Utils/SegmentedToggle.vue'
import UploadExcel from '@/Components/Upload/UploadExcel.vue'
import UploadReports from '@/Components/Upload/UploadReports.vue'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import { capitalize } from '@/Composables/capitalize'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
    faAd, faArrowDown, faArrowUp, faBookmark, faChartLine, faClone, faExternalLink, faFileExport, faFilm,
    faHashtag, faHeart, faHistory, faImage, faInfoCircle, faSpinnerThird, faTimes, faUpload, faVideo,
    faSyncAlt, faCheck, faExclamationTriangle, faCog, faBullhorn,
} from '@fal'
import { PageHeadingTypes } from '@/types/PageHeading'
import { routeType } from '@/types/route'
import { Intervals, Settings } from '@/types/Components/Dashboard'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend } from 'chart.js'
import { Line } from 'vue-chartjs'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend)
library.add(faAd, faArrowDown, faArrowUp, faBookmark, faChartLine, faClone, faExternalLink, faFileExport, faFilm,
    faHashtag, faHeart, faHistory, faImage, faInfoCircle, faSpinnerThird, faTimes, faUpload, faVideo,
    faSyncAlt, faCheck, faExclamationTriangle, faCog, faBullhorn)

interface TrendPoint { date: string, posts: number, reach: number, engagements: number, views: number, organic_views: number, ad_views: number, interactions: number, followers: number | null }

interface Kpis {
    posts: number
    reach: number
    impressions: number
    engagements: number
    engagement_rate: number | null
    views: number
    organic_views: number
    ad_views: number
    unsplit_views: number
    interactions: number
    interaction_rate: number | null
    avg_daily_viewers: number | null
    visits: number | null
    new_follows: number | null
    link_clicks: number
    followers: number | null
    followers_accounts: number
    follower_growth: number | null
    spend: number
    revenue: number
    orders: number
    roas: number | null
    cpa: number | null
    ad_impressions: number
    ad_clicks: number
    ctr: number | null
    meta_purchases: number
    meta_value: number
    meta_roas: number | null
    meta_registrations: number
    meta_cost_per_registration: number | null
}

interface Post {
    id: number
    description: string | null
    permalink: string | null
    published_at: string
    type: string
    type_label: string
    platform: string
    platform_label: string
    account_name: string
    shop_name: string
    reach: number
    impressions: number
    reactions: number
    comments: number
    shares: number
    saves: number
    link_clicks: number
    engagements: number
    engagement_rate: number
    is_boosted: boolean
    ad_views: number | null
    ad_reach: number | null
    ad_link_clicks: number | null
}

interface Performance {
    from: string | null
    to: string | null
    previous_from: string | null
    previous_to: string | null
    period: string
    period_label: string
    currency_code: string
    filters: { shop: string | null, country: string | null, platform: string | null, compare: string, compare_from: string | null, compare_to: string | null }
    options: {
        shops: { id: number, slug: string, name: string, country_code: string, has_accounts: boolean }[]
        countries: { code: string, name: string }[]
        platforms: { value: string, label: string }[]
    }
    data_until: string | null
    kpis: { current: Kpis, previous: Kpis | null }
    trend: { bucket: 'day' | 'week' | 'month', points: TrendPoint[] }
    previous_trend: { bucket: 'day' | 'week' | 'month', points: TrendPoint[] } | null
    platforms: { platform: string, label: string, posts: number, reach: number, share: number | null, views: number, views_share: number | null, organic_views: number, ad_views: number, engagements: number, engagement_rate: number | null, followers: number | null }[]
    insight: string | null
    formats: { type: string, label: string, posts: number, reach: number, average_reach: number, engagements: number, saves: number, shares: number, engagement_rate: number | null }[]
    top_posts: Post[]
    accounts: { id: number, name: string, username: string | null, platform: string, platform_label: string, shop_name: string, shop_slug: string, country_code: string, followers: number | null, followers_at: string | null, posts: number, reach: number, engagements: number, link_clicks: number, engagement_rate: number | null, views: number, organic_views: number, ad_views: number, interactions: number, interaction_rate: number | null }[]
    paid: {
        channels: { type: string, label: string, spend: number, revenue: number, orders: number, roas: number | null, impressions: number, clicks: number, ctr: number | null, meta_purchases: number, meta_roas: number | null, meta_registrations: number, meta_cost_per_registration: number | null }[]
        campaigns: { name: string, channel: string, placements: { channel: string, spend: number }[], owner: string | null, spend: number, revenue: number, orders: number, impressions: number, clicks: number, ctr: number | null, cpa: number | null, roas: number | null, meta_purchases: number, meta_value: number, meta_roas: number | null, meta_registrations: number, meta_cost_per_registration: number | null }[]
    }
}

interface MetaSync {
    id: number
    slug: string
    name: string
    route: routeType
    edit_route: routeType
    organic: boolean
    paid: boolean
    running: boolean
    last_sync: { finished_at: string, ok: boolean, message: string | null } | null
    available_at: string | null
    first_sync: boolean
    can_edit: boolean
    organic_updated_at: string | null
    paid_updated_at: string | null
}

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    intervals: Intervals
    settings: Settings
    upload_shops: { id: number, name: string, route: routeType }[]
    meta_sync?: MetaSync[]
    export_route: routeType
    performance?: Performance
    uploads?: { data: any[], meta?: any }
}>()

const locale = useLocaleStore()
const layout = inject('layout', layoutStructure)

const currency = computed(() => props.performance?.currency_code ?? layout.group?.currency?.code ?? 'GBP')
const money = (value: number | null | undefined) => value === null || value === undefined ? '–' : locale.currencyFormat(currency.value, value)
const count = (value: number | null | undefined) => value === null || value === undefined ? '–' : locale.number(value)
const short = (value: number | null | undefined) => value === null || value === undefined ? '–' : locale.numberShort(value)
const pct = (value: number | null | undefined, decimals = 1) => value === null || value === undefined ? '–' : value.toFixed(decimals) + '%'
const ratio = (value: number | null | undefined) => value === null || value === undefined ? '–' : value.toFixed(2) + '×'
const day = (value: string | null | undefined) => value ? useFormatTime(value, { formatTime: 'aiku' }) : ''

/* Tabs are client-side: every tab reads the same deferred prop, so switching costs no request. */
const currentTab = ref('overview')
const tabs = {
    overview: { title: ctrans('Overview'), icon: 'fal fa-chart-line' },
    organic: { title: ctrans('Organic'), icon: 'fal fa-heart' },
    paid: { title: ctrans('Paid'), icon: 'fal fa-ad' },
    uploads: { title: ctrans('Upload history'), icon: 'fal fa-upload', icon_badge: 'fal fa-history', type: 'icon', align: 'right' },
}

/* Filters live in the URL so the export link, the period change and a shared link all agree on
   what is on screen. An empty string, not a dropped key, clears a filter: Inertia merges data into
   the current query string, so a dropped key would keep the old value. */
const shopFilter = ref<string | null>(null)
const countryFilter = ref<string | null>(null)
const platformFilter = ref<string | null>(null)
const compareMode = ref('previous')
const compareRange = ref<{ from: string | null, to: string | null }>({ from: null, to: null })
const isReloading = ref(false)

watch(() => props.performance?.filters, (filters) => {
    shopFilter.value = filters?.shop ?? null
    countryFilter.value = filters?.country ?? null
    platformFilter.value = filters?.platform ?? null
    compareMode.value = filters?.compare ?? 'previous'
    compareRange.value = { from: filters?.compare_from ?? null, to: filters?.compare_to ?? null }
}, { immediate: true })

const filterQuery = computed(() => ({
    shop: shopFilter.value ?? '',
    country: countryFilter.value ?? '',
    platform: platformFilter.value ?? '',
    compare: compareMode.value === 'previous' ? '' : compareMode.value,
    compare_from: compareMode.value === 'custom' ? compareRange.value.from ?? '' : '',
    compare_to: compareMode.value === 'custom' ? compareRange.value.to ?? '' : '',
}))

/* The comparison: the same number of days just before, the same dates a year earlier, or a range
   picked by hand, which reloads once both of its dates are set. */
const compareOptions = [
    { label: ctrans('vs previous period'), value: 'previous' },
    { label: ctrans('vs same period last year'), value: 'year' },
    { label: ctrans('vs custom range'), value: 'custom' },
    { label: ctrans('No comparison'), value: 'none' },
]
const onCompareMode = () => {
    if (compareMode.value !== 'custom' || (compareRange.value.from && compareRange.value.to)) applyFilters()
}
const fromIsoDate = (iso: string): Date => {
    const [year, month, dayOfMonth] = iso.split('-').map(Number)
    return new Date(year, month - 1, dayOfMonth)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
const compareDate = (key: 'from' | 'to') => computed<Date | null>({
    get: () => compareRange.value[key] ? fromIsoDate(compareRange.value[key]!) : null,
    set: (value) => {
        if (!value) return
        compareRange.value = { ...compareRange.value, [key]: toLocalIsoDate(value) }
        if (compareRange.value.from && compareRange.value.to) applyFilters()
    },
})
const compareFrom = compareDate('from')
const compareTo = compareDate('to')
const datePickerPt = { pcInputText: { root: { class: '!w-32 !py-1 !text-xs' } } }
const fieldFocusClass = '[&.p-focus]:!border-[--app-accent] [&_input:focus]:!border-[--app-accent]'

const comparedIsEmpty = computed(() => {
    const p = props.performance?.kpis.previous
    return p !== null && p !== undefined && !p.posts && !p.views && !p.interactions && !p.spend && !p.ad_impressions
})

const versusLabel = computed(() => {
    const p = props.performance
    if (!p?.previous_from) return ctrans('vs previous')
    if (p.filters.compare === 'year') return ctrans('vs last year')
    if (p.filters.compare === 'custom') return ctrans('vs') + ' ' + day(p.previous_from) + ' – ' + day(p.previous_to)
    return ctrans('vs previous')
})

const applyFilters = () => {
    router.reload({
        data: filterQuery.value,
        only: ['performance'],
        onStart: () => { isReloading.value = true },
        onFinish: () => { isReloading.value = false },
    })
}

const exportHref = computed(() => route(props.export_route.name, {
    ...(props.export_route.parameters as object),
    ...Object.fromEntries(Object.entries(filterQuery.value).filter(([, value]) => value !== '')),
}))
const reportHref = computed(() => route(props.export_route.name, {
    ...(props.export_route.parameters as object),
    ...Object.fromEntries(Object.entries(filterQuery.value).filter(([, value]) => value !== '')),
    report: 'summary',
}))

const selectPt = { label: { class: '!py-1 !text-xs' }, root: { class: '!border-gray-300 !shadow-none' } }

/* Deltas against the previous period. Rates compare in percentage points, everything else as a
   percentage change; a missing or zero previous figure hides the delta rather than showing ∞. */
interface Delta { text: string, direction: 'up' | 'down' | 'flat', good: boolean }

const delta = (current: number | null | undefined, previous: number | null | undefined, options: { points?: boolean, lowerIsBetter?: boolean } = {}): Delta | null => {
    if (current === null || current === undefined || previous === null || previous === undefined) return null
    let change: number
    let text: string
    if (options.points) {
        change = current - previous
        text = Math.abs(change).toFixed(1) + ' pp'
    } else {
        if (previous === 0) return null
        change = (current - previous) / Math.abs(previous) * 100
        text = Math.abs(change).toFixed(1) + '%'
    }
    const direction = Math.abs(change) < 0.05 ? 'flat' : change > 0 ? 'up' : 'down'
    const good = direction === 'flat' || (options.lowerIsBetter ? direction === 'down' : direction === 'up')

    return { text, direction, good }
}

interface ViewsSplit { organic: number, ads: number, unsplit: number }

interface Kpi { key: string, label: string, value: string, delta: Delta | null, sub?: string, help?: string, split?: ViewsSplit }

const kpi = (key: string, label: string, value: string, change: Delta | null, sub?: string, help?: string): Kpi => ({ key, label, value, delta: change, sub, help })

const current = computed(() => props.performance?.kpis.current)
const previous = computed(() => props.performance?.kpis.previous ?? null)

/* Where views came from. Meta counts content shown in an ad (boosts and campaigns) inside an
   account's views, so the page always says which part was organic and which came from ads, and can
   show either alone. Days synced before Meta was asked for the split are shown as not split, never
   folded into organic. */
type ViewsSource = 'all' | 'organic' | 'ads'
const viewsSource = ref<ViewsSource>('all')
watch(currentTab, (tab) => {
    viewsSource.value = tab === 'organic' ? 'organic' : 'all'
})
const viewsSourceOptions = [
    { label: ctrans('All views'), value: 'all' as ViewsSource },
    { label: ctrans('Organic'), value: 'organic' as ViewsSource },
    { label: ctrans('Ads'), value: 'ads' as ViewsSource },
]
const SOURCE_COLORS = { organic: '#3b7a2a', ads: '#7c8db5', unsplit: '#d1d5db' }
const viewsSourceHelp = ctrans('All views: everything Meta counted on the accounts. Organic: seen without paying (posts, reels, stories, profile). Ads: seen through boosted posts and Meta Ads campaigns. Days synced before the split existed are shown as not split.')
const viewsOf = (row: { views: number, organic_views: number, ad_views: number } | null | undefined): number | null => {
    if (!row) return null
    if (viewsSource.value === 'organic') return row.organic_views
    if (viewsSource.value === 'ads') return row.ad_views
    return row.views
}
const viewsLabel = computed(() => viewsSource.value === 'organic' ? ctrans('Organic views') : viewsSource.value === 'ads' ? ctrans('Views from ads') : ctrans('Views'))
const viewsSplitOf = (row: { views: number, organic_views: number, ad_views: number }): ViewsSplit => ({
    organic: row.organic_views,
    ads: row.ad_views,
    unsplit: Math.max(0, row.views - row.organic_views - row.ad_views),
})
const splitShare = (split: ViewsSplit, part: keyof ViewsSplit) => {
    const total = split.organic + split.ads + split.unsplit
    return total > 0 ? (split[part] / total * 100) : 0
}

/* Search Console style: beside each figure, the same figure in the compared period. Followers are
   only set beside each other when both periods count the same accounts. */
const previousText = (key: string): string | null => {
    const p = previous.value
    const c = current.value
    if (!p || !c) return null
    const value = (number: number | null | undefined, format: (n: number) => string) => number === null || number === undefined ? '–' : format(number)
    const formats: Record<string, () => string> = {
        views: () => value(viewsOf(p), short),
        posts: () => value(p.posts, count),
        viewers: () => value(p.avg_daily_viewers, short),
        interactions: () => value(p.interactions, short),
        interaction_rate: () => pct(p.interaction_rate),
        visits: () => value(p.visits, short),
        new_follows: () => value(p.new_follows, short),
        followers: () => p.followers_accounts === c.followers_accounts ? value(p.followers, short) : '',
        link_clicks: () => value(p.link_clicks, short),
        spend: () => money(p.spend),
        revenue: () => money(p.revenue),
        roas: () => ratio(p.roas),
        meta_roas: () => ratio(p.meta_roas),
        cpa: () => money(p.cpa),
        ctr: () => pct(p.ctr, 2),
    }
    return formats[key]?.() || null
}

const followersSub = computed(() => {
    const growth = current.value?.follower_growth
    if (growth === null || growth === undefined || growth === 0) return undefined
    return (growth > 0 ? '+' : '−') + count(Math.abs(growth)) + ' ' + ctrans('this period')
})

/* Meta replaced reach with viewers in October 2026. Viewers are people counted once a day, so a
   period shows the average day: adding the days up would count the same person every day. */
const viewsHelp = ctrans('Views from the accounts\' daily figures where they were imported, otherwise from their posts. Meta counts content shown in ads too unless the export is organic only.')
const viewersHelp = ctrans('People who saw the accounts\' content on an average day of the period.')

const viewersSub = computed(() => {
    const viewers = current.value?.avg_daily_viewers
    return viewers === null || viewers === undefined ? undefined : short(viewers) + ' ' + ctrans('viewers a day')
})

const audienceKpi = computed<Kpi>(() => {
    const c = current.value!
    const p = previous.value
    if (c.followers !== null) {
        return kpi('followers', ctrans('Followers'), short(c.followers), null, followersSub.value, ctrans('Followers across every account at the latest imported day. Growth is the change over the period.'))
    }
    return kpi('new_follows', ctrans('New follows'), c.new_follows === null ? '–' : short(c.new_follows), delta(c.new_follows, p?.new_follows), undefined, ctrans('Follows over the period, from the accounts\' daily figures.'))
})

const overviewKpis = computed<Kpi[]>(() => {
    const c = current.value
    const p = previous.value
    if (!c) return []
    return [
        { ...kpi('views', viewsLabel.value, short(viewsOf(c) ?? 0), delta(viewsOf(c), viewsOf(p)), viewersSub.value, viewsSourceHelp), split: viewsSource.value === 'all' ? viewsSplitOf(c) : undefined },
        kpi('interaction_rate', ctrans('Interaction rate'), pct(c.interaction_rate), delta(c.interaction_rate, p?.interaction_rate, { points: true }), short(c.interactions) + ' ' + ctrans('interactions'), ctrans('Interactions (reactions, comments, shares and saves) per 100 views.')),
        audienceKpi.value,
        kpi('link_clicks', ctrans('Link clicks'), short(c.link_clicks), delta(c.link_clicks, p?.link_clicks)),
        kpi('spend', ctrans('Paid spend'), money(c.spend), delta(c.spend, p?.spend, { lowerIsBetter: true }), c.orders ? count(c.orders) + ' ' + ctrans('orders') : undefined),
        kpi('meta_roas', ctrans('ROAS'), ratio(c.meta_roas), delta(c.meta_roas, p?.meta_roas), c.meta_purchases ? count(c.meta_purchases) + ' ' + ctrans('purchases') : undefined, ctrans('Purchase value Meta reports for the ads, divided by spend, as Ads Manager shows it.')),
    ]
})

const organicKpis = computed<Kpi[]>(() => {
    const c = current.value
    const p = previous.value
    if (!c) return []
    return [
        kpi('posts', ctrans('Posts'), count(c.posts), delta(c.posts, p?.posts)),
        { ...kpi('views', viewsLabel.value, short(viewsOf(c) ?? 0), delta(viewsOf(c), viewsOf(p)), undefined, viewsSourceHelp), split: viewsSource.value === 'all' ? viewsSplitOf(c) : undefined },
        kpi('viewers', ctrans('Viewers per day'), c.avg_daily_viewers === null ? '–' : short(c.avg_daily_viewers), delta(c.avg_daily_viewers, p?.avg_daily_viewers), undefined, viewersHelp),
        kpi('interactions', ctrans('Interactions'), short(c.interactions), delta(c.interactions, p?.interactions)),
        kpi('visits', ctrans('Profile visits'), c.visits === null ? '–' : short(c.visits), delta(c.visits, p?.visits)),
        audienceKpi.value,
    ]
})

const paidKpis = computed<Kpi[]>(() => {
    const c = current.value
    const p = previous.value
    if (!c) return []
    return [
        kpi('spend', ctrans('Spend'), money(c.spend), delta(c.spend, p?.spend, { lowerIsBetter: true })),
        kpi('revenue', ctrans('Revenue'), money(c.revenue), delta(c.revenue, p?.revenue), c.orders ? count(c.orders) + ' ' + ctrans('orders') : undefined),
        kpi('roas', ctrans('ROAS · Aiku'), ratio(c.roas), delta(c.roas, p?.roas), undefined, ctrans('Revenue of the orders Aiku credits to the ads, divided by spend.')),
        kpi('meta_roas', ctrans('ROAS · Meta'), ratio(c.meta_roas), delta(c.meta_roas, p?.meta_roas), count(c.meta_purchases) + ' ' + ctrans('Meta purchases') + ' · ' + money(c.meta_value), ctrans('Purchase value Meta\'s pixel reports for the ads, divided by spend: the figure Ads Manager shows. Meta counts by its own attribution window, so it is usually higher than the orders Aiku credits.')),
        kpi('cpa', ctrans('CPA'), money(c.cpa), delta(c.cpa, p?.cpa, { lowerIsBetter: true }), undefined, ctrans('Spend divided by attributed orders.')),
        kpi('meta_registrations', ctrans('Registrations · Meta'), count(c.meta_registrations), delta(c.meta_registrations, p?.meta_registrations), c.meta_cost_per_registration !== null ? money(c.meta_cost_per_registration) + ' ' + ctrans('per registration') : undefined, ctrans('Registrations completed as Ads Manager reports them (Meta\'s pixel), with spend divided by them as the cost per registration.')),
        kpi('ctr', ctrans('CTR'), pct(c.ctr, 2), delta(c.ctr, p?.ctr, { points: true }), short(c.ad_clicks) + ' ' + ctrans('clicks') + ' · ' + short(c.ad_impressions) + ' ' + ctrans('impressions')),
    ]
})

/* Chart colours. Reach is the dark green of the brief's mock-up, engagements amber, followers teal;
   validated together with the dataviz palette checker. Platforms and formats are identity colours
   that always sit beside their label, never colour alone. */
const SERIES = { reach: '#3b7a2a', engagements: '#d97706', followers: '#0d9488' }
const PLATFORM_COLORS: Record<string, string> = { facebook: '#475569', instagram: '#e11d48', tiktok: '#0d9488' }
const platformColor = (platform: string) => PLATFORM_COLORS[platform] ?? '#9ca3af'

const FORMAT_STYLE: Record<string, { icon: string, color: string }> = {
    reel: { icon: 'fal fa-film', color: '#e11d48' },
    carousel: { icon: 'fal fa-clone', color: '#d97706' },
    image: { icon: 'fal fa-image', color: '#3b7a2a' },
    video: { icon: 'fal fa-video', color: '#0d9488' },
    story: { icon: 'fal fa-history', color: '#475569' },
}
const formatStyle = (type: string) => FORMAT_STYLE[type] ?? FORMAT_STYLE[Object.keys(FORMAT_STYLE).find(key => type?.toLowerCase().includes(key)) ?? ''] ?? { icon: 'fal fa-hashtag', color: '#9ca3af' }

const bucketLabel = (date: string) => {
    const parsed = new Date(date)
    if (props.performance?.trend.bucket === 'month') return parsed.toLocaleDateString(undefined, { month: 'short', year: '2-digit' })
    return parsed.toLocaleDateString(undefined, { day: 'numeric', month: 'short' })
}

const trendLabels = computed(() => (props.performance?.trend.points ?? []).map(point => bucketLabel(point.date)))

/* The compared period is laid over the current one point for point, dashed, as Search Console does;
   each of its points keeps its own date for the tooltip. */
const comparedPoints = computed(() => props.performance?.previous_trend?.points ?? [])
const hasComparedTrend = computed(() => comparedPoints.value.some(point => point.views > 0 || point.interactions > 0 || point.followers !== null))
const comparedRangeLabel = computed(() => props.performance?.previous_from ? day(props.performance.previous_from) + ' – ' + day(props.performance.previous_to) : '')
const comparedDataset = (label: string, pick: (point: TrendPoint) => number | null, color: string) => ({
    ...lineDataset(label + ' · ' + ctrans('compared'), (props.performance?.trend.points ?? []).map((_, index) => {
        const point = comparedPoints.value[index]
        const value = point ? pick(point) : null
        return value === null ? NaN : value
    }), color, false),
    borderColor: color + 'aa',
    borderDash: [5, 4],
    borderWidth: 1.5,
    spanGaps: true,
    dates: comparedPoints.value.map(point => bucketLabel(point.date)),
})
const hasTrend = computed(() => (props.performance?.trend.points ?? []).some(point => point.views > 0 || point.interactions > 0))
const hasFollowersTrend = computed(() => (props.performance?.trend.points ?? []).some(point => point.followers !== null))

const lineDataset = (label: string, data: number[], color: string, fill: boolean) => ({
    label,
    data,
    borderColor: color,
    backgroundColor: fill ? color + '22' : 'transparent',
    fill,
    tension: 0.3,
    cubicInterpolationMode: 'monotone' as const,
    pointRadius: 0,
    pointHoverRadius: 4,
    pointHoverBackgroundColor: color,
    borderWidth: 2,
})

/* Views and interactions live an order of magnitude apart, so they share the x axis as two stacked
   panes rather than fighting over one y axis or inventing a second one. */
/* Views stack organic under ads, so the line's height is still every view and each band says where
   that part came from; picking one source draws that source alone. */
const trendPoints = computed(() => props.performance?.trend.points ?? [])
const trendHasSplit = computed(() => trendPoints.value.some(point => point.organic_views > 0 || point.ad_views > 0))
const viewsLegend = computed(() => {
    if (viewsSource.value === 'organic') return [{ label: ctrans('Organic views'), color: SOURCE_COLORS.organic }]
    if (viewsSource.value === 'ads') return [{ label: ctrans('Views from ads'), color: SOURCE_COLORS.ads }]
    if (!trendHasSplit.value) return [{ label: ctrans('Views'), color: SERIES.reach }]
    return [
        { label: ctrans('Organic'), color: SOURCE_COLORS.organic },
        { label: ctrans('Ads'), color: SOURCE_COLORS.ads },
        ...(trendPoints.value.some(point => point.views > point.organic_views + point.ad_views) ? [{ label: ctrans('Not split'), color: SOURCE_COLORS.unsplit }] : []),
    ]
})
const viewsDatasets = computed(() => {
    const points = trendPoints.value
    if (viewsSource.value !== 'all') {
        return [{ ...lineDataset(viewsLabel.value, points.map(point => viewsOf(point) ?? 0), viewsSource.value === 'ads' ? SOURCE_COLORS.ads : SOURCE_COLORS.organic, true), stack: 'views' }]
    }
    if (!trendHasSplit.value) {
        return [{ ...lineDataset(ctrans('Views'), points.map(point => point.views), SERIES.reach, true), stack: 'views' }]
    }
    const unsplit = points.map(point => Math.max(0, point.views - point.organic_views - point.ad_views))
    return [
        { ...lineDataset(ctrans('Organic'), points.map(point => point.organic_views), SOURCE_COLORS.organic, true), stack: 'views', fill: 'origin' },
        { ...lineDataset(ctrans('Ads'), points.map(point => point.ad_views), SOURCE_COLORS.ads, true), stack: 'views', fill: '-1' },
        ...(unsplit.some(value => value > 0) ? [{ ...lineDataset(ctrans('Not split'), unsplit, SOURCE_COLORS.unsplit, true), stack: 'views', fill: '-1' }] : []),
    ]
})
const reachChart = computed(() => ({
    labels: trendLabels.value,
    datasets: [
        ...viewsDatasets.value,
        ...(hasComparedTrend.value ? [{ ...comparedDataset(viewsLabel.value, point => viewsOf(point), viewsSource.value === 'ads' ? SOURCE_COLORS.ads : SERIES.reach), stack: 'compared' }] : []),
    ],
}))
const engagementsChart = computed(() => ({
    labels: trendLabels.value,
    datasets: [
        lineDataset(ctrans('Interactions'), (props.performance?.trend.points ?? []).map(point => point.interactions), SERIES.engagements, false),
        ...(hasComparedTrend.value ? [comparedDataset(ctrans('Interactions'), point => point.interactions, SERIES.engagements)] : []),
    ],
}))
const followersChart = computed(() => ({
    labels: trendLabels.value,
    datasets: [
        { ...lineDataset(ctrans('Followers'), (props.performance?.trend.points ?? []).map(point => point.followers ?? NaN), SERIES.followers, false), pointRadius: 2, spanGaps: true },
        ...(comparedPoints.value.some(point => point.followers !== null) && previous.value?.followers_accounts === current.value?.followers_accounts ? [comparedDataset(ctrans('Followers'), point => point.followers, SERIES.followers)] : []),
    ],
}))

const chartOptions = (showX: boolean, beginAtZero = true, stacked = false) => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#111827',
            titleFont: { size: 11 },
            bodyFont: { size: 11 },
            padding: 8,
            displayColors: false,
            callbacks: {
                label: (context: any) => context.dataset.label + (context.dataset.dates ? ' (' + (context.dataset.dates[context.dataIndex] ?? '–') + ')' : '') + ': ' + (Number.isNaN(context.parsed.y) ? '–' : locale.number(context.parsed.y)),
            },
        },
    },
    scales: {
        x: { display: showX, grid: { display: false }, ticks: { maxTicksLimit: 10, maxRotation: 0, font: { size: 10 }, color: '#6b7280' } },
        y: {
            stacked,
            beginAtZero,
            grid: { color: 'rgba(0,0,0,0.05)' },
            border: { display: false },
            ticks: { maxTicksLimit: 4, font: { size: 10 }, color: '#6b7280', callback: (value: any) => locale.numberShort(Number(value)) },
        },
    },
})

const maxOf = (values: number[]) => Math.max(0, ...values)
const barWidth = (value: number, max: number) => max > 0 ? Math.max(1, value / max * 100) + '%' : '0%'

const platformViewsShare = (row: { views: number, organic_views: number, ad_views: number }) => {
    const total = platformRows.value.reduce((sum, item) => sum + (viewsOf(item) ?? 0), 0)
    return total > 0 ? Math.round((viewsOf(row) ?? 0) / total * 1000) / 10 : 0
}
const platformRows = computed(() => (props.performance?.platforms ?? []).filter(row => row.posts > 0 || row.views > 0).sort((a, b) => b.views - a.views))
const formatRows = computed(() => [...(props.performance?.formats ?? [])].filter(row => row.posts > 0).sort((a, b) => b.average_reach - a.average_reach))
const maxFormatReach = computed(() => maxOf(formatRows.value.map(row => row.average_reach)))

/* Top content: sorted on the client so the ranking question (by rate, by views, by reach, by
   volume) is one click, and the long tail opens on request rather than filling the screen. */
type PostSort = 'engagement_rate' | 'impressions' | 'reach' | 'engagements'
const postSort = ref<PostSort>('engagement_rate')
const postSortOptions = [
    { label: ctrans('Engagement rate'), value: 'engagement_rate' as PostSort },
    { label: ctrans('Views'), value: 'impressions' as PostSort },
    { label: ctrans('Viewers'), value: 'reach' as PostSort },
    { label: ctrans('Engagements'), value: 'engagements' as PostSort },
]
const showAllPosts = ref(false)
const sortedPosts = computed(() => [...(props.performance?.top_posts ?? [])].sort((a, b) => (b[postSort.value] ?? 0) - (a[postSort.value] ?? 0)).slice(0, 50))
const visiblePosts = computed(() => sortedPosts.value.slice(0, showAllPosts.value ? 50 : 10))
const overviewPosts = computed(() => [...(props.performance?.top_posts ?? [])].sort((a, b) => b.engagement_rate - a.engagement_rate).slice(0, 5))
const postTitle = (post: Post) => post.description?.trim() || ctrans('Untitled :type', { type: post.type_label })

const accounts = computed(() => [...(props.performance?.accounts ?? [])].sort((a, b) => b.views - a.views))

const paidChannels = computed(() => (props.performance?.paid.channels ?? []).filter(channel => channel.spend > 0 || channel.impressions > 0))
const maxChannelSpend = computed(() => maxOf(paidChannels.value.map(channel => channel.spend)))
const campaigns = computed(() => [...(props.performance?.paid.campaigns ?? [])].sort((a, b) => b.spend - a.spend))
const hasPaid = computed(() => paidChannels.value.length > 0 || campaigns.value.length > 0)
const hasOrganic = computed(() => props.performance?.data_until !== null && props.performance?.data_until !== undefined)

/* Every ROAS pill says the same thing: paid back or not. Colour is information here. */
const roasClass = (roas: number | null) => roas === null ? 'bg-gray-100 text-gray-400' : roas >= 1 ? 'bg-green-50 text-green-700 ring-1 ring-green-200' : 'bg-red-50 text-red-700 ring-1 ring-red-200'

/* Sync from Meta: one brand at a time, since every brand has its own Page, Instagram account and
   ad account. A brand that is not set up says so and links to its settings instead. While a sync
   runs the list is refreshed every few seconds, and the figures once it has finished. */
const isSyncOpen = ref(false)
const syncErrors = ref<Record<number, string>>({})
const syncShops = computed(() => (props.meta_sync ?? []).filter(shop => !shopFilter.value || shop.slug === shopFilter.value))
const selectedSync = computed(() => shopFilter.value ? (props.meta_sync ?? []).find(shop => shop.slug === shopFilter.value) ?? null : null)
const isSetUp = (shop: MetaSync) => shop.organic || shop.paid
const anyRunning = computed(() => (props.meta_sync ?? []).some(shop => shop.running))
const time = (value: string | null | undefined) => value ? useFormatTime(value, { formatTime: 'hm' }) : '–'
const now = ref(Date.now())
const nowTimer = setInterval(() => now.value = Date.now(), 15000)
const isCoolingDown = (shop: MetaSync) => !!shop.available_at && new Date(shop.available_at).getTime() > now.value
const syncLabel = (shop: MetaSync) => shop.running ? ctrans('Syncing…') : isCoolingDown(shop) ? ctrans('Sync again at :time', { time: new Date(shop.available_at as string).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }) : ctrans('Sync now')

let pollTimer: ReturnType<typeof setInterval> | null = null
const stopPolling = () => {
    if (pollTimer) clearInterval(pollTimer)
    pollTimer = null
}
const startPolling = () => {
    if (pollTimer) return
    pollTimer = setInterval(() => router.reload({ only: ['meta_sync'] }), 8000)
}
watch(anyRunning, (running, wasRunning) => {
    if (running) {
        startPolling()
        return
    }
    stopPolling()
    if (wasRunning) applyFilters()
}, { immediate: true })
onBeforeUnmount(() => {
    stopPolling()
    clearInterval(nowTimer)
})

const syncDays = ref<number | null>(null)
const syncRangeOptions = [
    { value: null, label: ctrans('Recent days (organic :organic, ads :paid)', { organic: 28, paid: 7 }) },
    { value: 7, label: ctrans('Last :days days', { days: 7 }) },
    { value: 28, label: ctrans('Last :days days', { days: 28 }) },
    { value: 90, label: ctrans('Last :days days (the most)', { days: 90 }) },
]
const syncShop = async (shop: MetaSync) => {
    syncErrors.value = { ...syncErrors.value, [shop.id]: '' }
    try {
        await axios.post(route(shop.route.name, shop.route.parameters), { days: syncDays.value })
        router.reload({ only: ['meta_sync'] })
    } catch (error: any) {
        syncErrors.value = { ...syncErrors.value, [shop.id]: error?.response?.data?.errors?.shop?.[0] ?? error?.response?.data?.message ?? ctrans('The sync could not start') }
    }
}
const syncAll = () => syncShops.value.filter(shop => isSetUp(shop) && !shop.running && !isCoolingDown(shop)).forEach(syncShop)

/* Upload: the import lands on one shop, so the shop is chosen first and the modal opens after.
   The platform is only needed for a single-metric chart export whose title does not name it. */
const isUploadOpen = ref(false)
const isShopPickerOpen = ref(false)
const uploadShop = ref<{ id: number, name: string, route: routeType } | null>(null)
const uploadShopOptions = computed(() => props.upload_shops ?? [])
const uploadPlatform = ref('')
const uploadPlatformOptions = [
    { label: ctrans('Detect'), value: '' },
    { label: 'Facebook', value: 'facebook' },
    { label: 'Instagram', value: 'instagram' },
]

const startUpload = () => {
    if (uploadShopOptions.value.length === 1) {
        uploadShop.value = uploadShopOptions.value[0]
    }
    isShopPickerOpen.value = true
}

const continueUpload = () => {
    if (!uploadShop.value) return
    isShopPickerOpen.value = false
    isUploadOpen.value = true
}

const uploadSpreadsheet = computed(() => uploadShop.value ? {
    event: 'action-progress',
    channel: 'grp.personal.' + layout.group?.id,
    required_fields: [],
    template: { label: '' },
    route: {
        upload: {
            name: uploadShop.value.route.name,
            parameters: uploadPlatform.value
                ? { ...uploadShop.value.route.parameters, _query: { platform: uploadPlatform.value } }
                : uploadShop.value.route.parameters,
        },
    },
} as any : undefined)

const uploadInformation = ctrans('Upload the CSV or Excel exports from Meta Business Suite (Insights → Export data → Content, or a chart\'s Export for one figure per day). Content exports add or update posts; daily exports add followers, views and visits per day. Re-uploading a period updates its figures, it never duplicates them.')
</script>

<template>
  <div>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <a :href="reportHref" download data-test="social-export-report">
                <Button icon="fal fa-file-export" :label="ctrans('Export report')" type="tertiary" v-tooltip="ctrans('The period on one sheet: every figure beside the compared period, platforms, accounts and the best posts. Pick Last Month for the monthly report.')" />
            </a>
            <a :href="exportHref" download>
                <Button icon="fal fa-file-export" :label="ctrans('Export posts')" type="tertiary" />
            </a>
            <Button v-if="meta_sync?.length" icon="fal fa-sync-alt" :label="ctrans('Sync from Meta')" type="tertiary" data-test="social-sync-open" @click="isSyncOpen = true">
                <template #icon><FontAwesomeIcon icon="fal fa-sync-alt" :spin="anyRunning" class="text-[--app-accent]" fixed-width /></template>
            </Button>
            <Button v-if="uploadShopOptions.length" icon="fal fa-upload" :label="ctrans('Import export')" type="tertiary" @click="startUpload" />
        </template>
    </PageHeading>

    <div class="pt-3">
        <DashboardSettings :intervals="intervals" :settings="settings" currentTab="social_media" :reloadOnly="['performance', 'intervals']" />
    </div>

    <div class="px-4 py-4 space-y-4 max-w-[1600px]">
        <Deferred data="performance">
            <template #fallback>
                <div class="space-y-4">
                    <div class="h-8 w-full max-w-2xl animate-pulse rounded-md bg-gray-100" />
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                        <div v-for="card in 6" :key="card" class="h-24 animate-pulse rounded-xl bg-gray-100 ring-1 ring-gray-200" />
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 h-72 animate-pulse rounded-xl bg-gray-100 ring-1 ring-gray-200" />
                        <div class="h-72 animate-pulse rounded-xl bg-gray-100 ring-1 ring-gray-200" />
                    </div>
                    <div class="h-56 animate-pulse rounded-xl bg-gray-100 ring-1 ring-gray-200" />
                </div>
            </template>

            <div v-if="performance" class="space-y-4">
                <!-- Scope: the sentence the whole screen is about, and the filters that change it -->
                <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-gray-500 mr-1">{{ ctrans('Organic and paid social in one view') }} ·</span>
                    <span v-if="performance.data_until" class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700 ring-1 ring-green-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-green-500" />
                        {{ ctrans('Data up to') }} {{ day(performance.data_until) }}
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 ring-1 ring-amber-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                        {{ ctrans('No data imported yet') }}
                    </span>
                    <button v-if="selectedSync && !isSetUp(selectedSync)" type="button" class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100" data-test="social-sync-not-set-up" @click="isSyncOpen = true">
                        <FontAwesomeIcon icon="fal fa-exclamation-triangle" fixed-width />
                        {{ ctrans('Not connected to Meta') }}
                    </button>
                    <button v-else-if="selectedSync" type="button" class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2 py-0.5 text-xs text-gray-600 ring-1 ring-gray-200 hover:bg-gray-100" @click="isSyncOpen = true">
                        <FontAwesomeIcon icon="fal fa-sync-alt" :spin="selectedSync.running" fixed-width />
                        <template v-if="selectedSync.running">{{ ctrans('Syncing from Meta…') }}</template>
                        <template v-else-if="selectedSync.last_sync">{{ ctrans('Synced') }} {{ time(selectedSync.last_sync.finished_at) }}</template>
                        <template v-else>{{ ctrans('Not synced yet') }}</template>
                    </button>
                    <FontAwesomeIcon v-if="isReloading" icon="fal fa-spinner-third" spin class="text-gray-400 text-xs" fixed-width />
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center sm:justify-end" data-test="social-filters">
                    <Select v-model="shopFilter" :options="performance.options.shops" optionLabel="name" optionValue="slug" showClear filter autoFilterFocus resetFilterOnHide
                            :filterPlaceholder="ctrans('Search brand')" :placeholder="ctrans('All brands')" class="w-full sm:w-44" :pt="selectPt" data-test="social-brand-filter" @change="applyFilters" />
                    <Select v-model="countryFilter" :options="performance.options.countries" optionLabel="name" optionValue="code" showClear filter autoFilterFocus resetFilterOnHide
                            :filterPlaceholder="ctrans('Search market')" :placeholder="ctrans('All markets')" class="w-full sm:w-40" :pt="selectPt" @change="applyFilters" />
                    <Select v-model="platformFilter" :options="performance.options.platforms" optionLabel="label" optionValue="value" showClear
                            :placeholder="ctrans('All platforms')" class="w-full sm:w-40" :pt="selectPt" @change="applyFilters" />
                    <Select v-model="compareMode" :options="compareOptions" optionLabel="label" optionValue="value"
                            class="w-full sm:w-48" :class="fieldFocusClass" :pt="selectPt" data-test="social-compare" @change="onCompareMode" />
                    <div v-if="compareMode === 'custom'" class="col-span-2 flex items-center gap-2">
                        <DatePicker v-model="compareFrom" :maxDate="compareTo ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input"
                                    class="flex-1 sm:flex-none" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('Compare from')" />
                        <span class="text-gray-400">–</span>
                        <DatePicker v-model="compareTo" :minDate="compareFrom ?? undefined" dateFormat="d M yy" :manualInput="false" showIcon iconDisplay="input"
                                    class="flex-1 sm:flex-none" :class="fieldFocusClass" :pt="datePickerPt" :aria-label="ctrans('Compare to')" />
                    </div>
                    <span v-if="comparedIsEmpty" class="col-span-2 text-xs text-gray-400" data-test="social-compare-empty">{{ ctrans('No figures in the compared period') }}</span>
                </div>
                </div>

                <Tabs :current="currentTab" :navigation="tabs" @update:tab="(tab: string) => currentTab = tab" />

                <div class="space-y-4 transition-opacity" :class="isReloading ? 'opacity-50 pointer-events-none' : ''">
                    <!-- Empty state: nothing imported yet, say how to start -->
                    <div v-if="!hasOrganic && currentTab !== 'paid' && currentTab !== 'uploads'" class="rounded-xl ring-1 ring-gray-200 bg-white p-6 flex flex-col md:flex-row gap-6 items-start">
                        <div class="h-12 w-12 shrink-0 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                            <FontAwesomeIcon icon="fal fa-upload" class="text-lg" fixed-width />
                        </div>
                        <div class="space-y-2 text-sm text-gray-600 max-w-2xl">
                            <div class="text-base font-medium text-gray-800">{{ ctrans('No social media data for this period yet') }}</div>
                            <p>{{ ctrans('This dashboard is fed by the exports the platforms already produce. Nothing is typed in by hand.') }}</p>
                            <ol class="list-decimal pl-5 space-y-1">
                                <li>{{ ctrans('Meta Business Suite: Insights → Export data → Content, for the period you want; Results or Audience per day for followers and reach.') }}</li>
                                <li>{{ ctrans('Upload the file here with Import export, choosing the brand it belongs to. Re-uploading a period updates it, it never duplicates it.') }}</li>
                            </ol>
                            <Button v-if="uploadShopOptions.length" icon="fal fa-upload" :label="ctrans('Import export')" type="tertiary" class="mt-2" @click="startUpload" />
                        </div>
                    </div>

                    <!-- Overview -->
                    <template v-if="currentTab === 'overview'">
                        <div class="flex flex-wrap items-center justify-between gap-2" data-test="social-views-source">
                            <span class="text-xs text-gray-500">{{ ctrans('Views from') }}</span>
                            <div class="flex items-center gap-1">
                                <SegmentedToggle v-model="viewsSource" :options="viewsSourceOptions" />
                                <FontAwesomeIcon v-tooltip="viewsSourceHelp" icon="fal fa-info-circle" class="text-gray-300 cursor-help" fixed-width />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                            <div v-for="card in overviewKpis" :key="card.key" class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                                <div class="text-xs text-gray-400 flex items-center gap-1">
                                    {{ card.label }}
                                    <FontAwesomeIcon v-if="card.help" v-tooltip="card.help" icon="fal fa-info-circle" class="text-gray-300 cursor-help" fixed-width />
                                </div>
                                <div class="mt-1 flex items-center justify-between gap-2 tabular-nums">
                                    <span class="text-lg text-gray-900">{{ card.value }}</span>
                                    <span v-if="previousText(card.key)" class="w-4 border-t-2 border-gray-500" />
                                </div>
                                <template v-if="previousText(card.key)">
                                    <div class="text-[11px] text-gray-400">{{ performance.period_label }}</div>
                                    <div class="mt-1 flex items-center justify-between gap-2 tabular-nums" data-test="social-kpi-previous">
                                        <span class="text-sm text-gray-600">{{ previousText(card.key) }}</span>
                                        <span class="w-4 border-t-2 border-dashed border-gray-400" />
                                    </div>
                                    <div class="text-[11px] text-gray-400">{{ comparedRangeLabel }}</div>
                                </template>
                                <div v-if="card.split && (card.split.ads || card.split.unsplit)" class="mt-2" data-test="social-views-split">
                                    <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                                        <span v-for="part in (['organic', 'ads', 'unsplit'] as const)" :key="part" class="h-full" :style="{ width: splitShare(card.split, part) + '%', background: SOURCE_COLORS[part] }" />
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-gray-500 tabular-nums">
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.organic }" />{{ ctrans('Organic') }} {{ short(card.split.organic) }}</span>
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.ads }" />{{ ctrans('Ads') }} {{ short(card.split.ads) }}</span>
                                        <span v-if="card.split.unsplit" v-tooltip="ctrans('Views of days synced before Meta was asked where they came from. Sync again to split them.')" class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.unsplit }" />{{ ctrans('Not split') }} {{ short(card.split.unsplit) }}</span>
                                    </div>
                                </div>
                                <div v-if="card.delta" class="mt-0.5 text-xs flex items-center gap-1 tabular-nums" :class="card.delta.direction === 'flat' ? 'text-gray-400' : card.delta.good ? 'text-green-700' : 'text-red-600'">
                                    <FontAwesomeIcon v-if="card.delta.direction !== 'flat'" :icon="card.delta.direction === 'up' ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width />
                                    <span>{{ card.delta.text }}</span>
                                    <span class="text-gray-400">{{ versusLabel }}</span>
                                </div>
                                <div v-else-if="card.sub" class="mt-0.5 text-xs text-gray-400 tabular-nums">{{ card.sub }}</div>
                            </div>
                        </div>

                        <div v-if="hasOrganic" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <div class="flex items-baseline justify-between gap-4">
                                    <div>
                                        <span class="text-sm font-medium text-gray-800">{{ ctrans('Performance trend') }}</span>
                                        <span class="ml-2 text-xs text-gray-400">{{ performance.period_label }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-gray-500">
                                        <span v-for="item in viewsLegend" :key="item.label" class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ background: item.color }" />{{ item.label }}</span>
                                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ background: SERIES.engagements }" />{{ ctrans('Interactions') }}</span>
                                        <span v-if="hasComparedTrend" class="flex items-center gap-1.5" data-test="social-compared-legend"><span class="w-4 border-t-2 border-dashed border-gray-400" />{{ comparedRangeLabel }}</span>
                                    </div>
                                </div>
                                <template v-if="hasTrend">
                                    <div class="h-44 mt-3"><Line :data="reachChart" :options="chartOptions(false, true, true)" /></div>
                                    <div class="h-28"><Line :data="engagementsChart" :options="chartOptions(true)" /></div>
                                </template>
                                <p v-else class="text-sm text-gray-400 py-16 text-center">{{ ctrans('No figures in this period') }}</p>
                                <div v-if="performance.insight" class="mt-4 rounded-lg bg-green-50 px-3 py-2 text-xs text-gray-700 ring-1 ring-green-100" data-test="social-insight">
                                    <span class="font-medium text-green-800">{{ ctrans('Performance insight') }}:</span> {{ performance.insight }}
                                </div>
                            </div>

                            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <span class="text-sm font-medium text-gray-800">{{ ctrans('Platform contribution') }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ viewsSource === 'organic' ? ctrans('share of organic views') : viewsSource === 'ads' ? ctrans('share of views from ads') : ctrans('share of views') }}</span>
                                <div v-if="platformRows.length" class="mt-4 space-y-4">
                                    <div v-for="row in platformRows" :key="row.platform">
                                        <div class="flex items-baseline justify-between text-sm">
                                            <span class="flex items-center gap-2 text-gray-800">
                                                <span class="h-2.5 w-2.5 rounded-full" :style="{ background: platformColor(row.platform) }" />
                                                {{ row.label }}
                                            </span>
                                            <span class="tabular-nums text-gray-900">{{ short(viewsOf(row) ?? 0) }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full" :style="{ width: platformViewsShare(row) + '%', background: platformColor(row.platform) }" />
                                        </div>
                                        <div class="mt-1 flex flex-wrap justify-between gap-x-3 text-xs text-gray-400 tabular-nums">
                                            <span>{{ pct(platformViewsShare(row)) }} {{ ctrans('of total') }} · {{ count(row.posts) }} {{ ctrans('posts') }}<template v-if="viewsSource === 'all' && row.ad_views"> · {{ ctrans('organic') }} {{ short(row.organic_views) }} · {{ ctrans('ads') }} {{ short(row.ad_views) }}</template></span>
                                            <span>{{ ctrans('ER') }} {{ pct(row.engagement_rate) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400 py-16 text-center">{{ ctrans('No figures in this period') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                            <div v-if="hasOrganic" class="lg:col-span-3 rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-sm font-medium text-gray-800">{{ ctrans('Top organic content') }}</span>
                                    <button type="button" class="text-xs text-gray-500 hover:underline" @click="currentTab = 'organic'">{{ ctrans('See all') }}</button>
                                </div>
                                <table v-if="overviewPosts.length" class="mt-3 w-full table-fixed text-sm" data-test="social-overview-posts">
                                    <thead class="text-xs text-gray-400 whitespace-nowrap">
                                        <tr class="text-left">
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Content') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right w-16">{{ ctrans('Views') }}</th>
                                            <th class="hidden sm:table-cell font-normal pb-2 pr-3 text-right w-16">{{ ctrans('Viewers') }}</th>
                                            <th class="hidden md:table-cell font-normal pb-2 pr-3 text-right w-24">{{ ctrans('Engagements') }}</th>
                                            <th class="font-normal pb-2 text-right w-16">{{ ctrans('ER') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="post in overviewPosts" :key="post.id">
                                            <td class="py-2 pr-3 min-w-0">
                                                <a :href="post.permalink ?? undefined" :target="post.permalink ? '_blank' : undefined" rel="noopener" class="block truncate text-gray-800" :class="post.permalink ? 'hover:underline' : ''">{{ postTitle(post) }}</a>
                                                <div class="truncate text-xs text-gray-400 flex items-center gap-1.5">
                                                    <FontAwesomeIcon :icon="formatStyle(post.type).icon" :style="{ color: formatStyle(post.type).color }" fixed-width />
                                                    {{ post.platform_label }} · {{ post.type_label }}
                                                </div>
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(post.impressions) }}</td>
                                            <td class="hidden sm:table-cell py-2 pr-3 text-right tabular-nums">{{ short(post.reach) }}</td>
                                            <td class="hidden md:table-cell py-2 pr-3 text-right tabular-nums">{{ short(post.engagements) }}</td>
                                            <td class="py-2 text-right"><span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-700">{{ pct(post.engagement_rate) }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p v-else class="text-sm text-gray-400 py-10 text-center">{{ ctrans('No posts in this period') }}</p>
                            </div>

                            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5" :class="hasOrganic ? 'lg:col-span-2' : 'lg:col-span-5'">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-sm font-medium text-gray-800">{{ ctrans('Paid summary') }}</span>
                                    <button type="button" class="text-xs text-gray-500 hover:underline" @click="currentTab = 'paid'">{{ ctrans('Campaigns') }}</button>
                                </div>
                                <div v-if="paidChannels.length" class="mt-3 divide-y divide-gray-100">
                                    <div v-for="channel in paidChannels" :key="channel.type" class="py-2 flex items-center justify-between gap-3 text-sm">
                                        <div class="min-w-0">
                                            <div class="text-gray-800 truncate">{{ channel.label }}</div>
                                            <div class="text-xs text-gray-400 tabular-nums">{{ short(channel.clicks) }} {{ ctrans('clicks') }} · {{ ctrans('CTR') }} {{ pct(channel.ctr, 2) }}</div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <div class="tabular-nums text-gray-900">{{ money(channel.spend) }}</div>
                                            <span class="inline-block rounded-full px-2 py-0.5 text-xs tabular-nums" :class="roasClass(channel.meta_roas)" v-tooltip="ctrans('ROAS as Ads Manager reports it')">{{ ctrans('ROAS') }} {{ ratio(channel.meta_roas) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400 py-10 text-center">{{ ctrans('No paid spend in this period') }}</p>
                            </div>
                        </div>
                    </template>

                    <!-- Organic -->
                    <template v-else-if="currentTab === 'organic' && hasOrganic">
                        <div class="flex flex-wrap items-center justify-between gap-2" data-test="social-views-source">
                            <span class="text-xs text-gray-500">{{ ctrans('Views from') }}</span>
                            <div class="flex items-center gap-1">
                                <SegmentedToggle v-model="viewsSource" :options="viewsSourceOptions" />
                                <FontAwesomeIcon v-tooltip="viewsSourceHelp" icon="fal fa-info-circle" class="text-gray-300 cursor-help" fixed-width />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                            <div v-for="card in organicKpis" :key="card.key" class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                                <div class="text-xs text-gray-400">{{ card.label }}</div>
                                <div class="mt-1 flex items-center justify-between gap-2 tabular-nums">
                                    <span class="text-lg text-gray-900">{{ card.value }}</span>
                                    <span v-if="previousText(card.key)" class="w-4 border-t-2 border-gray-500" />
                                </div>
                                <template v-if="previousText(card.key)">
                                    <div class="text-[11px] text-gray-400">{{ performance.period_label }}</div>
                                    <div class="mt-1 flex items-center justify-between gap-2 tabular-nums" data-test="social-kpi-previous">
                                        <span class="text-sm text-gray-600">{{ previousText(card.key) }}</span>
                                        <span class="w-4 border-t-2 border-dashed border-gray-400" />
                                    </div>
                                    <div class="text-[11px] text-gray-400">{{ comparedRangeLabel }}</div>
                                </template>
                                <div v-if="card.split && (card.split.ads || card.split.unsplit)" class="mt-2" data-test="social-views-split">
                                    <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                                        <span v-for="part in (['organic', 'ads', 'unsplit'] as const)" :key="part" class="h-full" :style="{ width: splitShare(card.split, part) + '%', background: SOURCE_COLORS[part] }" />
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-gray-500 tabular-nums">
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.organic }" />{{ ctrans('Organic') }} {{ short(card.split.organic) }}</span>
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.ads }" />{{ ctrans('Ads') }} {{ short(card.split.ads) }}</span>
                                        <span v-if="card.split.unsplit" v-tooltip="ctrans('Views of days synced before Meta was asked where they came from. Sync again to split them.')" class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.unsplit }" />{{ ctrans('Not split') }} {{ short(card.split.unsplit) }}</span>
                                    </div>
                                </div>
                                <div v-if="card.delta" class="mt-0.5 text-xs flex items-center gap-1 tabular-nums" :class="card.delta.direction === 'flat' ? 'text-gray-400' : card.delta.good ? 'text-green-700' : 'text-red-600'">
                                    <FontAwesomeIcon v-if="card.delta.direction !== 'flat'" :icon="card.delta.direction === 'up' ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width />
                                    <span>{{ card.delta.text }}</span>
                                    <span class="text-gray-400">{{ versusLabel }}</span>
                                </div>
                                <div v-else-if="card.sub" class="mt-0.5 text-xs text-gray-400 tabular-nums">{{ card.sub }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="lg:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <div class="flex items-baseline justify-between gap-4">
                                    <span class="text-sm font-medium text-gray-800">{{ ctrans('Views and interactions') }}</span>
                                    <div class="flex items-center gap-3 text-xs text-gray-500">
                                        <span v-for="item in viewsLegend" :key="item.label" class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ background: item.color }" />{{ item.label }}</span>
                                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ background: SERIES.engagements }" />{{ ctrans('Interactions') }}</span>
                                        <span v-if="hasComparedTrend" class="flex items-center gap-1.5" data-test="social-compared-legend"><span class="w-4 border-t-2 border-dashed border-gray-400" />{{ comparedRangeLabel }}</span>
                                    </div>
                                </div>
                                <template v-if="hasTrend">
                                    <div class="h-40 mt-3"><Line :data="reachChart" :options="chartOptions(false, true, true)" /></div>
                                    <div class="h-24"><Line :data="engagementsChart" :options="chartOptions(!hasFollowersTrend)" /></div>
                                </template>
                                <p v-else class="text-sm text-gray-400 py-16 text-center">{{ ctrans('No figures in this period') }}</p>
                                <template v-if="hasFollowersTrend">
                                    <div class="mt-3 flex items-center gap-1.5 text-xs text-gray-500"><span class="h-2 w-2 rounded-sm" :style="{ background: SERIES.followers }" />{{ ctrans('Followers') }}</div>
                                    <div class="h-24"><Line :data="followersChart" :options="chartOptions(true, false)" /></div>
                                </template>
                            </div>

                            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <span class="text-sm font-medium text-gray-800">{{ ctrans('Format comparison') }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ ctrans('average viewers per post') }}</span>
                                <div v-if="formatRows.length" class="mt-4 space-y-4">
                                    <div v-for="row in formatRows" :key="row.type">
                                        <div class="flex items-baseline justify-between text-sm">
                                            <span class="flex items-center gap-2 text-gray-800">
                                                <FontAwesomeIcon :icon="formatStyle(row.type).icon" :style="{ color: formatStyle(row.type).color }" fixed-width />
                                                {{ row.label }}
                                                <span class="text-xs text-gray-400">{{ count(row.posts) }}</span>
                                            </span>
                                            <span class="tabular-nums text-gray-900">{{ short(row.average_reach) }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full" :style="{ width: barWidth(row.average_reach, maxFormatReach), background: SERIES.reach }" />
                                        </div>
                                        <div class="mt-1 flex justify-between text-xs text-gray-400 tabular-nums">
                                            <span>{{ ctrans('ER') }} {{ pct(row.engagement_rate) }}</span>
                                            <span>{{ short(row.saves) }} {{ ctrans('saves') }} · {{ short(row.shares) }} {{ ctrans('shares') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-gray-400 py-16 text-center">{{ ctrans('No posts in this period') }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <span class="text-sm font-medium text-gray-800">{{ ctrans('Top content') }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ ctrans('ranked by') }}</span>
                                </div>
                                <SegmentedToggle v-model="postSort" :options="postSortOptions" />
                            </div>
                            <div v-if="visiblePosts.length" class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-400 whitespace-nowrap">
                                        <tr class="text-left">
                                            <th class="font-normal pb-2 pr-3 w-8">#</th>
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Content') }}</th>
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Account') }}</th>
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Published') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Views') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Viewers') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Engagements') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('ER') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Saves') }}</th>
                                            <th class="font-normal pb-2 text-right">{{ ctrans('Clicks') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="(post, index) in visiblePosts" :key="post.id" class="hover:bg-gray-50">
                                            <td class="py-2 pr-3 text-xs text-gray-400 tabular-nums">{{ index + 1 }}</td>
                                            <td class="py-2 pr-3 max-w-sm">
                                                <a :href="post.permalink ?? undefined" :target="post.permalink ? '_blank' : undefined" rel="noopener" class="flex items-center gap-1.5 text-gray-800" :class="post.permalink ? 'hover:underline' : ''">
                                                    <span class="truncate">{{ postTitle(post) }}</span>
                                                    <FontAwesomeIcon v-if="post.permalink" icon="fal fa-external-link" class="text-gray-300 text-xs shrink-0" fixed-width />
                                                </a>
                                                <div class="text-xs text-gray-400 flex items-center gap-1.5">
                                                    <FontAwesomeIcon :icon="formatStyle(post.type).icon" :style="{ color: formatStyle(post.type).color }" fixed-width />
                                                    {{ post.platform_label }} · {{ post.type_label }}
                                                    <span v-if="post.is_boosted" v-tooltip="ctrans('Promoted with an ad. Figures here are organic; the Instagram app adds what the ad brought.')" class="ml-1 inline-flex items-center gap-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600" data-test="social-post-boosted">
                                                        <FontAwesomeIcon icon="fal fa-bullhorn" fixed-width />{{ ctrans('Boosted') }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="py-2 pr-3 text-xs text-gray-500 whitespace-nowrap">
                                                <div>{{ post.account_name }}</div>
                                                <div class="text-gray-400">{{ post.shop_name }}</div>
                                            </td>
                                            <td class="py-2 pr-3 text-xs text-gray-500 whitespace-nowrap">{{ day(post.published_at) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums" data-test="social-post-views">
                                                {{ short(post.impressions) }}
                                                <div v-if="post.ad_views" v-tooltip="ctrans('Views the boost ad added; organic and ad together: :total', { total: short(post.impressions + post.ad_views) })" class="text-[11px] text-gray-400" data-test="social-post-ad-views">+{{ short(post.ad_views) }} {{ ctrans('ads') }}</div>
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(post.reach) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(post.engagements) }}</td>
                                            <td class="py-2 pr-3 text-right"><span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-700">{{ pct(post.engagement_rate) }}</span></td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(post.saves) }}</td>
                                            <td class="py-2 text-right tabular-nums">{{ short(post.link_clicks) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div v-if="sortedPosts.length > 10" class="mt-3 text-center">
                                    <button type="button" class="text-xs text-gray-500 hover:underline" @click="showAllPosts = !showAllPosts">
                                        {{ showAllPosts ? ctrans('Show top 10') : ctrans('Show :count more', { count: String(sortedPosts.length - 10) }) }}
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-400 py-10 text-center">{{ ctrans('No posts in this period') }}</p>
                        </div>

                        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                            <span class="text-sm font-medium text-gray-800">{{ ctrans('Accounts') }}</span>
                            <span class="ml-2 text-xs text-gray-400">{{ ctrans('brand × platform × market') }}</span>
                            <div v-if="accounts.length" class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-gray-400 whitespace-nowrap">
                                        <tr class="text-left">
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Account') }}</th>
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Brand') }}</th>
                                            <th class="font-normal pb-2 pr-3">{{ ctrans('Market') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Followers') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Posts') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Views') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Seen without paying')">{{ ctrans('Organic') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Seen through boosted posts and Meta Ads campaigns')">{{ ctrans('Ads') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Interactions') }}</th>
                                            <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Clicks') }}</th>
                                            <th class="font-normal pb-2 text-right" v-tooltip="ctrans('Interactions per 100 views')">{{ ctrans('Rate') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50">
                                            <td class="py-2 pr-3">
                                                <div class="flex items-center gap-2 text-gray-800">
                                                    <span class="h-2.5 w-2.5 rounded-full shrink-0" :style="{ background: platformColor(account.platform) }" />
                                                    {{ account.name }}
                                                </div>
                                                <div class="text-xs text-gray-400 pl-[18px]">{{ account.platform_label }}<span v-if="account.username"> · @{{ account.username }}</span></div>
                                            </td>
                                            <td class="py-2 pr-3 text-gray-600">{{ account.shop_name }}</td>
                                            <td class="py-2 pr-3 text-gray-600 whitespace-nowrap">{{ account.country_code }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">
                                                <span v-tooltip="account.followers_at ? ctrans('at') + ' ' + day(account.followers_at) : undefined">{{ account.followers === null ? '–' : short(account.followers) }}</span>
                                            </td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ count(account.posts) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(account.views) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums text-gray-600">{{ account.organic_views || account.ad_views ? short(account.organic_views) : '–' }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums text-gray-600">{{ account.organic_views || account.ad_views ? short(account.ad_views) : '–' }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(account.interactions) }}</td>
                                            <td class="py-2 pr-3 text-right tabular-nums">{{ short(account.link_clicks) }}</td>
                                            <td class="py-2 text-right"><span class="inline-block rounded-full bg-gray-100 px-2 py-0.5 text-xs tabular-nums text-gray-700">{{ pct(account.interaction_rate) }}</span></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-else class="text-sm text-gray-400 py-10 text-center">{{ ctrans('No accounts match these filters') }}</p>
                        </div>
                    </template>

                    <!-- Paid -->
                    <template v-else-if="currentTab === 'paid'">
                        <p class="text-xs text-gray-500 max-w-3xl">
                            {{ ctrans('Paid figures come from the nightly Meta Ads sync. ROAS · Aiku and CPA use the orders Aiku credits to the ads, so they move as invoices are raised; ROAS · Meta and Meta purchases are what Ads Manager reports.') }}
                            <span class="text-gray-400">{{ ctrans('All figures in') }} {{ currency }}.</span>
                        </p>

                        <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-3">
                            <div v-for="card in paidKpis" :key="card.key" class="rounded-xl ring-1 ring-gray-200 bg-white p-4">
                                <div class="text-xs text-gray-400 flex items-center gap-1">
                                    {{ card.label }}
                                    <FontAwesomeIcon v-if="card.help" v-tooltip="card.help" icon="fal fa-info-circle" class="text-gray-300 cursor-help" fixed-width />
                                </div>
                                <div class="mt-1 flex items-center justify-between gap-2 tabular-nums">
                                    <span class="text-lg text-gray-900">{{ card.value }}</span>
                                    <span v-if="previousText(card.key)" class="w-4 border-t-2 border-gray-500" />
                                </div>
                                <template v-if="previousText(card.key)">
                                    <div class="text-[11px] text-gray-400">{{ performance.period_label }}</div>
                                    <div class="mt-1 flex items-center justify-between gap-2 tabular-nums" data-test="social-kpi-previous">
                                        <span class="text-sm text-gray-600">{{ previousText(card.key) }}</span>
                                        <span class="w-4 border-t-2 border-dashed border-gray-400" />
                                    </div>
                                    <div class="text-[11px] text-gray-400">{{ comparedRangeLabel }}</div>
                                </template>
                                <div v-if="card.split && (card.split.ads || card.split.unsplit)" class="mt-2" data-test="social-views-split">
                                    <div class="flex h-1.5 overflow-hidden rounded-full bg-gray-100">
                                        <span v-for="part in (['organic', 'ads', 'unsplit'] as const)" :key="part" class="h-full" :style="{ width: splitShare(card.split, part) + '%', background: SOURCE_COLORS[part] }" />
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-gray-500 tabular-nums">
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.organic }" />{{ ctrans('Organic') }} {{ short(card.split.organic) }}</span>
                                        <span class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.ads }" />{{ ctrans('Ads') }} {{ short(card.split.ads) }}</span>
                                        <span v-if="card.split.unsplit" v-tooltip="ctrans('Views of days synced before Meta was asked where they came from. Sync again to split them.')" class="flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full" :style="{ background: SOURCE_COLORS.unsplit }" />{{ ctrans('Not split') }} {{ short(card.split.unsplit) }}</span>
                                    </div>
                                </div>
                                <div v-if="card.delta" class="mt-0.5 text-xs flex items-center gap-1 tabular-nums" :class="card.delta.direction === 'flat' ? 'text-gray-400' : card.delta.good ? 'text-green-700' : 'text-red-600'">
                                    <FontAwesomeIcon v-if="card.delta.direction !== 'flat'" :icon="card.delta.direction === 'up' ? 'fal fa-arrow-up' : 'fal fa-arrow-down'" fixed-width />
                                    <span>{{ card.delta.text }}</span>
                                    <span class="text-gray-400">{{ versusLabel }}</span>
                                </div>
                                <div v-else-if="card.sub" class="mt-0.5 text-xs text-gray-400 tabular-nums">{{ card.sub }}</div>
                            </div>
                        </div>

                        <div v-if="hasPaid" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                            <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <span class="text-sm font-medium text-gray-800">{{ ctrans('Channel split') }}</span>
                                <span class="ml-2 text-xs text-gray-400">{{ ctrans('spend') }}</span>
                                <div class="mt-4 space-y-4">
                                    <div v-for="channel in paidChannels" :key="channel.type">
                                        <div class="flex items-baseline justify-between text-sm">
                                            <span class="text-gray-800">{{ channel.label }}</span>
                                            <span class="tabular-nums text-gray-900">{{ money(channel.spend) }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-full bg-gray-700" :style="{ width: barWidth(channel.spend, maxChannelSpend) }" />
                                        </div>
                                        <div class="mt-1 flex flex-wrap justify-between gap-x-3 text-xs text-gray-400 tabular-nums">
                                            <span>{{ short(channel.impressions) }} {{ ctrans('impressions') }} · {{ short(channel.clicks) }} {{ ctrans('clicks') }} · {{ ctrans('CTR') }} {{ pct(channel.ctr, 2) }}</span>
                                            <span>{{ ctrans('ROAS') }} {{ ratio(channel.roas) }} · {{ ctrans('Meta') }} {{ ratio(channel.meta_roas) }}<template v-if="channel.meta_registrations"> · {{ count(channel.meta_registrations) }} {{ ctrans('registrations') }} · {{ money(channel.meta_cost_per_registration) }} {{ ctrans('each') }}</template></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="lg:col-span-2 rounded-xl ring-1 ring-gray-200 bg-white p-5">
                                <span class="text-sm font-medium text-gray-800">{{ ctrans('Paid campaign performance') }}</span>
                                <div v-if="campaigns.length" class="mt-3 max-h-[28rem] overflow-auto" data-test="social-campaigns-scroll">
                                    <table class="w-full text-sm">
                                        <thead class="sticky top-0 z-10 bg-white text-xs text-gray-400 whitespace-nowrap">
                                            <tr class="text-left">
                                                <th class="font-normal pb-2 pr-3">{{ ctrans('Campaign') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Spend') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('Clicks') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('CTR') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Orders Aiku credits to the campaign')">{{ ctrans('Orders · Aiku') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right">{{ ctrans('CPA') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Revenue of the orders Aiku credits, divided by spend')">{{ ctrans('ROAS · Aiku') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Purchases Meta reports, as in Ads Manager')">{{ ctrans('Meta purchases') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Purchase conversion value Meta reports, as in Ads Manager')">{{ ctrans('Purchase value · Meta') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Spend divided by the purchases Meta reports, as in Ads Manager')">{{ ctrans('Cost per purchase') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Purchase value Meta reports, divided by spend, as in Ads Manager')">{{ ctrans('Meta ROAS') }}</th>
                                                <th class="font-normal pb-2 pr-3 text-right" v-tooltip="ctrans('Registrations completed, as in Ads Manager')">{{ ctrans('Registrations · Meta') }}</th>
                                                <th class="font-normal pb-2 text-right" v-tooltip="ctrans('Spend divided by the registrations Meta reports, as in Ads Manager')">{{ ctrans('Cost per registration') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr v-for="campaign in campaigns" :key="campaign.channel + campaign.name" class="hover:bg-gray-50">
                                                <td class="py-2 pr-3 max-w-xs">
                                                    <div class="truncate text-gray-800">{{ campaign.name }}</div>
                                                    <div class="text-xs text-gray-400">
                                                        <template v-if="campaign.placements.length > 1">
                                                            <span v-for="(placement, index) in campaign.placements" :key="placement.channel">{{ index ? ' + ' : '' }}{{ placement.channel }} {{ money(placement.spend) }}</span>
                                                        </template>
                                                        <template v-else>{{ campaign.channel }}</template>
                                                        <span v-if="campaign.owner"> · {{ campaign.owner }}</span>
                                                    </div>
                                                </td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ money(campaign.spend) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ short(campaign.clicks) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ pct(campaign.ctr, 2) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ count(campaign.orders) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ money(campaign.cpa) }}</td>
                                                <td class="py-2 pr-3 text-right"><span class="inline-block rounded-full px-2 py-0.5 text-xs tabular-nums" :class="roasClass(campaign.roas)">{{ ratio(campaign.roas) }}</span></td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ count(campaign.meta_purchases) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ money(campaign.meta_value) }}</td>
                                                <td class="py-2 pr-3 text-right tabular-nums">{{ money(campaign.meta_purchases ? campaign.spend / campaign.meta_purchases : null) }}</td>
                                                <td class="py-2 pr-3 text-right"><span class="inline-block rounded-full px-2 py-0.5 text-xs tabular-nums" :class="roasClass(campaign.meta_roas)">{{ ratio(campaign.meta_roas) }}</span></td>
                                                <td class="py-2 pr-3 text-right tabular-nums" data-test="social-campaign-registrations">{{ campaign.meta_registrations ? count(campaign.meta_registrations) : '–' }}</td>
                                                <td class="py-2 text-right tabular-nums">{{ money(campaign.meta_cost_per_registration) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p v-else class="text-sm text-gray-400 py-10 text-center">{{ ctrans('No campaigns in this period') }}</p>
                            </div>
                        </div>
                        <div v-else class="rounded-xl ring-1 ring-gray-200 bg-white p-6 text-sm text-gray-500 text-center">
                            {{ ctrans('No paid spend in this period. Spend appears here the morning after the Meta Ads sync has run for a connected ad account.') }}
                        </div>
                    </template>

                    <!-- Uploads -->
                    <template v-else-if="currentTab === 'uploads'">
                        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="text-sm text-gray-600 max-w-3xl">
                                <div class="font-medium text-gray-800">{{ ctrans('Import platform exports') }}</div>
                                <p class="mt-1 text-xs text-gray-500">{{ uploadInformation }}</p>
                            </div>
                            <Button v-if="uploadShopOptions.length" icon="fal fa-upload" :label="ctrans('Import export')" type="tertiary" class="shrink-0" @click="startUpload" />
                        </div>
                        <Deferred data="uploads">
                            <template #fallback>
                                <div class="h-40 animate-pulse rounded-xl bg-gray-100 ring-1 ring-gray-200" />
                            </template>
                            <UploadReports :data="uploads" tab="uploads" />
                        </Deferred>
                    </template>
                </div>
            </div>
        </Deferred>
    </div>

    <Dialog v-model:visible="isSyncOpen" modal :header="ctrans('Sync from Meta')" :style="{ width: '46rem' }" :breakpoints="{ '768px': '95vw' }">
        <p class="text-xs text-gray-500">{{ ctrans('Every brand has its own Facebook Page, Instagram account and Meta Ads account, so each is synced on its own, one brand at a time. A brand\'s first sync fetches the last :max days; after that the recent days, or the range chosen here. Figures also sync by themselves every night, and a sync stops by itself before Meta\'s usage limit.', { max: 90 }) }}</p>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-gray-600">
            <span>{{ ctrans('Range') }}</span>
            <Select v-model="syncDays" :options="syncRangeOptions" optionLabel="label" optionValue="value" :placeholder="syncRangeOptions[0].label" class="w-72" :pt="selectPt" data-test="social-sync-range" />
        </div>
        <ul class="mt-3 divide-y divide-gray-100" data-test="social-sync-list">
            <li v-for="shop in syncShops" :key="shop.id" class="py-3 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-800">{{ shop.name }}</div>
                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        <span :class="shop.organic ? 'text-gray-600' : 'text-amber-700'">
                            <FontAwesomeIcon :icon="shop.organic ? 'fal fa-check' : 'fal fa-exclamation-triangle'" :class="shop.organic ? 'text-green-600' : ''" fixed-width />
                            {{ ctrans('Organic') }}:
                            <template v-if="shop.organic">{{ ctrans('updated') }} {{ time(shop.organic_updated_at) }}</template>
                            <template v-else>{{ ctrans('no Facebook Page or Instagram account set') }}</template>
                        </span>
                        <span :class="shop.paid ? 'text-gray-600' : 'text-amber-700'">
                            <FontAwesomeIcon :icon="shop.paid ? 'fal fa-check' : 'fal fa-exclamation-triangle'" :class="shop.paid ? 'text-green-600' : ''" fixed-width />
                            {{ ctrans('Paid') }}:
                            <template v-if="shop.paid">{{ ctrans('updated') }} {{ time(shop.paid_updated_at) }}</template>
                            <template v-else>{{ ctrans('no Meta Ads account set') }}</template>
                        </span>
                    </div>
                    <div v-if="shop.first_sync && !shop.running" class="mt-1 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-history" fixed-width /> {{ ctrans('First sync: fetches the last :days days', { days: syncDays ?? 90 }) }}</div>
                    <div v-if="shop.running" class="mt-1 text-xs text-gray-500"><FontAwesomeIcon icon="fal fa-spinner-third" spin fixed-width /> {{ ctrans('Syncing… the figures refresh when it is done.') }}</div>
                    <div v-else-if="shop.last_sync" class="mt-1 text-xs" :class="shop.last_sync.ok ? 'text-gray-500' : 'text-red-600'">
                        <FontAwesomeIcon :icon="shop.last_sync.ok ? 'fal fa-check' : 'fal fa-exclamation-triangle'" fixed-width />
                        {{ ctrans('Last sync') }} {{ time(shop.last_sync.finished_at) }}<template v-if="!shop.last_sync.ok">: {{ shop.last_sync.message }}</template>
                    </div>
                    <div v-if="syncErrors[shop.id]" class="mt-1 text-xs text-red-600">{{ syncErrors[shop.id] }}</div>
                </div>
                <div class="shrink-0">
                    <Button v-if="isSetUp(shop)" :label="syncLabel(shop)" type="tertiary" size="s" :disabled="shop.running || isCoolingDown(shop)" v-tooltip="isCoolingDown(shop) ? ctrans('A brand can sync again :minutes minutes after its last sync, so Meta\'s rate limit is not spent twice', { minutes: 10 }) : undefined" :data-test="'social-sync-' + shop.slug" @click="syncShop(shop)">
                        <template #icon><FontAwesomeIcon icon="fal fa-sync-alt" :spin="shop.running" class="text-[--app-accent]" fixed-width /></template>
                    </Button>
                    <span v-else-if="!shop.can_edit" class="text-xs text-gray-500" data-test="social-sync-ask-admin">{{ ctrans('Ask an admin of this brand to set it up') }}</span>
                    <a v-else :href="route(shop.edit_route.name, shop.edit_route.parameters)" class="inline-flex">
                        <Button :label="ctrans('Set up in Edit shop')" type="tertiary" size="s">
                            <template #icon><FontAwesomeIcon icon="fal fa-cog" class="text-gray-500" fixed-width /></template>
                        </Button>
                    </a>
                </div>
            </li>
        </ul>
        <div v-if="syncShops.filter(isSetUp).length > 1" class="mt-3 flex justify-end">
            <Button :label="ctrans('Sync all set-up brands')" type="tertiary" :disabled="anyRunning" @click="syncAll">
                <template #icon><FontAwesomeIcon icon="fal fa-sync-alt" class="text-[--app-accent]" fixed-width /></template>
            </Button>
        </div>
    </Dialog>

    <Dialog v-model:visible="isShopPickerOpen" modal :header="ctrans('Which brand is this export for?')" :style="{ width: '28rem' }">
        <template v-if="uploadShopOptions.length > 1">
            <p class="text-xs text-gray-500">{{ ctrans('Posts and daily figures are stored against the brand they belong to, so the dashboard can compare brands and markets.') }}</p>
            <Select v-model="uploadShop" :options="uploadShopOptions" optionLabel="name" :placeholder="ctrans('Choose a brand')" class="mt-3 w-full" data-test="social-upload-shop" />
        </template>
        <p v-else class="text-sm text-gray-700">{{ uploadShop?.name }}</p>
        <div class="mt-4">
            <p class="text-xs font-medium text-gray-600">{{ ctrans('Platform') }}</p>
            <SegmentedToggle v-model="uploadPlatform" :options="uploadPlatformOptions" :ariaLabel="ctrans('Platform')" class="mt-1" data-test="social-upload-platform" />
            <p class="mt-1 text-xs text-gray-500">{{ ctrans('Leave on Detect for content exports. Choose the platform for a single chart export, such as Views or Content interactions, whose title does not name it.') }}</p>
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <Button :label="ctrans('Cancel')" icon="fal fa-times" type="tertiary" @click="isShopPickerOpen = false">
                <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width /></template>
            </Button>
            <Button :label="ctrans('Continue')" icon="fal fa-upload" type="tertiary" :disabled="!uploadShop" @click="continueUpload" />
        </div>
    </Dialog>

    <UploadExcel
        v-if="uploadSpreadsheet"
        v-model="isUploadOpen"
        scope="SocialMediaPost"
        :title="{ label: ctrans('Import social media export') + (uploadShop ? ' · ' + uploadShop.name : ''), information: uploadInformation }"
        :progressDescription="ctrans('Importing social media insights')"
        :upload_spreadsheet="uploadSpreadsheet"
        :propsRefreshAfterFinish="['performance', 'uploads']"
    />
  </div>
</template>
