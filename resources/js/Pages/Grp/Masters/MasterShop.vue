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
import { trans } from "laravel-vue-i18n";
import { useLayoutStore } from "@/Stores/layout"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import Modal from "@/Components/Utils/Modal.vue"

library.add(faChartLine, faCheckCircle, faFolderTree, faFolder, faCube, faShoppingCart, faFileInvoice, faStickyNote,
  faMoneyBillWave, faFolderOpen, faAtom, faExclamationTriangle, faFolderDownload
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
  history?: {}
  shops?: {}
  organisations_list: {
    [key: string]: {
      label: string
    }
  }
}>();

let currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
const layout = useLayoutStore();

const component = computed(() => {

  const components = {
    showcase: ShopShowcase,
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
              <FontAwesomeIcon :icon="faStoreAlt"/>
              <FontAwesomeIcon :icon="faPlusCircle" style="left: unset; right: -12px; bottom: -22px; width: 75%;"/>
            </FontAwesomeLayers>
            {{ trans('Add Shop') }}
          </Button>
        </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :tab="currentTab" :data="props[currentTab]"></component>

  <Modal :isOpen="isOpenModalAddShop" width="w-full max-w-lg" @close="isOpenModalAddShop = false">
      <div>
        <div class="font-bold text-2xl text-center mb-4">
          {{ trans("Create Shop") }}
        </div>

        <div class="">
          {{ trans("Select organisation for the new shop") }}:
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
            :label="trans('Create shop')"
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
