<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 17 Feb 2026 15:35:32 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Table as TableTS } from "@/types/Table"
import Icon from "@/Components/Icon.vue"
import { faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl } from "@fal"
import { faSkull, faWandMagic } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { Link } from "@inertiajs/vue3"
import { DeliveryNote } from "@/types/delivery-note"

library.add(faSkull, faArrowDown, faDebug, faClipboardListCheck, faUndoAlt, faHandHoldingBox, faListOl, faWandMagic)


defineProps<{
    data: TableTS
    tab?: string
}>()

function deliveryNoteRoute(deliveryNote: DeliveryNote) {
    return route("grp.helpers.redirect_delivery_notes", [deliveryNote.id])
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5" rowAlignTop>
        <!-- Column: state -->
        <template #cell(state)="{ item }">
            <Icon :data="item.state_icon" />
        </template>

        <template #cell(reference)="{ item: deliveryNote }">
            <div class="flex gap-2 flex-wrap items-center">
                <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                    {{ deliveryNote["reference"] }}
                </Link>
            </div>
        </template>

        <template #cell(date)="{ item }">
            {{
                useFormatTime(item.date, {
                    formatTime: "EEE, do MMM yy, HH:mm"
                })
            }}
        </template>


        <!-- Column: Quantity Required -->
        <template #cell(quantity_required)="{ item }">

            <span v-tooltip="item.quantity_required">
                <FractionDisplay v-if="item.quantity_required_fractional"
                                 :fractionData="item.quantity_required_fractional" />
                <span v-else>{{ item.quantity_required }}</span>

            </span>


        </template>

        <template #cell(quantity_dispatched)="{ item: item }">
            <FractionDisplay v-if="item.quantity_dispatched_fractional"
                             :fractionData="item.quantity_dispatched_fractional" />
            <span v-else>{{ item.quantity_dispatched }}</span>

        </template>

        <template #cell(quantity_picked)="{ item: item }">
            <FractionDisplay v-if="item.quantity_picked_fractional" :fractionData="item.quantity_picked_fractional" />
            <span v-else>{{ item.quantity_picked }}</span>

        </template>

        <template #cell(quantity_packed)="{ item: item }">
            <FractionDisplay v-if="item.quantity_packed_fractional" :fractionData="item.quantity_packed_fractional" />
            <span v-else>{{ item.quantity_packed }}</span>

        </template>


        <template #cell(quantity_to_pick)="{ item: item }">
            {{ item.quantity_to_pick }}
        </template>


    </Table>


</template>
