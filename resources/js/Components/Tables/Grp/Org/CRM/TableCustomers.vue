<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FulfilmentCustomer } from "@/types/fulfilment-customer"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useLocaleStore } from "@/Stores/locale"
import { RouteParams } from "@/types/route-params"

defineProps<{
    data: object,
    tab?: string
}>()


function customerRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "shops.show.customers.index":
            return route(
                "grp.org.shops.show.crm.customers.show",
                [customer.shop_slug, customer.slug])
        case "grp.fulfilment.customers.index":
            return route(
                "grp.fulfilment.customers.show",
                [customer.slug])
        case "grp.overview.crm.customers.index":
            return null
        case "grp.org.overview.customers.index":
        case "grp.org.overview.crm.customers.index":
            return null
        default:
            return route(
                "grp.org.shops.show.crm.customers.show",
                [
                    (route().params as RouteParams).organisation,
                    (route().params as RouteParams).shop,
                    customer.slug
                ])
    }
}

function shopRoute(customer: FulfilmentCustomer) {
    return route(
        "shops.show",
        [customer.shop_slug])
}

function tagColorClass(scope?: string) {
    const normalized = (scope || '').toLowerCase()

    switch (normalized) {
        case 'system customer':
            return 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
        case 'admin customer':
            return 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100'
        case 'user customer':
            return 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
        default:
            return 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100'
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(reference)="{ item: customer }">
            <Link v-if="customerRoute(customer)" :href="customerRoute(customer) as string" class="primaryLink">
                {{ customer["reference"] }}
            </Link>
            <span v-else>
                {{ customer["reference"] }}
            </span>
        </template>
        <template #cell(shop)="{ item: customer }" class="primaryLink">
            <Link :href="shopRoute(customer)">
                {{ customer["shop"] }}
            </Link>
        </template>
        <template #cell(location)="{ item: customer }">
            <AddressLocation :data="customer['location']" />
        </template>

        <template #cell(invoiced_net_amount)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(sales_all)="{ item: customer }">
            <div class="text-gray-500">{{ useLocaleStore().currencyFormat(customer.currency_code, customer.sales_all) }}</div>
        </template>
        <template #cell(tags)="{ item: customer }">
            <div v-if="customer.tags && customer.tags.length" class="flex flex-wrap gap-1">
                <span
                    v-for="tag in customer.tags"
                    v-tooltip="tag.scope"
                    :key="tag.id || tag.name"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors duration-200 ease-in-out"
                    :class="tagColorClass(tag.scope)"
                >
                    {{ tag.name }}
                </span>
            </div>
            <div v-else class="text-gray-400 text-xs italic">
                No tags
            </div>
        </template>
    </Table>
</template>
