<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 08 Sep 2026
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import TrafficSourceAudienceMix from "@/Components/DataDisplay/Dashboard/Widget/TrafficSourceAudienceMix.vue"
import { Deferred, Head, Link } from "@inertiajs/vue3"
import { computed } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faGoogle } from "@fortawesome/free-brands-svg-icons"
import TableGoogleAdsCampaigns from "@/Components/Tables/Grp/Org/CRM/TableGoogleAdsCampaigns.vue"
import DateIntervalTabs from "@/Components/Navigation/DateIntervalTabs.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"

library.add(faGoogle)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    unreachable_reason: string | null
    last_fetched_at: string | null
    shop_currency: string
    settings_route: { name: string; parameters: object }
    periods: Record<string, string>
    period: string
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

/**
 * Figures a fortnight old look exactly like this morning's unless the page says otherwise, and a
 * nightly job is the kind of thing that stops without anyone noticing. Two days is the threshold:
 * it clears an ordinary run plus one missed night before it starts complaining.
 */
const lastFetched = computed(() => {
    if (!props.last_fetched_at) return null

    const hours = (Date.now() - new Date(props.last_fetched_at).getTime()) / 36e5

    return {
        label: useFormatTime(props.last_fetched_at, { formatTime: "short-datetime" }),
        isStale: hours > 48,
    }
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <!-- Names the one thing that is wrong and the one place to fix it, rather than a generic
         "not connected" that sends people to reconnect an account that was never the problem. -->
    <div
        v-if="unreachable_reason"
        class="mx-4 mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        {{ unreachable_reason }}
        <Link :href="route(settings_route.name, settings_route.parameters)" class="primaryLink ml-1">
            {{ trans("Open shop settings") }}
        </Link>
    </div>

    <div class="mx-4 mt-4 rounded-xl bg-white p-5 ring-1 ring-gray-200">
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
    </div>

    <div class="mx-4 mt-4 flex flex-wrap items-center justify-between gap-3">
        <DateIntervalTabs :options="periods" :selected="period" :label="trans('Period')" />

        <div v-if="lastFetched" class="text-xs" :class="lastFetched.isStale ? 'text-[#a15c00]' : 'text-gray-500'">
            {{ trans("Read from Google") }}: {{ lastFetched.label }}
            <span v-if="lastFetched.isStale">· {{ trans("the nightly fetch may have stopped") }}</span>
        </div>
    </div>

    <div class="mx-4 mt-2 text-xs text-gray-500">
        {{ trans("Impressions, clicks, conversions and ROAS are Google's own figures for the period. Spend is shown in") }}
        {{ shop_currency }}; {{ trans("budgets and cost per click are in the ad account's currency.") }}
    </div>

    <TableGoogleAdsCampaigns :data="data" :currency="shop_currency" />
</template>
