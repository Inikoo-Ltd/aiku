<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 16:45:41 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">

import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faChartLine } from '@fal'
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, defineAsyncComponent, ref } from "vue"
import type { Component } from 'vue'
import WarehouseShowcase from "@/Components/Warehouse/WarehouseShowcase.vue"
import ModelDetails from "@/Components/ModelDetails.vue"
import TableLocations from "@/Components/Tables/Grp/Org/Inventory/TableLocations.vue"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TableWarehouseAreas from "@/Components/Tables/Grp/Org/Inventory/TableWarehouseAreas.vue"
import { useTabChange } from "@/Composables/tab-change"
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'

const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))
library.add(faInventory, faWarehouse, faMapSigns, faChartLine)

const props = defineProps<{
    pageHead: PageHeadingTypes
    tabs: TSTabs
    showcase: {}
    tagsList: {
        data: {}[]
    }
    title: string
    dashboard?: {}
    warehouse_areas?: {}
    locations?: {
        data: []
    }
    history?: {}
}>()


const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: Component = {
        showcase: WarehouseShowcase,
        warehouse_areas: TableWarehouseAreas,
        locations: TableLocations,
        details: ModelDetails,
        history: TableHistories
    }

    return components[currentTab.value]
})

</script>


<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" :tagsList="tagsList?.data" :link="pageHead"></component>
</template>
