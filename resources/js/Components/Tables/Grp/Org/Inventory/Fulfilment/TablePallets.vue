<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 15:57:55 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link,router } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Icon from "@/Components/Icon.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import { faTrashAlt } from "@far";
import { faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote,faSquare } from "@fal";
import Tag from "@/Components/Tag.vue";
import Popover from "@/Components/Popover.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Multiselect from "@vueform/multiselect";
import axios from "axios";
import { inject, ref } from "vue";
import { notify } from "@kyvg/vue3-notification";
import type { Meta, Links } from "@/types/Table";
import { Pallet } from "@/types/Pallet";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { useLayoutStore } from "@/Stores/layout"
import ButtonAction from "@/Components/Pallet/ActionButton.vue"
import { trans } from "laravel-vue-i18n"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"

library.add(faTrashAlt, faSignOutAlt, faSpellCheck, faCheck, faTimes, faCheckDouble, faCross, faFragile, faGhost, faBoxUp, faStickyNote,faSquare);

const isMovePallet = inject("isMovePallet", false);

defineProps<{
  data: {
    data: {}[]
    links: Links
    meta: Meta
  },
  tab?: string
}>();

const layout = useLayoutStore()

const routeCurrent = route().current()
const routeParams = route().params

function palletRoute(pallet: Pallet) {
  switch (routeCurrent) {


    case 'grp.org.warehouses.show.inventory.pallets.current.index':
      return route(
        'grp.org.warehouses.show.inventory.pallets.current.show',
        [
          routeParams["organisation"],
          routeParams["warehouse"],
          pallet.slug
        ]);
    case 'grp.org.warehouses.show.fulfilment.locations.show' :
      return route(
        'grp.org.warehouses.show.fulfilment.locations.show.pallets.show',
        [
          routeParams["organisation"],
          routeParams["warehouse"],
          routeParams["location"],
          pallet.slug
        ]);

    default:
      return [];
  }
}

function locationRoute(pallet: Pallet) {

  return route(
    "grp.org.warehouses.show.fulfilment.locations.show",
    [
      routeParams["organisation"],
      routeParams["warehouse"],
      pallet.location_slug
    ]);
}

const palletSelected = ref<{ [key: string]: number } | null>({
  abc: 1
});  // Helper on which pallet selected to move
const isLoading = ref(false);
const locationsList = ref([]);

// Method: Get locations list from current Warehouse
const getLocationsList = async () => {
//   isLoading.value = true;
//   try {
//     const response = await axios.get(route("grp.org.warehouses.show.infrastructure.locations.index", { "organisation": layout?.currentParams?.organisation, "warehouse": layout?.currentParams?.warehouse }));
//     console.log(response.data)
//     // Add 'disabled' key to current location
//     locationsList.value = response.data.data.map(loc => {
//       if (loc.slug == route().params.location) {
//         return {
//           ...loc,
//           disabled: true
//         };
//       }
//       return loc;
//     });

//     // console.log('resposne', locationsList.value)
//     isLoading.value = false;
//   } catch (error) {
//     console.error(error);
//     isLoading.value = false;
//     // notify({
//     //     title: "Failed",
//     //     text: "Error while fetching data",
//     //     type: "error"
//     // })
//   }
};

// Method: On submit move pallet
const isLoadingMove = ref(false)
const onMovePallet = async (url: string, locationId: number, palletReference: string, closePopup: Function) => {
  router.patch(url,
    { location_id: locationId },
    {
      onStart: () => {
        isLoadingMove.value = true
      },
      onFinish: () => {
        isLoadingMove.value = false
      },
      onSuccess: (e) => {
        closePopup()
        notify({
          title: trans("Success!"),
          text: trans("Pallet has been moved"),
          type: "success"
        })
      }
    }
  )
}

const onUpdateStatus=(routes,data)=>{
  router.patch(route(routes.name, routes.parameters),data)
}


</script>

<template>
  <!-- <pre>{{ props.data.data[0] }}</pre> -->
  <Table :resource="data" :name="tab" class="mt-5" :is-check-box="false">
    <!-- Column: Reference -->

    <!-- Column: Pallet Reference -->
    <template #cell(reference)="{ item: pallet }">
      <Link :href="palletRoute(pallet)" class="primaryLink">
      {{ pallet.reference }}
      </Link>
    </template>

    <!-- Column: Customer Reference -->
    <template #cell(customer_reference)="{ item: item }">
      <div>
        {{ item.customer_reference }}
        <span v-if="item.notes" class="text-gray-400 text-xs ml-1">
          <FontAwesomeIcon icon="fal fa-sticky-note" class="text-gray-400" fixed-width aria-hidden="true" />
          {{ item.notes }}
        </span>
      </div>
    </template>

    <template #cell(location_code)="{ item: pallet }">
      <Link v-if="pallet.location_slug" :href="locationRoute(pallet)" class="secondaryLink">
      {{ pallet["location_code"] }}
      </Link>
    </template>

    <!-- Column: State -->
    <template #cell(state)="{ item: pallet }">
      <Icon :data="pallet['status_icon']" />
      <Icon :data="pallet['state_icon']" />
    </template>



    <!-- Column: Stored Items -->
    <template #cell(stored_items)="{ item: pallet }">
      <div v-if="pallet.stored_items.length" class="flex flex-wrap gap-x-1 gap-y-1.5">
        <Tag v-for="item of pallet.stored_items" :theme="item.id" :label="`${item.reference} (${item.quantity})`"
          :closeButton="false" :stringToColor="true">
          <template #label>
            <div class="whitespace-nowrap text-xs">
              {{ item["reference"] }} (<span class="font-light">{{ item["quantity"] }}</span>)
            </div>
          </template>
        </Tag>
      </div>
      <div v-else class="text-gray-400 text-xs italic">
        {{ trans("No items in this pallet") }}
      </div>
    </template>

    <!-- Column: Action (move pallet) -->
    <template #cell(actions)="{ item, proxyItem }">
      <div class="flex gap-x-1 gap-y-1.5 isolate">
        <!-- Action: Move Pallet -->
        <Popover v-if="item.status === 'storing' && isMovePallet" class="relative" position="bottom-[125%] right-1/2">
          <template #button="{ open }">
            <Button
              @click="() => (locationsList.length ? '' : getLocationsList(), palletSelected?.[item.reference] ? '' : palletSelected = { [item.reference]: item.location_id })"
              type="tertiary" tooltip="Move pallet to another location" label="Move" :key="item.index"
              :disabled="open"
              :size="'xs'" />
          </template>

          <template #content="{ close }">
            <div class="w-[250px]">
              <div class="text-xs px-1 my-2">{{ trans('Select new location to move')}}:</div>
              <div>
                <PureMultiselectInfiniteScroll
                    v-model="palletSelected[item.reference]"
                    :fetchRoute="{ name: 'grp.org.warehouses.show.infrastructure.locations.index', parameters: { organisation: layout?.currentParams?.organisation, warehouse: layout?.currentParams?.warehouse } }"
                    :placeholder="trans('Select warehouse')"
                    valueProp="id"
                    labelProp="code"
                    :isLoading="isLoadingMove"
                />
                <!-- <Multiselect ref="_multiselectRef" v-model="palletSelected[item.reference]" :canClear="false"
                  :canDeselect="false" label="code" valueProp="id" placeholder="Select location.."
                  :options="locationsList" :noResultsText="isLoading ? 'loading...' : 'No Result'">
                </Multiselect> -->
              </div>
              <div class="flex justify-end mt-2">
                <Button
                  @click="() => onMovePallet(route(item.updateLocationRoute.name, item.updateLocationRoute.parameters), palletSelected?.[item.reference], item.reference, close)"
                  type="primary"
                  full
                  tooltip="Move pallet"
                  :loading="isLoadingMove"
                  label="save"
                  :key="item.index + palletSelected?.[item.reference]"
                  :disabled="palletSelected?.[item.reference] == item.location_id" />
              </div>
            </div>
          </template>
        </Popover>

        <ButtonAction :item="item"/>
    <!--     <pre>{{ item }}</pre> -->
        <!-- Action: Set as storing, damaged, lost -->
        <!-- <div v-if="item.status === 'storing' && isMovePallet" class="flex gap-x-1 gap-y-2">
          <Button label="Set as damaged" type="negative" iconRight="fal fa-fragile" size="xs"
            @click="() => onUpdateStatus(item.updateRoute, { status: 'incident', state: 'damaged' })" />
          <Button label="Set as lost" type="negative" iconRight="fal fa-ghost" size="xs"
            @click="() => onUpdateStatus(item.updateRoute, { status: 'incident', state: 'lost' })" />
        </div>
        <div v-else-if="(item.status === 'lost' || item.status === 'damaged') && isMovePallet">
          <Button label="Undo" type="tertiary" icon="fal fa-box-up" size="xs" v-tooltip="`Set pallet as stored`" />
        </div> -->
      </div>
    </template>


    <template #cell(type_icon)="{ item: pallet }">
      <Icon :data="pallet.type_icon" class="px-1" />
    </template>


  </Table>
</template>

<style src="../../../../../../../../node_modules/@vueform/multiselect/themes/default.css"></style>
