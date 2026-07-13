<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import ShowcaseStats from "@/Components/ShowcaseStats.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faShoppingCart } from "@fal"

library.add(faShoppingCart)

defineProps<{
    data: object
    title: string
    pageHead: object
    stats: { label: string; value: number | string }[]
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

function sendReminder(row: any) {
    router.post(
        route("grp.models.checkout_abandonment.send_reminder", row.id),
        {},
        { preserveScroll: true }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <ShowcaseStats v-if="stats?.length" :data="stats" class="mt-5" />
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
        <template #cell(email_sent_at)="{ item: row }">
            <span v-if="row['email_sent_at']" class="whitespace-nowrap text-green-600">
                {{ useFormatTime(row["email_sent_at"], { formatTime: "dd MMM yyyy, HH:mm", timeZone: 'UTC', keepTimezone: true }) }} UTC
            </span>
            <span v-else class="text-gray-400">—</span>
        </template>
        <template #cell(send_reminder)="{ item: row }">
            <Button
                v-if="row['state'] === 'abandoned' && !row['email_sent_at']"
                type="tertiary"
                size="xs"
                icon="fal fa-paper-plane"
                :label="trans('Send reminder')"
                @click="sendReminder(row)"
            />
            <span v-else class="text-gray-400">—</span>
        </template>
    </Table>
</template>
