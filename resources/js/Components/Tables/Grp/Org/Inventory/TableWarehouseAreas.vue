<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3";
import Table from "@/Components/Table/Table.vue";
import { WarehouseArea } from "@/types/warehouse-area";
import Button from "@/Components/Elements/Buttons/Button.vue"
import Modal from "@/Components/Utils/Modal.vue"
import { ref } from "vue"
import { InputNumber } from "primevue"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

const props = defineProps<{
    data: object,
    tab?: string
}>();

function warehouseAreaRoute(warehouseArea: WarehouseArea) {
    console.log(route().current());
    switch (route().current()) {
        case "grp.overview.inventory.warehouses-areas.index":
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show",
                [
                    warehouseArea.organisation_slug,
                    warehouseArea.warehouse_slug,
                    warehouseArea.slug
                ]
            );
        case "grp.org.warehouses.show.infrastructure.warehouse_areas.index":
        default:
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show",
                [
                    route().params["organisation"],
                    route().params["warehouse"],
                    warehouseArea.slug
                ]
            );

    }

}

function locationsRoute(warehouseArea: WarehouseArea) {
    switch (route().current()) {
        case "grp.overview.inventory.warehouses-areas.index":
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index",
                [
                    warehouseArea.organisation_slug,
                    warehouseArea.warehouse_slug,
                    warehouseArea.slug
                ]
            );
        case "grp.org.warehouses.show.infrastructure.warehouse_areas.index":
        default:
            return route(
                "grp.org.warehouses.show.infrastructure.warehouse_areas.show.locations.index",
                [
                    route().params["organisation"],
                    route().params["warehouse"],
                    warehouseArea.slug
                ]);

    }

}

// Section: Change Picking Position
const isLoadingSubmit = ref(false)
const isOpenModal = ref(false)
const selectedWarehouseArea = ref<WarehouseArea | null>(null)
const submitOrderPosition = async () => {
    if (!selectedWarehouseArea.value) return;

    try {
        console.log('222')
        isLoadingSubmit.value = true
        const xxx = await axios.patch(route("grp.models.warehouse_area.update", {
            warehouseArea: selectedWarehouseArea.value?.id
        }), {
            picking_position: Number(selectedWarehouseArea.value.picking_position)
        })

        // console.log('111 Update response:', selectedWarehouseArea.value.picking_position)

        const qqq = props.data.data.find((item => item.id === selectedWarehouseArea.value?.id))
        console.log('qqq:', qqq);
        if (qqq) {
            qqq.picking_position = xxx.data.data.picking_position
        }
    } catch (error) {
        console.log('Error updating picking position:', error);
        notify({
            title: trans("Something went wrong"),
            text: "Failed to update picking position.",
            type: "error",
        })
    } finally {
        isLoadingSubmit.value = false
    }

    isOpenModal.value = false
}
</script>


<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Name -->
        <template #cell(name)="{ item: warehouseArea }">
            <Link :href="warehouseAreaRoute(warehouseArea)" class="primaryLink">
                {{ warehouseArea["name"] }}
            </Link>
        </template>

        <!-- Column: Picking Position -->
        <template #cell(picking_position)="{ item, proxyItem: warehouseArea }">
            <div class="mx-auto">
                <Button
                    @click="() => {
                        selectedWarehouseArea = item
                        isOpenModal = true
                    }"
                    size="xs"
                    :key="`set-order-position-${item.id}${item.picking_position}`"
                    :type="item.picking_position ? 'tertiary' : 'secondary'"
                    :icon="item.picking_position ? 'fal fa-pencil' : ''"
                    :label="item.picking_position ? `${item.picking_position}` : 'Set order position'"
                />
            </div>
        </template>

        <!-- Column: Number Locations -->
        <template #cell(number_locations)="{ item: warehouseArea }">
            <Link :href="locationsRoute(warehouseArea)" class="secondaryLink">
                {{ warehouseArea["number_locations"] }}
            </Link>
        </template>
    </Table>
    
    <!-- Modal: Picking Position -->
    <Modal :isOpen="isOpenModal" width="w-full max-w-lg" @close="isOpenModal = false">
        <div class="text-center font-semibold text-xl mb-4">
            {{ selectedWarehouseArea?.name }}
        </div>

        <div>
            {{ trans("Set picking order") }}
        </div>
        <InputNumber
            v-if="selectedWarehouseArea"
            v-model="selectedWarehouseArea.picking_position"
            xshowButtons="true"
            xbuttonLayout="'horizontal'"
            :min="0"
            :max="999"
            :placeholder="trans('Enter a number')"
            inputId="picking_position"
            class="w-full"
            mode="decimal"
            :maxFractionDigits="1"
            :disabled="isLoadingSubmit"
            @keydown.enter="() => submitOrderPosition()"
        />

        <div class="w-full mt-4">
            <Button
                @click="() => submitOrderPosition()"
                label="submit"
                full
                :loading="isLoadingSubmit"
            />
        </div>
    </Modal>
</template>
