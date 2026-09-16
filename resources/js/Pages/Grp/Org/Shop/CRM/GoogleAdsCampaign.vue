<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head } from "@inertiajs/vue3"
import TrafficSourceAudienceMix from "@/Components/DataDisplay/Dashboard/Widget/TrafficSourceAudienceMix.vue"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import DateIntervalTabs from "@/Components/Navigation/DateIntervalTabs.vue"
import GoogleAdsCampaignTrend from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsCampaignTrend.vue"
import GoogleAdsCampaignControls from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsCampaignControls.vue"
import GoogleAdsElementToggle from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsElementToggle.vue"
import GoogleAdsDuplicateAd from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsDuplicateAd.vue"
import GoogleAdsNegativeKeywords from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsNegativeKeywords.vue"
import GoogleAdsSearchTerms from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsSearchTerms.vue"
import GoogleAdsAddKeyword from "@/Components/DataDisplay/Dashboard/Widget/GoogleAdsAddKeyword.vue"
import ConfirmDialog from "primevue/confirmdialog"
import { capitalize } from "@/Composables/capitalize"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { PageHeadingTypes } from "@/types/PageHeading"
import { trans } from "laravel-vue-i18n"

library.add(faGoogle)

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
    }
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
        ads: {
            id: string
            type: string | null
            status: string | null
            final_urls: string[]
            headlines: string[]
            descriptions: string[]
            strength: string | null
            approval_status: string | null
        }[]
        keywords: { id?: string; text: string | null; match_type: string | null; status: string | null }[]
    }[]
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

    <div class="px-4 py-4">
        <DateIntervalTabs :options="periods" :selected="period" :label="trans('Period')" class="w-fit" />
    </div>

    <div class="grid grid-cols-1 gap-4 px-4 pb-6 lg:grid-cols-3">
        <!-- Identity: the settings that decide what the figures below were ever going to look like. -->
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">{{ trans("Campaign") }}</h2>
            <p class="mt-1 text-xs text-gray-500">{{ campaign.reference }}</p>

            <dl class="mt-5 space-y-3 text-xs">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ trans("Status") }}</dt>
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
                    <dt class="text-gray-500">{{ trans("Channel") }}</dt>
                    <dd class="capitalize text-gray-700">{{ enumLabel(campaign.channel_type) ?? "—" }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ trans("Bidding") }}</dt>
                    <dd class="capitalize text-gray-700">{{ enumLabel(campaign.bidding_strategy_type) ?? "—" }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ trans("Daily budget") }}</dt>
                    <dd class="tabular-nums text-gray-700">
                        {{ campaign.budget_amount !== null ? money(campaign.budget_amount) : "—" }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ trans("Running since") }}</dt>
                    <dd class="text-gray-700">
                        {{ campaign.start_date ? useFormatTime(campaign.start_date, { formatTime: "mdy" }) : "—" }}
                    </dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ trans("Read from Google") }}</dt>
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
                    {{ trans("Reported by Google") }}
                    <span class="font-normal text-gray-500">· {{ period_label }}</span>
                </h2>
                <span class="text-xs text-gray-500">
                    {{ trans("Google's attribution, in") }} {{ campaign.currency }}
                </span>
            </div>

            <div v-if="google.days" class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Impressions") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ locale.number(google.impressions) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Clicks") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ locale.number(google.clicks) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("CTR") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">
                        {{ google.ctr !== null ? google.ctr.toFixed(2) + "%" : "—" }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Avg. CPC") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">
                        {{ google.avg_cpc !== null ? money(google.avg_cpc) : "—" }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Cost") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ money(google.cost) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Conversions") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ locale.number(google.conversions) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Cost per conversion") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">
                        {{ google.cost_per_conversion !== null ? money(google.cost_per_conversion) : "—" }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("ROAS") }}</div>
                    <div class="text-lg tabular-nums" :class="roasClass(google.roas)">
                        {{ google.roas !== null ? google.roas.toFixed(2) + "×" : "—" }}
                    </div>
                </div>
            </div>

            <p v-else class="mt-5 text-xs text-gray-500">
                {{ trans("Google reported nothing for this campaign in this period. Try a longer one, or check that the nightly fetch has run.") }}
            </p>

            <div v-if="google.roas === 0 && google.cost > 0" class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-900">
                {{ trans("This campaign spent money but Google recorded no conversion value, which usually means the conversion actions in this account have no value attached.") }}
            </div>
        </section>

        <!-- Aiku's own attribution, on purpose in its own block and on its own time base. -->
        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-medium text-gray-800">{{ trans("Attributed by Aiku") }}</h2>
                <span class="text-xs text-gray-500">
                    {{ trans("Since attribution started recording, not the period above, in") }} {{ campaign.shop_currency }}
                </span>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-5">
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Customers") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ share(attribution.customers) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Orders") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">{{ share(attribution.purchases) }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Revenue") }}</div>
                    <div class="text-lg tabular-nums text-[#006300]">
                        {{ money(attribution.revenue, campaign.shop_currency) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("Spend") }}</div>
                    <div class="text-lg tabular-nums text-gray-900">
                        {{ money(attribution.cost, campaign.shop_currency) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">{{ trans("ROAS") }}</div>
                    <div class="text-lg tabular-nums" :class="roasClass(attribution.roas)">
                        {{ attribution.roas !== null ? attribution.roas.toFixed(2) + "×" : "—" }}
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">
                {{ trans("Is the spend still buying clicks?") }}
                <span class="font-normal text-gray-500">· {{ period_label }}</span>
            </h2>
            <div class="mt-4">
                <GoogleAdsCampaignTrend :daily="daily" :currency="campaign.currency" />
            </div>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-3">
            <h2 class="text-sm font-medium text-gray-800">{{ trans("Day by day") }}</h2>

            <div v-if="daily.length" class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th scope="col" class="py-1.5 pr-2 text-left font-normal">{{ trans("Date") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Impressions") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Clicks") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">{{ trans("Conversions") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-right font-normal">
                                {{ trans("Cost") }} ({{ campaign.currency }})
                            </th>
                            <th scope="col" class="py-1.5 pl-2 text-right font-normal">
                                {{ trans("Spend") }} ({{ campaign.shop_currency }})
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in daily" :key="day.date" class="border-b border-gray-50 text-gray-600">
                            <td class="py-2 pr-2">{{ useFormatTime(day.date, { formatTime: "mdy" }) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ locale.number(day.impressions) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ locale.number(day.clicks) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ locale.number(day.conversions) }}</td>
                            <td class="px-2 text-right tabular-nums">{{ money(day.cost) }}</td>
                            <td class="pl-2 text-right tabular-nums">{{ money(day.shop_cost, campaign.shop_currency) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ trans("No days recorded in this period.") }}
            </p>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200 lg:col-span-2">
            <h2 class="text-sm font-medium text-gray-800">
                {{ trans("Ads") }}
                <span v-if="adCount" class="font-normal text-gray-500">· {{ adCount }}</span>
            </h2>

            <!-- Says plainly why there is no button to write an ad here, because a section that offers
                 pausing and copying but not creating otherwise reads as unfinished rather than decided. -->
            <p class="mt-1 max-w-3xl text-xs text-gray-600">
                {{ trans("You can pause an ad here, add headlines to it, and copy it into a variant to test. Writing a brand new ad from scratch is done in Google Ads, because that is where you get its strength scored as you type, headlines pinned to positions, and a preview of how it looks before it runs. Aiku would give you text boxes and no feedback.") }}
                <span v-if="adsNeedingVariants" class="mt-1 block text-[#a15c00]">
                    {{ trans(":count ad group(s) here run a single ad, so Google has nothing to rotate it against.", { count: String(adsNeedingVariants) }) }}
                </span>
            </p>

            <div v-if="adCount" class="mt-3 space-y-4">
                <div v-for="group in ad_groups" :key="group.id" class="rounded-lg p-3 ring-1 ring-gray-100">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs font-medium text-gray-700">
                            {{ group.name ?? group.id }}
                            <span class="font-normal capitalize text-gray-500">{{ enumLabel(group.status) }}</span>
                        </div>
                        <GoogleAdsElementToggle
                            type="ad_group"
                            :ad-group-id="String(group.id)"
                            :status="group.status"
                            :label="trans('ad group')"
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
                                    {{ ad.strength ? enumLabel(ad.strength) : trans("not rated yet") }}
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
                                :label="trans('ad')"
                                :update-route="element_route" />
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <span
                                v-for="(headline, i) in ad.headlines"
                                :key="i"
                                class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">
                                {{ headline }}
                            </span>
                        </div>
                        <p v-for="(description, i) in ad.descriptions" :key="i" class="mt-1 text-gray-600">
                            {{ description }}
                        </p>
                        <a
                            v-for="(url, i) in ad.final_urls"
                            :key="i"
                            :href="url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="primaryLink mt-1 block truncate">
                            {{ url }}
                        </a>

                        <GoogleAdsDuplicateAd
                            v-if="ad.status === 'ENABLED'"
                            class="mt-2"
                            :ad-group-id="String(group.id)"
                            :ad="ad"
                            :store-route="ad_route" />
                    </div>

                    <GoogleAdsAddKeyword
                        :ad-group-id="String(group.id)"
                        :ad-group-name="group.name"
                        :update-route="keyword_route" />
                </div>
            </div>

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ trans("No ads read for this campaign. Performance Max and Shopping campaigns hold their creative in asset groups, which the Google Ads API does not return here.") }}
            </p>
        </section>

        <section class="rounded-xl bg-white p-5 ring-1 ring-gray-200">
            <h2 class="text-sm font-medium text-gray-800">
                {{ trans("Keywords") }}
                <span v-if="keywordCount" class="font-normal text-gray-500">· {{ keywordCount }}</span>
            </h2>

            <div v-if="keywordCount" class="mt-3 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th scope="col" class="py-1.5 pr-2 text-left font-normal">{{ trans("Text") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-left font-normal">{{ trans("Match") }}</th>
                            <th scope="col" class="px-2 py-1.5 text-left font-normal">{{ trans("Status") }}</th>
                            <th scope="col" class="py-1.5 pl-2 text-right font-normal">
                                <span class="sr-only">{{ trans("Actions") }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in ad_groups" :key="group.id">
                            <tr
                                v-for="(keyword, i) in group.keywords"
                                :key="keyword.id ?? i"
                                class="border-b border-gray-50 text-gray-600">
                                <td class="py-2 pr-2">{{ keyword.text }}</td>
                                <td class="px-2 capitalize">{{ enumLabel(keyword.match_type) }}</td>
                                <td class="px-2 capitalize">{{ enumLabel(keyword.status) }}</td>
                                <td class="pl-2 text-right">
                                    <GoogleAdsElementToggle
                                        v-if="keyword.id"
                                        type="keyword"
                                        :ad-group-id="String(group.id)"
                                        :element-id="String(keyword.id)"
                                        :status="keyword.status"
                                        :label="trans('keyword')"
                                        :update-route="element_route" />
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p v-else class="mt-3 text-xs text-gray-500">
                {{ trans("No keywords. Only Search campaigns have them.") }}
            </p>
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
