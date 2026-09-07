<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 08 Aug 2026 19:30:00 Central European Summer Time, Mijas, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { useFormatTime } from '@/Composables/useFormatTime'
import { trans } from 'laravel-vue-i18n'
import AspoDepositsChecklist from '@/Components/Procurement/AspoDepositsChecklist.vue'

const props = defineProps<{
    pageHead: {}
    title: string
    deposits: object
    showcase: {
        reference: string
        state: string
        delivery_state: string
        date: string
        confirmed_at?: string
        cancelled_at?: string
        cost_total: number
        currency_code: string
        notes?: string
        deposit_amount?: number
        deposit_paid_at?: string
        balance_paid_at?: string
        estimated_received_at?: string
        is_overdue: boolean
        supplier?: { code: string, name: string, route: { name: string, parameters: string[] } }
        purchase_order?: { reference: string }
        number_transactions: number
    }
}>()
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>

    <div class="px-4 py-5 max-w-xl">
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
            <dt class="text-gray-500">{{ trans('Supplier') }}</dt>
            <dd>
                <Link v-if="showcase.supplier" :href="route(showcase.supplier.route.name, showcase.supplier.route.parameters)" class="primaryLink">
                    {{ showcase.supplier.code }}
                </Link>
                <span v-if="showcase.supplier" class="ml-2 text-gray-500">{{ showcase.supplier.name }}</span>
            </dd>

            <dt class="text-gray-500">{{ trans('Purchase order') }}</dt>
            <dd>{{ showcase.purchase_order?.reference }}</dd>

            <dt class="text-gray-500">{{ trans('State') }}</dt>
            <dd>{{ showcase.state }}</dd>

            <dt class="text-gray-500">{{ trans('Delivery') }}</dt>
            <dd>{{ showcase.delivery_state }}</dd>

            <dt class="text-gray-500">{{ trans('Date') }}</dt>
            <dd>{{ useFormatTime(showcase.date) }}</dd>

            <template v-if="showcase.confirmed_at">
                <dt class="text-gray-500">{{ trans('Confirmed') }}</dt>
                <dd>{{ useFormatTime(showcase.confirmed_at) }}</dd>
            </template>

            <template v-if="showcase.cancelled_at">
                <dt class="text-gray-500">{{ trans('Cancelled') }}</dt>
                <dd>{{ useFormatTime(showcase.cancelled_at) }}</dd>
            </template>

            <dt class="text-gray-500">{{ trans('Amount') }}</dt>
            <dd>{{ showcase.cost_total }} {{ showcase.currency_code }}</dd>

            <dt class="text-gray-500">{{ trans('Deposit') }}</dt>
            <dd>{{ showcase.deposit_amount != null ? `${showcase.deposit_amount} ${showcase.currency_code}` : '-' }}</dd>

            <dt class="text-gray-500">{{ trans('Balance paid') }}</dt>
            <dd>{{ showcase.balance_paid_at ? useFormatTime(showcase.balance_paid_at) : '-' }}</dd>

            <dt class="text-gray-500">{{ trans('Expected by') }}</dt>
            <dd>
                {{ showcase.estimated_received_at ? useFormatTime(showcase.estimated_received_at) : '-' }}
                <span v-if="showcase.is_overdue" class="ml-2 inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                    {{ trans('Overdue') }}
                </span>
            </dd>

            <dt class="text-gray-500">{{ trans('Transactions') }}</dt>
            <dd>{{ showcase.number_transactions }}</dd>

            <template v-if="showcase.notes">
                <dt class="text-gray-500">{{ trans('Notes') }}</dt>
                <dd>{{ showcase.notes }}</dd>
            </template>
        </dl>
    </div>

    <AspoDepositsChecklist :deposits="deposits" />
</template>
