<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 27 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { reactive } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { trans } from "laravel-vue-i18n"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: object
    groupBy: string | null
    groups: { label: string, items: { id: number, quantity: number, state: string, stock_code: string, stock_name: string, family: string | null, maker: string | null, buyer_code: string | null, customer_name: string | null, order_reference: string | null, priority: string, needed_by: string | null }[] }[] | null
    pickedOrders: {
        id: number
        reference: string
        net_amount: string
        currency_code: string
        buyer_name: string
        transactions_count: number
    }[]
}>()

const selected = reactive<Record<number, number>>({})

function toggle(item: { id: number, quantity: number }) {
    if (item.id in selected) {
        delete selected[item.id]
    } else {
        selected[item.id] = Number(item.quantity)
    }
}

function sendToWarehouse(orderId: number) {
    router.post(
        route("grp.org.productions.show.partners.send_to_warehouse", [route().params["organisation"], route().params["production"], orderId]),
        {},
        { preserveScroll: true }
    )
}

function submitCherryPick() {
    const lines = Object.entries(selected).map(([id, quantity]) => ({ id: Number(id), quantity }))
    router.post(
        route("grp.org.productions.show.partners.cherry_pick", [route().params["organisation"], route().params["production"]]),
        { lines },
        { preserveScroll: true, onSuccess: () => { for (const k in selected) delete selected[k] } }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div v-if="Object.keys(selected).length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center justify-between rounded-lg bg-indigo-600 px-4 py-2 text-white">
        <span>{{ Object.keys(selected).length }} {{ trans("lines selected") }}</span>
        <button type="button" class="rounded bg-white px-3 py-1 text-indigo-600" @click="submitCherryPick">
            {{ trans("Pick into order") }}
        </button>
    </div>

    <div v-if="pickedOrders?.length" class="mx-4 mt-4 rounded-lg border border-gray-200 dark:border-gray-700">
        <div class="border-b border-gray-200 px-4 py-2 font-medium dark:border-gray-700">
            {{ trans("Picked orders waiting to be sent to warehouse") }}
        </div>
        <div v-for="order in pickedOrders" :key="order.id" class="flex items-center justify-between px-4 py-2">
            <div class="flex items-center gap-4">
                <span class="font-medium">{{ order.buyer_name }}</span>
                <span class="text-gray-500">{{ order.reference }}</span>
                <span class="text-gray-500">{{ order.transactions_count }} {{ trans("lines") }}</span>
                <span class="tabular-nums">{{ useLocaleStore().currencyFormat(order.currency_code, Number(order.net_amount)) }}</span>
            </div>
            <button type="button" class="rounded bg-indigo-600 px-3 py-1 text-white" @click="sendToWarehouse(order.id)">
                {{ trans("Send to warehouse") }}
            </button>
        </div>
    </div>

    <div v-if="groups" class="mx-4 mt-5 space-y-6">
        <div v-for="group in groups" :key="group.label" class="rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-2 font-medium dark:border-gray-700 dark:bg-gray-800">
                <span>{{ group.label }}</span>
                <span class="text-gray-500">{{ group.items.length }} {{ trans("lines") }}</span>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                        <td class="w-36 px-4 py-1.5">
                            <div class="flex items-center gap-2">
                                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                                <input v-if="item.id in selected" v-model.number="selected[item.id]" type="number" step="0.001" :max="item.quantity" min="0.001" class="w-24 rounded border-gray-300" />
                            </div>
                        </td>
                        <td v-if="groupBy !== 'buyer_code'" class="w-40 px-2 py-1.5">{{ item.buyer_code ?? item.customer_name }}</td>
                        <td class="w-32 px-2 py-1.5">{{ item.stock_code }}</td>
                        <td class="px-2 py-1.5">{{ item.stock_name }}</td>
                        <td v-if="groupBy !== 'family'" class="w-40 px-2 py-1.5 text-gray-500">{{ item.family }}</td>
                        <td v-if="groupBy !== 'maker'" class="w-32 px-2 py-1.5 text-gray-500">{{ item.maker }}</td>
                        <td class="w-24 px-2 py-1.5 text-right tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</td>
                        <td class="w-24 px-2 py-1.5 text-gray-500">{{ item.priority }}</td>
                        <td class="w-28 px-2 py-1.5 text-gray-500">{{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}</td>
                        <td class="w-20 px-4 py-1.5 text-gray-500">{{ item.state }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Table v-else :resource="data" class="mt-5">
        <template #cell(buyer_code)="{ item }">
            <span v-if="item.buyer_code">{{ item.buyer_code }}</span>
            <span v-else>{{ item.customer_name }} <span class="text-gray-500">{{ item.order_reference }}</span></span>
        </template>
        <template #cell(pick)="{ item }">
            <div class="flex items-center gap-2">
                <input v-if="item.state === 'open'" type="checkbox" :checked="item.id in selected" @change="toggle(item)" />
                <input
                    v-if="item.id in selected"
                    v-model.number="selected[item.id]"
                    type="number"
                    step="0.001"
                    :max="item.quantity"
                    min="0.001"
                    class="w-24 rounded border-gray-300"
                />
            </div>
        </template>
        <template #cell(quantity)="{ item }">
            <span class="tabular-nums">{{ useLocaleStore().number(Number(item.quantity)) }}</span>
        </template>
        <template #cell(needed_by)="{ item }">
            {{ item.needed_by ? useFormatTime(item.needed_by, { formatTime: "mdy" }) : "-" }}
        </template>
        <template #cell(created_at)="{ item }">
            {{ useFormatTime(item.created_at, { formatTime: "mdy" }) }}
        </template>
    </Table>
</template>
