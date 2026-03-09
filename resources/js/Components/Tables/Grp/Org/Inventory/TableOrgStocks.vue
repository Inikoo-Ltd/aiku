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
import { faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle, faTriangle, faEquals, faMinus } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { RouteParams } from "@/types/route-params"
import { OrgStock } from "@/types/org-stock"
import FractionDisplay from "@/Components/DataDisplay/FractionDisplay.vue"

library.add(faCheckCircle, faTimesCircle, faPauseCircle, faExclamationCircle, faTriangle, faEquals, faMinus)

defineProps<{
    data: object
    tab?: string
}>()

const locale = inject("locale", aikuLocaleStructure)
const layout = inject("layout", {})

function orgStockRoute(orgStock: OrgStock) {
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
    }else{
      return route(
            "grp.helpers.redirect_org_stock",
            [
                orgStock.id
            ]
        )
    }
}

const getIntervalChangesIcon = (isPositive: boolean) => {
    if (isPositive) {
        return { icon: faTriangle }
    } else {
        return { icon: faTriangle, class: "rotate-180" }
    }
}

const getIntervalStateColor = (isPositive: boolean) => {
    return isPositive ? "text-green-500" : "text-red-500"
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
        <template #cell(org_sku)="{ item: stock }">
            <Link :href="orgStockRoute(stock) as string" class="primaryLink">
                {{ stock["organisation_code"] }}
            </Link>
        </template>

        <template #cell(code)="{ item: stock }">
            <Link :href="orgStockRoute(stock) as string" class="primaryLink">
                {{ stock["code"] }}
            </Link>
        </template>
        <template #cell(name)="{ item: stock }">
            <div class="flex gap-3">
                {{ stock["name"] }} <span v-if="stock?.is_on_demand"
												class="text-[10px] px-1.5 rounded bg-amber-100 text-amber-700">
												On Demand
											</span>
            </div>
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
                <FractionDisplay v-if="stock.pick_fractional.length > 0" :fractionData="stock.pick_fractional"/>
            </div>
        </template>

        <template #cell(notes)="{ item: stock }">
            {{ stock.notes ?? "" }}
        </template>

        <template #cell(sales_grp_currency_external)="{ item }">
            <span class="tabular-nums">{{ locale.currencyFormat('GBP', item.sales_grp_currency_external) }}</span>
        </template>

        <template #cell(sales_grp_currency_external_delta)="{ item }">
            <div v-if="item.sales_grp_currency_external_delta">
                <span>{{ item.sales_grp_currency_external_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.sales_grp_currency_external_delta.is_positive).class,
                        getIntervalStateColor(item.sales_grp_currency_external_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>

        <template #cell(invoices)="{ item }">
            <span class="tabular-nums">{{ item.invoices }}</span>
        </template>

        <template #cell(invoices_delta)="{ item }">
            <div v-if="item.invoices_delta">
                <span>{{ item.invoices_delta.formatted }}</span>
                <FontAwesomeIcon
                    :icon="getIntervalChangesIcon(item.invoices_delta.is_positive)?.icon"
                    class="text-xxs md:text-sm"
                    :class="[
                        getIntervalChangesIcon(item.invoices_delta.is_positive).class,
                        getIntervalStateColor(item.invoices_delta.is_positive),
                    ]"
                    fixed-width
                    aria-hidden="true"
                />
            </div>
            <div v-else>
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faMinus" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
                <FontAwesomeIcon :icon="faEquals" class="text-xxs md:text-sm" fixed-width aria-hidden="true" />
            </div>
        </template>
    </Table>
</template>


