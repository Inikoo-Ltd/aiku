<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import Tag from "primevue/tag"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

interface WhatsappOutboxMessage {
    id: number
    state: string
    message_text: string | null
    sent_at: string | null
    error: string | null
    phone_number: string | null
    customer_name: string | null
    customer_slug: string | null
    order_reference: string | null
    order_slug: string | null
}

defineProps<{
    data: object
    tab?: string
}>()

const stateSeverity = (state: string) => ({
    read: "success",
    delivered: "success",
    sent: "info",
    failed: "danger",
}[state] ?? "secondary")

const stateLabel = (state: string) => ({
    read: ctrans("Read"),
    delivered: ctrans("Delivered"),
    sent: ctrans("Sent"),
    failed: ctrans("Failed"),
}[state] ?? state)

const customerRoute = (message: WhatsappOutboxMessage) =>
    message.customer_slug
        ? route("grp.org.shops.show.crm.customers.show", [route().params["organisation"], route().params["shop"], message.customer_slug])
        : null

const orderRoute = (message: WhatsappOutboxMessage) =>
    message.order_slug
        ? route("grp.org.shops.show.ordering.orders.show", [route().params["organisation"], route().params["shop"], message.order_slug])
        : null
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #cell(state)="{ item: message }">
            <Tag :severity="stateSeverity(message.state)" :value="stateLabel(message.state)" v-tooltip="message.error ?? undefined" />
        </template>
        <template #cell(sent_at)="{ item: message }">
            <span class="whitespace-nowrap">{{ useFormatTime(message.sent_at, { formatTime: "aiku" }) }}</span>
        </template>
        <template #cell(customer_name)="{ item: message }">
            <Link v-if="customerRoute(message)" :href="customerRoute(message)!" class="primaryLink">{{ message.customer_name }}</Link>
            <span v-else>{{ message.customer_name ?? "-" }}</span>
        </template>
        <template #cell(phone_number)="{ item: message }">
            <span class="whitespace-nowrap">{{ message.phone_number }}</span>
        </template>
        <template #cell(order_reference)="{ item: message }">
            <Link v-if="orderRoute(message)" :href="orderRoute(message)!" class="primaryLink whitespace-nowrap">{{ message.order_reference }}</Link>
            <span v-else>-</span>
        </template>
        <template #cell(message_text)="{ item: message }">
            <span class="line-clamp-2 text-xs text-gray-600">{{ message.message_text }}</span>
            <span v-if="message.error" class="block text-xs text-red-600">{{ message.error }}</span>
        </template>
    </Table>
</template>
