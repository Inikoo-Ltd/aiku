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

defineProps<{
    title: string
    pageHead: any
    data: { currency_code: string, payments_route: routeType, rows: PaymentMethodRow[] }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 grid gap-4">
        <PaymentMethodsGroupedTable
            :title="trans('By provider')"
            :subtitle="trans('who processed it, and what customers paid with through them · all time, in :currency', { currency: data.currency_code })"
            :currencyCode="data.currency_code" :rows="data.rows" :paymentsRoute="data.payments_route" groupBy="provider" />
        <PaymentMethodsGroupedTable
            :title="trans('By payment method')"
            :subtitle="trans('what customers paid with, and which provider carried it · all time, in :currency', { currency: data.currency_code })"
            :currencyCode="data.currency_code" :rows="data.rows" :paymentsRoute="data.payments_route" groupBy="method" />
    </div>
</template>
