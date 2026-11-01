<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:44:55 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { capitalize } from '@/Composables/capitalize'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import WebsitesEdgeTraffic from '@/Components/Web/WebsitesEdgeTraffic.vue'
import { computed } from 'vue'
import { useCurrentTab } from '@/Composables/tab-change'
import { ctrans } from '@/Composables/useTrans'
import { edgeDrillHref } from '@/Components/Web/edgeDrill'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faSpider, faFlag, faGlobe, faTimes } from '@fal'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import TableCrawls from '@/Components/Tables/Grp/Org/Web/TableCrawls.vue'
import TableWebsitesEdgeTraffic from '@/Components/Tables/Grp/Org/Web/TableWebsitesEdgeTraffic.vue'
import TableWebsiteVisitorCountries from '@/Components/Tables/Grp/Org/Web/TableWebsiteVisitorCountries.vue'

library.add(faSpider, faFlag, faGlobe, faTimes)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
        current: string
        navigation: object
    }
    edge_filters: { website: string; website_label: string | null; days: number; day: string | null; country: string | null }
    showcase?: {}
    edge_websites?: {}
    visitor_countries?: {}
    crawls?: {}
}>()

const currentTab = useCurrentTab(props.tabs.current)

const handleTabUpdate = (tabSlug: string) => {
    if (tabSlug === currentTab.value) {
        return
    }
    router.get(edgeDrillHref(props.edge_filters, tabSlug), {}, { only: [tabSlug, 'edge_filters'], preserveState: true, preserveScroll: true })
}

const component = computed(() => {
    const components = {
        showcase: WebsitesEdgeTraffic,
        edge_websites: TableWebsitesEdgeTraffic,
        visitor_countries: TableWebsiteVisitorCountries,
        crawls: TableCrawls
    }
    return components[currentTab.value]
})

const regionNames = new Intl.DisplayNames(undefined, { type: 'region' })
const dayLabel = (iso: string) => new Date(`${iso}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', timeZone: 'UTC' })

const filterChips = computed(() => {
    const filters = props.edge_filters
    const keep = { day: filters.day, country: filters.country }
    return [
        { key: 'website', label: filters.website_label ?? ctrans('All websites'), clear: filters.website_label ? { website: null } : null },
        { key: 'days', label: filters.day ? dayLabel(filters.day) : ctrans('Last :days days', { days: filters.days }), clear: filters.day ? { day: null } : null },
        ...(currentTab.value === 'visitor_countries' && filters.country ? [{ key: 'country', label: regionNames.of(filters.country) ?? filters.country, clear: { country: null } }] : []),
    ].map((chip) => ({ ...chip, href: chip.clear ? edgeDrillHref(filters, currentTab.value, { ...keep, ...chip.clear }) : null }))
})
</script>

<template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <div v-if="['edge_websites', 'visitor_countries'].includes(currentTab)" class="flex flex-wrap items-center gap-2 px-4 pt-4 text-xs text-gray-500">
        <span>{{ ctrans('Showing') }}</span>
        <span v-for="chip in filterChips" :key="chip.key" class="inline-flex items-center gap-1 rounded-sm border border-gray-200 bg-white px-1.5 py-0.5 text-gray-700">
            {{ chip.label }}
            <Link v-if="chip.href" :href="chip.href" class="text-gray-400 hover:text-gray-700" :aria-label="ctrans('Clear')">
                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
            </Link>
        </span>
        <Link :href="edgeDrillHref(edge_filters, 'showcase', { day: null, country: null })" class="ml-1 text-gray-500 underline decoration-dotted underline-offset-4 hover:text-gray-700">{{ ctrans('Back to the overview') }}</Link>
    </div>
    <component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
