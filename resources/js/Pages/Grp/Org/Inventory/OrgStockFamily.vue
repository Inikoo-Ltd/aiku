<!--
 -  Author: Raul Perusquia <raul@inikoo.com>
 -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
 -  Copyright (c) 2022, Raul A Perusquia Flores
 -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import ToggleSwitch from 'primevue/toggleswitch'
import { ctrans } from '@/Composables/useTrans'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBoxesAlt, faChartLine } from '@fal'
import { computed, ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import Tabs from '@/Components/Navigation/Tabs.vue'
import { capitalize } from '@/Composables/capitalize'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'
import OrgStockFamilyShowcase from '@/Components/Showcases/Grp/OrgStockFamilyShowcase.vue'
import ProductCategoryTimeSeriesTable from '@/Components/Product/ProductCategoryTimeSeriesTable.vue'
import SalesAnalysis from '@/Components/SalesAnalysis/SalesAnalysis.vue'
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'

library.add(faBoxesAlt, faChartLine)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase?: object
    sales_analysis?: object
    sales_analysis_teaser?: object
    sales?: object
    history?: object
    salesData?: object
    gb_pallet?: { value: boolean, can_edit: boolean, gb_org_stocks: number, route: { name: string, parameters: Record<string, string> } } | null
}>()

const currentTab = ref(props.tabs.current)
const deferredPropsOfTab: Record<string, string[]> = { showcase: ['sales_analysis_teaser'], sales_analysis: ['sales_analysis'] }
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, deferredPropsOfTab[tabSlug] ?? [])

const breakdownRoute = (row: { slug: string | null }) => {
    const params = route().params
    return row.slug && params.organisation && params.warehouse
        ? route('grp.org.warehouses.show.inventory.org_stocks.active_org_stocks.show', [params.organisation, params.warehouse, row.slug])
        : null
}

const setGbPallet = (value: boolean) => {
    if (!props.gb_pallet) return
    router.patch(route(props.gb_pallet.route.name, props.gb_pallet.route.parameters), { gb_separate_pallet: value }, { preserveScroll: true })
}

const component = computed(() => {
    const components = {
        showcase: OrgStockFamilyShowcase,
        sales_analysis: SalesAnalysis,
        sales: ProductCategoryTimeSeriesTable,
        history: TableHistories,
    }
    return components[currentTab.value]
})
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div v-if="gb_pallet" class="mx-4 mt-3 flex items-center gap-3 text-sm">
        <ToggleSwitch :modelValue="gb_pallet.value" :disabled="!gb_pallet.can_edit" inputId="gb_separate_pallet" @update:modelValue="setGbPallet" />
        <label for="gb_separate_pallet" class="font-medium">{{ ctrans("Send on separate GB pallet") }}</label>
        <span class="text-gray-500">{{ ctrans(":count GB-origin SKOs", { count: String(gb_pallet.gb_org_stocks) }) }}</span>
    </div>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :data="props[currentTab]"
        :tab="currentTab"
        :salesData="salesData"
        :salesAnalysisTeaser="sales_analysis_teaser"
        :breakdownRoute="breakdownRoute"
    />
</template>
