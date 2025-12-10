<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 17 Oct 2022 17:33:07 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { capitalize } from "@/Composables/capitalize"
  import TableStoredItems from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItems.vue"
  import Tabs from "@/Components/Navigation/Tabs.vue"
  import { computed, ref } from "vue"
  import type { Component } from "vue"
  import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
  import { useTabChange } from "@/Composables/tab-change"
    import RetinaPalletShowcase from './RetinaPalletShowcase.vue'
  import { PageHeadingTypes } from '@/types/PageHeading'
  import { Tabs as TSTabs } from '@/types/Tabs'
import StockItemsMovements from '@/Components/Showcases/Grp/StockItemsMovements.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faExchange, faFragile, faNarwhal } from '@fal'
import TableStoredItemsInWarehouse from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsInWarehouse.vue'
library.add(faFragile, faNarwhal, faExchange)

  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      tabs: TSTabs
      stored_items?: {}
      movements?: {}
      history?: {}
      showcase: {}
  }>()
  
  const currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
  const component = computed(() => {
      const components: Component = {
          showcase: RetinaPalletShowcase,
          stored_items: TableStoredItemsInWarehouse,
          movements: StockItemsMovements,
          history: TableHistories
      }
      return components[currentTab.value]
  
  })
  
  console.log('plm',props)
  </script>
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
      <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
  </template>
  