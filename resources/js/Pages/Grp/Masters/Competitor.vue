<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import TableMasterAssetCompetitorProducts from "@/Components/Tables/Grp/Masters/TableMasterAssetCompetitorProducts.vue"
import TableCompetitorProducts from "@/Components/Tables/Grp/Masters/TableCompetitorProducts.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faExclamationTriangle, faCircle, faLock, faBalanceScale, faCube } from "@fal"
import { PageHeadingTypes } from "@/types/PageHeading"

library.add(faCheckCircle, faExclamationTriangle, faCircle, faLock, faBalanceScale, faCube)

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: { current: string; navigation: object }
    competitor: {
        name: string
        website: string
        master_shop: { name: string; slug: string }
        sells_to: string
        currency_code: string
        read_method: string
        has_login: boolean
        status: string | null
        last_error: string | null
        fetched_at: string | null
        number_products: number
    }
    can_edit: boolean
    matches?: object
    products?: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => ({ matches: TableMasterAssetCompetitorProducts, products: TableCompetitorProducts })[currentTab.value])

const statusLabels: Record<string, string> = {
    ok: ctrans("Read fine last time"),
    login_failed: ctrans("Could not log in, check the email and password"),
    blocked: ctrans("The website blocked us"),
    error: ctrans("Could not open the website"),
}

const readMethodLabels: Record<string, string> = {
    shopify: ctrans("Shopify catalogue, read every morning"),
    feed: ctrans("Product feed, read every morning"),
    search: ctrans("Website search, every week for the products with a price tip"),
    none: ctrans("Nothing yet: add a search link, or it is read by itself if it is a Shopify shop"),
}

const facts = computed(() => [
    { label: ctrans("Master shop"), value: props.competitor.master_shop.name, href: route("grp.masters.master_shops.show", { masterShop: props.competitor.master_shop.slug }) },
    { label: ctrans("Website"), value: props.competitor.website, href: props.competitor.website, external: true },
    { label: ctrans("Sells to"), value: props.competitor.sells_to },
    { label: ctrans("Currency of their prices"), value: props.competitor.currency_code },
    { label: ctrans("Prices read from"), value: readMethodLabels[props.competitor.read_method] + (props.competitor.has_login ? " · " + ctrans("logs in with our trade account") : "") },
    { label: ctrans("Products found"), value: String(props.competitor.number_products) },
])
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mx-4 mt-4 grid gap-x-6 gap-y-2 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="fact in facts" :key="fact.label">
            <div class="text-xs text-gray-500">{{ fact.label }}</div>
            <a v-if="fact.external" :href="fact.href" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">{{ fact.value }}</a>
            <Link v-else-if="fact.href" :href="fact.href" class="font-medium text-gray-700 hover:underline">{{ fact.value }}</Link>
            <span v-else>{{ fact.value }}</span>
        </div>
        <div>
            <div class="text-xs text-gray-500">{{ ctrans("Last read") }}</div>
            <div class="flex items-center gap-1.5">
                <FontAwesomeIcon v-if="competitor.status === 'ok'" icon="fal fa-check-circle" class="text-green-500" fixed-width />
                <FontAwesomeIcon v-else-if="competitor.status" icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width />
                <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-300" fixed-width />
                <span v-if="competitor.fetched_at">{{ useFormatTime(competitor.fetched_at) }}</span>
                <span>{{ competitor.status ? statusLabels[competitor.status] : ctrans("Not read yet") }}</span>
            </div>
            <div v-if="competitor.last_error" class="text-xs text-red-600">{{ competitor.last_error }}</div>
        </div>
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" :canEdit="can_edit" :currencyCode="competitor.currency_code" />
</template>
