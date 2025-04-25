<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 18 Mar 2024 13:45:06 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { FulfilmentCustomer } from "@/types/Customer"
import AddressLocation from "@/Components/Elements/Info/AddressLocation.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { inject } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

const props = defineProps<{
    data: {}
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)


function customerRoute(customer: FulfilmentCustomer) {
    switch (route().current()) {
        case "grp.org.shops.show.crm.customers.show.customer-clients.index":
            return route(
                "grp.org.shops.show.crm.customers.show.customer-clients.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], customer.ulid])
        case "grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.aiku.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"], route().params["customerHasPlatform"], customer.ulid])
        case "grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.aiku.index":
            return route(
                "grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.show",
                [route().params["organisation"], route().params["shop"], route().params["customer"], route().params["platform"], customer.ulid])
        case "grp.org.fulfilments.show.crm.customers.show.customer-clients.index":
            return route(
                "grp.org.fulfilments.show.crm.customers.show.customer-clients.show",
                [route().params["organisation"], route().params["fulfilment"], route().params["fulfilmentCustomer"], customer.ulid])
        case "grp.fulfilment.customers.index":
            return route(
                "grp.fulfilment.customers.show",
                [customer.slug])
        case "retina.dropshipping.client.index":
        case "retina.dropshipping.platforms.client.index":
            return route(
                "retina.dropshipping.client.show",
                [customer.ulid]
            )
        default:
            return ''
    }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(name)="{ item: customer }">
            <Link :href="customerRoute(customer)" class="primaryLink">
                {{ customer["name"] }}
            </Link>
        </template>

        <template #cell(location)="{ item: customer }">
            <AddressLocation :data="customer['location']" />
        </template>

        <template #cell(created_at)="{ item: customer }">
            <div class="text-gray-500">{{ useFormatTime(customer["created_at"]) }}</div>
        </template>
    </Table>
</template>
