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
import type { Links, Meta, Table as TableTS } from "@/types/Table"
// import { useFormatTime } from '@/Composables/useFormatTime'

defineProps<{
    data: TableTS
    tab?: string
}>()


function offerRoute(offer: Order) {
    switch (route().current()) {
        case "grp.org.shops.show.discounts.offers.index":
        case "grp.org.shops.show.discounts.campaigns.show":
            return route(
                "grp.org.shops.show.discounts.offers.show",
                [route().params["organisation"], route().params["shop"], offer.slug])
        default:
            return ''
    }
}


</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <!-- Column: Reference -->
        <template #cell(code)="{ item: offer }">
            <Link :href="offerRoute(offer)" class="primaryLink">
                {{ offer.code }}
            </Link>
        </template>


        <!-- Column: Date -->
        <!-- <template #cell(date)="{ item: order }">
            {{ useFormatTime(order.date) }}
        </template> -->
    </Table>
</template>
