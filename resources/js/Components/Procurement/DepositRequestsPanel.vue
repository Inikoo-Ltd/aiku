<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { routeType } from "@/types/route"

interface PendingDeposit {
    id: number
    reference: string | null
    amount: number
    currency_code: string
    organisation_id: number
    organisation_name: string
}

interface RequestItem {
    id: number
    organisation_name: string
    amount: number
    exchange: number
    paid_at: string | null
    markPaidRoute: routeType | null
}

interface DepositRequestRow {
    id: number
    reference: string | null
    state: string
    currency_code: string
    items: RequestItem[]
}

const props = defineProps<{
    data: {
        pendingDeposits: PendingDeposit[]
        requests: DepositRequestRow[]
        storeRoute: routeType
    }
}>()

const selected = ref<number[]>([])
const currencyId = ref<number | null>(null)

const onError = () => notify({ title: trans("Something went wrong"), type: "error" })

const createRequest = () => {
    if (!selected.value.length) return

    const items = selected.value.map(id => {
        const deposit = props.data.pendingDeposits.find(d => d.id === id)!
        return { aspo_deposit_id: deposit.id, organisation_id: deposit.organisation_id, amount: deposit.amount, exchange: 1 }
    })

    router.post(
        route(props.data.storeRoute.name, props.data.storeRoute.parameters),
        { currency_id: currencyId.value ?? undefined, items },
        { preserveScroll: true, onError, onSuccess: () => { selected.value = [] } }
    )
}

const markPaid = (item: RequestItem) => {
    if (!item.markPaidRoute) return
    router.patch(route(item.markPaidRoute.name, item.markPaidRoute.parameters), {}, { preserveScroll: true, onError })
}
</script>

<template>
    <div class="mt-6 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5">
        <div class="border-b border-gray-900/5 bg-gray-50/80 px-6 py-5">
            <h2 class="text-base font-semibold text-gray-900">{{ trans("Deposit requests") }}</h2>
        </div>

        <div class="px-6 py-4 text-sm">
            <div class="font-medium mb-1">{{ trans("Pending deposits") }}</div>
            <div v-for="deposit in data.pendingDeposits" :key="deposit.id" class="flex items-center gap-2">
                <input type="checkbox" :value="deposit.id" v-model="selected" />
                <span>{{ deposit.organisation_name }}</span>
                <span>{{ deposit.amount }} {{ deposit.currency_code }}</span>
            </div>
            <button
                v-if="data.pendingDeposits.length"
                type="button"
                class="mt-2 text-xs text-gray-500 hover:text-gray-700"
                @click="createRequest"
            >
                {{ trans("Create deposit request") }}
            </button>

            <div class="font-medium mt-4 mb-1">{{ trans("Requests") }}</div>
            <div v-for="request in data.requests" :key="request.id" class="mb-2">
                <div class="text-gray-500">{{ request.reference || request.id }} — {{ request.state }}</div>
                <div v-for="item in request.items" :key="item.id" class="flex items-center gap-2 pl-3">
                    <span>{{ item.organisation_name }}</span>
                    <span>{{ item.amount }} {{ request.currency_code }}</span>
                    <label v-if="!item.paid_at && item.markPaidRoute" class="flex items-center gap-1 text-xs">
                        <input type="checkbox" @change="markPaid(item)" />
                        {{ trans("Mark paid") }}
                    </label>
                    <span v-else-if="item.paid_at" class="text-xs text-green-600">{{ trans("Paid") }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
