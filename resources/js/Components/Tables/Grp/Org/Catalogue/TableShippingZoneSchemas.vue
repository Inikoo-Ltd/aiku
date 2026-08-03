<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"

defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    },
    tab?: string
}>()


function shopRoute(schema: {}) {
    switch (route().current()) {
        case "grp.org.shops.show.billables.shipping.index":
            return route(
                schema.is_current ? "grp.org.shops.show.billables.shipping.current.show" : "grp.org.shops.show.billables.shipping.discount.show",
                [route().params["organisation"], route().params["shop"], schema.slug])
        default:
            return null
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: schema }">
            <Link :href="shopRoute(schema)" class="primaryLink">
                {{ schema["name"] }}
            </Link>
        </template>
    </Table>
</template>