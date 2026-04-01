<!--
 -  Author: Nickel
 -  Created: Tue, 01 Apr 2026
 -  Copyright (c) 2026, Inikoo LTD
 -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"
import { useTabChange } from "@/Composables/tab-change"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faInventory, faDownload } from "@fal"
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue"
import TableLocationOrgStockHistories from "@/Components/Tables/Grp/Org/Inventory/TableLocationOrgStockHistories.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { routeType } from "@/types/route"

library.add(faBox, faInventory, faDownload)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    download_route: routeType
    org_stocks?: {}
    location_org_stocks?: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Record<string, any> = {
        org_stocks: TableOrgStocks,
        location_org_stocks: TableLocationOrgStockHistories,
    }
    return components[currentTab.value]
})

function exportUrl(): string {
    if (!props.download_route?.name) return ""
    return route(props.download_route.name, {
        ...props.download_route.parameters,
        tab: currentTab.value,
        type: 'xlsx'
    })
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #otherBefore>
            <a :href="exportUrl()" download target="_blank" rel="noopener">
                <Button :icon="faDownload" label="Excel" type="tertiary" />
            </a>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
    />
</template>
