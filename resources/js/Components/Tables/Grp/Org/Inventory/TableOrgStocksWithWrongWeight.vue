<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Thu, 05 Nov 2026, Kuala Lumpur, Malaysia
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

interface OrgStockWithWrongWeight {
    id: number
    slug: string
    code: string
    name: string
    system_weight: number
    measured_weight: number
    difference: number
    measured_weight_parcels: number
}

function orgStockRoute(orgStock: OrgStockWithWrongWeight) {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
        routeParams.organisation,
        routeParams.warehouse,
        orgStock.slug,
    ])
}

function kilograms(grams: number): string {
    return locale.number(Math.round(grams / 10) / 100) + " kg"
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'wrong_weights'">
        <template #cell(code)="{ item: orgStock }">
            <Link :href="orgStockRoute(orgStock) as string" class="font-medium text-gray-700 hover:underline whitespace-nowrap">
                {{ orgStock.code }}
            </Link>
        </template>

        <template #cell(system_weight)="{ item: orgStock }">
            <span class="tabular-nums whitespace-nowrap">{{ kilograms(orgStock.system_weight) }}</span>
        </template>

        <template #cell(measured_weight)="{ item: orgStock }">
            <span class="tabular-nums whitespace-nowrap">{{ kilograms(orgStock.measured_weight) }}</span>
        </template>

        <template #cell(difference)="{ item: orgStock }">
            <span class="tabular-nums whitespace-nowrap font-semibold">{{ orgStock.difference > 0 ? "+" : "" }}{{ kilograms(orgStock.difference) }}</span>
        </template>

        <template #cell(measured_weight_parcels)="{ item: orgStock }">
            <span class="tabular-nums">{{ locale.number(orgStock.measured_weight_parcels) }}</span>
        </template>
    </Table>
</template>
