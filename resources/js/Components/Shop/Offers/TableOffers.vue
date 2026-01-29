<!--
    -  Author: Vika Aqordi <aqordivika@yahoo.co.id>
    -  Github: aqordeon
    -  Created: Mon, 9 September 2024 16:24:07 Bali, Indonesia
    -  Copyright (c) 2024, Vika Aqordi
-->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Order } from "@/types/order"
import type { Table as TableTS } from "@/types/Table"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: TableTS
    tab?: string
}>()


function offerRoute(offer: Order) {
    switch (route().current()) {
        case "grp.org.shops.show.discounts.offers.index":
        case "grp.org.shops.show.discounts.campaigns.show":
        case "grp.org.shops.show.catalogue.families.show":
            return route(
                "grp.org.shops.show.discounts.offers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    offer.slug])
        default:
            return ""
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: offer }">
            <Link :href="offerRoute(offer)" class="primaryLink">
                {{ offer.name }}
            </Link>
        </template>

    </Table>
</template>
