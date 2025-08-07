<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 17 Sept 2022 02:06:31 Malaysia Time, Kuala Lumpur, Malaysia
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { computed, ref } from "vue"
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faExchange, faInventory, faWarehouse, faMapSigns, faPallet } from "@fal"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import LocationShowcase from "@/Components/Showcases/Org/LocationShowcase.vue"
import TablePallets from "@/Components/Tables/Grp/Org/Inventory/Fulfilment/TablePallets.vue"
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue"
import { PageHeading as PageHeadingTS } from "@/types/PageHeading"
import { Tabs as TSTabs } from "@/types/Tabs"


library.add(faInventory, faExchange, faBox, faWarehouse, faMapSigns, faPallet)


const props = defineProps<{
    title: string
    pageHead: PageHeadingTS
    tabs: TSTabs
    details?: {}
    history?: {}
    stocks?: {}
    org_stocks?: {}
    pallets?: {}
    showcase?: {}
}>()


let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const component = computed(() => {

    const components = {
        showcase: LocationShowcase,
        pallets: TablePallets,
        org_stocks: TableOrgStocks,
        details: ModelDetails,
        history: TableHistories
    }
    return components[currentTab.value]
})

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
