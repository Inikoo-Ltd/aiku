<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 05 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableMasterAssetCompetitorProducts from "@/Components/Tables/Grp/Masters/TableMasterAssetCompetitorProducts.vue"
import TableCompetitorProducts from "@/Components/Tables/Grp/Masters/TableCompetitorProducts.vue"
import TableCompetitorPriceChecks from "@/Components/Tables/Grp/Masters/TableCompetitorPriceChecks.vue"
import CompetitorPriceCheckDialog from "@/Components/Masters/CompetitorPriceCheckDialog.vue"
import CompetitorDossier from "@/Components/Masters/CompetitorDossier.vue"
import CompetitorPolicies from "@/Components/Masters/CompetitorPolicies.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faExclamationTriangle, faCircle, faSync, faFileAlt, faFileContract, faBalanceScale, faCube, faClipboardCheck, faPlus } from "@fal"
import { PageHeadingTypes } from "@/types/PageHeading"
import { routeType } from "@/types/route"

library.add(faCheckCircle, faExclamationTriangle, faCircle, faSync, faFileAlt, faFileContract, faBalanceScale, faCube, faClipboardCheck, faPlus)

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
        language: string | null
        read_method: string
        prices_by_hand: boolean
        has_login: boolean
        status: string | null
        last_error: string | null
        fetched_at: string | null
        number_products: number
        platform: string | null
        logo: string | null
        intel_fetched_at: string | null
        intel_error: string | null
        screenshot: { original: string } | null
    }
    can_edit: boolean
    refresh_route: routeType
    price_check_route: routeType
    price_checks?: object
    dossier?: object
    matches?: object
    products?: object
    policies?: { url: string; title: string; text: string }[]
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const statusLabels: Record<string, string> = {
    ok: ctrans("Read fine last time"),
    login_failed: ctrans("Could not log in, check the email and password"),
    blocked: ctrans("The website blocked us"),
    search_broken: ctrans("The search link does not search, check it on Edit"),
    error: ctrans("Could not open the website"),
}

const readMethodLabels: Record<string, string> = {
    shopify: ctrans("Shopify catalogue, read every morning"),
    woocommerce: ctrans("WooCommerce catalogue, read every morning"),
    feed: ctrans("Product feed, read every morning"),
    search: ctrans("Website search, every week for the products with a price tip"),
    hand: ctrans("Checked by hand each month: their website blocks automated visits"),
    none: ctrans("Nothing yet: add a search link, or it is read by itself if it is a Shopify or WooCommerce shop"),
}

const platformLabels: Record<string, string> = { shopify: "Shopify", woocommerce: "WooCommerce", prestashop: "PrestaShop", magento: "Magento", wix: "Wix", squarespace: "Squarespace", bigcommerce: "BigCommerce", other: ctrans("Other platform") }

const facts = computed(() => [
    { label: ctrans("Master shop"), value: props.competitor.master_shop.name, href: route("grp.masters.master_shops.show", { masterShop: props.competitor.master_shop.slug }) },
    { label: ctrans("Sells to"), value: props.competitor.sells_to },
    { label: ctrans("Currency of their prices"), value: props.competitor.currency_code },
    { label: ctrans("Language of their website"), value: props.competitor.language ?? ctrans("Not known yet") },
    { label: ctrans("Prices read from"), value: readMethodLabels[props.competitor.read_method] + (props.competitor.has_login ? " · " + ctrans("logs in with our trade account") : "") },
])

const isAddingPriceCheck = ref(false)

const isRefreshing = ref(false)
const refreshQueued = ref(false)
const refreshError = ref<string | null>(null)
const refreshDossier = () => {
    isRefreshing.value = true
    refreshError.value = null
    router.post(route(props.refresh_route.name, props.refresh_route.parameters), {}, {
        preserveScroll: true,
        onSuccess: () => (refreshQueued.value = true),
        onError: () => (refreshError.value = ctrans("Could not queue the refresh")),
        onFinish: () => (isRefreshing.value = false),
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="mx-4 mt-4 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
        <div class="flex flex-wrap items-start gap-4">
            <img v-if="competitor.logo" :src="competitor.logo" alt="" class="h-12 max-w-40 object-contain" />
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium text-gray-800">{{ competitor.name }}</span>
                    <a :href="competitor.website" target="_blank" rel="noopener noreferrer" class="font-medium text-gray-700 hover:underline">{{ competitor.website }}</a>
                    <span v-if="competitor.platform" class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ platformLabels[competitor.platform] ?? competitor.platform }}</span>
                </div>
                <div class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="fact in facts" :key="fact.label">
                        <div class="text-xs text-gray-500">{{ fact.label }}</div>
                        <Link v-if="fact.href" :href="fact.href" class="font-medium text-gray-700 hover:underline">{{ fact.value }}</Link>
                        <span v-else>{{ fact.value }}</span>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">{{ competitor.prices_by_hand ? ctrans("Last price check") : ctrans("Last read") }}</div>
                        <div v-if="competitor.prices_by_hand">
                            <span v-if="competitor.fetched_at">{{ useFormatTime(competitor.fetched_at) }}</span>
                            <span v-else class="text-gray-500">{{ ctrans("None yet") }}</span>
                        </div>
                        <div v-else class="flex items-center gap-1.5">
                            <FontAwesomeIcon v-if="competitor.status === 'ok'" icon="fal fa-check-circle" class="text-green-500" fixed-width />
                            <FontAwesomeIcon v-else-if="competitor.status" icon="fal fa-exclamation-triangle" class="text-red-500" fixed-width />
                            <FontAwesomeIcon v-else icon="fal fa-circle" class="text-gray-300" fixed-width />
                            <span v-if="competitor.fetched_at">{{ useFormatTime(competitor.fetched_at) }}</span>
                            <span>{{ competitor.status ? statusLabels[competitor.status] : ctrans("Not read yet") }}</span>
                        </div>
                        <div v-if="competitor.last_error" class="text-xs text-red-600">{{ competitor.last_error }}</div>
                    </div>
                    <div v-if="!competitor.prices_by_hand">
                        <div class="text-xs text-gray-500">{{ ctrans("Dossier refreshed") }}</div>
                        <span v-if="competitor.intel_fetched_at">{{ useFormatTime(competitor.intel_fetched_at) }}</span>
                        <span v-else class="text-gray-500">{{ ctrans("Not read yet") }}</span>
                        <div v-if="competitor.intel_error" class="text-xs text-red-600">{{ competitor.intel_error }}</div>
                    </div>
                </div>
            </div>
            <div v-if="can_edit" class="flex shrink-0 flex-col items-end gap-y-1">
                <Button type="tertiary" :label="ctrans('Add price check')" @click="isAddingPriceCheck = true">
                    <template #icon><FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width /></template>
                </Button>
                <Button v-if="!competitor.prices_by_hand" type="tertiary" :label="ctrans('Refresh dossier')" :loading="isRefreshing" :disabled="refreshQueued" @click="refreshDossier">
                    <template #icon><FontAwesomeIcon icon="fal fa-sync" class="text-gray-500" fixed-width /></template>
                </Button>
                <div v-if="!competitor.prices_by_hand && refreshQueued" class="mt-1 text-xs text-gray-500">{{ ctrans("Queued: come back in a few minutes") }}</div>
                <div v-if="!competitor.prices_by_hand && refreshError" class="mt-1 text-xs text-red-600">{{ refreshError }}</div>
            </div>
        </div>
    </div>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <CompetitorDossier v-if="currentTab === 'dossier' && dossier" :dossier="dossier" :screenshot="competitor.screenshot" :currencyCode="competitor.currency_code" :intelFetchedAt="competitor.intel_fetched_at" />
    <TableCompetitorPriceChecks v-else-if="currentTab === 'price_checks' && price_checks" tab="price_checks" :data="price_checks" :masterShopSlug="competitor.master_shop.slug" :currencyCode="competitor.currency_code" />
    <TableMasterAssetCompetitorProducts v-else-if="currentTab === 'matches'" tab="matches" :data="matches" :canEdit="can_edit" :currencyCode="competitor.currency_code" />
    <TableCompetitorProducts v-else-if="currentTab === 'products'" tab="products" :data="products" :currencyCode="competitor.currency_code" />
    <CompetitorPolicies v-else-if="currentTab === 'policies'" :pages="policies ?? []" />
    <CompetitorPriceCheckDialog v-model:visible="isAddingPriceCheck" :route="price_check_route" :competitorName="competitor.name" :currencyCode="competitor.currency_code" :pricesByHand="competitor.prices_by_hand" />
</template>
