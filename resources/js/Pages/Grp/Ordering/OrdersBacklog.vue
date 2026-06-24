<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:45:56 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import TabsBox from "@/Components/Navigation/TabsBox.vue"
import { PageHeadingTypes } from '@/types/PageHeading'
import { Tabs as TSTabs } from '@/types/Tabs'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen } from '@fal'
import { ref } from 'vue'
import { useTabChange } from '@/Composables/tab-change'
import TableOrders from '@/Components/Tables/Grp/Org/Ordering/TableOrders.vue'
import TableDeliveryNotes from '@/Components/Tables/Grp/Org/Dispatching/TableDeliveryNotes.vue'
import { computed } from 'vue'

library.add(faInventory, faWarehouse, faMapSigns, faBox, faBoxesAlt, faCircle, faCheckCircle, faHandsHelping, faBoxOpen)

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    tabs: TSTabs
    in_basket: {}
    submitted_paid: {}
    submitted_unpaid: {}
    picking: {}
    blocked: {}
    packed_done: {}
    dispatched_today: {}
    finalise: {}
    creating: {}
    submitted: {}
    in_warehouse: {}
    handling: {}
    handling_blocked: {}
    packed: {}
    finalised: {}
    dispatched: {}
    cancelled: {}
    picked: {}
    packing: {}
    returned: {}
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
    const components: any = {
      in_basket: TableOrders,
      submitted_paid: TableOrders,
      submitted_unpaid: TableOrders,
      in_warehouse: TableOrders,
      handling: TableOrders,
      handling_blocked: TableOrders,
      picked: TableOrders,
      packing: TableOrders,
      packed: TableOrders,
      finalised: TableOrders,
      dispatched_today: TableOrders,
      returned: TableDeliveryNotes
    }

    return components[currentTab.value]
})
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <KeepAlive>
      <TabsBox :tabs_box="tabs.navigation" :current="currentTab" @update:tab="handleTabUpdate" />
    </KeepAlive>
    <!-- <TableOrders :key="currentTab" :tab="currentTab" :data="props[currentTab]"></TableOrders> -->
    <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

</template>
