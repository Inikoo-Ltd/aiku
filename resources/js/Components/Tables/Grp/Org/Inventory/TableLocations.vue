<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 May 2024 18:31:26 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import { notify } from "@kyvg/vue3-notification";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ctrans } from "@/Composables/useTrans";
import { routeType } from "@/types/route";
import Table from "@/Components/Table/Table.vue";
import { Location } from "@/types/location";
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome";
import { faBox, faHandHoldingBox, faPallet, faPencil, faTrashAlt } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";
import { Table as TableTS } from "@/types/Table";
import { RouteParams } from "@/types/route-params";

library.add(faBox, faHandHoldingBox, faPallet, faPencil, faTrashAlt);

const props = defineProps<{
    data: TableTS,
    tab?: string
    bulkDeleteRoute?: routeType | null
}>();

const selectedRows = ref<Record<string, boolean>>({})
const tableKey = ref(0)
const isDeleteOpen = ref(false)

const selectedLocations = computed(() =>
    ((props.data as { data?: Location[] })?.data ?? []).filter((location) => selectedRows.value[location.id])
)
const confirmationWord = computed(() =>
    `DELETE ${selectedLocations.value.length} ${selectedLocations.value.length === 1 ? "LOCATION" : "LOCATIONS"}`
)

const deleteForm = useForm<{ locations: number[], confirmation: string }>({
    locations: [],
    confirmation: "",
})

function openDelete() {
    deleteForm.reset()
    deleteForm.clearErrors()
    isDeleteOpen.value = true
}

function onDelete() {
    if (!props.bulkDeleteRoute || deleteForm.confirmation !== confirmationWord.value) return

    deleteForm
        .transform((data) => ({ ...data, locations: selectedLocations.value.map((location) => location.id) }))
        .delete(route(props.bulkDeleteRoute.name, props.bulkDeleteRoute.parameters), {
            preserveScroll: true,
            onSuccess: () => {
                isDeleteOpen.value = false
                selectedRows.value = {}
                tableKey.value++
            },
            onError: (errors) => {
                notify({
                    title: ctrans("Locations not deleted"),
                    text: errors.locations ?? errors.confirmation ?? Object.values(errors)[0],
                    type: "error",
                })
            },
        })
}

const routeCurrent = route().current()
const routeParams = route().params

function locationRoute(location: Location) {
    switch (routeCurrent) {
        case "grp.org.warehouses.show.infrastructure.dashboard":
        case "grp.org.warehouses.show.infrastructure.locations.index":
        case "grp.org.warehouses.show.infrastructure.locations.all_empty":
        case "grp.org.warehouses.show.infrastructure.locations.partial_empty":
            return route(
                "grp.org.warehouses.show.infrastructure.locations.show",
                [
                    (routeParams as RouteParams).organisation,
                    (routeParams as RouteParams).warehouse,
                    location.slug]);
        case "grp.org.warehouse-areas.show":
        case "grp.org.warehouse-areas.locations.index":
        case  "grp.overview.inventory.locations.index":
            return route(
                "grp.org.warehouse-areas.show.locations.show",
                [
                    (routeParams as RouteParams).organisation,
                    (routeParams as RouteParams).warehouseArea,
                    location.slug]
            );

        case "grp.org.warehouses.show.infrastructure.warehouse_areas.show":
        case "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index":
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.show",
                [
                    (routeParams as RouteParams).organisation,
                    (routeParams as RouteParams).warehouse,
                    (routeParams as RouteParams).warehouseArea,
                    location.slug
                ]);

        default:
            return route(
                "grp.org.locations.show",
                [
                    (routeParams as RouteParams).organisation,
                    location.slug
                ]);
    }
}


</script>

<template>
    <div>
        <div v-if="bulkDeleteRoute" class="mt-5 flex justify-end px-4">
            <Button
                :class="{ invisible: !selectedLocations.length }"
                type="delete"
                icon="fal fa-trash-alt"
                :label="ctrans('Delete :count selected', { count: selectedLocations.length })"
                @click="openDelete"
            />
        </div>

        <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="!!bulkDeleteRoute" @onSelectRow="(value) => selectedRows = { ...value }" :key="tableKey">
            <!-- Column: Code -->
            <template #cell(code)="{ item: location }">
                <Link :href="locationRoute(location)" class="primaryLink">
                    {{ location.code }}
                </Link>
            </template>

            <!-- Column: Scope -->
            <template #cell(scope)="{ item: location }">
                <div class="flex">
                    <div v-tooltip="location.allow_stocks ? 'Allow stock' : 'No stock'" class="px-1 py-0.5">
                        <FontAwesomeIcon icon="fal fa-box" fixed-width aria-hidden="true"
                                         :class="[location.allow_stocks ? location.has_stock_slots ? 'text-green-500' : 'text-gray-400' : location.has_stock_slots ? 'text-red-500' : 'text-gray-400']"
                        />
                    </div>
                    <div v-tooltip="location.allow_dropshipping ? 'Allow dropshipping' : 'No dropshipping'" class="px-1 py-0.5">
                        <FontAwesomeIcon icon="fal fa-hand-holding-box" class="" fixed-width aria-hidden="true"
                                         :class="[location.allow_dropshipping ? location.has_dropshipping_slots ? 'text-green-500' : 'text-gray-400' : location.has_dropshipping_slots ? 'text-red-500' : 'text-gray-400']"
                        />
                    </div>
                    <div v-tooltip="location.allow_fulfilment ? 'Allow fulfilment' : 'No fulfilment'" class="px-1 py-0.5">
                        <FontAwesomeIcon icon="fal fa-pallet" class="" fixed-width aria-hidden="true"
                                         :class="[location.allow_fulfilment ? location.has_fulfilment ? 'text-green-500' : 'text-gray-400' : location.has_fulfilment ? 'text-red-500' : 'text-gray-400']"
                        />
                    </div>
                </div>


            </template>

        </Table>

        <Dialog
            v-model:visible="isDeleteOpen"
            :header="ctrans('Delete selected locations (:count)', { count: selectedLocations.length })"
            modal
            :style="{ width: '32rem' }"
        >
            <div class="space-y-3 text-sm">
                <p>{{ ctrans('Only empty locations can be deleted: no stock and no pallets in them. SKOs assigned with zero stock are unlinked.') }}</p>
                <p class="text-gray-500 break-words">{{ selectedLocations.map((location) => location.code).join(', ') }}</p>
                <label class="block">
                    <span class="mb-1 block text-gray-600">{{ ctrans('Type :word to confirm', { word: confirmationWord }) }}</span>
                    <InputText v-model="deleteForm.confirmation" class="w-full" :placeholder="confirmationWord" autofocus />
                </label>
                <p v-if="deleteForm.errors.locations" class="text-red-500">{{ deleteForm.errors.locations }}</p>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="isDeleteOpen = false" />
                    <Button
                        type="delete"
                        :label="ctrans('Delete')"
                        :loading="deleteForm.processing"
                        :disabled="deleteForm.confirmation !== confirmationWord"
                        @click="onDelete"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>
