<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 08 Feb 2024 12:36:21 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { faFragile, faGlobe, faLink, faPencil, faUser, faChartLine, faUserCheck, faUserSecret } from "@fal"
import { computed, ref, inject, watch } from "vue"
import { Link } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue"
import { trans } from "laravel-vue-i18n"
import { StatsBoxTS } from "@/types/Components/StatsBox"
import StatsBox from "@/Components/Stats/StatsBox.vue"
import { routeType } from "@/types/route"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import LiveVisitorsPanel from "@/Components/Web/LiveVisitorsPanel.vue"
import { useLiveVisitors } from "@/Composables/useLiveVisitors"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { faDoorOpen } from "@far"

library.add(faGlobe, faLink, faFragile, faUser, faChartLine, faUserCheck, faUserSecret)

import SearchAnalyticsDisplay from "@/Components/DataDisplay/Dashboard/Widget/SearchAnalyticsDisplay.vue"
import SearchMerchandising from "@/Components/DataDisplay/Dashboard/Widget/SearchMerchandising.vue"

// Deep link to the website's search analytics page; null (hidden) when the route
// doesn't apply, e.g. fulfilment websites
const searchAnalyticsUrl = (() => {
    try {
        return route("grp.org.shops.show.web.analytics.search", route().params)
    } catch {
        return null
    }
})()

const searchQueryUrl = (query: string) => {
    try {
        return route("grp.org.shops.show.web.analytics.search.query", { ...route().params, q: query })
    } catch {
        return ""
    }
}

const searchCustomerUrl = (row: { customer_slug?: string }) => {
    if (!row.customer_slug) return null
    try {
        return route("grp.org.shops.show.web.analytics.search.customer", { ...route().params, customer: row.customer_slug })
    } catch {
        return null
    }
}

const searchPageUrl = (clickedUrl: string) => {
    try {
        return route("grp.org.shops.show.web.analytics.search.page", { ...route().params, url: clickedUrl })
    } catch {
        return clickedUrl
    }
}

// Stat cards deep link through the route the server attached to them, so shop and fulfilment
// websites each get the right target without guessing from the current route name.
const statUrl = (stat?: StatsBoxTS) => {
    if (!stat?.route?.name) {
        return null
    }
    try {
        return route(stat.route.name, stat.route.parameters)
    } catch {
        return null
    }
}

const props = defineProps<{
    data: {
        id: number
        slug: string
        url: string
        domain: string
        state: string
        status: string
        created_at: string
        updated_at: string
        layout: any
        stats: StatsBoxTS[]
        content_blog_stats: StatsBoxTS[]
        website_stats: StatsBoxTS[]
        live_visitors?: any[]
        live_visitors_enabled?: boolean
        currency_code?: string | null
        route_live_users?: routeType
        website_type: string
        migrated?: boolean
        pic?: {
            webmaster?: { name: string }[]
            seo?: { name: string }[]
        } | null
        route_restricted_country?: routeType
        search_insights?: any
        search_merchandising?: any
    }
    route_storefront: routeType
    route_welcome?:routeType
}>()

const layout = inject('layout', layoutStructure)

const websiteStats = ref([...props.data.website_stats])

const { visitors: liveVisitors, counts: liveCounts, syncFromServer } = useLiveVisitors(
    props.data.id,
    props.data.currency_code ?? null,
    props.data.live_visitors_enabled ?? false
)

syncFromServer(props.data.live_visitors ?? [])

const liveUsersUrl = computed(() => statUrl({ route: props.data.route_live_users } as StatsBoxTS))

watch(() => props.data.website_stats, (newStats) => {
    websiteStats.value = [...newStats]
}, { deep: true })

const links = computed(() => {
    const baseLinks: { label: string; route_target: any; icon: any; disabled?: boolean }[] = [
        { label: trans("Edit Header"), route_target: props.data.layout.headerRoute, icon: faPencil },
        { label: trans("Edit Menu"), route_target: props.data.layout.menuRoute, icon: faPencil },
        { label: trans("Edit Footer"), route_target: props.data.layout.footerRoute, icon: faPencil }
    ];

    // Add Edit Sidebar button only for dropshipping websites
    if (props.data.website_type !== "fulfilment") {
        baseLinks.splice(2, 0, {
            label: trans("Edit Sidebar"),
            route_target: props.data.layout.sidebarRoute,
            icon: faPencil,
            // disabled: layout?.app.environment !== 'local' 
        });
    }

    return baseLinks;
})

</script>

<template>
    <!-- Box: Url and Buttons in a single row -->
    <div class="px-6 pt-4 pb-12 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_20rem] gap-6">
            <!-- URL Box + compact visitor stats -->
            <div class="">
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
                    <div class="bg-white w-fit h-fit flex items-center gap-x-3">
                        <a :href="props.data.url" target="_blank" v-tooltip="trans('Go To Website')"
                            class="hover:bg-gray-50 ring-1 ring-gray-300 cursor-pointer rounded overflow-hidden flex text-xxs md:text-base text-gray-500">
                            <div class="bg-gray-200 py-2 px-2">
                                <FontAwesomeIcon :icon="faGlobe" class="px-1" aria-hidden="true" />
                            </div>
                            <div class="flex items-center px-4">
                                {{ props.data.url }}
                            </div>
                        </a>
                    </div>

                    <component
                        :is="statUrl(stat) ? Link : 'div'"
                        v-for="stat in websiteStats"
                        :key="stat.label"
                        :href="statUrl(stat)"
                        class="flex items-baseline gap-2"
                        :class="statUrl(stat) ? 'hover:opacity-80 transition-opacity' : ''"
                    >
                        <FontAwesomeIcon v-if="typeof stat.icon === 'string'" :icon="stat.icon" :style="{ color: stat.color }" fixed-width aria-hidden="true" />
                        <span class="text-2xl font-semibold tabular-nums">{{ (stat.value ?? 0).toLocaleString() }}</span>
                        <span class="text-sm text-gray-400">{{ stat.label }}</span>
                    </component>
                </div>

                <div v-if="props.data.live_visitors_enabled" class="border-t border-gray-300 mt-6 pt-4">
                    <LiveVisitorsPanel
                        :visitors="liveVisitors"
                        :counts="liveCounts"
                        :currency="props.data.currency_code ?? null"
                        :live-users-url="liveUsersUrl"
                    />
                </div>

                <div class="border-t border-gray-300 mt-6 pt-4">
                    <div class="flex flex-col xl:flex-row gap-6">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between flex-wrap gap-2 mb-2">
                                <div class="font-semibold w-fit text-lg">
                                    {{ trans('Website Search') }}
                                </div>
                                <SearchMerchandising
                                    v-if="props.data.search_merchandising"
                                    :merchandising="props.data.search_merchandising"
                                    :top-zero-queries="props.data.search_insights?.top_zero_queries"
                                />
                            </div>
                            <SearchAnalyticsDisplay
                                :widget="props.data.search_insights"
                                :logs-url="searchAnalyticsUrl"
                                :logs-label="trans('Search analytics')"
                                :live-website-id="props.data.id"
                                :query-url="searchAnalyticsUrl ? searchQueryUrl : undefined"
                                :customer-url="searchAnalyticsUrl ? searchCustomerUrl : undefined"
                                :page-url="searchAnalyticsUrl ? searchPageUrl : undefined"
                            />
                        </div>

                        <div class="w-full xl:w-56 shrink-0">
                            <div class="font-semibold w-fit text-lg mb-2">
                                {{ trans('Product Catalogue') }}
                            </div>
                            <div class="gap-2 grid grid-cols-2 xl:grid-cols-1">
                                <StatsBox v-for="stat in props.data.stats" :stat />
                            </div>

                            <div class="mt-6 font-semibold w-fit text-lg mb-2">
                                {{ trans('Content & Blog') }}
                            </div>
                            <div class="gap-2 grid grid-cols-2 xl:grid-cols-1">
                                <StatsBox v-for="stat in props.data.content_blog_stats" :stat />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: PIC Webmaster and SEO -->
                <div v-if="props.data.pic?.webmaster?.length || props.data.pic?.seo?.length" class="mt-6">
                    <div class="font-semibold w-fit text-lg mb-2">
                        {{ trans('Person in Contact') }}
                    </div>

                    <div v-if="props.data.pic?.webmaster?.length">
                        {{ trans("Webmaster") }}:  {{ props.data.pic.webmaster.map(x => x.name).join(', ') }}
                    </div>

                    <div v-if="props.data.pic?.seo?.length">
                        {{ trans("SEO") }}:  {{ props.data.pic.seo.map(x => x.name).join(', ') }}
                    </div>
                </div>
            </div>

            <!-- Buttons Card (in the right part of the grid) -->
            <div class="flex justify-end">
                <div class="w-64 border border-gray-300 rounded-md p-2 h-fit">
                    <div class="p-2" v-if="props.data.route_restricted_country?.name">
                        <ButtonWithLink :routeTarget="props.data.route_restricted_country" icon="fal fa-ban"
                            type="tertiary" :label="trans('Restricted Countries')"
                            :tooltip="trans('Countries restricted from this website')" full />
                    </div>

                    <div class="p-2">
                        <ButtonWithLink :routeTarget="route_storefront" icon="fal fa-home" type="tertiary"
                            :label="trans('Storefront')" full />
                    </div>

                    <div class="p-2" v-if="route_welcome?.name">
                        <ButtonWithLink :routeTarget="route_welcome" :icon="faDoorOpen" type="tertiary"
                            :label="trans('Welcome Page')" full />
                    </div>

                    <div v-for="(item, index) in links" :key="index" class="px-2 py-1">
                        <ButtonWithLink :routeTarget="item.route_target" full :icon="item.icon" :label="item.label"
                            type="secondary" :disabled="item?.disabled" />
                    </div>

                    <div class="p-2 space-y-2">
                        <ModalConfirmationDelete
                            :description="trans('Purge all cached files. Purging your cache may slow your website temporarily')"
                            :title="trans('Break cache')" :noLabel="trans('Confirm')" noIcon="" :routeDelete="{
                                name: 'grp.models.website.break_cache',
                                parameters: {
                                    website: data?.id
                                },
                                method: 'post'
                            }">
                            <template #default="{ changeModel }">
                                <ButtonWithLink @click="changeModel" :icon="faFragile" type="tertiary"
                                    :label="trans('Break cache')" full>
                                    <template #iconRight>
                                        <div v-tooltip="trans('If you made some changes but did not updated yet in the website, use this feature')"
                                            class="text-gray-400 hover:text-gray-700">
                                            <FontAwesomeIcon icon="fal fa-info-circle" class="" fixed-width
                                                aria-hidden="true" />
                                        </div>
                                    </template>
                                </ButtonWithLink>
                            </template>
                        </ModalConfirmationDelete>

                    </div>

                </div>
            </div>
        </div>
    </div>
</template>
