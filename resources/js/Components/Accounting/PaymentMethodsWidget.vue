<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"
import PaymentMethodBadge from "@/Components/Accounting/PaymentMethodBadge.vue"

const locale = useLocaleStore()

defineProps<{
    summary: {
        currency_code: string
        period_label: string
        total_sales: number
        others: number
        methods: {
            method: string
            method_label: string
            payment_account_type: string
            number_payments: number
            total_sales: number
            share: number
        }[]
    }
    tableRoute: routeType
}>()
</script>

<template>
    <Link :href="route(tableRoute.name, tableRoute.parameters)"
        class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-400 transition-colors">
        <div class="flex items-baseline justify-between mb-3">
            <div class="font-medium text-gray-700">{{ trans("Payment methods") }}</div>
            <div class="text-xs text-gray-500">{{ summary.period_label.toLowerCase() }} · {{ locale.currencyFormat(summary.currency_code, summary.total_sales) }}</div>
        </div>

        <div v-if="!summary.methods.length" class="text-sm text-gray-400">{{ trans("No payments yet") }}</div>

        <div v-for="row in summary.methods" :key="row.method + row.payment_account_type" class="grid grid-cols-[1fr_auto] gap-x-3 items-center text-sm py-0.5">
            <div class="flex items-center gap-2 min-w-0">
                <div class="w-52 shrink-0 truncate">
                    <PaymentMethodBadge :label="row.method_label" :method="row.method" :accountType="row.payment_account_type" />
                </div>
                <div class="h-1.5 flex-1 rounded bg-gray-100">
                    <div class="h-1.5 rounded bg-indigo-500" :style="{ width: row.share + '%' }" />
                </div>
            </div>
            <div class="tabular-nums text-right text-gray-600 whitespace-nowrap">
                {{ row.share }}% <span class="text-gray-400 text-xs">· {{ row.number_payments.toLocaleString() }}</span>
            </div>
        </div>

        <div v-if="summary.others" class="text-xs text-gray-400 mt-1">{{ trans("+:n more", { n: summary.others }) }}</div>
    </Link>
</template>
