<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'
import { paymentProviderLogo } from '@/Composables/usePaymentProviderLogo'

type Row = {
    method: string
    method_label: string
    sub_method: string | null
    sub_method_label: string | null
    payment_account_type: string
    number_payments: number
    number_success: number
    total_sales: number
    last_payment_at: string | null
    route: routeType
}

const props = defineProps<{
    title: string
    pageHead: any
    data: { currency_code: string, rows: Row[] }
}>()

const locale = useLocaleStore()

/* Expanded by default: the schemes are why the screen exists. Collapsed is one line per method for
   anybody who only wants to know what share cards take. */
const showDetail = ref(true)

const groups = computed(() => {
    const byMethod: Record<string, any> = {}

    for (const row of props.data.rows) {
        const key = row.method + '|' + row.payment_account_type
        byMethod[key] ??= {
            key,
            label: row.method_label,
            method: row.method,
            payment_account_type: row.payment_account_type,
            rows: [],
            number_payments: 0, number_success: 0, total_sales: 0, last_payment_at: null as string | null,
            route: { name: row.route.name, parameters: { ...row.route.parameters, filter: { method: row.method } } },
        }
        const g = byMethod[key]
        g.rows.push(row)
        g.number_payments += row.number_payments
        g.number_success += row.number_success
        g.total_sales += row.total_sales
        if (row.last_payment_at && (!g.last_payment_at || row.last_payment_at > g.last_payment_at)) g.last_payment_at = row.last_payment_at
    }

    return Object.values(byMethod).sort((a: any, b: any) => b.total_sales - a.total_sales)
})

const totals = computed(() => groups.value.reduce((t: any, g: any) => ({
    number_payments: t.number_payments + g.number_payments,
    number_success: t.number_success + g.number_success,
    total_sales: t.total_sales + g.total_sales,
}), { number_payments: 0, number_success: 0, total_sales: 0 }))

const money = (value: number) => locale.currencyFormat(props.data.currency_code, value)
const share = (part: number, whole: number) => whole > 0 ? (part / whole * 100).toFixed(1) + '%' : '—'
const successRate = (success: number, total: number) => total > 0 ? (success / total * 100).toFixed(1) + '%' : '—'
const rateClass = (success: number, total: number) => {
    if (total === 0) return 'text-gray-400'
    const rate = success / total
    return rate >= 0.95 ? 'text-[#006300]' : rate >= 0.85 ? 'text-amber-600' : 'text-[#d03b3b]'
}
/* A scheme row that is really "no scheme recorded" is shown as such, not as a blank that looks like
   a rendering bug. */
const subLabel = (row: Row) => row.sub_method_label ?? trans('unspecified')

const columnHelp = {
    payments: trans('All payment attempts through this method, successful or not. Refunds are not counted.'),
    sales: trans('Amount of the successful payments, in the accounting currency.'),
    success: trans('Successful payments against all attempts. A low rate on cards usually means declined cards, on wallets or redirects usually means people giving up halfway.'),
    last: trans('When this method was last used.'),
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 rounded-xl ring-1 ring-gray-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <span class="text-sm font-medium text-gray-800">{{ trans('How customers paid') }}</span>
                <span class="ml-2 text-xs text-gray-400">{{ trans('every payment method, all time, in :currency', { currency: data.currency_code }) }}</span>
            </div>
            <button type="button" @click="showDetail = !showDetail"
                class="shrink-0 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-md px-2 py-1">
                {{ showDetail ? trans('Collapse') : trans('Expand') }}
            </button>
        </div>

        <table class="mt-4 w-full text-xs">
            <thead>
                <tr class="text-gray-400 border-b border-gray-100">
                    <th class="text-left font-normal py-1.5 pr-2">{{ trans('Method') }}</th>
                    <th class="text-right font-normal py-1.5 px-2">
                        {{ trans('Payments') }}<sup v-tooltip="columnHelp.payments" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                    </th>
                    <th class="text-right font-normal py-1.5 px-2">
                        {{ trans('Successful') }}<sup v-tooltip="columnHelp.success" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                    </th>
                    <th class="text-right font-normal py-1.5 px-2">
                        {{ trans('Sales') }}<sup v-tooltip="columnHelp.sales" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                    </th>
                    <th class="text-right font-normal py-1.5 pl-2">
                        {{ trans('Last used') }}<sup v-tooltip="columnHelp.last" class="ml-0.5 text-gray-300 cursor-help">?</sup>
                    </th>
                </tr>
            </thead>

            <tbody v-for="group in groups" :key="group.key">
                <tr class="text-gray-900 bg-gray-100/80 border-t-2 border-b border-gray-300 font-medium leading-tight">
                    <td class="py-1 pr-2">
                        <Link :href="route(group.route.name, group.route.parameters)" class="hover:underline">{{ group.label }}</Link>
                        <img v-if="group.payment_account_type !== group.method && paymentProviderLogo(group.payment_account_type)"
                            :src="paymentProviderLogo(group.payment_account_type)" :alt="group.payment_account_type" :title="group.payment_account_type"
                            class="inline-block ml-2 h-3 w-auto max-w-16 opacity-60 align-middle" />
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.25rem]">
                            <span>{{ locale.number(group.number_payments) }}</span>
                            <span class="font-normal text-gray-400">{{ share(group.number_payments, totals.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ locale.number(group.number_success) }}</span>
                            <span class="font-normal" :class="rateClass(group.number_success, group.number_payments)">{{ successRate(group.number_success, group.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[7rem_3.5rem]">
                            <span>{{ money(group.total_sales) }}</span>
                            <span class="font-normal text-gray-400">{{ share(group.total_sales, totals.total_sales) }}</span>
                        </span>
                    </td>
                    <td class="text-right pl-2 tabular-nums whitespace-nowrap font-normal text-gray-500">
                        {{ group.last_payment_at ? useFormatTime(group.last_payment_at) : '' }}
                    </td>
                </tr>
                <tr v-for="row in (showDetail && (group.rows.length > 1 || group.rows[0].sub_method) ? group.rows : [])" :key="row.sub_method ?? '-'"
                    class="border-b border-gray-50 text-gray-600">
                    <td class="py-1.5 pr-2 pl-5">
                        <Link :href="route(row.route.name, row.route.parameters)" class="text-gray-500 hover:text-gray-900 hover:underline"
                            :class="row.sub_method ? '' : 'italic text-gray-400'">{{ subLabel(row) }}</Link>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.25rem]">
                            <span>{{ locale.number(row.number_payments) }}</span>
                            <span class="text-gray-400">{{ share(row.number_payments, group.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ locale.number(row.number_success) }}</span>
                            <span :class="rateClass(row.number_success, row.number_payments)">{{ successRate(row.number_success, row.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[7rem_3.5rem]">
                            <span>{{ money(row.total_sales) }}</span>
                            <span class="text-gray-400">{{ share(row.total_sales, group.total_sales) }}</span>
                        </span>
                    </td>
                    <td class="text-right pl-2 tabular-nums whitespace-nowrap text-gray-400">
                        {{ row.last_payment_at ? useFormatTime(row.last_payment_at) : '' }}
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr class="text-gray-900 border-t-2 border-gray-300 font-medium">
                    <td class="py-1.5 pr-2">{{ trans('All methods') }}</td>
                    <td class="text-right px-2 tabular-nums">
                        <span class="inline-grid grid-cols-[4.5rem_3.25rem]"><span>{{ locale.number(totals.number_payments) }}</span><span></span></span>
                    </td>
                    <td class="text-right px-2 tabular-nums">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ locale.number(totals.number_success) }}</span>
                            <span class="font-normal" :class="rateClass(totals.number_success, totals.number_payments)">{{ successRate(totals.number_success, totals.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums">
                        <span class="inline-grid grid-cols-[7rem_3.5rem]"><span>{{ money(totals.total_sales) }}</span><span></span></span>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div v-if="!groups.length" class="mt-4 text-sm text-gray-400">{{ trans('No payments yet') }}</div>
    </div>
</template>
