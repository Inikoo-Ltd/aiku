<script setup lang="ts">
import PureAddress from "@/Components/Pure/PureAddress.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { router } from "@inertiajs/vue3";
import { notify } from "@kyvg/vue3-notification";
import { computed, ref } from "vue";
import { routeType } from "@/types/route";
import { trans } from "laravel-vue-i18n";
import { Address, AddressManagement } from "@/types/PureComponent/Address";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faCheckCircle as faCheckCircleSolid } from "@fas";
import { faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, fal } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue";
import { useTruncate } from "@/Composables/useTruncate";
import { faThumbtack as faThumbtackSolid } from "@fas";

library.add(faThumbtack, faPencil, faHouse, faTrashAlt, faTruck, faTruckCouch, faCheckCircle, faThumbtack, faCheckCircleSolid);

const props = defineProps<{
  updateRoute: routeType
  addresses: AddressManagement
  keyPayloadEdit?: string
  address_modal_title?: string
}>();

const emits = defineEmits<{
    (e: "onDone"): void
    (e: "onHasChange"): void
}>()

const homeAddress = computed(() => {
  return props.addresses.address_list?.data.find(
    address => address.id === props.addresses.home_address_id
  );
});

// Method: Create new address
const isSubmitAddressLoading = ref(false);
const onSubmitNewAddress = async (address: Address) => {
  const filterDataAddress = { ...address };
  delete filterDataAddress.formatted_address;
  delete filterDataAddress.country;
  delete filterDataAddress.id;  // Remove id cuz create new one

  router[props.addresses.routes_list.store_route.method || "post"](
    route(props.addresses.routes_list.store_route.name, props.addresses.routes_list.store_route.parameters),
    {
      delivery_address: filterDataAddress
    },
    {
      preserveScroll: true,
      onStart: () => isSubmitAddressLoading.value = true,
      onFinish: () => {
        isSubmitAddressLoading.value = false,
          isCreateNewAddress.value = false;
      },
      onSuccess: () => {
        emits("onHasChange")
        notify({
          title: trans("Success"),
          text: trans("Successfully create new address."),
          type: "success"
        });
      },
      onError: () => notify({
        title: trans("Failed"),
        text: trans("Failed to submit the address, try again"),
        type: "error"
      })
    }
  );

};

// Method: Edit address history
const isEditAddress = ref(false);
const selectedAddress = ref<Address | { country_id: null }>({
  country_id: null
});
const onEditAddress = (address: Address) => {
  isEditAddress.value = true;
  selectedAddress.value = { ...address };
};
const onSubmitEditAddress = (address: Address) => {
  if (!props.updateRoute) {
    notify({
        title: trans("Failed to update the address"),
        text: trans("Please contact the administrator to fix."),
        type: "error",
    })

    return
  }

  const filterDataAddress = { ...address };
  delete filterDataAddress.formatted_address;
  delete filterDataAddress.country;
  delete filterDataAddress.country_code;

  router.patch(
    route(props.updateRoute.name, props.updateRoute.parameters),
    {
      [props.keyPayloadEdit || "address"]: filterDataAddress
    },
    {
      preserveScroll: true,
      onStart: () => isSubmitAddressLoading.value = true,
      onFinish: () => {
        isSubmitAddressLoading.value = false;
        isCreateNewAddress.value = false;
        // isModalAddress.value = false
        isEditAddress.value = false;
        
      },
      onSuccess: () => {
        emits("onHasChange")
        notify({
          title: trans("Success"),
          text: trans("Successfully update the address."),
          type: "success"
        });
      },
      onError: () => notify({
        title: trans("Failed"),
        text: trans("Failed to update the address, try again."),
        type: "error"
      })
    }
  );
};

// Method: Select address history
const isCreateNewAddress = ref(false);
const isSelectAddressLoading = ref<number | boolean | null | undefined>(false);
const onSelectAddress = (selectedAddress: Address) => {
  router.patch(
    route(props.addresses.routes_list.switch_route.name, props.addresses.routes_list.switch_route.parameters),
    {
      delivery_address_id: selectedAddress.id
    },
    {
      onStart: () => isSelectAddressLoading.value = selectedAddress.id,
      onSuccess: () => {
        emits("onHasChange")
      },
      onFinish: () => isSelectAddressLoading.value = false
    }
  );
};

const isLoading = ref<string | boolean>(false);
// Method: Pinned address
const onPinnedAddress = (addressID: number) => {
  router[props.addresses.routes_list.pinned_route.method || "patch"](
    route(props.addresses.routes_list.pinned_route.name, props.addresses.routes_list.pinned_route.parameters),
    {
      delivery_address_id: addressID
    },
    {
      preserveScroll: true,
      onStart: () => isLoading.value = "onPinned" + addressID,
      onSuccess: () => {
        emits("onHasChange")
      },
      onFinish: () => {
        isLoading.value = false;
      },
      onError: () => notify({
        title: trans("Failed"),
        text: "Failed to pinned the address, try again.",
        type: "error"
      })
    }
  );
};
// Method: Delete address
const onDeleteAddress = (addressID: number) => {
  router.delete(
    route(props.addresses.routes_list.delete_route.name, {
      ...props.addresses.routes_list.delete_route.parameters,
      address: addressID
    }),
    {
      preserveScroll: true,
      onStart: () => isLoading.value = "onDelete" + addressID,
      onSuccess: () => {
        emits("onHasChange")
      },
      onFinish: () => {
        isLoading.value = false;
      },
      onError: () => notify({
        title: trans("Failed"),
        text: trans("Failed to delete the address, try again"),
        type: "error"
      })
    }
  );
};


</script>

<template>
  <div class="h-[600px] px-2 py-1 overflow-auto">
    <div class="flex justify-between items-center border-b border-gray-300 py-2">
      <div class="text-2xl font-bold text-center flex gap-x-2">
        {{ address_modal_title ?? trans('Manage address') }}

        <div class="relative">
          <Transition name="slide-to-right">
            <div v-if="isEditAddress" class="inline text-gray-400 italic text-base font-normal">({{ trans("Edit") }})</div>
            <div v-else-if="isCreateNewAddress" class="inline text-gray-400 italic text-base font-normal">({{ trans("Create new") }})</div>
            <div v-else></div>
          </Transition>
        </div>
      </div>

      <div class="flex gap-x-2 h-fit">
        <Button v-if="isCreateNewAddress || isEditAddress" type="cancel" @click="() => (selectedAddress = {country_id: null}, isCreateNewAddress = false, isEditAddress = false)"></Button>
        <Button v-else label="New address" type="create" @click="() => (selectedAddress = {country_id: null}, isCreateNewAddress = true)"></Button>
      </div>
    </div>

    <div class="relative transition-all">
      <!-- <Transition name="v"> -->
      <div v-if="isCreateNewAddress" class="mx-auto max-w-96 py-4">
        <div class="mb-2">{{ trans("Create new address") }}</div>
        <div class="border border-gray-300 rounded-lg relative p-3 ">
          <PureAddress
            v-model="selectedAddress"
            :options="addresses.options"
            fieldLabel
          />
          <div class="mt-6 flex justify-center gap-x-2">
            <Button
              @click="() => onSubmitNewAddress(selectedAddress)"
              label="Create new and select"
              :loading="isSubmitAddressLoading"
              full
              :disabled="!selectedAddress?.country_id"
            />
          </div>
        </div>
      </div>

      <!-- Section: Edit address -->
      <div v-else-if="isEditAddress" :key="'edit' + selectedAddress?.id" class="col-span-2 relative py-4 h-fit grid grid-cols-2 gap-x-4">
        <div class="overflow-hidden relative text-xs rounded-lg h-fit transition-all"
             :class="[
                            selectedAddress?.id ? 'border border-gray-300 ring-2 ring-offset-4 ring-indigo-500' : 'ring-1 ring-gray-300'
                        ]"
        >
          <div class="flex justify-between border-b border-gray-300 px-3 py-2"
               :class="addresses.current_selected_address_id == selectedAddress?.id ? 'bg-green-50' : 'bg-gray-100'"
          >
            <div class="flex gap-x-1 items-center relative">
              <div v-if="[...addresses.address_list?.data].find(xxx => xxx.id === selectedAddress?.id)?.label" class="font-semibold text-sm whitespace-nowrap">
                {{ [...addresses.address_list.data].find(xxx => xxx.id === selectedAddress?.id)?.label }}
              </div>
              <div v-else class="text-xs italic whitespace-nowrap text-gray-400">
                ({{ trans("No label") }})
              </div>
              <div class="relative">
                <Transition name="spin-to-right">
                  <FontAwesomeIcon v-if="addresses.current_selected_address_id == selectedAddress?.id" :icon="faCheckCircleSolid" class="text-green-500" fixed-width aria-hidden="true" />
                  <Button
                    v-else
                    @click="() => onSelectAddress(selectedAddress)"
                    :label="isSelectAddressLoading == selectedAddress?.id ? '' : 'Select'"
                    size="xxs"
                    type="tertiary"
                    :loading="isSelectAddressLoading == selectedAddress?.id"
                  />
                </Transition>
              </div>
            </div>
          </div>

          <div v-html="selectedAddress?.formatted_address" class="px-3 py-2"></div>
        </div>

        <!-- Form: Edit address -->
        <div class="relative bg-gray-100 p-4 rounded-md">
          <div @click="() => (isEditAddress = false, selectedAddress = null)"
               class="absolute top-2 right-2 cursor-pointer">
            <FontAwesomeIcon icon="fal fa-times" class="text-gray-400 hover:text-gray-500"
                             fixed-width aria-hidden="true" />
          </div>
          <PureAddress
            v-model="selectedAddress"
            :options="addresses.options"
            fieldLabel
          />
          <div class="mt-6 flex justify-center">
            <Button
              @click="() => onSubmitEditAddress(selectedAddress)"
              :label="trans('Save')"
              :loading="isSubmitAddressLoading"
              full
            />
          </div>
        </div>
      </div>

      <!-- Section: Address list -->
      <div v-else class="relative py-4 h-fit">
        <template v-if="addresses.address_list?.data?.length">
          <div class="grid gap-x-3 gap-y-4 h-fit transition-all"
               :class="[isEditAddress ? '' : 'col-span-2 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4']">

            <!-- Section: Address Home -->
            <div v-if="homeAddress" class="overflow-hidden relative text-xs ring-1 ring-gray-300 rounded-lg h-full transition-all">
              <div class="flex justify-between border-b border-gray-300 px-3 py-2"
              :class="addresses.current_selected_address_id == homeAddress?.id ? 'bg-green-100' : 'bg-gray-100'"

              >
                <!-- {{ homeAddresses.id }} -->
                <div class="flex gap-x-1 items-center relative">
                  <div class="font-semibold text-sm whitespace-nowrap">
                    <FontAwesomeIcon icon="fal fa-house" class="" v-tooltip="'Contact Address'" fixed-width aria-hidden="true" />
                  </div>

                  <div class="relative">
                    <Transition name="spin-to-right">
                      <FontAwesomeIcon v-if="addresses.current_selected_address_id == homeAddress?.id" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
                      <Button
                        v-else
                        @click="() => onSelectAddress(homeAddress)"
                        :label="isSelectAddressLoading == homeAddress?.id ? '' : 'Use this'"
                        size="xxs"
                        type="tertiary"
                        :loading="isSelectAddressLoading == homeAddress?.id" />
                    </Transition>

                  </div>
                </div>

                <!-- Action: Pin, edit, delete -->
                <div class="flex items-center">
                  <LoadingIcon v-if="isLoading == 'onPinned' + homeAddress?.id" class="px-0.5" />
                  <FontAwesomeIcon v-else-if="addresses.address_list.data?.length > 1" @click="() => onPinnedAddress(homeAddress.id)" class="px-0.5 py-1 cursor-pointer "
                                   :icon="addresses.pinned_address_id === homeAddress?.id ?  faThumbtackSolid: faThumbtack"
                                   :class="addresses.pinned_address_id  === homeAddress?.id ? 'text-green-600' : 'text-gray-500 '" fixed-width aria-hidden="true"
                                   v-tooltip="(addresses.pinned_address_id  === homeAddress?.id ? trans('Current default delivery address') :trans('Select as default delivery address'))"
                  />
                  <FontAwesomeIcon @click="() => onEditAddress(homeAddress)" icon="fal fa-pencil" class="px-0.5 py-1 text-gray-400 hover:text-gray-600 cursor-pointer" fixed-width aria-hidden="true"
                                   v-tooltip="trans('Edit this address')" />
                </div>
              </div>

              <div v-html="homeAddress?.formatted_address" class="px-3 py-2"></div>
            </div>

            <!-- Section: Address looping -->
            <TransitionGroup>
              <div v-for="(address, idxAddress) in addresses.address_list.data.filter(xxx => xxx.id != addresses.home_address_id)"
                   :key="idxAddress + address.id"
                   class="overflow-hidden relative text-xs rounded-lg h-40"
                   :class="[
                                        selectedAddress?.id == address.id ? 'ring-2 ring-offset-4 ring-indigo-500' : 'ring-1 ring-gray-300 '
                                    ]"
              >
                <!-- {{ address.id }} -->
                <div class="flex justify-between border-b border-gray-300 px-3 py-2"
                :class="addresses.current_selected_address_id == address.id ? 'bg-green-100' : 'bg-gray-100'"

                >
                  <div class="flex gap-x-1 items-center relative">
                    <div v-if="address.label" class="font-semibold text-sm whitespace-nowrap">
                      {{ useTruncate(address.label, 14) }}
                    </div>
                    <div v-else class="text-xs italic whitespace-nowrap text-gray-400">
                      ({{ trans("No label") }})
                    </div>
                    <div class="relative">
                      <Transition name="spin-to-right">
                        <FontAwesomeIcon
                          v-if="addresses.current_selected_address_id == address.id"
                          :icon="faCheckCircleSolid"
                          class="text-green-500" fixed-width aria-hidden="true"
                        />
                        <Button
                          v-else
                          @click="() => onSelectAddress(address)"
                          :label="isSelectAddressLoading == address.id ? '' : 'Use this'"
                          size="xxs"
                          type="tertiary"
                          :loading="isSelectAddressLoading == address.id"
                          v-tooltip="'Apply to this section only'"
                        />
                      </Transition>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <LoadingIcon v-if="isLoading === 'onPinned' + address.id" class="px-0.5" />
                    <FontAwesomeIcon v-else-if="addresses.address_list.data?.length > 1"
                                     @click="() => addresses.pinned_address_id  === address.id? false : onPinnedAddress(address.id)"
                                     :icon="addresses.pinned_address_id === address.id ?  faThumbtackSolid: faThumbtack"

                                     class="px-0.5 py-1 cursor-pointer "
                                     :class="addresses.pinned_address_id === address.id ? 'text-green-600' : 'text-gray-500 '"
                                     fixed-width
                                     aria-hidden="true"
                                     v-tooltip="(addresses.pinned_address_id === address.id  ? trans('Current default delivery address') :trans('Select as default delivery address'))"

                    />

                    <FontAwesomeIcon v-if="address.can_edit" @click="() => onEditAddress(address)" icon="fal fa-pencil" class="px-0.5 py-1 text-gray-400 hover:text-gray-600 cursor-pointer" fixed-width aria-hidden="true"
                                     v-tooltip="trans('Edit this address')" />

                    <template v-if="address.can_delete">
                      <LoadingIcon v-if="isLoading === 'onDelete' + address.id" class="text-sm px-[1px]" />
                      <FontAwesomeIcon v-else @click="() => onDeleteAddress(address.id)" icon="fal fa-trash-alt" class="px-0.5 py-1 text-gray-400 hover:text-red-500 cursor-pointer" fixed-width aria-hidden="true"
                                       v-tooltip="trans('Delete this address')" />
                    </template>
                  </div>
                </div>
                <div v-html="address.formatted_address" class="px-3 py-2"></div>
              </div>
            </TransitionGroup>
          </div>
        </template>

        <div v-else class="text-sm flex items-center justify-center h-3/4 font-medium text-center text-gray-400">
          {{ trans("No address history found") }}
        </div>
      </div>

    </div>
    
  </div>
  
  <div class="border-t border-gray-300 pt-3">
    <Button @click="emits('onDone')" type="tertiary" label="done" full>
    
    </Button>
  </div>
</template>

<style scoped></style>