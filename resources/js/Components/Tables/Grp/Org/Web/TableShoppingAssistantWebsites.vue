<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

defineProps<{
    data: object
    tab?: string
    currency: string
    conversationsUrl: (websiteId: number) => string
}>()

const locale = useLocaleStore()

const budgetUsed = (budget: { monthly: number; spent: number; paused: boolean }) => {
    const used = budget.monthly > 0 ? budget.spent / budget.monthly : 1
    return { width: Math.min(100, used * 100), warning: !budget.paused && used >= 0.8 }
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-2">
        <template #cell(domain)="{ item }">
            <Link :href="route(item.route.name, item.route.parameters)" class="primaryLink whitespace-nowrap">{{ item.domain }}</Link>
            <span v-if="!item.enabled" class="ml-2 whitespace-nowrap text-xs text-gray-400">{{ ctrans("Off") }}</span>
        </template>

        <template #cell(conversations)="{ item }">
            <Link v-if="item.conversations" :href="conversationsUrl(item.id)" class="primaryLink tabular-nums">{{ locale.number(item.conversations) }}</Link>
            <span v-else class="tabular-nums text-gray-400">0</span>
        </template>

        <template #cell(customers)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.customers) }}</span>
        </template>

        <template #cell(orders)="{ item }">
            <span class="tabular-nums">{{ locale.number(item.orders) }}</span>
        </template>

        <template #cell(revenue)="{ item }">
            <span class="whitespace-nowrap tabular-nums">{{ locale.currencyFormat(currency, item.revenue) }}</span>
        </template>

        <template #cell(conversion)="{ item }">
            <span class="tabular-nums">{{ (item.conversion * 100).toFixed(1) }}%</span>
        </template>

        <template #cell(cost)="{ item }">
            <span class="whitespace-nowrap tabular-nums text-gray-600">{{ locale.currencyFormat("USD", item.cost) }}</span>
        </template>

        <template #cell(budget)="{ item }">
            <div class="grid grid-cols-[8rem_5rem_1rem_5rem_auto] items-center gap-1 whitespace-nowrap text-sm">
                <div class="h-2 rounded-sm bg-gray-100">
                    <div
                        class="h-2 rounded-sm"
                        :class="item.budget.paused ? 'bg-red-500' : budgetUsed(item.budget).warning ? 'bg-amber-500' : 'bg-[var(--app-accent)]'"
                        :style="{ width: budgetUsed(item.budget).width + '%' }" />
                </div>
                <span class="text-right tabular-nums text-gray-900">{{ locale.currencyFormat("USD", item.budget.spent) }}</span>
                <span class="text-center text-gray-400">/</span>
                <span class="text-right tabular-nums text-gray-500">{{ locale.currencyFormat("USD", item.budget.monthly) }}</span>
                <span v-if="item.budget.paused" class="pl-2 text-xs text-red-600">{{ ctrans("Paused") }}</span>
                <span v-else-if="budgetUsed(item.budget).warning" class="pl-2 text-xs text-amber-700">{{ ctrans("80% used") }}</span>
                <span v-else />
            </div>
        </template>
    </Table>
</template>
