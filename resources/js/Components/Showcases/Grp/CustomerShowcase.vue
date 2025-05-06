<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Thu, 25 May 2023 15:03:05 Central European Summer Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { useFormatTime } from "@/Composables/useFormatTime";
import { routeType } from "@/types/route";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faLink } from "@far";
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faPencil } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";
import { ref } from "vue";
import Modal from "@/Components/Utils/Modal.vue";
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue";
import { Address, AddressManagement } from "@/types/PureComponent/Address";
import Tag from "@/Components/Tag.vue";
import { faCheck, faTimes } from "@fas";
import ModalRejected from "@/Components/Utils/ModalRejected.vue";
import ButtonPrimeVue from "primevue/button";
import { Link } from "@inertiajs/vue3";
import ButtonWithLink from "@/Components/Elements/Buttons/ButtonWithLink.vue";

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale, faCheck, faPencil);

interface CustomerDropshipping {
  slug: string;
  reference: string;
  name: string;
  contact_name: string;
  company_name: string;
  location: string[];
  email: string;
  phone: string;
  created_at: string;
  number_current_customer_clients: number | null;
  address: Address;
  is_dropshipping: boolean;
  state: string;
}

const props = defineProps<{
  data: {
    address_management: {
      can_open_address_management: boolean
      updateRoute: routeType
      addresses: AddressManagement
      address_update_route: routeType,
      address_modal_title: string
    }
    fulfilment_customer: {
      radioTabs: {
        [key: string]: boolean
      }
      number_pallets?: number
      number_pallets_state_received?: number
      number_stored_items?: number
      number_pallets_deliveries?: number
      number_pallets_returns?: number
      customer: {
        address: Address
      }
    }
    editWebUser:{}
    status: string
    customer: CustomerDropshipping
    updateRoute: routeType
    approveRoute: routeType
  },
  tab: string
}>();

const isModalAddress = ref(false);
const isModalUploadOpen = ref(false);
const isModalBalanceOpen = ref(false);
const balanceModalType = ref("");
const visible = ref(false);

const customerID = ref();
const customerName = ref();

function openModalBalance(type: string) {
  balanceModalType.value = type;
  isModalBalanceOpen.value = true;
}

function openRejectedModal(customer: any) {
  customerID.value = customer.id;
  customerName.value = customer.name;
  isModalUploadOpen.value = true;
}

const links = ref([
    { label: trans("Edit Web User"), route_target: props.data.editWebUser, icon: faPencil },
]);

</script>

<template>
  <!-- Section: Stats box -->
  <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-8">
    <div v-if="data.customer.status === 'pending_approval'" class="w-full max-w-md justify-self-end">
      <div class="p-5 border rounded-lg bg-white">
        <div class="flex flex-col items-center text-center gap-2">
          <h3 class="text-lg font-semibold text-gray-800">Pending Application</h3>
          <p class="text-sm text-gray-600">
            This application is currently awaiting approval.
          </p>
        </div>

        <div class="mt-5 flex justify-center gap-3">
          <Link
            :href="route(data.approveRoute.name, data.approveRoute.parameters)"
            method="patch"
            :data="{ status: 'approved' }">
            <ButtonPrimeVue
              class="fixed-width-btn"
              severity="success"
              size="small"
              variant="outlined">
              <FontAwesomeIcon :icon="faCheck" @click="visible = false" />
              <span> Approve </span>
            </ButtonPrimeVue>
          </Link>

          <ButtonPrimeVue
            class="fixed-width-btn"
            severity="danger"
            size="small"
            variant="outlined"
            @click="() => openRejectedModal(data.fulfilment_customer.customer)">
            <FontAwesomeIcon :icon="faTimes" @click="visible = false" />
            <span> Reject </span>
          </ButtonPrimeVue>
        </div>
      </div>
    </div>

    <!-- Section: Profile box -->
    <div>
      <div class="rounded-lg shadow-sm ring-1 ring-gray-900/5">
        <dl class="flex flex-wrap">
          <!-- Profile: Header -->
          <div class="flex w-full py-6">
            <div v-if="data?.customer.is_dropshipping" class="flex-auto pl-6">
              <dt class="text-sm text-gray-500">{{ trans("Total Clients") }}</dt>
              <dd class="mt-1 text-base font-semibold leading-6">{{ data?.customer?.number_current_customer_clients || 0 }}</dd>
            </div>

            <div class="flex-none self-end px-6">
              <dt class="sr-only">state</dt>
              <dd class="capitalize">
                <Tag :label="data?.customer?.state"
                     :theme="data?.customer?.state === 'active'
                                        ? 3
                                        : data?.customer?.state === 'lost'
                                            ? 7
                                            : 99
                                    "
                />
              </dd>
            </div>
          </div>

          <!-- Section: Field -->
          <div class="flex flex-col gap-y-3 border-t border-gray-900/5 w-full py-6">
            <!-- Field: Contact name -->
            <div v-if="data?.customer?.contact_name" class="flex items-center w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="trans('Contact name')" class="flex-none">
                <span class="sr-only">Contact name</span>
                <FontAwesomeIcon icon="fal fa-male" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="text-gray-500">{{ data?.customer?.contact_name }}</dd>
            </div>

            <!-- Field: Contact name -->
            <div v-if="data?.customer?.company_name" class="flex items-center w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="trans('Company name')" class="flex-none">
                <span class="sr-only">Company name</span>
                <FontAwesomeIcon icon="fal fa-building" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="text-gray-500">{{ data?.customer?.company_name }}</dd>
            </div>

            <!-- Field: Created at -->
            <div v-if="data?.customer?.created_at" class="flex items-center w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="trans('Created at')" class="flex-none">
                <span class="sr-only">Created at</span>
                <FontAwesomeIcon icon="fal fa-calendar-alt" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="text-gray-500">
                <time datetime="2023-01-31">{{ useFormatTime(data?.customer?.created_at) }}</time>
              </dd>
            </div>

            <!-- Field: Email -->
            <div v-if="data?.customer?.email" class="flex items-center w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="trans('Email')" class="flex-none">
                <span class="sr-only">Email</span>
                <FontAwesomeIcon icon="fal fa-envelope" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="text-gray-500">
                <a :href="`mailto:${data.customer.email}`">{{ data?.customer?.email }}</a>
              </dd>
            </div>

            <!-- Field: Phone -->
            <div v-if="data?.customer?.phone" class="flex items-center w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="trans('Phone')" class="flex-none">
                <span class="sr-only">Phone</span>
                <FontAwesomeIcon icon="fal fa-phone" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="text-gray-500">
                <a :href="`tel:${data.customer.email}`">{{ data?.customer?.phone }}</a>
              </dd>
            </div>

            <!-- Field: Address -->
            <div v-if="data?.customer?.address" class="relative flex items w-full flex-none gap-x-4 px-6">
              <dt v-tooltip="'Address'" class="flex-none">
                <FontAwesomeIcon icon="fal fa-map-marker-alt" class="text-gray-400" fixed-width aria-hidden="true" />
              </dt>
              <dd class="w-full text-gray-500">
                <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
                  <span class="" v-html="data?.customer?.address.formatted_address" />

                  <div v-if="data.address_management.can_open_address_management" @click="() => isModalAddress = true"
                       class="w-fit pr-4 whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
                    <span>{{ trans("Edit") }}</span>
                  </div>
                </div>
              </dd>
            </div>
          </div>
        </dl>
      </div>
    </div>
    <div class="justify-self-end">
        <div class="w-64 border border-gray-300 rounded-md p-2">
            <div v-for="(item, index) in links" :key="index" class="p-2">
                <ButtonWithLink
                    :routeTarget="item.route_target"
                    full
                    :icon="item.icon"
                    :label="item.label"
                    type="secondary"
                />
            </div>
        </div>
    </div>
  </div>

  <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
    <CustomerAddressManagementModal
      :addresses="data.address_management.addresses"
      :updateRoute="data.address_management.address_update_route"
    />
  </Modal>
  
  <ModalRejected
    v-model="isModalUploadOpen"
    :customerID="customerID"
    :customerName="customerName" />
</template>
