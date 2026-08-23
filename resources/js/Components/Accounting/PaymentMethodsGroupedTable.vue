<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { trans } from 'laravel-vue-i18n'
import { useLocaleStore } from '@/Stores/locale'
import { useFormatTime } from '@/Composables/useFormatTime'
import { routeType } from '@/types/route'
import { paymentProviderLogo } from '@/Composables/usePaymentProviderLogo'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faCheckCircle } from '@fas'

export type PaymentMethodRow = {
    method: string
    method_label: string
    sub_method: string | null
    sub_method_label: string | null
    payment_account_type: string
    payment_account_label: string
    number_payments: number
    number_success: number
    total_sales: number
    last_payment_at: string | null
}

const props = defineProps<{
    title: string
    subtitle: string
    currencyCode: string
    rows: PaymentMethodRow[]
    paymentsRoute: routeType
    /* 'provider': Checkout.com → Card · Visa, Apple Pay, Klarna...   'method': PayPal → Checkout.com, PayPal */
    groupBy: 'provider' | 'method'
}>()

const locale = useLocaleStore()
const showDetail = ref(true)

const groupKeyOf = (row: PaymentMethodRow) => props.groupBy === 'provider' ? row.payment_account_type : row.method
const groupLabelOf = (row: PaymentMethodRow) => props.groupBy === 'provider' ? row.payment_account_label : row.method_label
/* The card scheme is a curiosity, not a decision: it only shows as the last level under Card in the
   method view, across every provider. Other methods break down by provider. The provider view stops
   at the method. */
const childKeyOf = (row: PaymentMethodRow) => props.groupBy === 'provider' ? row.method : (row.sub_method ?? row.payment_account_type)
const childLabelOf = (row: PaymentMethodRow) => props.groupBy === 'provider'
    ? row.method_label
    : (row.sub_method_label ?? row.payment_account_label)
/* Every row drills down to the payments list filtered to exactly what the row counts. */
const groupFilterOf = (row: PaymentMethodRow) => props.groupBy === 'provider' ? { payment_account_type: row.payment_account_type } : { method: row.method }
const childFilterOf = (row: PaymentMethodRow) => props.groupBy === 'provider'
    ? { payment_account_type: row.payment_account_type, method: row.method }
    : (row.sub_method ? { method: row.method, sub_method: row.sub_method } : { method: row.method, payment_account_type: row.payment_account_type })
const paymentsHref = (filter: Record<string, string>) => route(props.paymentsRoute.name, { ...props.paymentsRoute.parameters, filter })
const later = (a: string | null, b: string | null) => !a ? b : !b ? a : a > b ? a : b

const blank = () => ({ number_payments: 0, number_success: 0, total_sales: 0, last_payment_at: null as string | null })
const add = (into: any, row: PaymentMethodRow) => {
    into.number_payments += row.number_payments
    into.number_success += row.number_success
    into.total_sales += row.total_sales
    into.last_payment_at = later(into.last_payment_at, row.last_payment_at)
}

const groups = computed(() => {
    const byKey: Record<string, any> = {}
    for (const row of props.rows) {
        const key = groupKeyOf(row)
        byKey[key] ??= { key, label: groupLabelOf(row), href: paymentsHref(groupFilterOf(row)), logo: props.groupBy === 'provider' ? paymentProviderLogo(row.payment_account_type) : null, providers: new Set<string>(), children: {} as Record<string, any>, ...blank() }
        byKey[key].providers.add(row.payment_account_type)
        const g = byKey[key]
        add(g, row)
        const childKey = childKeyOf(row)
        g.children[childKey] ??= { key: childKey, label: childLabelOf(row), href: paymentsHref(childFilterOf(row)), ...blank() }
        add(g.children[childKey], row)
    }
    return Object.values(byKey)
        .map((g: any) => ({ ...g, children: Object.values(g.children).sort((a: any, b: any) => b.total_sales - a.total_sales) }))
        .sort((a: any, b: any) => b.total_sales - a.total_sales)
})

const totals = computed(() => groups.value.reduce((t: any, g: any) => ({
    number_payments: t.number_payments + g.number_payments,
    number_success: t.number_success + g.number_success,
    total_sales: t.total_sales + g.total_sales,
}), { number_payments: 0, number_success: 0, total_sales: 0 }))

/* One child is not a breakdown: the group row carries its provider mark instead of a second line. */
const expandable = (g: any) => g.children.length > 1

const money = (value: number) => locale.currencyFormat(props.currencyCode, value)
const share = (part: number, whole: number) => whole > 0 ? (part / whole * 100).toFixed(1) + '%' : '—'
/* Failures are the signal, not successes: the column counts attempts that did not go through and
   what share of attempts that was. Nothing failed reads as a faint tick, not a zero. */
const failed = (success: number, total: number) => total - success
const failureRate = (success: number, total: number) => total > 0 && success < total ? ((total - success) / total * 100).toFixed(1) + '%' : ''
const allGood = (success: number, total: number) => total > 0 && success === total
const rateClass = (success: number, total: number) => {
    if (total === 0 || success === total) return 'text-gray-400'
    const rate = (total - success) / total
    return rate <= 0.05 ? 'text-gray-500' : rate <= 0.15 ? 'text-amber-600' : 'text-[#d03b3b]'
}

const columnHelp = {
    payments: trans('All payment attempts, successful or not. Refunds are not counted.'),
    sales: trans('Amount of the successful payments, in the accounting currency.'),
    failed: trans('Attempts that did not go through, and their share of all attempts. On cards that is usually declined cards, on wallets and redirects usually people giving up halfway.'),
    last: trans('When it was last used.'),
}
</script>

<template>
    <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <span class="text-sm font-medium text-gray-800">{{ title }}</span>
                <span class="ml-2 text-xs text-gray-400">{{ subtitle }}</span>
            </div>
            <button type="button" @click="showDetail = !showDetail"
                class="shrink-0 text-xs text-gray-500 hover:text-gray-800 border border-gray-200 rounded-md px-2 py-1">
                {{ showDetail ? trans('Collapse') : trans('Expand') }}
            </button>
        </div>

        <table class="mt-4 w-full text-xs">
            <thead>
                <tr class="text-gray-400 border-b border-gray-100">
                    <th class="text-left font-normal py-1.5 pr-2">{{ groupBy === 'provider' ? trans('Provider') : trans('Method') }}</th>
                    <th class="text-right font-normal py-1.5 px-2">{{ trans('Payments') }}<sup v-tooltip="columnHelp.payments" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                    <th class="text-right font-normal py-1.5 px-2">{{ trans('Failed') }}<sup v-tooltip="columnHelp.failed" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                    <th class="text-right font-normal py-1.5 px-2">{{ trans('Amount') }}<sup v-tooltip="columnHelp.sales" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                    <th class="text-right font-normal py-1.5 pl-2">{{ trans('Last used') }}<sup v-tooltip="columnHelp.last" class="ml-0.5 text-gray-300 cursor-help">?</sup></th>
                </tr>
            </thead>

            <tbody v-for="group in groups" :key="group.key">
                <tr class="text-gray-900 bg-gray-100/80 border-t-2 border-b border-gray-300 font-medium leading-tight">
                    <td class="py-1 pr-2">
                        <span class="inline-flex items-center gap-2">
                            <span v-if="groupBy === 'provider'" class="inline-flex w-16 shrink-0 justify-center">
                                <img v-if="group.logo" :src="group.logo" :alt="group.label" class="h-3 w-auto max-w-16 opacity-70" />
                            </span>
                            <Link :href="group.href" class="hover:underline">{{ group.label }}</Link>
                            <img v-if="groupBy === 'method' && group.providers.size === 1 && [...group.providers][0] !== group.key && paymentProviderLogo([...group.providers][0])"
                                :src="paymentProviderLogo([...group.providers][0])" :alt="[...group.providers][0]" :title="[...group.providers][0]" class="h-3 w-auto max-w-16 opacity-60" />
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.25rem]">
                            <span>{{ locale.number(group.number_payments) }}</span>
                            <span class="font-normal text-gray-400">{{ share(group.number_payments, totals.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ allGood(group.number_success, group.number_payments) ? '' : locale.number(failed(group.number_success, group.number_payments)) }}</span>
                            <span class="font-normal" :class="rateClass(group.number_success, group.number_payments)"><FontAwesomeIcon v-if="allGood(group.number_success, group.number_payments)" :icon="faCheckCircle" class="opacity-25" fixed-width /><template v-else>{{ failureRate(group.number_success, group.number_payments) }}</template></span>
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
                <tr v-for="child in (showDetail && expandable(group) ? group.children : [])" :key="child.key"
                    class="border-b border-gray-50 text-gray-600">
                    <td class="py-1.5 pr-2" :class="groupBy === 'provider' ? 'pl-[4.5rem]' : 'pl-5'">
                        <Link :href="child.href" class="text-gray-500 hover:text-gray-900 hover:underline">{{ child.label }}</Link>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.25rem]">
                            <span>{{ locale.number(child.number_payments) }}</span>
                            <span class="text-gray-400">{{ share(child.number_payments, group.number_payments) }}</span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ allGood(child.number_success, child.number_payments) ? '' : locale.number(failed(child.number_success, child.number_payments)) }}</span>
                            <span :class="rateClass(child.number_success, child.number_payments)"><FontAwesomeIcon v-if="allGood(child.number_success, child.number_payments)" :icon="faCheckCircle" class="opacity-25" fixed-width /><template v-else>{{ failureRate(child.number_success, child.number_payments) }}</template></span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums whitespace-nowrap">
                        <span class="inline-grid grid-cols-[7rem_3.5rem]">
                            <span>{{ money(child.total_sales) }}</span>
                            <span class="text-gray-400">{{ share(child.total_sales, group.total_sales) }}</span>
                        </span>
                    </td>
                    <td class="text-right pl-2 tabular-nums whitespace-nowrap text-gray-400">
                        {{ child.last_payment_at ? useFormatTime(child.last_payment_at) : '' }}
                    </td>
                </tr>
            </tbody>

            <tfoot>
                <tr class="text-gray-900 border-t-2 border-gray-300 font-medium">
                    <td class="py-1.5 pr-2" :class="groupBy === 'provider' ? 'pl-[4.5rem]' : ''">
                        <Link :href="route(paymentsRoute.name, paymentsRoute.parameters)" class="hover:underline">{{ trans('All') }}</Link>
                    </td>
                    <td class="text-right px-2 tabular-nums"><span class="inline-grid grid-cols-[4.5rem_3.25rem]"><span>{{ locale.number(totals.number_payments) }}</span><span></span></span></td>
                    <td class="text-right px-2 tabular-nums">
                        <span class="inline-grid grid-cols-[4.5rem_3.5rem]">
                            <span>{{ allGood(totals.number_success, totals.number_payments) ? '' : locale.number(failed(totals.number_success, totals.number_payments)) }}</span>
                            <span class="font-normal" :class="rateClass(totals.number_success, totals.number_payments)"><FontAwesomeIcon v-if="allGood(totals.number_success, totals.number_payments)" :icon="faCheckCircle" class="opacity-25" fixed-width /><template v-else>{{ failureRate(totals.number_success, totals.number_payments) }}</template></span>
                        </span>
                    </td>
                    <td class="text-right px-2 tabular-nums"><span class="inline-grid grid-cols-[7rem_3.5rem]"><span>{{ money(totals.total_sales) }}</span><span></span></span></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <div v-if="!groups.length" class="mt-4 text-sm text-gray-400">{{ trans('No payments yet') }}</div>
    </div>
</template>
