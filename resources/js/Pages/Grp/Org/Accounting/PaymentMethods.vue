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

defineProps<{
    title: string
    pageHead: any
    data: { currency_code: string, rows: PaymentMethodRow[] }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 my-4 grid gap-4">
        <PaymentMethodsGroupedTable
            :title="trans('By provider')"
            :subtitle="trans('who processed it, and what customers paid with through them · all time, in :currency', { currency: data.currency_code })"
            :currencyCode="data.currency_code" :rows="data.rows" groupBy="provider" />
        <PaymentMethodsGroupedTable
            :title="trans('By payment method')"
            :subtitle="trans('what customers paid with, and which provider carried it · all time, in :currency', { currency: data.currency_code })"
            :currencyCode="data.currency_code" :rows="data.rows" groupBy="method" />
    </div>
</template>
