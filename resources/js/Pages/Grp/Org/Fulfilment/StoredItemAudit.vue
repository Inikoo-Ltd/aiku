<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 15:57:55 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import BoxAuditStoredItems from '@/Components/Box/BoxAuditStoredItems.vue'
import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import { PalletDelivery } from '@/types/Pallet'
import { routeType } from "@/types/route"
import { faStickyNote, faCheckCircle as falCheckCircle, faUndo, faArrowToLeft, faTrashAlt } from '@fal'
import { faCheckCircle } from '@fad'
import { faPlus, faMinus, faStar } from '@fas'
import { Table as TableTS } from '@/types/Table'
import TableStoredItemAuditDeltas from '@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemAuditDeltas.vue'
import TableEditStoredItemAuditDeltas from '@/Components/Tables/Grp/Org/Fulfilment/TableEditStoredItemAuditDeltas.vue'
library.add(faStickyNote, faPlus, faMinus, falCheckCircle, faUndo, faArrowToLeft, faTrashAlt, faCheckCircle, faStar)

const props = defineProps<{
    data: {
        data: PalletDelivery
    }
    title: string
    pageHead: PageHeadingTypes
    notes_data: any
    edit_stored_item_deltas: TableTS
    stored_item_deltas: TableTS
    fulfilment_customer: any
    route_list: {
        update: routeType
        stored_item_audit_delta: {
            update: routeType  // Update quantity
            store: routeType  // add new stored item
            delete: routeType  // undo select
        }
    }
    storedItemsRoute: {
        index: routeType  // Fetch list of stored items
        store: routeType  // Add stored items
        delete: routeType  // Delete stored items
    }
}>()

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="grid grid-cols-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
        <!-- <pre>{{ notes_data }}</pre> -->
        <BoxNote
            v-for="(note, index) in notes_data"
            :key="index + note.label"
            :noteData="note"
            :updateRoute="route_list.update"
        />
    </div>

    <BoxAuditStoredItems :auditData="data.data" :boxStats="fulfilment_customer" />
    <!-- <TableStoredItemsAudits :data="edit_stored_item_deltas" tab="edit_stored_item_deltas" :storedItemsRoute="storedItemsRoute" /> -->

    <!-- <pre>{{ edit_stored_item_deltas }}</pre> -->

    <TableEditStoredItemAuditDeltas
        v-if="edit_stored_item_deltas"
        :data="edit_stored_item_deltas"
        :route_list
        :storedItemsRoute
        tab="edit_stored_item_deltas"
    />

    <TableStoredItemAuditDeltas
        v-if="stored_item_deltas"
        :data="stored_item_deltas"
        tab="stored_item_deltas"
    />
</template>

<style scoped>
:deep(.p-inputtext) {
    padding: 0.5rem;
    font-size: 0.875rem;
    border: 1px solid transparent;
    background-color: transparent;
    border-radius: 0;
    box-shadow: 0;
    text-align: center;
}

:deep(.p-inputtext:enabled:hover) {
    border: 1px solid transparent;
}

:deep(.p-inputtext:enabled:focus) {
    border: 1px solid transparent;
    border-bottom: 1px solid rgb(192, 192, 192);
}
</style>