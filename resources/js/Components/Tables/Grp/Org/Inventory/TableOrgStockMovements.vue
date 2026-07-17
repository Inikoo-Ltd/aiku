<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { Stock } from "@/types/stock";
import { faBoxFull, faClipboardCheck, faDumpster, faHandsHelping, faInboxIn, faInboxOut, faInfoCircle, faMapSigns, faPersonCarry, faQuestionCircle, faTilde, faTruckLoading } from "@fal";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { trans } from "laravel-vue-i18n";
import OrgStockMovements from "@/Pages/Grp/Org/Inventory/OrgStockMovements.vue";
import { library } from "@fortawesome/fontawesome-svg-core";
import Icon from "@/Components/Icon.vue";
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue";
import { faNoteSticky } from "@fortawesome/free-solid-svg-icons";
import { ref } from "vue";
import { Dialog } from "primevue";
import { useBasicColor } from "@/Composables/useColors";
import { faTimes } from "@far";
import { useFormatTime } from "@/Composables/useFormatTime";

library.add(
  faTilde,
  faDumpster,
  faInfoCircle,
  faHandsHelping,
  faPersonCarry,
  faQuestionCircle
)

defineProps<{
  data: object
  tab?: string
}>();


function stockRoute(stock: Stock) {


  console.log(route().current());
  switch (route().current()) {
    case "grp.org.warehouses.show.inventory.org_stock_families.show":
      return route(
        "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          route().params["orgStockFamily"],
          stock.slug
        ]);
    case "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.slug
        ]);
    case "grp.overview.inventory.org-stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
        [
          stock.organisation_slug,
          stock.warehouse_slug,
          stock.slug
        ]);
    case "grp.org.warehouses.show.inventory.org_stocks.index":
      return route(
        "grp.org.warehouses.show.inventory.org_stocks.show",
        [
          route().params["organisation"],
          route().params["warehouse"],
          stock.slug
        ]);
    default:
        return null
  }
}

const locationRoute = (orgStockMovement, extraData = {}) => {
  return route('grp.org.warehouses.show.infrastructure.locations.show', {
    organisation: orgStockMovement.organisation_slug,
    warehouse: orgStockMovement.warehouse_slug,
    location: orgStockMovement.location_slug,
    ...extraData
  })
}

function deliveryNoteRoute(orgStockMovement) {
    return route("grp.majordomo.redirect_delivery_notes", [orgStockMovement.delivery_note_id])
}

const isNoteModalOpen = ref(false);
const selectedMovement = ref<{}|null>(null)

function noteBgColor(movement) {
  let colorBg = '#ffe975'; 
  let colorText = 'rgb(55, 65, 81)';

  if (movement?.type == 'audit') {
    colorBg = '#9bcefa'
  }

  return {
    'background-color': colorBg,
    'color': colorText
  }
}

function noteColor(movement) {
  let borderColor = '#ffd700'; 

  if (movement?.type == 'audit') {
    borderColor = '#67a5db'
  }

  return borderColor
}

</script>

<template>

  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(user)="{ item: orgStockMovement }">
      <span :class="orgStockMovement.user?.contact_name ? '' : ''" v-tooltip="orgStockMovement.user?.contact_name">
        {{ orgStockMovement.user ? `${orgStockMovement.user?.username}` : 'System' }}
      </span>
    </template>
    
    <template #cell(type)="{ item: orgStockMovement }">
      <span v-if="orgStockMovement.delivery_note_reference && orgStockMovement.delivery_note_id">
        <Link class="primaryLink !px-2 !py-1 !border !rounded-md !border-yellow-300 font-semibold" :href="deliveryNoteRoute(orgStockMovement)">
          <FontAwesomeIcon 
            :icon="faTruckLoading"
            class="pr-1"
          />
          {{ orgStockMovement.delivery_note_reference }}
        </Link>
      </span>
      <span v-else-if="orgStockMovement.is_migration_point" v-tooltip="ctrans('Anchor point. From where data is migrated from Aurora')">
        {{ ctrans('Migration Point') }}
        <FontAwesomeIcon
          :icon="faMapSigns"
          class="text-blue-500 ml-1"
        />
      </span>
      <span v-else>
        {{ orgStockMovement.type_label }}
      </span>
    </template>

    <template #cell(reason)="{ item: orgStockMovement }">
      <div class="flex justify-between">
        {{ orgStockMovement.reason_label }}
        <FontAwesomeIcon 
          v-if="orgStockMovement.note"
          v-tooltip="ctrans('View Note')"
          :icon="faNoteSticky"
          class="my-auto hover:text-yellow-300 transition cursor-pointer text-lg"
          :style="{
            color: noteColor(orgStockMovement)
          }"
          @click="() => {
            selectedMovement = orgStockMovement
            isNoteModalOpen = true
          }"
        />
      </div>
    </template>

    <template #cell(location_code)="{ item: orgStockMovement }">
      <div class="flex flex-wrap gap-2 justify-between">
        <Link class="primaryLink" :href="locationRoute(orgStockMovement)">
          {{ orgStockMovement.location_code }}
        </Link>
        <Link class="my-auto" :href="locationRoute(orgStockMovement, {tab: 'stock_movements'})">
          <span v-if="orgStockMovement.type == 'disassociate' || orgStockMovement.type == 'associate'">
          </span>
          <span v-else-if="orgStockMovement.flow == 'audit'" class="my-auto ml-auto px-2 py-[0.125rem] border rounded-md border-blue-300 text-blue-500 bg-blue-100" v-tooltip="ctrans('Audited quantity under this location')">
            <FontAwesomeIcon 
              :icon="faBoxFull"
            />
            <FractionDisplay v-if="orgStockMovement.audited_quantity_fractional" :fractionData="orgStockMovement.audited_quantity_fractional" class="ml-1"/>
            <span v-else>
              {{ orgStockMovement.audited_quantity ?? 0 }}
            </span>
          </span>
          <span v-else class="my-auto ml-auto px-2 py-[0.125rem] border rounded-md border-gray-400" v-tooltip="ctrans('Running quantity under this location')">
            <FontAwesomeIcon 
              :icon="faBoxFull"
            />
            <FractionDisplay v-if="orgStockMovement.running_quantity_fractional" :fractionData="orgStockMovement.running_quantity_fractional" class="ml-1"/>
            <span v-else>
              {{ orgStockMovement.running_quantity ?? 0 }}
            </span>
          </span>
        </Link>
      </div>
    </template>

    <template #cell(class)="{item}">
      <Icon
        :data="item.class_icon"
      />
    </template>

    <template #cell(flow)="{ item: orgStockMovement }">
      <FontAwesomeIcon v-if="orgStockMovement.flow == 'in'" v-tooltip="trans('Stock Coming In')" :icon="faInboxIn" class="text-green-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'out'" v-tooltip="trans('Stock Coming Out')" :icon="faInboxOut" class="text-red-500"/>
      <FontAwesomeIcon v-else-if="orgStockMovement.flow == 'audit'" v-tooltip="trans('Stock Audited')" :icon="faClipboardCheck" class="text-gray-500"/>
    </template>

    <template #cell(quantity)="{ item: orgStockMovement }">
      <span v-if="Number(orgStockMovement.quantity) != 0" :class="Number(orgStockMovement.quantity) == 0 ? 'border-gray-300' : (orgStockMovement.is_negative ? 'text-red-500 bg-red-100 border-red-300' : 'text-green-500 bg-green-100 border-green-300')" class="px-3 py-[0.125rem] border rounded-md w-fit min-w-14 text-center justify-self-end">
        <FractionDisplay v-if="orgStockMovement.quantity_fractional" :fractionData="orgStockMovement.quantity_fractional" :showPlus="Number(orgStockMovement.quantity) > 0"/>
        <span v-else>
          {{ orgStockMovement.quantity ?? 0 }}
        </span>
      </span>
      <span v-else>
      </span>
    </template>

    <template #cell(running_quantity_location)="{ item: orgStockMovement }">
      <span v-if="orgStockMovement.type == 'disassociate' || orgStockMovement.type == 'associate'">
      </span>
      <span v-else-if="orgStockMovement.flow == 'audit'" class="my-auto ml-auto px-2 py-[0.125rem] border rounded-md border-blue-300 text-blue-500 bg-blue-100" v-tooltip="ctrans('Audited quantity under this location')">
        <FontAwesomeIcon 
          :icon="faBoxFull"
        />
        <FractionDisplay v-if="orgStockMovement.audited_quantity_fractional" :fractionData="orgStockMovement.audited_quantity_fractional" class="ml-1"/>
        <span v-else>
          {{ orgStockMovement.audited_quantity ?? 0 }}
        </span>
      </span>
      <span v-else class="my-auto ml-auto px-2 py-[0.125rem] border rounded-md border-gray-400" v-tooltip="ctrans('Running quantity under this location')">
        <FontAwesomeIcon 
          :icon="faBoxFull"
        />
        <FractionDisplay v-if="orgStockMovement.running_quantity_fractional" :fractionData="orgStockMovement.running_quantity_fractional" class="ml-1"/>
        <span v-else>
          {{ orgStockMovement.running_quantity ?? 0 }}
        </span>
      </span>
    </template>

    <template #cell(running_quantity_org_stock)="{ item: orgStockMovement }">
      <span
        :class="[
            orgStockMovement.flow == 'audit' 
            ? 'text-blue-500' 
            : ''
        ]">
        <FractionDisplay v-if="orgStockMovement.running_quantity_org_stock_fractional" :fractionData="orgStockMovement.running_quantity_org_stock_fractional"/>
        <span v-else>
          {{ orgStockMovement.running_quantity_org_stock ?? 0 }}
        </span>
      </span>
    </template>
  </Table>

  <Dialog
    v-model:visible="isNoteModalOpen" 
    modal 
    :style="{ 
      width: '30vw', 
      overflow:'hidden', 
      'border-width': '1px',
      'border-style': 'solid',
      'border-color': noteColor(selectedMovement)
    }"
    :dismissableMask="true"
    :closeOnEscape="true"
    :breakpoints="{
      '1360px': '60vw',
      '1200px': '75vw',
      '992px': '80vw',
      '768px': '90vw',
      '576px': '95vw'
    }"
  >
    <template #container>
      <div 
        class="py-2 px-1" 
        :style="noteBgColor(selectedMovement)"
      >
        <div class="top-0 left-0 w-full flex gap-x-1 lg:pr-0 justify-between h-full">
          <div class="flex flex-row w-full text-md font-semibold truncate gap-x-2 text-center py-0.5 pl-3 pr-3" style="">
            <FontAwesomeIcon 
              :icon="faNoteSticky"
              class="my-auto"
            />
            <span 
              class="align-middle" 
              style="color: rgb(55, 65, 81);"
            >
              {{ ctrans('Movement Note') }}
              <span class="my-auto ml-2 px-2 py-[0.125rem] border rounded-md border-blue-300 text-blue-500 bg-blue-100 text-xs">
                {{ selectedMovement.location_code }}
                <FontAwesomeIcon 
                  :icon="faBoxFull"
                />
                <FractionDisplay v-if="selectedMovement.running_quantity_fractional" :fractionData="selectedMovement.running_quantity_fractional" class="ml-1"/>
                <span v-else>
                  {{ selectedMovement.running_quantity ?? 0 }}
                </span>
              </span>
            </span>
            
          </div>
          <div class="mr-2 text-lg my-auto">
            <FontAwesomeIcon
              :icon="faTimes"
              class="cursor-pointer"
              @click="() => isNoteModalOpen = false"
            />
          </div>
        </div>
      </div>
      <div class="w-full grid grid-cols-3">
        <div class="grid text-xs py-1 px-3 border border-grey-700">
          <div class="font-semibold">
            {{ ctrans('Type') }}:
          </div>
          <span class="my-auto">
            {{ selectedMovement.type_label }}
          </span>
        </div>
        <div class="grid text-xs py-1 px-3 border border-grey-700">
          <span class="font-semibold">
            {{ ctrans('Reason') }}:
          </span>
          <span class="my-auto">
            {{ selectedMovement.reason_label }}
          </span>
        </div>
        <div class="grid text-xs py-1 px-3 border border-grey-700">
          <span class="font-semibold">
            {{ ctrans('User') }}:
          </span>
          <span class="my-auto">
            {{ selectedMovement.user?.contact_name ?? ctrans('System') }}
          </span>
        </div>
      </div>
      <p class="h-full w-full max-h-32 mx-auto px-4 py-3 text-sm break-words">
        {{ selectedMovement?.note }}
      </p>
      <div class="w-full text-right px-3 text-xs pt-3 pb-2">
        {{ useFormatTime(selectedMovement.date, { formatTime: 'hms' }) }}
      </div>
    </template>
  </Dialog>
</template>


