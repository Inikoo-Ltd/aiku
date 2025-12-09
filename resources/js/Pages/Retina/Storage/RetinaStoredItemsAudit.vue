<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 15:57:55 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import BoxNote from "@/Components/Pallet/BoxNote.vue"
import BoxAuditStoredItems from '@/Components/Box/BoxAuditStoredItems.vue'

import { PageHeadingTypes } from "@/types/PageHeading"
import { library } from "@fortawesome/fontawesome-svg-core"
import TableStoredItemsAudits from "@/Components/Tables/Grp/Org/Fulfilment/TableStoredItemsAudits.vue"

import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Tag from "@/Components/Tag.vue"

import { Pallet, PalletDelivery } from '@/types/Pallet'
import { routeType } from "@/types/route"

import { faStickyNote, } from '@fal'
import Table from '@/Components/Table/Table.vue'
import { useFormatTime } from '@/Composables/useFormatTime'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Icon from '@/Components/Icon.vue'
import StoredItemsProperty from '@/Components/StoredItemsProperty.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
library.add(faStickyNote,)

const props = defineProps<{
    data: {
        data: PalletDelivery
    }
    storedItemsRoute: {
        store: routeType
        index: routeType
        delete: routeType
    }
    title: string
    pageHead: PageHeadingTypes
    notes_data: any
    pallets: any
    fulfilment_customer: any
    route_list: {
        update: routeType
    }
}>()
console.log(props)

function palletRoute(pallet: Pallet) {
    switch (route().current()) {
        case "grp.overview.fulfilment.pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    pallet.organisation_slug,
                    pallet.fulfilment_slug,
                    pallet.slug
                ])
        case "grp.org.fulfilments.show.operations.pallets.current.index":
            return route(
                "grp.org.fulfilments.show.operations.pallets.current.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.operations.returned_pallets.index":
            return route(
                "grp.org.fulfilments.show.operations.returned_pallets.index",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    pallet.slug
                ])

        case "grp.org.fulfilments.show.crm.customers.show":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.pallets.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    route().params["fulfilmentCustomer"],
                    pallet.slug
                ])
        case "grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.pallets.show",
                [
                    route().params["organisation"],
                    route().params["fulfilment"],
                    route().params["fulfilmentCustomer"],
                    pallet.slug
                ])

        default:
            return []
    }
}
</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="grid grid-cols-2 h-fit lg:max-h-64 w-full lg:justify-center border-b border-gray-300">
        <BoxNote v-for="(note, index) in notes_data" :key="index + note.label" :noteData="note"
            :updateRoute="route_list.update" />
    </div>
    <!-- <BoxAuditStoredItems :auditData="data.data" :boxStats="fulfilment_customer" /> -->
    <!-- <TableStoredItemsAudits :data="pallets" tab="pallets" :storedItemsRoute="storedItemsRoute" /> -->

    <Table :resource="pallets" name="pallets" class="mt-5">
        <!-- Column: Reference -->
        <template #cell(reference)="{ item: pallet }">
            <component :is="pallet.slug ? Link : 'div'" :href="pallet.slug ? palletRoute(pallet) : undefined"
                :class="pallet.slug ? 'primaryLink' : ''">
                {{ pallet.reference }}
            </component>
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


        <!-- Column: Icon (status and state) -->
        <template #cell(state)="{ item: pallet }">
            <div class="flex gap-x-1 gap-y-1.5">
                <Icon :data="pallet.type_icon" class="px-1" />
                <Icon :data="pallet['status_icon']" />
                <Icon :data="pallet['state_icon']" />
            </div>
        </template>


        <!-- Column: Notes -->
        <template #cell(notes)="{ item: pallet }">
            <div class="text-gray-500 italic">{{ pallet.notes }}</div>
        </template>


        <!-- Column: Stored Items -->
        <template #cell(stored_items)="{ item: pallet }">
            <!-- <table>
            <td>caca</td><td>2</td><td>  Checked (e.g. correct stock)   </td><td> + (add e.g. found item)   </td><td> -  (e.g. lost item)  </td>
            <td>popo</td><td>3</td><td>  Checked (e.g. correct stock)   </td><td> + (add e.g. found item)   </td><td> -  (e.g. lost item)  </td>
          </table> -->
            <!-- <pre>{{ pallet }}</pre> -->
            <!-- <pre>{{pallet   }}</pre> -->

            <DataTable v-if="pallet.stored_items?.length" :value="pallet.stored_items">
                <Column field="reference" header="Reference">
                    <template #body="{ data }">
                        <Tag :label="data.reference" no-hover-color string-to-color />
                    </template>
                </Column>

                <Column field="quantity" header="Quantity" sortable>

                </Column>

                <Column field="quantity" header="Checked (e.g. correct stock)">

                </Column>

                <Column field="quantity" header="+ (add e.g. found item)">

                </Column>

                <Column field="quantity" header="-  (e.g. lost item)">

                </Column>
            </DataTable>

            <div v-else class="text-gray-400">
                -
            </div>
        </template>

        <!-- Column: edited -->
        <template #cell(audits)="{ item }">

            <StoredItemsProperty :pallet="item" :storedItemsRoute="storedItemsRoute" :editable="true"
                :saveRoute="item.auditRoute" />

            <div v-if="item.audited_at" class="flex items-center justify-center">
                <font-awesome-icon :icon="['fas', 'check-circle']" class="text-lg text-green-500 mr-2"
                    v-tooltip="`Audited at: ${useFormatTime(item.audited_at)}`" />

                <Popover>
                    <template #button="{ isOpen }">
                        <Button label="Undo" type="tertiary" size="xs" icon="fal fa-history" />
                    </template>

                    <template #content="{ open, close }">
                        <div class="font-bold text-xs mb-3">are you sure to undo the Audit ?</div>
                        <div class="flex justify-end gap-1">
                            <Button label="No" type="tertiary" size="xs" @click="close()" />
                            <Link :href="route(item.resetAuditRoute.name, item.resetAuditRoute.parameters)"
                                method="delete" type="button" preserve-scroll>
                            <Button label="Yes" size="xs" />
                            </Link>
                        </div>
                    </template>
                </Popover>


            </div>
        </template>


    </Table>

</template>
