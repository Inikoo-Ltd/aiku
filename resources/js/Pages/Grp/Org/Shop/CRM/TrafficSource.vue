<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Deferred, Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { useTabChange } from "@/Composables/tab-change";
import { computed, defineAsyncComponent, ref } from "vue";
import type { Component } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue";
import CustomerShowcase from "@/Components/Showcases/Grp/CustomerShowcase.vue";
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import TableCustomerFavourites from "@/Components/Tables/Grp/Org/CRM/TableCustomerFavourites.vue";
import TableCustomerBackInStockReminders from "@/Components/Tables/Grp/Org/CRM/TableCustomerBackInStockReminders.vue";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue"
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCodeCommit, faUsers, faGlobe, faGraduationCap, faMoneyBill, faPaperclip, faPaperPlane, faStickyNote, faTags, faCube, faCodeBranch, faShoppingCart, faHeart } from "@fal";
import { routeType } from "@/types/route";
import { AddressManagement } from "@/types/PureComponent/Address";
import TableCreditTransactions from "@/Components/Tables/Grp/Org/Accounting/TableCreditTransactions.vue";
import TableCustomers from '@/Components/Tables/Grp/Org/CRM/TableCustomers.vue';
import TrafficSourceShowcase from "@/Components/Showcases/Grp/TrafficSourceShowcase.vue";
import TrafficSourceAudienceMix from "@/Components/DataDisplay/Dashboard/Widget/TrafficSourceAudienceMix.vue";
import TableMailshots from "@/Components/Tables/TableMailshots.vue";

library.add(faUsers);


const props = defineProps<{
  title: string
  pageHead: PageHeadingTypes
  tabs: {
    current: string
    navigation: {}
  }
  overview?: {}
  customers?: {}
  orders?: {}
  newsletters?: {}
  audience?: {
    buckets: {
      key: string
      label: string
      description: string
      colour: string
      count: number
      share: number
      is_acquisition: boolean
    }[]
    total: number
    identified: number
    acquisition: number
    window_days: number
    measured_from: string | null
  }
}>();

let currentTab = ref(props.tabs.current);
const isModalUploadOpen = ref(false);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
  const components: Component = {
    overview: TrafficSourceShowcase,
    customers: TableCustomers,
    orders: TableOrders,
    newsletters: TableMailshots
  };

  return components[currentTab.value];
});


</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead"></PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  
  <component
    :is="component"
    :data="props[currentTab as keyof typeof props]"
    :tab="currentTab"
    :handleTabUpdate
    />

  <!-- Overview only: it describes the channel as a whole, which is the question that tab asks. -->
  <div v-if="currentTab === 'overview'" class="mx-4 mt-4 rounded-xl bg-white p-5 ring-1 ring-gray-200">
    <Deferred data="audience">
      <template #fallback>
        <div class="space-y-3">
          <div class="h-4 w-1/3 animate-pulse rounded bg-gray-100" />
          <div class="h-2.5 w-full animate-pulse rounded-full bg-gray-100" />
          <div v-for="row in 4" :key="row" class="h-6 animate-pulse rounded bg-gray-100" />
        </div>
      </template>

      <TrafficSourceAudienceMix :mix="audience ?? null" />
    </Deferred>
  </div>
</template>
