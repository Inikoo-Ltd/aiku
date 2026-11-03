<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 03 Nov 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3"
import { ref } from "vue"
import ToggleSwitch from "primevue/toggleswitch"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { capitalize } from "@/Composables/capitalize"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { PageHeadingTypes } from "@/types/PageHeading"

const props = defineProps<{
    title: string
    pageHead: PageHeadingTypes
    awaiting_only: boolean
    awaiting_count: number
    currency_code: string
    data: object
}>()

const locale = useLocaleStore()
const awaitingOnly = ref(props.awaiting_only)
const processing = ref(false)

function filterAwaiting(value: boolean) {
    router.get(window.location.pathname, { awaiting_only: value ? 1 : undefined }, { preserveScroll: true })
}

function approve(item: { approve_route: null | { name: string, parameters: object } }) {
    if (!item.approve_route) return
    processing.value = true
    router.patch(route(item.approve_route.name, item.approve_route.parameters), {}, { preserveScroll: true, onFinish: () => processing.value = false })
}

const signedMoney = (amount: number | null) => amount === null ? "" : (amount > 0 ? "+" : "") + locale.currencyFormat(props.currency_code, amount)
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="mx-4 mt-4 flex flex-wrap items-center gap-4 text-sm text-gray-600">
        <label class="flex items-center gap-2">
            <ToggleSwitch v-model="awaitingOnly" @update:modelValue="filterAwaiting" />
            {{ ctrans('Only changes awaiting the accountant') }}
        </label>
        <span v-if="awaiting_count" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800">{{ ctrans(':count awaiting the accountant', { count: awaiting_count }) }}</span>
    </div>
    <p class="mx-4 mt-2 text-xs text-gray-500">
        {{ ctrans('Every change made to a week after it was locked for payroll. The difference in pay is paid or deducted in the pay run of the week the change was made. These records cannot be edited or deleted.') }}
    </p>

    <Table :resource="data" class="mt-2">
        <template #cell(created_at)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.created_at, { formatTime: 'd MMM yyyy HH:mm' }) }}</span>
        </template>
        <template #cell(pay_week)="{ item }">
            <span class="whitespace-nowrap">{{ useFormatTime(item.pay_week) }}</span>
        </template>
        <template #cell(change)="{ item }">
            <div class="font-medium text-gray-700">{{ item.change }}</div>
            <div v-for="change in item.changes" :key="change.field" class="text-xs text-gray-500 whitespace-nowrap">
                {{ change.field }}: {{ change.before ?? '—' }} → {{ change.after ?? '—' }}
            </div>
        </template>
        <template #cell(amount_delta)="{ item }">
            <span class="tabular-nums whitespace-nowrap" :class="item.amount_delta < 0 ? 'text-red-600' : ''">{{ signedMoney(item.amount_delta) }}</span>
        </template>
        <template #cell(approval)="{ item }">
            <span v-if="item.approved_at" class="text-xs text-gray-500 whitespace-nowrap">{{ ctrans('Approved by :user, :when', { user: item.approved_by ?? '', when: useFormatTime(item.approved_at) }) }}</span>
            <Button v-else-if="item.approve_route" type="tertiary" size="xs" :label="ctrans('Seen / approved')" :disabled="processing" @click="approve(item)" />
            <span v-else-if="item.awaiting_approval" class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-800 whitespace-nowrap">{{ ctrans('Awaiting the accountant') }}</span>
            <span v-else class="text-xs text-gray-400">{{ ctrans('Made by the accountant') }}</span>
        </template>
    </Table>
</template>
