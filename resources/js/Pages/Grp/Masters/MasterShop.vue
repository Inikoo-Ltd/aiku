<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 15 Apr 2025 13:06:38 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
  faCube,
  faFileInvoice,
  faFolder,
  faFolderOpen,
  faAtom,
  faFolderTree,
  faChartLine,
  faShoppingCart,
  faStickyNote,
  faMoneyBillWave,
  faExclamationTriangle,
  faFolderDownload,
  faStoreAlt,
  faAlignLeft,
} from "@fal";
import { faCheckCircle, faPlusCircle } from "@fas";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { computed, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";

import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import ShopShowcase from "@/Components/Showcases/Grp/ShopShowcase.vue";
import CatalogueDashboard from "@/Components/Dropshipping/CatalogueDashboard.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import TableShopInMaster from "@/Components/Tables/Grp/Masters/TableShopInMaster.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { FontAwesomeIcon, FontAwesomeLayers } from "@fortawesome/vue-fontawesome";
import { ctrans } from "@/Composables/useTrans"
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
import SalesAnalysisTeaser from "@/Components/SalesAnalysis/SalesAnalysisTeaser.vue"
import SalesAnalysisMovers from "@/Components/SalesAnalysis/SalesAnalysisMovers.vue"
import { useLayoutStore } from "@/Stores/layout"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Modal from "@/Components/Utils/Modal.vue"

library.add(faChartLine, faCheckCircle, faFolderTree, faFolder, faCube, faShoppingCart, faFileInvoice, faStickyNote,
  faMoneyBillWave, faFolderOpen, faAtom, faExclamationTriangle, faFolderDownload, faAlignLeft
);

const props = defineProps<{
  pageHead: PageHeadingTypes
  tabs: {
    current: string
    navigation: {}
  },
  title: string
  dashboard?: {}
  showcase?: {}
  sales_analysis?: object
  sales_analysis_teaser?: object
  history?: {}
  shops?: {}
  organisations_list: {
    [key: string]: {
      label: string
    }
  }
}>();

let currentTab = ref(props.tabs.current);
const deferredPropsOfTab: Record<string, string[]> = { showcase: ["sales_analysis_teaser"], sales_analysis: ["sales_analysis"] }
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab, deferredPropsOfTab[tabSlug] ?? []);
const layout = useLayoutStore();

const component = computed(() => {

  const components = {
    showcase: ShopShowcase,
    sales_analysis: SalesAnalysis,
    dashboard: CatalogueDashboard,
    history: TableHistories,
    shops: TableShopInMaster,
  };
  return components[currentTab.value];
});


// Section: create shop
function transformObjectToArray(obj) {
  const result = [];

  for (const key in obj) {
    if (obj.hasOwnProperty(key)) {
      result.push({
        label: obj[key].label,
        value: key
      });
    }
  }

  return result;
}
const isLoadingVisit = ref(false)
const createShop = () => {
  router.visit(route('grp.masters.master_shops.show.shop.create', {
    masterShop: route().params['masterShop'],
    organisation: selectedOrganisation.value,
  }), {
    onStart: () => {
      isLoadingVisit.value = true
    }
  })
}
const organisationList = transformObjectToArray(props.organisations_list)
const selectedOrganisation = ref(null)
const isOpenModalAddShop = ref(false)
</script>


<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
        <template #otherBefore>
          <Button v-if="currentTab == 'shops'" :type="'edit'" @click="isOpenModalAddShop = true">
            <FontAwesomeLayers class="me-2">
              <FontAwesomeIcon :icon="faStoreAlt" fixed-width/>
              <FontAwesomeIcon :icon="faPlusCircle" style="left: unset; right: -12px; bottom: -22px; width: 75%;" fixed-width/>
            </FontAwesomeLayers>
            {{ ctrans('Add Shop') }}
          </Button>
        </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <div v-if="currentTab === 'showcase'" class="grid gap-4 px-4 pt-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <SalesAnalysisTeaser :teaser="sales_analysis_teaser" />
    <SalesAnalysisMovers :teaser="sales_analysis_teaser" />
  </div>
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

  <Modal :isOpen="isOpenModalAddShop" width="w-full max-w-lg" @close="isOpenModalAddShop = false">
      <div>
        <div class="font-bold text-2xl text-center mb-4">
          {{ ctrans("Create Shop") }}
        </div>

        <div class="">
          {{ ctrans("Select organisation for the new shop") }}:
        </div>

        <div>
          <PureMultiselect
            v-model="selectedOrganisation"
            placeholder="Select one option"
            :options="organisationList"
            required
          />
        </div>

        <div class="mt-6">
          <Button
            v-tooltip="selectedOrganisation ? '' : 'Select an organisation to create shop'"
            :label="ctrans('Create shop')"
            :loading="isLoadingVisit"
            :disabled="!selectedOrganisation"
            @click="() => createShop()"
            iconRight="fal fa-arrow-right"
            full
          />
        </div>
      </div>
  </Modal>
</template>
