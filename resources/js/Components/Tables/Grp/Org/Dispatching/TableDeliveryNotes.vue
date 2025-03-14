<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { DeliveryNote } from "@/types/delivery-note"
import { Tab } from "@headlessui/vue"
import type { Table as TableTS } from "@/types/Table"
import { inject } from "vue"
import { layoutStructure } from "@/Composables/useLayoutStructure"
import { useFormatTime } from '@/Composables/useFormatTime'
import Icon from "@/Components/Icon.vue"
import { useLocaleStore } from "@/Stores/locale";

const props = defineProps<{
    data: TableTS,
    tab?: string
}>()

const locale = useLocaleStore();
const layout = inject('layout', layoutStructure)

function deliveryNoteRoute(deliveryNote: DeliveryNote) {
     console.log(route().current())
    switch (route().current()) {
        case "shops.show.orders.show":
            return route(
                "shops.show.orders.show.delivery-notes.show",
                [route().params["shop"], route().params["order"], deliveryNote.slug])
        case "orders.show":
            return route(
                "orders.show,delivery-notes.show",
                [route().params["order"], deliveryNote.slug])
        case "shops.show.delivery-notes.index":
            return route(
                "shops.show.delivery-notes.show",
                [deliveryNote.shop_id, deliveryNote.slug])
        case "grp.org.warehouses.show.dispatching.delivery-notes":
            return route(
                "grp.org.warehouses.show.dispatching.delivery-notes.show",
                [route().params["organisation"], route().params["warehouse"], deliveryNote.slug])
        case "grp.org.shops.show.ordering.delivery-notes.index":
            return route(
                    "grp.org.shops.show.ordering.delivery-notes.index",
                    [route().params["organisation"], route().params["shop"]])
        case "grp.org.shops.show.ordering.orders.index":
            return route(
                "grp.org.shops.show.ordering.show.delivery-note.show",
                [route().params["organisation"], route().params["shop"], deliveryNote.slug])
        case "grp.org.shops.show.ordering.orders.show":
            return route(
                "grp.org.shops.show.ordering.orders.show.delivery-note",
                [route().params["organisation"], route().params["shop"], route().params["order"], deliveryNote.slug])
        case "grp.org.shops.show.crm.customers.show.delivery_notes.index":
            return route(
                "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], deliveryNote.slug])
        case "grp.org.shops.show.crm.customers.show.orders.show":
            return route(
                "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], deliveryNote.slug])
        case "grp.overview.ordering.delivery_notes.index":
            return route(
               "grp.org.shops.show.crm.customers.show.delivery_notes.show",
                [deliveryNote.organisation_slug, deliveryNote.shop_slug, deliveryNote.customer_slug, deliveryNote.slug])
        default:
            return route(
                "grp.org.warehouses.show.dispatching.delivery-notes.show",
                [route().params["organisation"], route().params["warehouse"], deliveryNote.slug]);
    }
}

function customerRoute(deliveryNote: DeliveryNote) {
    switch (route().current()) {
         case "grp.overview.ordering.delivery_notes.index":
            return route(
               "grp.org.shops.show.crm.customers.show",
                [deliveryNote.organisation_slug, deliveryNote.shop_slug, deliveryNote.customer_slug])
        case "grp.org.warehouses.show.dispatching.delivery-notes":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [route().params["organisation"], deliveryNote.shop_slug, deliveryNote.customer_slug])
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [route().params["organisation"], deliveryNote.shop_slug, deliveryNote.customer_slug])
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(status)="{ item: deliveryNote }">
            <!-- {{deliveryNote.state_icon}} -->
            <Icon :data="deliveryNote.state_icon" />
            <!-- <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                {{ deliveryNote["reference"] }}
            </Link> -->
        </template>

        <template #cell(reference)="{ item: deliveryNote }">
            <Link :href="deliveryNoteRoute(deliveryNote)" class="primaryLink">
                {{ deliveryNote["reference"] }}
            </Link>
        </template>

        <template #cell(date)="{ item }">
            {{ useFormatTime(item.date) }}
        </template>
        
        <template #cell(customer_name)="{ item: deliveryNote }">
            <Link :href="customerRoute(deliveryNote)" class="secondaryLink">
                {{ deliveryNote["customer_name"] }}
            </Link>
        </template>
    </Table>
</template>
