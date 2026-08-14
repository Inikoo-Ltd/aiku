<!--
  -  Author: stewicca <stewicalf@gmail.com>
  -  Created: Thu, 13 Aug 2026, Bali, Indonesia
  -  Copyright (c) 2026, Steven Wicca Alfredo
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

interface LowStockAudit {
    id: number
    slug: string
    code: string
    name: string
    family_code: string | null
    family_slug: string | null
    stock: number
}

function orgStockRoute(lowStockAudit: LowStockAudit) {
    return route("grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show", [
        routeParams.organisation,
        routeParams.warehouse,
        lowStockAudit.slug,
    ])
}

function familyRoute(slug: string) {
    return route("grp.org.warehouses.show.inventory.org_stock_families.show", [
        routeParams.organisation,
        routeParams.warehouse,
        slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab ?? 'low_stock_audits'">
        <template #cell(code)="{ item: lowStockAudit }">
            <Link :href="orgStockRoute(lowStockAudit) as string" class="primaryLink">
                {{ lowStockAudit.code }}
            </Link>
        </template>

        <template #cell(family_code)="{ item: lowStockAudit }">
            <Link
                v-if="lowStockAudit.family_slug"
                :href="familyRoute(lowStockAudit.family_slug) as string"
                class="primaryLink"
            >
                {{ lowStockAudit.family_code }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(stock)="{ item: lowStockAudit }">
            <span class="tabular-nums">{{ locale.number(Number(lowStockAudit.stock)) }}</span>
        </template>
    </Table>
</template>
