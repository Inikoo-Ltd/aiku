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

function shipperRoute(shipper: {}) {
    switch (route().current()) {
         case "grp.org.warehouses.show.dispatching.shippers.inactive.index":
         case "grp.org.warehouses.show.dispatching.shippers.current.index":
            return route(
               "grp.org.warehouses.show.dispatching.shippers.show",
                [route().params["organisation"], route().params["warehouse"], shipper.slug])
        default:
            return ''
    }
}

</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
         <template #cell(code)="{ item: shipper }">
            <Link :href="shipperRoute(shipper)" class="primaryLink">
                {{ shipper["code"] }}
            </Link>
        </template>
    </Table>
</template>
