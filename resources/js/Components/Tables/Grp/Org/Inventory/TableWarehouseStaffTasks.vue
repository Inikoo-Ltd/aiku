<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sun, 22 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Icon from "@/Components/Icon.vue"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object
    tab?: string
}>()

const routeParams = route().params as RouteParams

interface WarehouseTask {
    reference: string
    subject: string
    status_icon: object
    is_open: boolean
    is_overdue: boolean
    requester: string | null
    days_waiting: number
    linked: string | null
    location_slug: string | null
    org_stock_slug: string | null
    url: string
}

function linkedRoute(task: WarehouseTask): string | null {
    if (task.location_slug) {
        return route("grp.org.warehouses.show.infrastructure.locations.show", [routeParams.organisation, routeParams.warehouse, task.location_slug])
    }
    if (task.org_stock_slug) {
        return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [routeParams.organisation, routeParams.warehouse, task.org_stock_slug])
    }

    return null
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'warehouse_tasks'">
        <template #cell(reference)="{ item: task }">
            <a :href="task.url" class="primaryLink whitespace-nowrap">{{ task.reference }}</a>
        </template>

        <template #cell(linked)="{ item: task }">
            <Link v-if="linkedRoute(task)" :href="linkedRoute(task) as string" class="primaryLink">{{ task.linked }}</Link>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(status)="{ item: task }">
            <Icon :data="task.status_icon" />
        </template>

        <template #cell(requester)="{ item: task }">
            <span class="text-gray-700">{{ task.requester ?? "-" }}</span>
        </template>

        <template #cell(days_waiting)="{ item: task }">
            <span class="tabular-nums" :class="task.is_open && (task.is_overdue || task.days_waiting > 30) ? 'text-red-600 font-medium' : 'text-gray-700'">
                {{ task.days_waiting }}
            </span>
        </template>
    </Table>
</template>
