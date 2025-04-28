<script setup lang="ts">
import { trans } from "laravel-vue-i18n";
import BoxStatPallet from "@/Components/Pallet/BoxStatPallet.vue";
import DatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { useFormatTime, retinaUseDaysLeftFromToday } from "@/Composables/useFormatTime";
import { notify } from "@kyvg/vue3-notification";
import { router } from "@inertiajs/vue3";
import Popover from "@/Components/Popover.vue";
import { PalletDelivery, BoxStats, PalletReturn, PDRNotes } from "@/types/Pallet";
import Modal from "@/Components/Utils/Modal.vue";
import { Switch, SwitchGroup, SwitchLabel } from "@headlessui/vue";
import { computed, inject, ref } from "vue";
import { capitalize } from "@/Composables/capitalize";
import { routeType } from "@/types/route";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import RetinaBoxNote from "@/Components/Retina/Storage/RetinaBoxNote.vue";
import OrderSummary from "@/Components/Summary/OrderSummary.vue";
import CustomerAddressManagementModal from "@/Components/Utils/CustomerAddressManagementModal.vue";
import DeliveryAddressManagementModal from "@/Components/Utils/DeliveryAddressManagementModal.vue";
import Textarea from "primevue/textarea";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBuilding, faIdCardAlt, faMapMarkerAlt, faPencil, faPenSquare, faCalendarDay } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import PalletEditCustomerReference from "@/Components/Pallet/PalletEditCustomerReference.vue";
import { AddressManagement } from "@/types/PureComponent/Address";

library.add(faBuilding, faIdCardAlt, faMapMarkerAlt, faPenSquare, faPencil, faCalendarDay);

const props = defineProps<{
  box_stats: BoxStats
  data_pallet: PalletDelivery | PalletReturn
  notes_data: { [key: string]: PDRNotes }
  address_management: {
    updateRoute: routeType
    addresses: AddressManagement
    address_update_route: routeType
    address_modal_title: string
  },
}>();

const layout = inject("layout", layoutStructure);
const deliveryListError = inject("deliveryListError", []);

const isLoadingSetEstimatedDate = ref<string | boolean>(false);
const isModalAddress = ref(false);
const isDeliveryAddressManagementModal = ref(false);
const enabled = ref(props.data_pallet?.is_collection || false);
const isLoading = ref<string | boolean>(false);
const textValue = ref(props.box_stats?.collection_notes);

// ✅ Auto-select collectionBy based on existing notes
const collectionBy = ref(props.box_stats?.collection_notes ? "thirdParty" : "myself");

// Computed property for switch toggle (Collection switch)
const computedEnabled = computed({
  get() {
    return enabled.value;
  },
  set(newValue: boolean) {
    const addressID = props.box_stats.fulfilment_customer.address?.address_customer?.value.id;
    const address = props.box_stats.fulfilment_customer.address?.address_customer?.value;

    if (!newValue) {
      // If NOT collection, reset to the selected address
      const filterDataAddress = { ...address };
      delete filterDataAddress.formatted_address;
      delete filterDataAddress.country;
      delete filterDataAddress.id;

      router[
      props.box_stats.fulfilment_customer.address.routes_address.store.method || "post"
        ](
        route(
          props.box_stats.fulfilment_customer.address.routes_address.store.name,
          props.box_stats.fulfilment_customer.address.routes_address.store.parameters
        ),
        {
          delivery_address_id: props.address_management.addresses?.current_selected_address_id || props.address_management.addresses?.pinned_address_id || props.address_management.addresses?.home_address_id
        },
        {
          preserveScroll: true,
          onSuccess: () => {
            notify({
              title: trans("Success"),
              text: trans("Set the address to selected address."),
              type: "success"
            });
          },
          onError: () =>
            notify({
              title: trans("Something went wrong"),
              text: trans("Failed to submit the address, try again"),
              type: "error"
            })
        }
      );
    } else {
      try {
        router.delete(
          route(props.box_stats.fulfilment_customer.address.routes_address.delete.name, {
            ...props.box_stats.fulfilment_customer.address.routes_address.delete.parameters
          }),
          {
            preserveScroll: true,
            onStart: () => (isLoading.value = "onDelete" + addressID),
            onFinish: () => {
              isLoading.value = false;
            }
          }
        );
        notify({
          title: trans("Success"),
          text: trans("Set the address to follow collection."),
          type: "success"
        });
      } catch (error) {
        console.error("Error disabling collection:", error);
        notify({
          title: trans("Something went wrong"),
          text: trans("Failed to disable collection."),
          type: "error"
        });
      }
    }
    enabled.value = newValue;
  }
});

// Update collection type (myself or thirdParty)
function updateCollectionType() {
  const payload: Record<string, any> = {
    collection_by: collectionBy.value
  };

  // Logic:
  // - If "myself", clear the notes (set to null)
  // - If "thirdParty", preserve whatever is in textValue (even empty string)
  if (collectionBy.value === "myself") {
    payload.collection_notes = null;
    textValue.value = null; // also clear in frontend
  }

  router.patch(
    route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
    payload,
    {
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: trans("Success"),
          text: trans("Collection type updated successfully"),
          type: "success"
        });
      },
      onError: () => {
        notify({
          title: trans("Something went wrong"),
          text: trans("Failed to update collection type"),
          type: "error"
        });
      }
    }
  );
}


// Update notes for thirdParty
function updateCollectionNotes() {
  router.patch(
    route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
    { collection_notes: textValue.value },
    {
      preserveScroll: true,
      onSuccess: () => {
        notify({
          title: trans("Success"),
          text: trans("Text updated successfully"),
          type: "success"
        });
      },
      onError: () => {
        notify({
          title: trans("Something went wrong"),
          text: trans("Failed to update text"),
          type: "error"
        });
      }
    }
  );
}

// Estimated delivery date change
const onChangeEstimateDate = async (close: Function) => {
  try {
    router.patch(
      route(props.address_management.updateRoute.name, props.address_management.updateRoute.parameters),
      {
        estimated_delivery_date: props.data_pallet.estimated_delivery_date
      },
      {
        onStart: () => isLoadingSetEstimatedDate.value = true,
        onError: () => {
          notify({
            title: "Failed",
            text: "Failed to update the Delivery date, try again.",
            type: "error"
          });
        },
        onSuccess: () => {
          const index = deliveryListError?.indexOf("estimated_delivery_date");
          if (index > -1) {
            deliveryListError?.splice(index, 1);
          }
          close();
        },
        onFinish: () => isLoadingSetEstimatedDate.value = false
      }
    );
  } catch (error) {
    console.log(error);
    notify({
      title: "Failed",
      text: "Failed to update the Delivery date, try again.",
      type: "error"
    });
  }
};

// Disable selecting past dates
const disableBeforeToday = (date: Date) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return date < today;
};
</script>


<template>
  <div class="h-min grid sm:grid-cols-2 lg:grid-cols-4 border-t border-b border-gray-200 divide-x divide-gray-300">
    <!-- Box: Detail -->
    <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class="pb-2 py-5 px-3"
                   :tooltip="trans('Detail')" :label="capitalize(data_pallet.state)" icon="fal fa-truck-couch">
      <!-- Field: Reference -->
      <dl as="a" v-if="box_stats.fulfilment_customer.customer.reference"
           class="flex items-center w-fit flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Reference</span>
          <FontAwesomeIcon icon="fal fa-id-card-alt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-gray-500">{{ box_stats.fulfilment_customer.customer.reference }}</dd>
      </dl>
      <!-- Field: Contact name -->
      <dl v-if="box_stats.fulfilment_customer.customer.contact_name"
           class="flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Contact name'" class="flex-none">
          <span class="sr-only">Contact name</span>
          <FontAwesomeIcon icon="fal fa-user" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-gray-500">{{ box_stats.fulfilment_customer.customer.contact_name }}</dd>
      </dl>
      <!-- Field: Company name -->
      <dl v-if="box_stats.fulfilment_customer.customer.company_name"
           class="flex items-center w-full flex-none gap-x-2">
        <dt v-tooltip="'Company name'" class="flex-none">
          <span class="sr-only">Company name</span>
          <FontAwesomeIcon icon="fal fa-building" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
        </dt>
        <dd class="text-gray-500">{{ box_stats.fulfilment_customer.customer.company_name }}</dd>
      </dl>
      <!-- Estimated Delivery Date -->
      <dl class="flex items-center w-full flex-none gap-x-2" :class="deliveryListError.includes('estimated_delivery_date') ? 'errorShake' : ''">
        <dt class="flex-none">
          <span class="sr-only">{{ box_stats.delivery_state.tooltip }}</span>
          <FontAwesomeIcon :icon="['fal', 'calendar-day']" :class="box_stats?.delivery_status?.class" fixed-width aria-hidden="true" size="xs" />
        </dt>
        <Popover v-if="data_pallet.state === 'in_process'" position="">
          <template #button>
            <div v-if="data_pallet?.estimated_delivery_date"
                 v-tooltip="retinaUseDaysLeftFromToday(data_pallet?.estimated_delivery_date)"
                 class="group text-sm text-gray-500">
              {{ useFormatTime(data_pallet?.estimated_delivery_date) }}
              <FontAwesomeIcon icon="fal fa-pencil" size="sm" class="text-gray-400 group-hover:text-gray-600" fixed-width aria-hidden="true" />
            </div>
            <div v-else class="text-sm text-gray-500 hover:text-gray-600 underline">
              {{ trans("Set estimated delivery") }}
            </div>
          </template>
          <template #content="{ close }">
            <DatePicker v-model="data_pallet.estimated_delivery_date"
                        @update:modelValue="() => onChangeEstimateDate(close)" inline auto-apply
                        :xdisabled-dates="disableBeforeToday" :enable-time-picker="false" />
            <div v-if="isLoadingSetEstimatedDate" class="absolute inset-0 bg-white/70 flex items-center justify-center">
              <LoadingIcon class="text-5xl" />
            </div>
          </template>
        </Popover>
        <div v-else>
          <dd class="text-sm text-gray-500">
            {{ data_pallet?.estimated_delivery_date ? useFormatTime(data_pallet?.estimated_delivery_date) : trans("Not Set") }}
          </dd>
        </div>
      </dl>
      <!-- Delivery Address / Collection by Section -->
      <div class="flex flex-col w-full gap-y-2 mb-1">
        <!-- Top Row: Icon dan Switch -->
        <dl class="flex items-center gap-x-2">
          <dt v-tooltip="trans('Pallet Return\'s address')" class="flex-none">
            <span class="sr-only">Delivery address</span>
            <FontAwesomeIcon icon="fal fa-map-marker-alt" size="xs" class="text-gray-400" fixed-width aria-hidden="true" />
          </dt>
          <SwitchGroup as="div" class="flex items-center">
            <Switch
              v-model="computedEnabled"
              :class="[computedEnabled ? 'bg-indigo-600' : 'bg-gray-200']"
              class="relative inline-flex h-6 w-11 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
							<span
                aria-hidden="true"
                :class="[computedEnabled ? 'translate-x-5' : 'translate-x-0']"
                class="pointer-events-none inline-block h-5 w-5 transform bg-white rounded-full shadow transition duration-200 ease-in-out" />
            </Switch>
            <SwitchLabel as="span" class="ml-3 text-sm font-medium text-gray-900">
              {{ trans("Collection") }}
            </SwitchLabel>
          </SwitchGroup>
        </dl>

        <div v-if="data_pallet.is_collection" class="w-full">
          <span class="block mb-1">{{ trans("Collection by:") }}</span>
          <div class="flex space-x-4">
            <label class="inline-flex items-center">
              <input
                type="radio"
                value="myself"
                v-model="collectionBy"
                @change="updateCollectionType"
                class="form-radio"
              />
              <span class="ml-2">{{ trans("My Self") }}</span>
            </label>
            <label class="inline-flex items-center">
              <input
                type="radio"
                value="thirdParty"
                v-model="collectionBy"
                @change="updateCollectionType"
                class="form-radio"
              />
              <span class="ml-2">{{ trans("Third Party") }}</span>
            </label>
          </div>

          <div v-if="collectionBy === 'thirdParty'" class="mt-3">
						<Textarea
              v-model="textValue"
              @blur="updateCollectionNotes"
              autoResize
              rows="5"
              class="w-full"
              cols="30"
              placeholder="Type additional notes..."
            />
          </div>
        </div>
        <div v-else class="w-full text-xs text-gray-500">
          Send to:
          <div class="relative px-2.5 py-2 ring-1 ring-gray-300 rounded bg-gray-50">
            <span v-html="box_stats.fulfilment_customer?.address?.value?.formatted_address" />
            <div
              @click="() => (isDeliveryAddressManagementModal = true)"
              class="whitespace-nowrap select-none text-gray-500 hover:text-blue-600 underline cursor-pointer">
              <span>{{trans('Edit')}}</span>
            </div>
          </div>
        </div>
      </div>
    </BoxStatPallet>

    <!-- Box: Notes -->
    <BoxStatPallet :color="{ bgColor: layout.app.theme[0], textColor: layout.app.theme[1] }" class="pb-2 pt-2 px-3"
                   :tooltip="trans('Notes')" :percentage="0">
      <!-- Customer reference -->
      <div class="mb-1">
        <PalletEditCustomerReference
          :dataPalletDelivery="data_pallet"
          :updateRoute="address_management.updateRoute"
          :disabled="data_pallet?.state !== 'in_process' && data_pallet?.state !== 'submitted'"
        />
      </div>
      <div class="grid gap-y-3 mb-3">
        <RetinaBoxNote
          :noteData="notes_data.return"
          :updateRoute="address_management.updateRoute"
        />
      </div>
      <div class="border-t border-gray-300 pt-1">
        <dl class="flex items-center w-full flex-none gap-x-2" :class="box_stats.delivery_state.class">
          <dt class="flex-none">
            <span class="sr-only">{{ box_stats.delivery_state.tooltip }}</span>
            <FontAwesomeIcon
              :icon="box_stats.delivery_state.icon"
              size="xs"
              fixed-width aria-hidden="true" />
          </dt>
          <dd class="">{{ box_stats?.delivery_state?.tooltip }}</dd>
        </dl>
      </div>
    </BoxStatPallet>

    <!-- Box: Order summary -->
    <BoxStatPallet class="sm:col-span-2 border-t sm:border-t-0 border-gray-300">
      <section aria-labelledby="summary-heading" class="rounded-lg px-4 py-4 sm:px-6 lg:mt-0">
        <h2 id="summary-heading" class="text-lg font-medium">Order summary</h2>
        <OrderSummary :order_summary="box_stats.order_summary" :currency_code="box_stats?.order_summary?.currency?.data?.code" />
      </section>
    </BoxStatPallet>
  </div>

  <Modal :isOpen="isModalAddress" @onClose="() => (isModalAddress = false)">
    <CustomerAddressManagementModal
      :addresses="box_stats.fulfilment_customer.address"
      :updateRoute="address_management.updateRoute"
    />
  </Modal>

  <Modal :isOpen="isDeliveryAddressManagementModal" @onClose="() => (isDeliveryAddressManagementModal = false)">
    <DeliveryAddressManagementModal
      :address_modal_title="address_management.address_modal_title"
      :addresses="address_management.addresses"
      :updateRoute="address_management.address_update_route"
    />
  </Modal>
</template>
