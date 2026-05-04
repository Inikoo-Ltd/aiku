<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { computed, ref } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import TablePalletStoredItem from '@/Components/Tables/Grp/Org/Fulfilment/TablePalletStoredItem.vue'
// import TablePallets from '@/Components/Tables/Grp/Org/Fulfilment/TablePallets.vue'
import { routeType } from '@/types/route'
import StoredItemShowcase from '@/Components/Showcases/Grp/StoredItemShowcase.vue'

// import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faFragile, faNarwhal, faExchange, faYinYang, faBallotCheck, faTruckCouch, faSignOutAlt, faGhost } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import StockItemsMovements from '@/Components/Showcases/Grp/StockItemsMovements.vue'
import TableStoredItemsAudits from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsAudits.vue'
import TableStoredItemAuditDeltas from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemAuditDeltas.vue'
library.add(faFragile, faNarwhal, faExchange, faYinYang, faBallotCheck, faTruckCouch, faSignOutAlt, faGhost)


const props = defineProps<{
    title: string
    tabs: {
        current: string
        navigation: {}
    }
    pallets?: {}
    showcase?: {}
    movements?: {}
    history?: {}
    audits?: {}
    pageHead: {}
    palletRoute: {
        index: routeType
    }
    locationRoute: {
        index: routeType
    }
    update: routeType
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const component = computed(() => {

    const components = {
        showcase: StoredItemShowcase,
        pallets: TablePalletStoredItem,
        history: TableHistories,
        movements: StockItemsMovements,
        audits: TableStoredItemAuditDeltas
    }
    return components[currentTab.value]

});

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" :tab="currentTab" :palletRoute="palletRoute"
        :locationRoute="locationRoute" :updateRoute="update" />
</template>
