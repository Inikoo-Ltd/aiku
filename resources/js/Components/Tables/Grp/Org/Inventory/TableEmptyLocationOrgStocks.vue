<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 02 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object
    tab?: string
}>()

const routeParams = route().params as RouteParams

interface EmptyLocationOrgStock {
    id: number
    date: string | null
    slug: string
    code: string
    name: string
    location_slug: string
    location_code: string
}

function stockHistoryRoute(item: EmptyLocationOrgStock) {
    return route("grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show.stock_history", [
        routeParams.organisation,
        routeParams.warehouse,
        item.slug,
    ])
}

function locationRoute(item: EmptyLocationOrgStock) {
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        routeParams.organisation,
        routeParams.warehouse,
        item.location_slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(date)="{ item }">
            <span class="whitespace-nowrap">{{ item.date ? useFormatTime(new Date(item.date), { formatTime: "short-datetime" }) : "-" }}</span>
        </template>

        <template #cell(location_code)="{ item }">
            <Link :href="locationRoute(item) as string" class="primaryLink whitespace-nowrap">
                {{ item.location_code }}
            </Link>
        </template>

        <template #cell(code)="{ item }">
            <Link :href="stockHistoryRoute(item) as string" class="primaryLink whitespace-nowrap">
                {{ item.code }}
            </Link>
        </template>
    </Table>
</template>
