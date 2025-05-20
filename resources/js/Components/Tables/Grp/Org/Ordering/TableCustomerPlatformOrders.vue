<script setup lang="ts">
import Table from "@/Components/Table/Table.vue";
import type { Table as TableTS } from "@/types/Table";
import { Link } from "@inertiajs/vue3";
import Icon from "@/Components/Icon.vue";
import Tag from "@/Components/Tag.vue";
import { RouteParams } from "@/types/route-params";
import { Order } from "@/types/order";


defineProps<{
    data: TableTS,
}>();

function orderRoute(order: Order) {

    if (route().current() === "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.index") {
        return route(
            "grp.org.shops.show.crm.customers.show.customer_sales_channels.show.orders.show",
            [
                (route().params as RouteParams).organisation,
                (route().params as RouteParams).shop,
                (route().params as RouteParams).customer,
                (route().params as RouteParams).customerSalesChannel,
                order.slug]);
    } else {
        return "";
    }
}

</script>
<template>
    <Table :resource="data">
        <!-- Column: State icon -->
        <template #cell(state)="{ item: order }">
            <Icon :data="order.state_icon" />
        </template>

        <!-- Column: Reference -->
        <template #cell(reference)="{ item: order }">
            <Link :href="orderRoute(order)" class="primaryLink">
                {{ order["reference"] }}
            </Link>
        </template>

        <!-- Column: Reference -->
        <template #cell(payment_status)="{ item: order }">
            <Tag v-if="order.payment_state === 'completed'" :label="order.payment_status" theme="3" noHoverColor>
                <template #label>
                    <div class="capitalize">
                        {{ order.payment_status }}
                    </div>
                </template>
            </Tag>

        </template>
    </Table>
</template>
