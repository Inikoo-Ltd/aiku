<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 02 Apr 2024 20:10:35 Central Indonesia Time, Sanur , Indonesia
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, inject, ref } from "vue";
import { useFormatTime } from "@/Composables/useFormatTime";
import CustomerShowcaseStats from "@/Components/Showcases/Grp/CustomerShowcaseStats.vue";
import { routeType } from "@/types/route";
import { FulfilmentCustomerStats } from "@/types/Pallet";
import { trans } from "laravel-vue-i18n";
import TabSelector from "@/Components/Elements/TabSelector.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { Link } from "@inertiajs/vue3";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import Dialog from "primevue/dialog";
import { get } from "lodash-es";
import ButtonPrimeVue from "primevue/button";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faLink, faLongArrowRight } from "@far";
import {
  faArrowAltFromBottom, faArrowAltFromTop, faWallet,
  faSync,
  faCalendarAlt,
  faEnvelope,
  faPhone,
  faChevronRight,
  faExternalLink,
  faMapMarkerAlt,
  faAddressCard
} from "@fal";
import { Address, AddressManagement } from "@/types/PureComponent/Address";
import CountUp from "vue-countup-v3";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import CustomerDataForm from "@/Components/CustomerDataForm.vue";
import { faCheck, faTimes } from "@fas";
import ModalRejected from "@/Components/Utils/ModalRejected.vue";
import Modal from "@/Components/Utils/Modal.vue";
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue";
import ModalBalance from "@/Components/Utils/ModalBalance.vue";

library.add(
  faWallet,
  faLink,
  faSync,
  faCalendarAlt,
  faEnvelope,
  faPhone,
  faChevronRight,
  faExternalLink,
  faMapMarkerAlt,
  faAddressCard,
  faLongArrowRight,
  faCheck,
  faArrowAltFromBottom,
  faArrowAltFromTop
);

const props = defineProps<{
  data: {
    addresses: AddressManagement
    address_update_route: routeType
    balance: {
      current: number
      credit_transactions: number
    }
    currency_code: string
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
    updateRoute: routeType
    stats: {
      [key: string]: FulfilmentCustomerStats
    }
    warehouse_summary: {
      [key: string]: number
    }
    webhook: {
      webhook_access_key: string | null
      domain: string
      route: routeType
    }
    rental_agreement: {
      stats?: {
        data: {
          id: number
          slug: string
          reference: string
          state: string
          billing_cycle: string
          pallets_limit: number
          route: routeType
        }
      }
      createRoute: routeType
      updated_at: string
    }
    updateBalanceRoute: routeType
    recurring_bill: {
      route: routeType
      status: string // 'former' and 'current'
      start_date: string
      end_date: string
      total: number
      currency_code: string
    }

    status: string
    additional_data: {
      product: String
      size_and_weight: string
      shipments_per_week: string
    }
    approveRoute: routeType
  }
  tab: string
}>();

console.log(props);

const locale = inject("locale", aikuLocaleStructure);
const layout = inject("layout", layoutStructure);

// Tabs radio: v-model
// const radioValue = ref<string[]>(Object.keys(props.data.fulfilment_customer.radioTabs).filter(key => props.data.fulfilment_customer.radioTabs[key]))
const radioValue = computed(() => {
  return Object.keys(props.data.fulfilment_customer.radioTabs).filter(
    (key) => props.data.fulfilment_customer.radioTabs[key]
  );
});

// Tabs radio: options
const optionRadio = [
  {
    value: "pallets_storage",
    label: trans("Pallet Storage")
  },
  {
    value: "items_storage",
    label: trans("Dropshipping")
  },
  {
    value: "space_rental",
    label: trans("Space (Parking)")
  }
];

const isLoadingButtonRentalAgreement = ref(false);
const isLoading = ref<string | boolean>(false);
const visible = ref(false);
const _CustomerDataForm = ref();

const sendUpdateInformation = () => {
  _CustomerDataForm?.value.form.patch(
    route(props.data.updateRoute.name, props.data.updateRoute.parameters),
    {
      onSuccess: () => (visible.value = false)
    }
  );
};

const isModalUploadOpen = ref(false);
const isModalBalanceOpen = ref(false);
const balanceModalType = ref("");

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


const isModalAddress = ref(false);

</script>

<template>
  <!-- Section: Stats box -->
  <div class="px-4 py-5 md:px-6 lg:px-8 grid md:grid-cols-2 gap-x-8 lg:gap-x-12 gap-y-3">
    <div v-if="data.status === 'pending_approval'" class="w-full max-w-md justify-self-end">
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

    <div class="space-y-3">
      <!-- Section: Radio -->
      <div class="space-y-3 relative w-full max-w-[500px]">
        <!-- Section: Profile box -->
        <!-- <Transition name="headlessui" mode="out-in"> -->
        <div class="col-span-2 grid">
          <div class="w-full">
            <div class="rounded-lg shadow-sm ring-1 ring-gray-300">
              <dl class="flex flex-wrap">
                <div class="flex flex-col gap-y-2 w-full py-6">
                  <!-- Field: Contact name -->
                  <div
                    v-if="data.fulfilment_customer.customer.contact_name"
                    class="flex items-center w-full flex-none gap-x-4 px-6">
                    <dt v-tooltip="'Contact name'" class="flex-none">
                      <FontAwesomeIcon
                        icon="fal fa-address-card"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                      {{ data.fulfilment_customer.customer.contact_name }}
                    </dd>
                  </div>

                  <!-- Field: Company name -->
                  <div
                    v-if="data.fulfilment_customer.customer.company_name"
                    class="flex items-center w-full flex-none gap-x-4 px-6">
                    <dt v-tooltip="'Company name'" class="flex-none">
                      <FontAwesomeIcon
                        icon="fal fa-building"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    </dt>
                    <dd class="text-gray-500">
                      {{ data.fulfilment_customer.customer.company_name }}
                    </dd>
                  </div>

                  <!-- Field: Email -->
                  <div
                    v-if="data.fulfilment_customer.customer?.email"
                    class="flex items-center w-full flex-none gap-x-4 px-6">
                    <dt v-tooltip="'Email'" class="flex-none">
                      <FontAwesomeIcon
                        icon="fal fa-envelope"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    </dt>
                    <a
                      :href="`mailto:${data.fulfilment_customer.customer?.email}`"
                      v-tooltip="'Click to send email'"
                      class="text-gray-500 hover:text-gray-700"
                    >{{ data.fulfilment_customer.customer?.email }}</a
                    >
                  </div>

                  <!-- Field: Phone -->
                  <div
                    v-if="data.fulfilment_customer.customer?.phone"
                    class="flex items-center w-full flex-none gap-x-4 px-6">
                    <dt v-tooltip="'Phone'" class="flex-none">
                      <FontAwesomeIcon
                        icon="fal fa-phone"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    </dt>
                    <a
                      :href="`tel:${data.fulfilment_customer.customer?.phone}`"
                      v-tooltip="'Click to make a phone call'"
                      class="text-gray-500 hover:text-gray-700"
                    >{{ data.fulfilment_customer.customer?.phone }}</a
                    >
                  </div>

                  <!-- Field: Address -->
                  <div
                    v-if="data.fulfilment_customer.customer?.address"
                    class="flex items w-full flex-none gap-x-4 px-6">
                    <dt v-tooltip="'Address'" class="flex-none">
                      <FontAwesomeIcon
                        icon="fal fa-map-marker-alt"
                        class="text-gray-400"
                        fixed-width
                        aria-hidden="true" />
                    </dt>
                    <dd class="w-full max-w-96 text-gray-500">
                      <div
                        class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
												<span
                          class=""
                          v-html="
														data.fulfilment_customer.customer?.address
															.formatted_address
													" />

                        <div @click="() => isModalAddress = true"
                             class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
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

        <!-- Information -->
        <div
          v-if="
						get(data, ['additional_data', 'product'], false) ||
						get(data, ['additional_data', 'size_and_weight'], false) ||
						get(data, ['additional_data', 'shipments_per_week'], false)
					"
          class="col-span-2 grid">
          <div class="w-full">
            <div class="rounded-lg shadow-lg ring-1 ring-gray-300 bg-white p-6">
              <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Information</h2>
              </div>
              <div
                class="relative px-5 py-2 ring-1 ring-gray-300 rounded-lg bg-gray-50 shadow-sm space-y-2">
                <!-- Field: Product -->
                <div class="text-gray-600">
                  <strong class="text-gray-500">Product:</strong>
                  {{ get(data, ["additional_data", "product"], false) }}
                </div>

                <!-- Field: Size and Weight -->
                <div class="text-gray-600">
                  <strong class="text-gray-500">Size & Weight:</strong>
                  {{ get(data, ["additional_data", "size_and_weight"], false) }}
                </div>

                <!-- Field: Shipments Per Week -->
                <div class="text-gray-600">
                  <strong class="text-gray-500">Shipments Per Week:</strong>
                  {{
                    get(data, ["additional_data", "shipments_per_week"], false)
                  }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Box Group: Pallets -->
        <CustomerShowcaseStats v-if="data?.rental_agreement?.stats" :stats="data.stats" />
      </div>
    </div>

    <!-- Section: RadioBox, Recurring bills balance, Rental agreement-->
    <div v-if="data.status == 'approved'" class="w-full max-w-lg space-y-4 justify-self-end">
      <div
        class="bg-indigo-50 border border-indigo-300 text-gray-700 flex flex-col justify-between px-4 py-5 sm:p-6 rounded-lg tabular-nums">
        <div class="w-full flex justify-between items-center">
          <div>
            <div class="text-base capitalize">
              {{ trans("balance") }}
            </div>
            <div class="text-gray-700/60 text-sm leading-4 font-normal">
              {{ data.balance.credit_transactions }} credit transactions
            </div>
          </div>
          <div class="flex flex-col items-end">
            <!-- Amount Display -->
            <div class="text-2xl font-bold">
              <CountUp
                :endVal="data.balance.current"
                :duration="1.5"
                :scrollSpyOnce="true"
                :options="{
									formattingFn: (value) =>
										locale.currencyFormat(data.currency_code, value),
								}" />
            </div>
            <!-- Icon Row -->
            <div class="flex items-center">
              <button aria-label="Increase Balance" class="focus:outline-none">
                <FontAwesomeIcon
                  @click="openModalBalance('increase')"
                  :icon="faArrowAltFromBottom"
                  class="text-base"
                  tooltip="Increase Balance"
                  fixed-width
                  aria-hidden="true" />
              </button>
              <span class="mx-2 text-gray-400">|</span>
              <button aria-label="Decrease Balance" class="focus:outline-none">
                <FontAwesomeIcon
                  @click="openModalBalance('decrease')"
                  :icon="faArrowAltFromTop"
                  class="text-base"
                  tooltip="Decrease Balance"
                  fixed-width
                  aria-hidden="true" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <TabSelector
        :optionRadio="optionRadio"
        :radioValue="radioValue"
        :updateRoute="data.updateRoute" />

      <div class="border-t border-gray-200 pt-4 w-full max-w-full">
        <!-- Section: Recurring Bills -->
        <div
          v-if="data.recurring_bill"
          class="block group relative w-full gap-x-2 border border-gray-300 px-4 py-4 rounded-lg mb-4">
          <!-- <FontAwesomeIcon icon='fal fa-receipt' class='text-3xl text-gray-400' fixed-width aria-hidden='true' /> -->
          <div
            class="pl-2 leading-none text-lg"
            :style="{
							borderLeft: `4px solid ${layout.app.theme[0]}`,
						}">
            <div class="block text-lg font-semibold">{{ trans("Current Bill") }}</div>
            <div class="text-sm flex items-center gap-x-1">
              {{
                locale.currencyFormat(
                  data.recurring_bill.currency_code,
                  data.recurring_bill.total || 0
                )
              }}
            </div>
          </div>

          <!-- State Date & End Date -->
          <div class="pl-1 mt-4 w-80 lg:w-96 grid grid-cols-9 gap-x-3">
            <div class="col-span-4 text-sm">
              <div class="text-gray-400">{{ trans("Start date") }}</div>
              <div class="font-medium">
                {{ useFormatTime(data.recurring_bill.start_date) }}
              </div>
            </div>

            <div class="flex justify-center items-center">
              <FontAwesomeIcon
                icon="fal fa-chevron-right"
                class="text-xs"
                fixed-width
                aria-hidden="true" />
            </div>

            <div class="col-span-4 text-sm">
              <div class="text-gray-400">{{ trans("End date") }}</div>
              <div class="font-medium">
                {{ useFormatTime(data.recurring_bill.end_date) }}
              </div>
            </div>
          </div>

          <div class="pl-1 mt-6 w-full flex items-end justify-between">
            <div class="flex h-fit">
            </div>

            <Link
              :href="
								route(
									data.recurring_bill.route.name,
									data.recurring_bill.route.parameters
								)
							"
              @start="() => (isLoading = 'loadingVisitRecurring')"
              @error="() => (isLoading = false)">
              <Button
                :type="'tertiary'"
                :loading="isLoading === 'loadingVisitRecurring'"
                size="s"
                label="See details"
                iconRight="fal fa-external-link" />
            </Link>
          </div>
        </div>

        <!-- Section: Rental Agreement -->
        <div class="rounded-lg ring-1 ring-gray-300">
          <div
            class="border-b border-gray-300 py-2 px-2 pl-4 flex items-center justify-between">
            <div class="">
              {{ trans("Rental Agreement") }}
              <span
                v-if="data.rental_agreement.stats?.data?.reference"
                class="text-gray-400 text-sm"
              >#{{ data.rental_agreement.stats?.data?.reference }}</span
              >
            </div>
            <Link
              v-if="data.rental_agreement.stats"
              :href="
								route(
									data.rental_agreement.stats?.data?.route.name,
									data.rental_agreement.stats?.data?.route.parameters
								)
							"
              @start="() => (isLoadingButtonRentalAgreement = true)"
              @cancel="() => (isLoadingButtonRentalAgreement = false)">
              <Button type="edit" :loading="isLoadingButtonRentalAgreement" />
            </Link>
          </div>

          <!-- Stats -->
          <div v-if="data.rental_agreement.stats" class="p-5 space-y-2">
            <div class="flex gap-x-1 items-center text-sm">
              <div class="">{{ trans("Last updated") }}:</div>
              <div class="text-gray-500">
                {{ useFormatTime(data.rental_agreement.updated_at) }}
              </div>
            </div>
            <div class="flex gap-x-1 items-center text-sm">
              <div class="">{{ trans("Billing Cycle") }}:</div>
              <div class="text-gray-500 capitalize">
                {{ data.rental_agreement.stats?.data.billing_cycle }}
              </div>
            </div>
            <div class="flex gap-x-1 items-center text-sm">
              <div class="">{{ trans("Pallet Limit") }}:</div>
              <div class="text-gray-500">
                {{
                  data.rental_agreement.stats?.data.pallets_limit ||
                  `(${trans("No limit")})`
                }}
              </div>
            </div>
          </div>

          <div v-else class="text-center py-16">
            <div class="text-gray-500 text-xs mb-1">
              The rental agreement is not created yet.
            </div>
            <Link
              :href="
								route(
									data.rental_agreement.createRoute.name,
									data.rental_agreement.createRoute.parameters
								)
							"
              @start="() => (isLoadingButtonRentalAgreement = true)"
              @cancel="() => (isLoadingButtonRentalAgreement = false)">
              <Button
                type="secondary"
                label="Create Rental Agreement"
                :loading="isLoadingButtonRentalAgreement" />
            </Link>
          </div>
        </div>
      </div>
    </div>
  </div>

  <Dialog v-model:visible="visible" modal header="Edit Information" :style="{ width: '50rem' }">
    <CustomerDataForm ref="_CustomerDataForm" :data="data.additional_data" />
    <div class="flex justify-end">
      <Button label="save" type="save" @click="() => sendUpdateInformation()" />
    </div>
  </Dialog>

  <ModalRejected
    v-model="isModalUploadOpen"
    :customerID="customerID"
    :customerName="customerName" />

  <ModalBalance
    v-model="isModalBalanceOpen"
    :type="balanceModalType"
    :updateBalanceRoute="data.updateBalanceRoute"
  />

  <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
    <CustomerAddressManagementModal
      :addresses="data.address_management.addresses"
      :updateRoute="data.address_management.address_update_route"
    />
  </Modal>

</template>
