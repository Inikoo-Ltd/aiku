<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { library } from "@fortawesome/fontawesome-svg-core";
import {
  faBullhorn,
  faCameraRetro,
  faCube,
  faFolder,
  faMoneyBillWave,
  faProjectDiagram,
  faTag,
  faUser,
  faBrowser
} from "@fal";

import PageHeading from "@/Components/Headings/PageHeading.vue";
import { computed, defineAsyncComponent, ref } from "vue";
import { useTabChange } from "@/Composables/tab-change";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableCustomers from "@/Components/Tables/Grp/Org/CRM/TableCustomers.vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";
import { capitalize } from "@/Composables/capitalize";
import FamilyShowcase from "@/Components/Showcases/Grp/FamilyShowcase.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Modal from "@/Components/Utils/Modal.vue";

library.add(
  faFolder,
  faCube,
  faCameraRetro,
  faTag,
  faBullhorn,
  faProjectDiagram,
  faUser,
  faMoneyBillWave,
  faBrowser
);

const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));

const props = defineProps<{
  title: string;
  pageHead: object;
  tabs: {
    current: string;
    navigation: object;
  };
  customers: object;
  mailshots: object;
  showcase: object;
  details: object;
  history: object;
}>();

const currentTab = ref(props.tabs.current);
const isOpenModal = ref(false); // ✅ Added missing ref

const handleTabUpdate = (tabSlug: string) => {
  useTabChange(tabSlug, currentTab);
};

const component = computed(() => {
  const components = {
    showcase: FamilyShowcase,
    mailshots: TableMailshots,
    customers: TableCustomers,
    details: ModelDetails,
    history: ModelChangelog
  };
  return components[currentTab.value] ?? ModelDetails;
});
</script>

<template>
  <Head :title="capitalize(title)" />

  <PageHeading :data="pageHead">
   <!--  <template #button-index-1="{ action }">
      <Button
        :style="action.style"
        :label="action.label"
        :icon="action.icon"
        :iconRight="action.iconRight"
        :key="`ActionButton${action?.key}${action.style}`"
        :tooltip="action.tooltip"
        @click="isOpenModal = true"
      />
    </template> -->
  </PageHeading>

  <Tabs
    :current="currentTab"
    :navigation="tabs.navigation"
    @update:tab="handleTabUpdate"
  />

  <component
    :is="component"
    :data="props[currentTab]"
    :tab="currentTab"
  />

 <!--  <Modal :isOpen="isOpenModal" @onClose="isOpenModal = false">
    <div class="p-4">
      <p class="text-gray-700">This is a modal placeholder content.</p>
    </div>
  </Modal> -->
</template>
