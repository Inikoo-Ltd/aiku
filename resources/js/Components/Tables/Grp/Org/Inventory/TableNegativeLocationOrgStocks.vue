<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 13 Aug 2026, Mijas, Spain
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const routeParams = route().params as RouteParams

interface NegativeStock {
    id: number
    slug: string
    code: string
    name: string
    location_slug: string | null
    location_code: string | null
    quantity: number
}

function orgStockRoute(negativeStock: NegativeStock) {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
        routeParams.organisation,
        routeParams.warehouse,
        negativeStock.slug,
    ])
}

function locationRoute(slug: string) {
    return route("grp.org.warehouses.show.infrastructure.locations.show", [
        routeParams.organisation,
        routeParams.warehouse,
        slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'negative_stocks'">
        <template #cell(code)="{ item: negativeStock }">
            <Link :href="orgStockRoute(negativeStock) as string" class="primaryLink">
                {{ negativeStock.code }}
            </Link>
        </template>

        <template #cell(location_code)="{ item: negativeStock }">
            <Link
                v-if="negativeStock.location_slug"
                :href="locationRoute(negativeStock.location_slug) as string"
                class="primaryLink"
            >
                {{ negativeStock.location_code }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(quantity)="{ item: negativeStock }">
            <span class="tabular-nums text-red-600 font-semibold">{{ locale.number(Number(negativeStock.quantity)) }}</span>
        </template>
    </Table>
</template>
