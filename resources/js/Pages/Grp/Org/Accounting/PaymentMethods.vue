<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import PaymentMethodsGroupedTable, { PaymentMethodRow } from '@/Components/Accounting/PaymentMethodsGroupedTable.vue'
import { routeType } from '@/types/route'
import { Intervals, Settings } from '@/types/Components/Dashboard'
import DashboardSettings from '@/Components/DataDisplay/Dashboard/DashboardSettings.vue'

defineProps<{
    title: string
    pageHead: any
    data: { currency_code: string, payments_route: routeType, period_label: string, period_from: string | null, period_to: string | null, rows: PaymentMethodRow[] }
    intervals: Intervals
    settings: Settings
}>()

const periodText = (data: { period_label: string, period_from: string | null }) =>
    data.period_from ? data.period_label.toLowerCase() : trans('all time')
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
    <div class="pt-3">
        <DashboardSettings :intervals="intervals" :settings="settings" currentTab="payment_methods" :reloadOnly="['data', 'intervals']" />
    </div>

    <div class="mx-4 my-4 grid gap-4">
        <PaymentMethodsGroupedTable
            :title="trans('By provider')"
            :subtitle="trans('who processed it, and what customers paid with through them') + ' · ' + periodText(data) + ', ' + data.currency_code"
            :currencyCode="data.currency_code" :rows="data.rows" :paymentsRoute="data.payments_route" groupBy="provider" />
        <PaymentMethodsGroupedTable
            :title="trans('By payment method')"
            :subtitle="trans('what customers paid with, and which provider carried it') + ' · ' + periodText(data) + ', ' + data.currency_code"
            :currencyCode="data.currency_code" :rows="data.rows" :paymentsRoute="data.payments_route" groupBy="method" />
    </div>
</template>
