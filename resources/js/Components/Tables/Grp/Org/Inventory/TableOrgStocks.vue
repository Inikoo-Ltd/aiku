<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:09:00 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Stock } from "@/types/stock"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import Icon from "@/Components/Icon.vue"
import { faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
import { RouteParams } from "@/types/route-params"
import { OrgStock } from "@/types/org-stock"

library.add(faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle)

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", {})

function stockRoute(orgStock: OrgStock) {
    const current = route().current()
    console.log(current)

    if (current === "grp.org.warehouses.show.inventory.org_stock_families.show") {
        return route(
            "grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                (route().params as RouteParams).orgStockFamily,
                orgStock.slug
            ]
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    } else if (current === "grp.overview.inventory.org-stocks.index" || current === "grp.org.shops.show.catalogue.products.all_products.show") {
        return route(
            "grp.helpers.redirect_org_stock",
            [orgStock.id])
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    } else if (current === "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.index") {
        return route(
            "grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).warehouse,
                orgStock.slug
            ]
        )
    }
}

function stockFamilyRoute(stock: Stock) {
    return route(
        "grp.org.warehouses.show.inventory.org_stock_families.show",
        [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).warehouse,
            stock.family_slug
        ]
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: stock }">
            <Icon :data="stock.state"></Icon>
        </template>
        <template #cell(code)="{ item: stock }">
            <Link :href="stockRoute(stock) as string" class="primaryLink">
                {{ stock["code"] }}
            </Link>
        </template>
        <template #cell(family_code)="{ item: stock }">
            <!--suppress TypeScriptUnresolvedReference -->
            <Link v-if="stock.family_slug" :href="stockFamilyRoute(stock)" class="secondaryLink">
                {{ stock["family_code"] }}
            </Link>
        </template>
        <template #cell(type)="{ item: stock }">
            {{ stock.type ?? "" }}
        </template>

        <template #cell(picking_priority)="{ item: stock }">
            {{ stock.picking_priority ?? "" }}
        </template>

        <template #cell(value)="{ item: stock }">
            {{ locale.currencyFormat(layout.group.currency.code, stock.value) }}
        </template>

        <template #cell(dropshipping_pipe)="{ item: stock }">
            {{ stock.dropshipping_pipe ?? "" }}
        </template>

        <template #cell(quantity)="{ item: stock }">
            <div class="text-right">
                {{ stock.quantity ?? "" }}
            </div>
        </template>

        <template #cell(notes)="{ item: stock }">
            {{ stock.notes ?? "" }}
        </template>

    </Table>
</template>


