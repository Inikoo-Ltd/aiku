<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 22 Nov 2026, Kuala Lumpur, Malaysia
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
    currency: string
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const routeParams = route().params as RouteParams

function orgStockRoute(slug: string): string {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [routeParams.organisation, routeParams.warehouse, slug])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'stock_value_findings'">
        <template #cell(code)="{ item: finding }">
            <Link :href="orgStockRoute(finding.slug)" class="primaryLink whitespace-nowrap">{{ finding.code }}</Link>
        </template>

        <template #cell(explanation)="{ item: finding }">
            <span class="whitespace-normal text-gray-700">{{ finding.explanation }}</span>
        </template>

        <template #cell(action)="{ item: finding }">
            <span class="whitespace-normal text-gray-700">{{ finding.action }}</span>
        </template>

        <template #cell(excess_amount)="{ item: finding }">
            <span class="tabular-nums text-gray-700">{{ locale.currencyFormat(currency, finding.excess_amount) }}</span>
        </template>

        <template #cell(raised)="{ item: finding }">
            <a v-if="finding.raised" :href="finding.raised.url" class="primaryLink">{{ finding.raised.reference }}</a>
            <span v-else class="text-gray-400">-</span>
        </template>
    </Table>
</template>
