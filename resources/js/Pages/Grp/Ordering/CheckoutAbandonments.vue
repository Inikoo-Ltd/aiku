<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingCart } from "@fal"

library.add(faShoppingCart)

defineProps<{
    data: object
    title: string
    pageHead: object
}>()

function orderRoute(row: any) {
    return route("grp.org.shops.show.ordering.orders.show", [
        row.organisation_slug,
        row.shop_slug,
        row.order_slug
    ])
}

function customerRoute(row: any) {
    return route("grp.org.shops.show.crm.customers.show", [
        row.organisation_slug,
        row.shop_slug,
        row.customer_slug
    ])
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <Table :resource="data" class="mt-5">
        <template #cell(reference)="{ item: row }">
            <Link :href="orderRoute(row)" class="primaryLink">
                {{ row["reference"] }}
            </Link>
        </template>
        <template #cell(customer_name)="{ item: row }">
            <Link :href="customerRoute(row)" class="primaryLink">
                {{ row["customer_name"] }}
            </Link>
        </template>
        <template #cell(checkout_visited_at)="{ item: row }">
            <span class="whitespace-nowrap">
                {{ useFormatTime(row["checkout_visited_at"], { formatTime: "dd MMM yyyy, HH:mm", timeZone: 'UTC', keepTimezone: true }) }} UTC
            </span>
        </template>
    </Table>
</template>
