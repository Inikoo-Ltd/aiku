<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { useTabChange } from "@/Composables/tab-change";
import { computed, defineAsyncComponent, ref } from "vue";
import type { Component } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue";
import CustomerShowcase from "@/Components/Showcases/Grp/CustomerShowcase.vue";
import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue";
import { PageHeading as PageHeadingTS } from "@/types/PageHeading";
import ModelDetails from "@/Components/ModelDetails.vue";
import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import TableCustomerFavourites from "@/Components/Tables/Grp/Org/CRM/TableCustomerFavourites.vue";
import TableCustomerBackInStockReminders from "@/Components/Tables/Grp/Org/CRM/TableCustomerBackInStockReminders.vue";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faCodeCommit, faUsers, faGlobe, faGraduationCap, faMoneyBill, faPaperclip, faPaperPlane, faStickyNote, faTags, faCube, faCodeBranch, faShoppingCart, faHeart } from "@fal";
import { routeType } from "@/types/route";
import { AddressManagement } from "@/types/PureComponent/Address";

library.add(faStickyNote, faUsers, faGlobe, faMoneyBill, faGraduationCap, faTags, faCodeCommit, faPaperclip, faPaperPlane, faCube, faCodeBranch, faShoppingCart, faHeart);
const ModelChangelog = defineAsyncComponent(() => import("@/Components/ModelChangelog.vue"));


const props = defineProps<{
  title: string
  pageHead: PageHeadingTS
  tabs: {
    current: string
    navigation: {}
  }
  showcase?: {
    address_management:{
      updateRoute: routeType
      addresses: AddressManagement
      address_update_route: routeType,
      address_modal_title: string
    }
  }
  orders?: {}
  products?: {}
  dispatched_emails?: {}
  web_users?: {}
  attachments?: {}
  attachmentRoutes?: {}
  favourites?: {}
  reminders?: {}
}>();

let currentTab = ref(props.tabs.current);
const isModalUploadOpen = ref(false);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
  const components: Component = {
    showcase: CustomerShowcase,
    products: TableProducts,
    orders: TableOrders,
    details: ModelDetails,
    history: ModelChangelog,
    dispatched_emails: TableDispatchedEmails,
    web_users: TableWebUsers,
    favourites: TableCustomerFavourites,
    reminders: TableCustomerBackInStockReminders,
    attachments: TableAttachments
  };

  return components[currentTab.value];
});


</script>

<template>
  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">
    <template #other>
      <Button v-if="currentTab === 'attachments'" @click="() => isModalUploadOpen = true" label="Attach" icon="upload" />
    </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" :detachRoute="attachmentRoutes.detachRoute" />

  <UploadAttachment v-model="isModalUploadOpen" scope="attachment" :title="{
        label: 'Upload your file',
        information: 'The list of column file: customer_reference, notes, stored_items'
    }" progressDescription="Adding Pallet Deliveries" :attachmentRoutes="attachmentRoutes" />
</template>
