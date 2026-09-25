<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head } from "@inertiajs/vue3"
import TrafficSourceAudienceMix from "@/Components/DataDisplay/Dashboard/Widget/TrafficSourceAudienceMix.vue"
import { computed, ref, watch } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import { faPause, faPaperPlane } from "@fal"
import DateIntervalTabs from "@/Components/Navigation/DateIntervalTabs.vue"
import GoogleAdsCampaignTrend from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsCampaignTrend.vue"
import GoogleAdsCampaignControls from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsCampaignControls.vue"
import GoogleAdsElementToggle from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsElementToggle.vue"
import GoogleAdsDuplicateAd from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsDuplicateAd.vue"
import GoogleAdsNegativeKeywords from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsNegativeKeywords.vue"
import GoogleAdsSearchTerms from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsSearchTerms.vue"
import GoogleAdsAddKeyword from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsAddKeyword.vue"
import ConfirmDialog from "primevue/confirmdialog"
import GoogleAdsMetric from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsMetric.vue"
import Timeline from "@/Components/Utils/Timeline.vue"
import type { Timeline as TimelineStep } from "@/types/Timeline"
import GoogleAdsAdCreative from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsAdCreative.vue"
import GoogleAdsDailyTable from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsDailyTable.vue"
import GoogleAdsKeywordsTable from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsKeywordsTable.vue"
import GoogleAdsPager from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsPager.vue"
import GoogleAdsTargeting from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsTargeting.vue"
import GoogleAdsAssetGroups from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsAssetGroups.vue"
import HelpTip from "@/Components/Utils/HelpTip.vue"
import { criterionLabel, criterionTypeLabel, type Criterion } from "@/Composables/googleAdsCriteria"
import { useLocalPagination } from "@/Composables/useLocalPagination"
import { capitalize } from "@/Composables/capitalize"
import { campaignTypeLabel } from "@/Composables/googleAdsCampaignType"
import { impressionShareLabel } from "@/Composables/googleAdsFormat"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { ctrans } from "@/Composables/useTrans"

library.add(faGoogle, faPause, faPaperPlane)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    campaign: {
        reference: string
        name: string
        status: string | null
        primary_status: string | null
        primary_status_reasons: string[]
        channel_type: string | null
        bidding_strategy_type: string | null
        budget_amount: number | null
        budget_is_shared: boolean
        start_date: string | null
        end_date: string | null
        currency: string
        shop_currency: string
        fetched_at: string | null
    }
    update_route: { name: string; parameters: Record<string, unknown> }
    element_route: { name: string; parameters: Record<string, unknown> }
    ad_route: { name: string; parameters: Record<string, unknown> }
    negative_keywords_route: { name: string; parameters: Record<string, unknown> }
    keyword_route: { name: string; parameters: Record<string, unknown> }
    periods: Record<string, string>
    period: string
    period_label: string
    state: {
        current: string
        timeline: TimelineStep[]
    }
    custom_range: { from: string; to: string } | null
    compare: boolean
    comparison_label: string | null
    google_previous: Record<string, number | null> | null
    google: {
        days: number
        impressions: number
        clicks: number
        conversions: number
        cost: number
        conversions_value: number
        ctr: number | null
        avg_cpc: number | null
        cost_per_conversion: number | null
        roas: number | null
        all_conversions: number
        all_conversions_value: number
        has_breakdown: boolean
        purchases: number | null
        cost_per_purchase: number | null
        purchase_rate: number | null
        registrations: number | null
        cost_per_registration: number | null
        registration_rate: number | null
    }
    impression_share: Record<string, number | null> | null
    conversions_by_action: {
        category: string
        action_name: string
        conversions: number
        all_conversions: number
        conversions_value: number
        all_conversions_value: number
    }[]
    daily: {
        date: string
        impressions: number
        clicks: number
        conversions: number
        cost: number
        conversions_value: number
        shop_cost: number
    }[]
    attribution: {
        customers: number
        purchases: number
        revenue: number
        cost: number
        roas: number | null
    }
    ad_groups: {
        id: string
        name: string | null
        status: string | null
        type?: string | null
        ads: {
            id: string
            type: string | null
            name?: string | null
            status: string | null
            final_urls: string[]
            headlines: string[]
            long_headlines?: string[]
            descriptions: string[]
            business_name?: string | null
            call_to_action?: string | null
            images?: { url: string | null; name: string | null; width: number | null; height: number | null }[]
            logos?: { url: string | null; name: string | null }[]
            videos?: { video_id: string | null; video_title: string | null; name: string | null }[]
            carousel_cards?: { headline: string | null; description: string | null; image: { url: string | null } | null }[]
            strength: string | null
            approval_status: string | null
        }[]
        keywords: {
            id?: string
            text: string | null
            match_type: string | null
            status: string | null
            quality_score?: number | null
            metrics?: {
                impressions: number
                clicks: number
                cost: number
                avg_cpc: number | null
                conversions: number
                conversions_value: number
            } | null
        }[]
        negative_keywords?: { id: string | null; text: string | null; match_type: string | null }[]
        targeting?: Criterion[]
    }[]
    asset_groups: any[]
    exclusions: Criterion[]
    structure_window: { from: string; to: string } | null
    negative_keywords: { id: string; text: string; match_type: string }[]
    search_terms?: { terms: any[]; error: string | null }
    audience?: {
        buckets: {
            key: string
            label: string
            description: string
            colour: string
            count: number
            share: number
            is_acquisition: boolean
        }[]
        total: number
        identified: number
        acquisition: number
        window_days: number
        measured_from: string | null
    }
}>()

const locale = useLocaleStore()

const money = (value: number, currency?: string) => locale.currencyFormat(currency ?? props.campaign.currency, value)

/* Share weighted: a customer credited to three campaigns counts a third here, so whole numbers read
   as counts and only a genuine fraction keeps its decimals. */
const share = (value: number) => (Number.isInteger(value) ? locale.number(value) : value.toFixed(1))

const roasClass = (roas: number | null) =>
    roas === null ? "text-gray-400" : roas >= 1 ? "text-[#006300]" : "text-[#d03b3b]"

const enumLabel = (value: string | null) => (value ? value.replace(/_/g, " ").toLowerCase() : null)

const percent = (value: number | null, decimals = 2) => (value === null ? "—" : value.toFixed(decimals) + "%")

const moneyOrDash = (value: number | null) => (value === null ? "—" : money(value))

const isSearch = computed(() => props.campaign.channel_type === "SEARCH")

/* Keyword and asset group figures are read with the nightly fetch for its own window, not for the
   period chosen at the top of the page, and the label says so beside them. */
const structureWindowLabel = computed(() => {
    if (!props.structure_window) return null

    return (
        ctrans("Figures for") +
        " " +
        useFormatTime(props.structure_window.from, { formatTime: "mdy" }) +
        " " +
        ctrans("to") +
        " " +
        useFormatTime(props.structure_window.to, { formatTime: "mdy" })
    )
})

const hasTargeting = computed(() => props.ad_groups.some((group) => (group.targeting ?? []).length > 0))

const groupsWithNegatives = computed(() => props.ad_groups.filter((group) => (group.negative_keywords ?? []).length > 0))

/* A Search campaign here arrives with sixty ad groups and six hundred headlines between them. Only
   the groups that actually hold an ad are worth a card; the rest exist to carry keywords and are
   already listed in the keyword table. */
const adGroupFilter = ref("")

const adGroupsWithAds = computed(() => props.ad_groups.filter((group) => group.ads.length > 0))

const filteredAdGroups = computed(() => {
    const needle = adGroupFilter.value.trim().toLowerCase()

    if (!needle) return adGroupsWithAds.value

    return adGroupsWithAds.value.filter((group) => (group.name ?? String(group.id)).toLowerCase().includes(needle))
})

const adGroupPager = useLocalPagination(filteredAdGroups, [5, 10, 25])
const pagedAdGroups = adGroupPager.paged

watch(adGroupFilter, adGroupPager.toFirstPage)

const exclusionsByType = computed(() => {
    const byType = new Map<string, Criterion[]>()

    props.exclusions.forEach((exclusion) => {
        const type = exclusion.type ?? "OTHER"

        if (!byType.has(type)) byType.set(type, [])
        byType.get(type)?.push(exclusion)
    })

    return Array.from(byType.entries())
})

/* Undefined rather than null when not comparing, which is how the metric tile tells "no comparison
   asked for" from "compared, and the period before had no figure". */
const previous = (key: string): number | null | undefined =>
    props.google_previous ? (props.google_previous[key] ?? null) : undefined

/* Three rows for the three places an ad can appear, each with what was received and the two reasons
   Google gives for the rest: outbid on Ad Rank, or the budget was already spent. */
const impressionShareRows = computed(() => {
    const share = props.impression_share

    if (!share) return []

    return [
        {
            label: ctrans("Anywhere on the results page"),
            received: share.search_impression_share,
            lostRank: share.search_rank_lost_impression_share,
            lostBudget: share.search_budget_lost_impression_share,
        },
        {
            label: ctrans("Above the organic results"),
            received: share.search_top_impression_share,
            lostRank: share.search_rank_lost_top_impression_share,
            lostBudget: share.search_budget_lost_top_impression_share,
        },
        {
            label: ctrans("In the first position"),
            received: share.search_absolute_top_impression_share,
            lostRank: share.search_rank_lost_absolute_top_impression_share,
            lostBudget: share.search_budget_lost_absolute_top_impression_share,
        },
    ]
})

/* Google's own rating, shown in Google's own words. Only Poor is coloured as a problem: Average is a
   fair description of a working ad, and colouring it red would send people rewriting ads that earn. */
const strengthClass = (strength: string | null) => {
    if (strength === "POOR") return "bg-[#fdeaea] text-[#d03b3b]"
    if (strength === "EXCELLENT" || strength === "GOOD") return "bg-[#eaf5ea] text-[#006300]"
    if (strength === "AVERAGE") return "bg-amber-50 text-[#a15c00]"
    return "bg-gray-100 text-gray-500"
}

const adsNeedingVariants = computed(
    () => props.ad_groups.filter((group) => group.ads.filter((ad) => ad.status === "ENABLED").length === 1).length
)

const keywordCount = computed(() =>
    props.ad_groups.reduce((total, group) => total + group.keywords.length, 0)
)

const adCount = computed(() => props.ad_groups.reduce((total, group) => total + group.ads.length, 0))

/* "ENABLED but LIMITED" is the state that quietly wastes money, so the reasons Google gives are
   shown rather than folded away behind the single word. */
const notServingReasons = computed(() =>
    (props.campaign.primary_status_reasons ?? []).map((reason) => enumLabel(reason))
)
</script>

<template>
    <Head :title="capitalize(title)" />
    <ConfirmDialog />
    <PageHeading :data="pageHead" />

    <div class="border-b border-gray-200 px-4 pb-2">
        <Timeline :options="state.timeline" :state="state.current" :slidesPerView="3" />
    </div>

    <div class="px-4 py-4">
        <DateIntervalTabs
            :options="periods"
            :selected="period"
            :label="ctrans('Period')"
            :custom-range="custom_range"
            :compare="compare"
            :comparison-label="comparison_label"
            class="w-fit" />
    </div>

    <div class="grid grid-cols-1 gap-4 px-4 pb-6 lg:grid-cols-3">
        <!-- Identity: the settings that decide what the figures below were ever going to look like. -->
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Campaign") }}
                <HelpTip :text="ctrans('How Google is set to run this campaign: whether it is serving and why not if it is not, the campaign type, the bidding strategy, the daily budget and the start date. Pausing, resuming and budget changes below are written to Google straight away.')" />
            </h2>
            <p class="mt-1 text-xs text-gray-500">{{ campaign.reference }}</p>

            <dl class="mt-5 space-y-3 text-xs">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Status") }}</dt>
                    <dd class="text-right">
                        <span :class="campaign.primary_status === 'ELIGIBLE' ? 'text-[#006300]' : 'text-gray-700'">
                            {{ capitalize(enumLabel(campaign.primary_status ?? campaign.status) ?? "—") }}
                        </span>
                        <span v-if="notServingReasons.length" class="mt-0.5 block text-[#a15c00]">
                            {{ notServingReasons.join(", ") }}
                        </span>
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Campaign type") }}</dt>
                    <dd class="text-gray-700">{{ campaignTypeLabel(campaign.channel_type) ?? "—" }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Bidding") }}</dt>
                    <dd class="capitalize text-gray-700">{{ enumLabel(campaign.bidding_strategy_type) ?? "—" }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Daily budget") }}</dt>
                    <dd class="tabular-nums text-gray-700">
                        {{ campaign.budget_amount !== null ? money(campaign.budget_amount) : "—" }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Running since") }}</dt>
                    <dd class="text-gray-700">
                        {{ campaign.start_date ? useFormatTime(campaign.start_date, { formatTime: "mdy" }) : "—" }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ ctrans("Read from Google") }}</dt>
                    <dd class="text-gray-700">
                        {{ campaign.fetched_at ? useFormatTime(campaign.fetched_at, { formatTime: "short-datetime" }) : "—" }}
                    </dd>
                </div>
            </dl>

            <GoogleAdsCampaignControls
                :status="campaign.status"
                :budget-amount="campaign.budget_amount"
                :budget-is-shared="campaign.budget_is_shared"
                :currency="campaign.currency"
                :update-route="update_route" />
        </section>

        <!-- Google's own numbers. Everything here is in the ad account's currency, under Google's
             attribution, and is deliberately not mixed with the Aiku block below. -->
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-gray-800">
                    {{ ctrans("Reported by Google") }}
                    <span class="font-normal text-gray-500">· {{ period_label }}</span>
                    <HelpTip :text="ctrans('Google\'s own totals for the period chosen above, in the ad account\'s currency and under Google\'s attribution. Conversions are whatever the account\'s conversion actions recorded. ROAS is conversion value divided by cost.')" />
                </h2>
                <span class="text-xs text-gray-500">
                    {{ ctrans("Google's attribution, in") }} {{ campaign.currency }}
                </span>
            </div>

            <div v-if="google.days" class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Impressions") }}</div>
                    <GoogleAdsMetric :value="google.impressions" kind="count" size="lg" :previous="previous('impressions')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Clicks") }}</div>
                    <GoogleAdsMetric :value="google.clicks" kind="count" size="lg" :previous="previous('clicks')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("CTR") }}</div>
                    <GoogleAdsMetric :value="google.ctr" kind="percent" size="lg" :previous="previous('ctr')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Avg. CPC") }}</div>
                    <GoogleAdsMetric :value="google.avg_cpc" kind="money" :currency="campaign.currency" size="lg" better="down" :previous="previous('avg_cpc')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Cost") }}</div>
                    <GoogleAdsMetric :value="google.cost" kind="money" :currency="campaign.currency" size="lg" better="none" :previous="previous('cost')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Conversions") }}</div>
                    <GoogleAdsMetric :value="google.conversions" kind="count" size="lg" :previous="previous('conversions')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Cost per conversion") }}</div>
                    <GoogleAdsMetric :value="google.cost_per_conversion" kind="money" :currency="campaign.currency" size="lg" better="down" :previous="previous('cost_per_conversion')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("ROAS") }}</div>
                    <GoogleAdsMetric :value="google.roas" kind="roas" size="lg" :previous="previous('roas')" />
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("All conversions") }}</div>
                    <GoogleAdsMetric :value="google.all_conversions" kind="count" size="lg" :previous="previous('all_conversions')" />
                    <div class="text-xs text-gray-500">{{ ctrans("worth") }} {{ money(google.all_conversions_value) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Purchases") }}</div>
                    <GoogleAdsMetric :value="google.purchases" kind="count" size="lg" :previous="previous('purchases')" />
                    <div v-if="google.purchases !== null" class="text-xs text-gray-500">
                        {{ moneyOrDash(google.cost_per_purchase) }} {{ ctrans("each") }} · {{ percent(google.purchase_rate, 2) }} {{ ctrans("of clicks") }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Registrations") }}</div>
                    <GoogleAdsMetric :value="google.registrations" kind="count" size="lg" :previous="previous('registrations')" />
                    <div v-if="google.registrations !== null" class="text-xs text-gray-500">
                        {{ moneyOrDash(google.cost_per_registration) }} {{ ctrans("each") }} · {{ percent(google.registration_rate, 2) }} {{ ctrans("of clicks") }}
                    </div>
                </div>
            </div>

            <p v-else class="mt-5 text-xs text-gray-500">
                {{ ctrans("Google reported nothing for this campaign in this period. Try a longer one, or check that the nightly fetch has run.") }}
            </p>

            <div v-if="google.roas === 0 && google.cost > 0" class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                {{ ctrans("This campaign spent money but Google recorded no conversion value, which usually means the conversion actions in this account have no value attached.") }}
            </div>

            <div v-if="google.days" class="mt-5">
                <h3 class="text-xs font-medium text-gray-700">
                    {{ ctrans("By conversion action") }}
                    <HelpTip :text="ctrans('Which conversion actions in the account recorded these conversions, under the names Google uses. Conversions counts primary actions only; All conv. counts secondary ones too, such as a GA4 import of the same sales. Purchases and registrations above are the primary actions in the Purchase and Sign-up categories, so a sale recorded twice is counted once.')" />
                </h3>

                <div v-if="conversions_by_action.length" class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[32rem] text-xs">
                        <thead>
                            <tr class="border-b border-gray-100 text-gray-500">
                                <th scope="col" class="py-1.5 pr-2 text-left font-normal">{{ ctrans("Action") }}</th>
                                <th scope="col" class="px-2 py-1.5 text-left font-normal">{{ ctrans("Category") }}</th>
                                <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ ctrans("Conversions") }}</th>
                                <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ ctrans("All conv.") }}</th>
                                <th scope="col" class="py-1.5 pl-2 text-right font-normal">{{ ctrans("Value") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="action in conversions_by_action"
                                :key="action.category + action.action_name"
                                class="border-b border-gray-50 text-gray-600">
                                <td class="py-2 pr-2 text-gray-700">{{ action.action_name }}</td>
                                <td class="px-2 capitalize">{{ enumLabel(action.category) }}</td>
                                <td class="px-2 text-right tabular-nums">{{ locale.number(action.conversions) }}</td>
                                <td class="px-2 text-right tabular-nums">{{ locale.number(action.all_conversions) }}</td>
                                <td class="pl-2 text-right tabular-nums">{{ money(action.all_conversions_value) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="mt-2 text-xs text-gray-500">
                    <template v-if="google.has_breakdown">{{ ctrans("No conversion action recorded anything in this period.") }}</template>
                    <template v-else>{{ ctrans("The split by conversion action has not been read for these days yet. The nightly fetch records it from now on; to fill earlier days, run the Google Ads fetch with more days.") }}</template>
                </p>
            </div>
        </section>

        <section v-if="impression_share" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-gray-800">
                    {{ ctrans("Share of Google Search impressions") }}
                    <span class="font-normal text-gray-500">· {{ period_label }}</span>
                    <HelpTip :text="ctrans('How often the ads were shown out of the times Google judged them eligible to show on Google Search, and why the rest were missed: Ad Rank is bid times quality, budget is the daily cap. Google reports anything under 10% as 9.99% and anything over 90% as 90.01%, so those read as under 10% and over 90% here.')" />
                </h2>
                <span class="text-xs text-gray-500">{{ ctrans("Weighted by eligible impressions, not averaged by day") }}</span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full max-w-2xl min-w-[28rem] text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th scope="col" class="py-1.5 pr-2 text-left font-normal">{{ ctrans("Where") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ ctrans("Received") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ ctrans("Lost to Ad Rank") }}</th>
                            <th scope="col" class="py-1.5 pl-2 text-right font-normal">{{ ctrans("Lost to budget") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in impressionShareRows" :key="row.label" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2 text-gray-700">{{ row.label }}</td>
                            <td class="px-2 text-right tabular-nums text-gray-900">{{ impressionShareLabel(row.received) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ impressionShareLabel(row.lostRank) }}</td>
                            <td class="pl-2 text-right tabular-nums">{{ impressionShareLabel(row.lostBudget) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Aiku's own attribution, on purpose in its own block and on its own time base. -->
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-gray-800">
                    {{ ctrans("Attributed by Aiku") }}
                    <HelpTip :text="ctrans('Customers and orders Aiku traced back to a click on this campaign, with their revenue in the shop\'s currency. Counted since attribution started recording, not for the period above, so it will not match Google\'s figures.')" />
                </h2>
                <span class="text-xs text-gray-500">
                    {{ ctrans("Since attribution started recording, not the period above, in") }} {{ campaign.shop_currency }}
                </span>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Customers") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ share(attribution.customers) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Orders") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ share(attribution.purchases) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Revenue") }}</div>
                    <div class="text-lg tabular-nums text-[#006300]">
                        {{ money(attribution.revenue, campaign.shop_currency) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("Spend") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">
                        {{ money(attribution.cost, campaign.shop_currency) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ ctrans("ROAS") }}</div>
                    <div class="text-lg tabular-nums" :class="roasClass(attribution.roas)">
                        {{ attribution.roas !== null ? attribution.roas.toFixed(2) + "×" : "—" }}
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Is the spend still buying clicks?") }}
                <span class="font-normal text-gray-500">· {{ period_label }}</span>
                <HelpTip :text="ctrans('Daily cost and daily clicks on one chart, each with its own axis. When the cost line climbs while the clicks line flattens, the campaign is paying more for the same traffic.')" />
            </h2>
            <div class="mt-4">
                <GoogleAdsCampaignTrend :daily="daily" :currency="campaign.currency" />
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Day by day") }}
                <span v-if="daily.length" class="font-normal text-gray-500">· {{ daily.length }}</span>
                <HelpTip :text="ctrans('One row per day Google reported, newest first until you sort by another column. Cost is what Google billed in the account\'s currency. Spend is the same money converted to the shop\'s currency at that day\'s rate, which is the figure the marketing dashboard uses.')" />
            </h2>

            <GoogleAdsDailyTable
                v-if="daily.length"
                :daily="daily"
                :currency="campaign.currency"
                :shop-currency="campaign.shop_currency" />

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ ctrans("No days recorded in this period. Try a longer one, or check that the nightly fetch has run.") }}
            </p>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Ads") }}
                <span v-if="adCount" class="font-normal text-gray-500">· {{ adCount }}</span>
                <HelpTip :text="ctrans('Ad groups and their ads as Google returned them, with Google\'s strength rating and review status for each ad. Pause or resume an ad group or an ad, add headlines, or copy an ad into a variant to test against it.')" />
            </h2>

            <!-- Says plainly why there is no button to write an ad here, because a section that offers
                 pausing and copying but not creating otherwise reads as unfinished rather than decided. -->
            <p class="mt-1 max-w-3xl text-xs text-gray-600">
                {{ ctrans("You can pause an ad here, add headlines to it, and copy it into a variant to test. Writing a brand new ad from scratch is done in Google Ads, because that is where you get its strength scored as you type, headlines pinned to positions, and a preview of how it looks before it runs. Aiku would give you text boxes and no feedback.") }}
                <span v-if="adsNeedingVariants" class="mt-1 block text-[#a15c00]">
                    {{ ctrans(":count ad group(s) here run a single ad, so Google has nothing to rotate it against.", { count: String(adsNeedingVariants) }) }}
                </span>
            </p>

            <div v-if="adCount && adGroupsWithAds.length > 1" class="mt-3">
                <label for="gads-ad-group-filter" class="sr-only">{{ ctrans("Search ad groups") }}</label>
                <input
                    id="gads-ad-group-filter"
                    v-model="adGroupFilter"
                    type="search"
                    :placeholder="ctrans('Search ad groups')"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-72" />
            </div>

            <div v-if="adCount" class="mt-3 space-y-4">
                <div v-for="group in pagedAdGroups" :key="group.id" class="rounded-lg p-3 ring-1 ring-gray-100">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-medium text-gray-700">
                            {{ group.name ?? group.id }}
                            <span class="font-normal capitalize text-gray-500">{{ enumLabel(group.status) }}</span>
                            <span v-if="group.type && !isSearch" class="font-normal capitalize text-gray-500">· {{ enumLabel(group.type) }}</span>
                        </div>
                        <GoogleAdsElementToggle
                            type="ad_group"
                            :ad-group-id="String(group.id)"
                            :status="group.status"
                            :label="ctrans('ad group')"
                            :update-route="element_route" />
                    </div>
                    <div
                        v-for="ad in group.ads"
                        :key="ad.id"
                        class="mt-2 border-l border-gray-100 pl-3 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="capitalize text-gray-500">{{ enumLabel(ad.type) }} · {{ enumLabel(ad.status) }}</span>
                                <span class="rounded px-1.5 py-0.5 text-[11px] capitalize" :class="strengthClass(ad.strength)">
                                    {{ ad.strength ? enumLabel(ad.strength) : ctrans("not rated yet") }}
                                </span>
                                <span
                                    v-if="ad.approval_status && ad.approval_status !== 'APPROVED'"
                                    class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] capitalize text-[#a15c00]">
                                    {{ enumLabel(ad.approval_status) }}
                                </span>
                            </span>
                            <GoogleAdsElementToggle
                                type="ad"
                                :ad-group-id="String(group.id)"
                                :element-id="String(ad.id)"
                                :status="ad.status"
                                :label="ctrans('ad')"
                                :update-route="element_route" />
                        </div>
                        <p v-if="ad.name" class="mt-1 text-gray-500">{{ ad.name }}</p>

                        <GoogleAdsAdCreative :ad="ad" />

                        <GoogleAdsDuplicateAd
                            v-if="ad.status === 'ENABLED' && ad.type === 'RESPONSIVE_SEARCH_AD'"
                            class="mt-2"
                            :ad-group-id="String(group.id)"
                            :ad="ad"
                            :store-route="ad_route" />
                    </div>

                    <GoogleAdsAddKeyword
                        v-if="isSearch"
                        :ad-group-id="String(group.id)"
                        :ad-group-name="group.name"
                        :update-route="keyword_route" />
                </div>

                <p v-if="!pagedAdGroups.length" class="py-4 text-center text-xs text-gray-500">
                    {{ ctrans("No ad group matches that search.") }}
                </p>

                <GoogleAdsPager
                    v-if="adGroupPager.isPaged.value"
                    :first-row="adGroupPager.firstRow.value"
                    :last-row="adGroupPager.lastRow.value"
                    :total="adGroupPager.total.value"
                    :page="adGroupPager.page.value"
                    :page-count="adGroupPager.pageCount.value"
                    :per-page="adGroupPager.perPage.value"
                    :per-page-options="adGroupPager.perPageOptions"
                    :unit="ctrans('ad groups')"
                    @update:page="adGroupPager.page.value = $event"
                    @update:per-page="((adGroupPager.perPage.value = $event), adGroupPager.toFirstPage())" />
            </div>

            <p v-else-if="asset_groups.length" class="mt-3 text-xs text-gray-500">
                {{ ctrans("Performance Max campaigns have no ad groups or ads of their own. Google assembles the ads from the asset groups shown further down.") }}
            </p>

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ ctrans("No ads read for this campaign yet. They appear after the nightly fetch has run.") }}
            </p>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Keywords") }}
                <span v-if="keywordCount" class="font-normal text-gray-500">· {{ keywordCount }}</span>
                <HelpTip :text="ctrans('The searches this campaign bids on, by ad group, with match type, status and Google\'s quality score from 1 to 10. The figures are for the window the nightly fetch read, named under the table, not for the period chosen at the top. Pausing a keyword stops bids on it without deleting it. Only Search campaigns have keywords.')" />
            </h2>

            <GoogleAdsKeywordsTable
                v-if="keywordCount"
                :ad-groups="ad_groups.map((group) => ({ id: String(group.id), name: group.name, keywords: group.keywords }))"
                :currency="campaign.currency"
                :element-route="element_route"
                :window-label="structureWindowLabel" />

            <p v-else-if="isSearch" class="mt-3 text-xs text-gray-500">
                {{ ctrans("No keywords read for this campaign yet.") }}
            </p>

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ ctrans("No keywords. Only Search campaigns bid on keywords; this one chooses audiences and placements instead, shown under Targeting.") }}
            </p>

            <div v-if="groupsWithNegatives.length" class="mt-4 space-y-1 text-xs">
                <p class="text-gray-500">{{ ctrans("Excluded within an ad group, on top of the campaign's own exclusions") }}</p>
                <div v-for="group in groupsWithNegatives" :key="'neg' + group.id" class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="text-gray-700">{{ group.name ?? group.id }}:</span>
                    <span
                        v-for="(keyword, i) in group.negative_keywords"
                        :key="keyword.id ?? i"
                        class="rounded bg-[#fdeaea] px-2 py-0.5 text-[#d03b3b] line-through"
                        :title="enumLabel(keyword.match_type) ?? ''">
                        {{ keyword.text }}
                    </span>
                </div>
            </div>
        </section>

        <section v-if="hasTargeting" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Targeting") }}
                <HelpTip :text="ctrans('Who each ad group is aimed at and where its ads may appear, as set in Google Ads: audiences, demographics, topics, placements and channels. Struck-through entries are excluded. Changing targeting is done in Google Ads.')" />
            </h2>

            <div class="mt-3">
                <GoogleAdsTargeting :ad-groups="ad_groups.map((group) => ({ id: String(group.id), name: group.name, status: group.status, targeting: group.targeting ?? [] }))" />
            </div>
        </section>

        <section v-if="asset_groups.length" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Asset groups") }}
                <span class="font-normal text-gray-500">· {{ asset_groups.length }}</span>
                <HelpTip :text="ctrans('The creative Google builds this campaign\'s ads from, one group per theme. Search themes and audience signals are hints that steer Google\'s targeting; they do not restrict it, which is why they are shown but cannot be edited here. Struck-through assets are not currently eligible to run. Figures are for the window the nightly fetch read.')" />
            </h2>

            <div class="mt-3">
                <GoogleAdsAssetGroups :asset-groups="asset_groups" :currency="campaign.currency" :window-label="structureWindowLabel" />
            </div>
        </section>

        <section v-if="exclusions.length" class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ ctrans("Campaign exclusions") }}
                <span class="font-normal text-gray-500">· {{ exclusions.length }}</span>
                <HelpTip :text="ctrans('Places and audiences this whole campaign never shows on: blocked websites and apps, YouTube channels, topics, audience lists and content categories. Excluded search terms have their own panel below. Changing these is done in Google Ads.')" />
            </h2>

            <dl class="mt-3 space-y-1.5 text-xs">
                <div v-for="[type, typeExclusions] in exclusionsByType" :key="type" class="flex flex-wrap gap-x-3 gap-y-1">
                    <dt class="w-36 shrink-0 text-gray-500">{{ criterionTypeLabel(type) }}</dt>
                    <dd class="flex flex-wrap gap-1">
                        <span
                            v-for="exclusion in typeExclusions"
                            :key="exclusion.id ?? exclusion.label ?? ''"
                            class="rounded bg-[#fdeaea] px-2 py-0.5 text-[#d03b3b] line-through">
                            {{ criterionLabel(exclusion) }}
                        </span>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <Deferred data="audience">
                <template #fallback>
                    <div class="space-y-3">
                        <div class="h-4 w-1/3 animate-pulse rounded bg-gray-100" />
                        <div class="h-2.5 w-full animate-pulse rounded-full bg-gray-100" />
                        <div v-for="row in 4" :key="row" class="h-6 animate-pulse rounded bg-gray-100" />
                    </div>
                </template>

                <TrafficSourceAudienceMix :mix="audience ?? null" />
            </Deferred>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <Deferred data="search_terms">
                <template #fallback>
                    <div class="space-y-3">
                        <div class="h-4 w-1/3 animate-pulse rounded bg-gray-100" />
                        <div class="h-8 w-64 animate-pulse rounded bg-gray-100" />
                        <div v-for="row in 6" :key="row" class="h-6 animate-pulse rounded bg-gray-100" />
                    </div>
                </template>

                <GoogleAdsSearchTerms
                    :search-terms="search_terms?.terms ?? []"
                    :error="search_terms?.error ?? null"
                    :currency="campaign.currency"
                    :ad-groups="ad_groups.map((group) => ({ id: String(group.id), name: group.name }))"
                    :keyword-route="keyword_route"
                    :negative-keywords-route="negative_keywords_route" />
            </Deferred>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <GoogleAdsNegativeKeywords
                :negative-keywords="negative_keywords"
                :update-route="negative_keywords_route" />
        </section>
    </div>
</template>
