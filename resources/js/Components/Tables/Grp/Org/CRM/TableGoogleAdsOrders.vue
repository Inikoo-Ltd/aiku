<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Created: Fri, 20 Nov 2026, Bali, Indonesia
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { computed } from "vue"
import Table from "@/Components/Table/Table.vue"
import type { TableColumn } from "@/types/Table"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"

type OrderRow = {
    id: number
    reference: string
    submitted_at: string | null
    customer_name: string | null
    campaign: string | null
    sales: number
    profit: number | null
    margin: number | null
    is_estimated: boolean
    route: { name: string; parameters: Record<string, string> }
}

type OrderTotals = {
    orders: number
    sales: number
    profit: number | null
    margin: number | null
}

const props = defineProps<{
    data: { totals?: OrderTotals | null }
    tab: string
    currency: string
}>()

const locale = useLocaleStore()

const totals = computed(() => props.data.totals ?? null)

const money = (amount: number | null) => (amount === null ? "—" : String(locale.currencyFormat(props.currency, amount)))

const percent = (value: number | null) => (value === null ? "—" : locale.number(value) + "%")

const totalCell = (key: string): string | null => {
    const sums = totals.value

    if (!sums) {
        return null
    }

    switch (key) {
        case "reference":
            return ctrans("Total (:count orders)", { count: locale.number(sums.orders) })
        case "sales":
            return money(sums.sales)
        case "profit":
            return money(sums.profit)
        case "margin":
            return percent(sums.margin)
        default:
            return null
    }
}

const profitClass = (amount: number | null) => (amount !== null && amount < 0 ? "text-[#d03b3b]" : "")
</script>

<template>
    <Table :resource="data" :name="tab">
        <template #topRows="{ show, columns, isCheckBox }: { show: (key: string) => boolean, columns: TableColumn[], isCheckBox: boolean }">
            <tr v-if="totals && totals.orders > 0" class="bg-gray-100 font-semibold text-gray-800">
                <td v-if="isCheckBox" />
                <td
                    v-for="column in columns"
                    v-show="show(column.key)"
                    :key="`totals-${column.key}`"
                    class="whitespace-nowrap py-1 text-xs lg:py-2 lg:text-[13px]"
                    :class="[
                        column.align === 'right' ? 'pl-1.5 pr-2 text-right tabular-nums lg:pl-3 lg:pr-9' : 'px-2 lg:px-6',
                        column.key === 'profit' ? profitClass(totals.profit) : '',
                    ]">
                    {{ totalCell(column.key) }}
                </td>
            </tr>
        </template>

        <template #cell(reference)="{ item: order }: { item: OrderRow }">
            <Link :href="route(order.route.name, order.route.parameters)" class="primaryLink whitespace-nowrap">
                {{ order.reference }}
            </Link>
        </template>

        <template #cell(submitted_at)="{ item: order }: { item: OrderRow }">
            <span class="whitespace-nowrap text-gray-600">
                {{ order.submitted_at ? useFormatTime(order.submitted_at, { formatTime: "aiku" }) : "—" }}
            </span>
        </template>

        <template #cell(customer_name)="{ item: order }: { item: OrderRow }">
            {{ order.customer_name ?? "—" }}
        </template>

        <template #cell(campaign)="{ item: order }: { item: OrderRow }">
            <span v-if="order.campaign" class="text-gray-600">{{ order.campaign }}</span>
            <span v-else v-tooltip="ctrans('Came from a Google ad, but the click could not be matched to a campaign')" class="text-gray-400">—</span>
        </template>

        <template #cell(sales)="{ item: order }: { item: OrderRow }">
            <span class="tabular-nums">{{ money(order.sales) }}</span>
        </template>

        <template #cell(profit)="{ item: order }: { item: OrderRow }">
            <span v-if="order.profit === null" v-tooltip="ctrans('None of the items has a cost')" class="text-gray-400">—</span>
            <span v-else class="tabular-nums" :class="profitClass(order.profit)">
                {{ money(order.profit) }}
                <span
                    v-if="order.is_estimated"
                    v-tooltip="ctrans('Partly estimated from the last purchase price, some items are not picked yet')"
                    class="text-xs font-normal text-gray-400">
                    ({{ ctrans("est.") }})
                </span>
            </span>
        </template>

        <template #cell(margin)="{ item: order }: { item: OrderRow }">
            <span class="tabular-nums" :class="order.margin === null ? 'text-gray-400' : ''">{{ percent(order.margin) }}</span>
        </template>
    </Table>
</template>
