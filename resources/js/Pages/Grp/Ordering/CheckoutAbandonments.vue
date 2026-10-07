<script setup lang="ts">
import { Head, Link, router } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShoppingCart, faInfoCircle, faUndo, faPercentage, faCoins } from "@fal"

library.add(faShoppingCart, faInfoCircle, faUndo, faPercentage, faCoins)

defineProps<{
    data: object
    title: string
    pageHead: object
    stats: { label: string; value: number | string; information?: string; tone?: "lost" | "recovered" | "rate"; icon?: string }[]
}>()

const tones = {
    lost: { border: "border-red-200", icon: "text-red-400", value: "text-red-600" },
    recovered: { border: "border-green-200", icon: "text-green-500", value: "text-green-600" },
    rate: { border: "border-gray-200", icon: "text-gray-400", value: "text-gray-800" },
}

const toneClasses = (tone?: keyof typeof tones) => tones[tone ?? "rate"]

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
    <div v-if="stats?.length" class="mx-4 mb-4 mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" :class="stats.length > 3 ? 'lg:grid-cols-5' : 'lg:grid-cols-3'">
        <div v-for="stat in stats" :key="stat.label" class="rounded-lg border bg-white px-4 py-3" :class="toneClasses(stat.tone).border">
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                <FontAwesomeIcon v-if="stat.icon" :icon="stat.icon" :class="toneClasses(stat.tone).icon" fixed-width />
                {{ stat.label }}
                <FontAwesomeIcon v-if="stat.information" v-tooltip="stat.information" icon="fal fa-info-circle" class="text-gray-400" fixed-width />
            </div>
            <div class="mt-1 text-xl font-semibold tabular-nums" :class="toneClasses(stat.tone).value">{{ stat.value }}</div>
        </div>
    </div>
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
                :label="ctrans('Send reminder')"
                :disabled="!row['outbox_state_active']"
                :tooltip="!row['outbox_state_active'] ? ctrans('Email not configure yet') : undefined"
                @click="sendReminder(row)"
            />
            <span v-else class="text-gray-400">—</span>
        </template>
    </Table>
</template>
