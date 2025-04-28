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
import { faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { trans } from "laravel-vue-i18n";
import { ref } from "vue";
import Modal from "@/Components/Utils/Modal.vue";
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue";
import { Address, AddressManagement } from "@/types/PureComponent/Address";
import Tag from "@/Components/Tag.vue";

library.add(faLink, faSync, faCalendarAlt, faEnvelope, faPhone, faMapMarkerAlt, faMale);

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

defineProps<{
  data: {
    address_management: {
      can_open_address_management: boolean
      updateRoute: routeType
      addresses: AddressManagement
      address_update_route: routeType,
      address_modal_title: string
    }

    customer: CustomerDropshipping
    updateRoute: routeType

  },
  tab: string
}>();

const isModalAddress = ref(false);
</script>

<template>
  <!-- Section: Stats box -->
  <div class="px-4 py-5 md:px-6 lg:px-8 grid grid-cols-2 gap-x-8 gap-y-3">

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
  </div>

  <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
    <CustomerAddressManagementModal
      :addresses="data.address_management.addresses"
      :updateRoute="data.address_management.address_update_route"
    />
  </Modal>
</template>
