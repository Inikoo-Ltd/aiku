<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Table from "@/Components/Table/Table.vue"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type ConversionCustomerRow = {
    name: string | null
    contact_name: string | null
    email: string | null
    route: routeType
    channels: string[]
    checkouts: number
    purchases: number
    revenue: number
    last_event_at: string | null
}

defineProps<{
    data: object
    tab: string
    currencyCode?: string | null
}>()

const locale = useLocaleStore()
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(name)="{ item: customer }: { item: ConversionCustomerRow }">
            <Link :href="route(customer.route.name, customer.route.parameters)" class="primaryLink">
                {{ customer.name || customer.contact_name || customer.email }}
            </Link>
            <div v-if="customer.contact_name && customer.contact_name !== customer.name" class="text-xs text-gray-500">
                {{ customer.contact_name }}
            </div>
        </template>

        <template #cell(traffic_source_types)="{ item: customer }: { item: ConversionCustomerRow }">
            <span v-if="customer.channels.length">{{ customer.channels.join(", ") }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>

        <template #cell(checkouts)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ locale.number(customer.checkouts) }}</span>
        </template>

        <template #cell(purchases)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ locale.number(customer.purchases) }}</span>
        </template>

        <template #cell(revenue)="{ item: customer }: { item: ConversionCustomerRow }">
            <span class="tabular-nums">{{ currencyCode ? locale.currencyFormat(currencyCode, customer.revenue) : locale.number(customer.revenue) }}</span>
        </template>

        <template #cell(last_event_at)="{ item: customer }: { item: ConversionCustomerRow }">
            <span v-if="customer.last_event_at" class="tabular-nums">{{ useFormatTime(customer.last_event_at, { formatTime: "mdy" }) }}</span>
        </template>
    </Table>
</template>
