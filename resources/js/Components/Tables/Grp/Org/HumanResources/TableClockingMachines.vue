<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
defineProps<{
  data: object,
  tab?: string
}>();

import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import Tag from "@/Components/Tag.vue";
import ModalClockingMachineKioskLink from "@/Components/HumanResources/ModalClockingMachineKioskLink.vue";
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue";
import { trans } from "laravel-vue-i18n";
import { faPencil, faTrash } from '@fortawesome/free-solid-svg-icons';
import { ClockingMachine } from "@/types/clocking-machine";

function clockingMachineRoute(clockingMachine: ClockingMachine) {
  switch (route().current()) {
    case "grp.org.hr.workplaces.show":
    case "grp.org.hr.workplaces.show.clocking_machines.index":
      return route(
        "grp.org.hr.workplaces.show.clocking_machines.show",
        [
          route().params.organisation,
          route().params.workplace,
          clockingMachine.slug
        ]);
    case "grp.overview.hr.clocking-machines.index":
      return route(
        "grp.org.hr.clocking_machines.show",
        [
          clockingMachine.organisation_slug,
          clockingMachine.slug
        ]);
    case "grp.org.hr.clocking_machines.index":
    default:
      return route(
        "grp.org.hr.clocking_machines.show",
        [
          route().params.organisation,
          clockingMachine.slug
        ]);
  }
}

function editClockingMachineRoute(clockingMachine: ClockingMachine) {
  switch (route().current()) {
    case "grp.org.hr.workplaces.show":
    case "grp.org.hr.workplaces.show.clocking_machines.index":
      return route(
        "grp.org.hr.workplaces.show.clocking_machines.edit",
        [
          route().params.organisation,
          route().params.workplace,
          clockingMachine.slug
        ]);
    case "grp.overview.hr.clocking-machines.index":
      return route(
        "grp.org.hr.clocking_machines.edit",
        [
          clockingMachine.organisation_slug,
          clockingMachine.slug
        ]);
    case "grp.org.hr.clocking_machines.index":
    default:
      return route(
        "grp.org.hr.clocking_machines.edit",
        [
          route().params.organisation,
          clockingMachine.slug
        ]);
  }
}

function workplaceRoute(clockingMachine: ClockingMachine) {
  return route(
    "grp.org.hr.workplaces.show",
    [
      route().params.organisation,
      clockingMachine.workplace_slug
    ]);

}

</script>

<template>
  <Table :resource="data" :name="tab" class="mt-5">
    <template #cell(name)="{ item: clockingMachine }">
      <Link :href="clockingMachineRoute(clockingMachine)" class="primaryLink">
        {{ clockingMachine["name"] }}
      </Link>
    </template>
    <template #cell(workplace_name)="{ item: clockingMachine }">
      <Link :href="workplaceRoute(clockingMachine)" class="secondaryLink">
        {{ clockingMachine["workplace_name"] }}
      </Link>
    </template>
    <template #cell(kiosk_enabled)="{ item: clockingMachine }">
      <Tag
        v-if="clockingMachine.kiosk_enabled !== null"
        :label="trans(clockingMachine.kiosk_enabled ? 'On' : 'Off')"
        :class="clockingMachine.kiosk_enabled
          ? 'bg-green-100 border border-green-200 text-green-600'
          : 'bg-gray-100 border border-gray-200 text-gray-500'" />
      <span v-else class="text-gray-300">—</span>
    </template>
    <template #cell(actions)="{ item: clockingMachine }">
      <div class="flex items-center gap-x-1">
        <ModalClockingMachineKioskLink
          v-if="clockingMachine.type === 'pin' || clockingMachine.type === 'barcode-scanner'"
          :clocking-machine="clockingMachine" />

        <Link :href="editClockingMachineRoute(clockingMachine)">
          <Button type="tertiary" size="xs" :icon="faPencil" :tooltip="trans('Edit')" />
        </Link>

        <ModalConfirmationDelete
          v-if="clockingMachine.delete_route"
          :routeDelete="clockingMachine.delete_route"
          :title="trans('Delete this clocking machine?')"
          :description="trans('This will also delete all clockings recorded on this machine. This action cannot be undone.')">
          <template #default="{ changeModel }">
            <Button type="cancel" :label="trans('Delete')" size="xs" :icon="faTrash" :tooltip="trans('Delete')" @click="changeModel(true)" />
          </template>
        </ModalConfirmationDelete>
      </div>
    </template>
  </Table>
</template>
