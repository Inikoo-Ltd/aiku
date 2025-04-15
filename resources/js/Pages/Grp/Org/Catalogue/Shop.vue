<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Sept 2024 22:02:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
  faCube,
  faFileInvoice,
  faFolder,
  faFolderTree,
  faChartLine,
  faShoppingCart, faStickyNote, faMoneyBillWave
} from "@fal";
import { faCheckCircle } from "@fas";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import ShopShowcase from "@/Components/Showcases/Grp/ShopShowcase.vue";
import CatalogueDashboard from "@/Components/Dropshipping/CatalogueDashboard.vue";
import { PageHeading as TSPageHeading } from "@/types/PageHeading";

library.add(faChartLine, faCheckCircle, faFolderTree, faFolder, faCube, faShoppingCart, faFileInvoice, faStickyNote,
  faMoneyBillWave
);

const props = defineProps<{
  pageHead: TSPageHeading
  tabs: {
    current: string
    navigation: {}
  },
  title: string
  dashboard?: {}
  showcase?: {}
  history?: {}

}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

  const components = {
    showcase: ShopShowcase,
    dashboard: CatalogueDashboard,
    history: TableHistories
  };
  return components[currentTab.value];

});

</script>


<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead" />
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>
</template>