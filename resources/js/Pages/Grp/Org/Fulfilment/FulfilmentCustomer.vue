<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import type { Component } from "vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { capitalize } from "@/Composables/capitalize";
import { library } from "@fortawesome/fontawesome-svg-core";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { computed, inject, onMounted, onUnmounted, ref } from "vue";
import TableRentalAgreementClauses from "@/Components/Tables/Grp/Org/Fulfilment/TableRentalAgreementClauses.vue";
import { useTabChange } from "@/Composables/tab-change";
import Tabs from "@/Components/Navigation/Tabs.vue";
import TableHistoryNotes from "@/Components/Tables/Grp/Org/Fulfilment/TableHistoryNotes.vue";
import FulfilmentCustomerShowcase from "@/Components/Showcases/Grp/FulfilmentCustomerShowcase.vue";
import UploadAttachment from "@/Components/Upload/UploadAttachment.vue";

import { trans } from "laravel-vue-i18n";
import {
  faStickyNote,
  faPallet,
  faUser,
  faNarwhal,
  faTruckCouch,
  faFileInvoiceDollar,
  faSignOutAlt,
  faPaperclip,
  faPaperPlane,
  faCheckDouble,
  faShare,
  faTruckLoading,
  faFileInvoice,
  faExclamationTriangle, faUsdCircle,
  faParking,
  faEnvelope,
  faWallet
} from "@fal";
import { notify } from "@kyvg/vue3-notification";
import { PageHeading as PageHeadingTypes } from "@/types/PageHeading";
import type { Navigation } from "@/types/Tabs";
import Modal from "@/Components/Utils/Modal.vue";
import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { routeType } from "@/types/route";
import TableAttachments from "@/Components/Tables/Grp/Helpers/TableAttachments.vue";
import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue";
import TableCustomerBalanceTransactions from "@/Components/Tables/Grp/Org/Fulfilment/TableCustomerBalanceTransactions.vue";
import { AddressManagement } from "@/types/PureComponent/Address";

library.add(faStickyNote, faEnvelope, faUser, faNarwhal, faWallet, faTruckCouch, faPallet, faFileInvoiceDollar, faSignOutAlt, faPaperclip, faPaperPlane, faCheckDouble, faShare, faTruckLoading, faFileInvoice, faExclamationTriangle, faUsdCircle, faParking);


const props = defineProps<{
  title: string
  pageHead: PageHeadingTypes,
  tabs: {
    current: string
    navigation: Navigation;
  }
  showcase?: {
    address_management: {
      updateRoute: routeType
      addresses: AddressManagement
      address_update_route: routeType,
      address_modal_title: string
    }
  }
  agreed_prices?: {}
  note: {},
  history: {},
  email?: {},
  balance?: {},
  attachments: {}
  attachmentRoutes: {
    attachRoute: routeType
    detachRoute: routeType
  }
  option_attach_file?: {
    name: string
    code: string
  }[]
}>();

const currentTab = ref(props.tabs.current);
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {

  const components: Component = {
    showcase: FulfilmentCustomerShowcase,
    agreed_prices: TableRentalAgreementClauses,
    history: TableHistories,
    note: TableHistoryNotes,
    attachments: TableAttachments,
    webhook: TableRentalAgreementClauses,
    email: TableDispatchedEmails,
    balance: TableCustomerBalanceTransactions
  };

  return components[currentTab.value];

});


const isModalOpen = ref(false);
const layout = inject("layout", layoutStructure);


onMounted(() => {
  window.Echo.private(`grp.${layout.group?.id}.fulfilmentCustomer.${layout.user.id}`).listen(".PalletDelivery", (e) => {
    notify({
      title: e.data.title,
      text: e.data.text,
      type: "info"
    });
  });
});

onUnmounted(() => {
  window.Echo.private(`grp.${layout.group?.id}.fulfilmentCustomer.${layout.user.id}`).stopListening(".PalletDelivery");
});

const isModalUploadFileOpen = ref(false);

</script>

<template>

  <Head :title="capitalize(title)" />
  <PageHeading :data="pageHead">

    <template #other>
      <Button
        v-if="currentTab === 'attachments'"
        @click="() => isModalUploadFileOpen = true"
        :label="trans('Attach file')"
        icon="fal fa-upload"
        type="secondary"
      />
    </template>
  </PageHeading>

  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
  <component
    :is="component"
    :data="props[currentTab]"
    :tab="currentTab"
    :detachRoute="attachmentRoutes.detachRoute"
  >
    <template #button-empty-state-attachments="{ action }">
      <Button
        v-if="currentTab === 'attachments'"
        @click="() => isModalUploadFileOpen = true"
        :label="trans('Attach file')"
        icon="fal fa-upload"
        type="secondary"
      />
    </template>
  </component>

  <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-1/4">
    <div class="text-center">
      <font-awesome-icon :icon="['fal', 'exclamation-triangle']" class="mx-auto h-12 w-12 text-gray-400" />
      <h3 class="mt-2 text-sm font-semibold text-gray-900">{{ trans("You Dont Have Rental Agreement") }}</h3>
      <p class="mt-1 text-sm text-gray-500">{{ trans("You need to make a rental agreement first to continue this") }}.</p>
      <div class="mt-6">
      </div>
    </div>
  </Modal>

  <UploadAttachment
    v-model="isModalUploadFileOpen"
    scope="attachment"
    :title="{
            label: 'Upload your file',
            information: 'The list of column file: customer_reference, notes, stored_items'
        }"
    progressDescription="Adding Pallet Deliveries"
    :attachmentRoutes
    :options="props.option_attach_file"
  />

</template>

