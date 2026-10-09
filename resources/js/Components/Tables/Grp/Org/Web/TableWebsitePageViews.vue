<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type PageViewRow = {
    viewed_at: string
    page_path: string
    webpage_slug: string | null
    page_type: string | null
    duration_seconds: number
    website_visitor_id: number
    visitor: string | null
    device_type: string | null
    country_code: string | null
    city: string | null
}

defineProps<{
    data: object
    tab?: string
}>()

const routeParams = route().params as Record<string, string>

const locale = useLocaleStore()

const countryName = (countryCode: string) => {
    try {
        return new Intl.DisplayNames([locale.language?.code || "en"], { type: "region" }).of(countryCode.toUpperCase()) ?? countryCode
    } catch {
        return countryCode
    }
}

const countryFlagData = (pageView: PageViewRow) => {
    if (!pageView.country_code) {
        return null
    }

    const name = countryName(pageView.country_code)

    return [pageView.country_code, pageView.city ? `${pageView.city}, ${name}` : name, ""]
}

const visitorsOfWebpageHref = (pageView: PageViewRow) => pageView.webpage_slug
    ? route("grp.org.shops.show.seo.visitors.webpage", [routeParams.organisation, routeParams.shop, pageView.webpage_slug])
    : null

const pageViewsOfVisitorHref = (pageView: PageViewRow) => route("grp.org.shops.show.seo.page_views.visitor", [routeParams.organisation, routeParams.shop, pageView.website_visitor_id])

const formatDuration = (totalSeconds: number) => {
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60

    return minutes > 0 ? `${minutes}m ${String(seconds).padStart(2, "0")}s` : `${seconds}s`
}

const pageTypeLabel = (pageType: string | null) => pageType ? pageType.charAt(0).toUpperCase() + pageType.slice(1).replace(/_/g, " ") : ""
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(viewed_at)="{ item: pageView }: { item: PageViewRow }">
            <span class="whitespace-nowrap tabular-nums text-gray-700">{{ useFormatTime(pageView.viewed_at, { formatTime: "short-datetime" }) }}</span>
        </template>

        <template #cell(page_path)="{ item: pageView }: { item: PageViewRow }">
            <Link v-if="visitorsOfWebpageHref(pageView)" :href="visitorsOfWebpageHref(pageView)" class="primaryLink break-all">
                {{ pageView.page_path }}
            </Link>
            <span v-else class="break-all">{{ pageView.page_path }}</span>
        </template>

        <template #cell(page_type)="{ item: pageView }: { item: PageViewRow }">
            {{ pageTypeLabel(pageView.page_type) }}
        </template>

        <template #cell(duration_seconds)="{ item: pageView }: { item: PageViewRow }">
            <span v-if="pageView.duration_seconds > 0" class="tabular-nums">{{ formatDuration(pageView.duration_seconds) }}</span>
            <span v-else class="text-gray-500" :title="ctrans('Measured when the visitor opens another page, so the last page of a visit has no time yet')">-</span>
        </template>

        <template #cell(country_code)="{ item: pageView }: { item: PageViewRow }">
            <AddressLocation v-if="countryFlagData(pageView)" :data="countryFlagData(pageView)" />
        </template>

        <template #cell(visitor)="{ item: pageView }: { item: PageViewRow }">
            <Link :href="pageViewsOfVisitorHref(pageView)" class="primaryLink font-mono text-xs">
                {{ pageView.visitor }}
            </Link>
        </template>
    </Table>
</template>
