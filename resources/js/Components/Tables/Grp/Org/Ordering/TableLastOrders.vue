<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Order } from "@/types/order"
import type { Links, Meta } from "@/types/Table"
import Icon from "@/Components/Icon.vue"

import { faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { RouteParams } from "@/types/route-params"
import { Column, DataTable, Tag } from "primevue"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { useFormatTime } from "@/Composables/useFormatTime"
import { trans } from "laravel-vue-i18n"
import { Link } from "@inertiajs/vue3"

library.add(faSeedling, faPaperPlane, faWarehouse, faHandsHelping, faBox, faTasks, faShippingFast, faTimesCircle)

defineProps<{
    data: {
        icon: Icon
        label: string
        date_key: string
    }[]
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

function orderRoute(order: Order) {
    switch (route().current()) {
        case 'grp.org.shops.show.ordering.backlog':
        case "grp.org.shops.show.ordering.orders.index":
            return route(
                "grp.org.shops.show.ordering.orders.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, order.slug])

        case "grp.org.shops.show.crm.show.orders.index":
            return route(
                "grp.org.shops.show.crm.show.orders.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).customer, order.slug])
        case "grp.org.overview.orders_in_basket.index":
        case "grp.overview.ordering.orders_in_basket.index":
            return route(
                "grp.org.shops.show.ordering.orders.show",
                [order.organisation_slug, order.shop_slug, order.slug])
        case "grp.org.shops.show.crm.customers.show.orders.index":
            return route(
                "grp.org.shops.show.crm.customers.show.orders.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).customer, order.slug])
        case "grp.org.shops.show.crm.customers.show.customer_clients.orders.index":
            return route(
                "grp.org.shops.show.crm.customers.show.customer_clients.orders.show",
                [(route().params as RouteParams).organisation, (route().params as RouteParams).shop, (route().params as RouteParams).customer, (route().params as RouteParams).customerClient, order.slug])
        default:
            return ''
    }
}

function shopRoute(order: Order) {
    return route(
        "grp.org.shops.show.ordering.backlog",
        [order.organisation_slug, order.shop_slug])
}

function organisationRoute(order: Order) {
    return route(
        "grp.org.overview.orders_in_basket.index",
        [order.organisation_slug])
}


function customerRoute(order: Order) {
    let routeCurr = route().current()
    console.log(routeCurr)
    switch (routeCurr) {
        case "grp.overview.ordering.orders.index":
        case "grp.org.overview.orders_in_basket.index":
        case "grp.org.overview.orders.index":
        case "grp.overview.ordering.orders_in_basket.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [order.organisation_slug, order.shop_slug, order.customer_slug]
            )
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    order.customer_slug
                ]
            )
    }
}
</script>

<template>
    <div class="p-4">
        <div class="w-full space-y-8 p-2 border border-gray-300 rounded">
            <div v-for="orderGroup in data">
                <DataTable xv-if="orderGroup.data.length" :value="orderGroup.data" removableSort xtableStyle="min-width: 50rem">
                    <template #header>
                        <div class="bg-gray-200 py-2 px-3 flex flex-wrap items-center xjustify-between gap-2">
                            <Icon :data="orderGroup.icon" xclass="text-2xl" />
                            <span class="text-xl font-bold">
                                {{ orderGroup.label }}
                                <span class="text-gray-500 font-light">({{ orderGroup.data.length }})</span>
                            </span>
                        </div>
                    </template>
                    <Column
                        :header="trans('Date')"
                        sortable
                        :field="orderGroup.date_key"
                        headerClass="w-64"
                        bodyClass="max-w-64 overflow-hidden text-ellipsis whitespace-nowrap"
                    >
                        <template #body="slotProps">
                            {{ useFormatTime(slotProps.data[orderGroup.date_key]) }}
                        </template>
                    </Column>
                    <Column field="reference" header="Reference"
                        headerClass="w-80"
                        bodyClass="max-w-80 overflow-hidden text-ellipsis whitespace-nowrap"
                        sortable
                    >
                        <template #body="slotProps">
                            <Link :href="(orderRoute(slotProps.data) as string)" class="primaryLink">
                                {{ slotProps.data.reference }}
                            </Link>
                        </template>
                    </Column>
                    <Column header="Customer" field="customer_name">
                        <template #body="slotProps">
                            <Link :href="customerRoute(slotProps.data)" class="secondaryLink">
                                {{ slotProps.data.customer_name }}
                            </Link>
                        </template>
                    </Column>
                    <Column field="net_amount" xheader="Net"
                        sortable
                    >
                        <template #header>
                            <div class="w-full text-right font-bold">
                                {{ trans("Net") }}
                            </div>
                        </template>
                        <template #body="slotProps">
                            <div class="text-right tabular-nums">
                                {{ locale.currencyFormat(slotProps.data.currency_code, slotProps.data.net_amount) }}
                            </div>
                        </template>
                    </Column>
                    <template #empty>
                        <div class="text-center text-gray-500 py-2">
                            {{ trans("No orders found.") }}
                        </div>
                    </template>
                    <!-- <template #footer> In total there are {{ products ? products.length : 0 }} products. </template> -->
                </DataTable>
                <!-- <div v-else class="text-center text-gray-500 py-4">
                    No orders found in this state.
                </div> -->
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>

:deep(.p-datatable-header) {
    padding: 0px !important;
}
</style>