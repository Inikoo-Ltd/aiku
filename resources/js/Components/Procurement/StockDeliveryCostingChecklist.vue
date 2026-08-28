<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faPlus, faTrashAlt } from "@fal"
import { faCheckCircle as fasCheckCircle, faExclamationTriangle } from "@fas"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"

library.add(faCheckCircle, faPlus, faTrashAlt, fasCheckCircle, faExclamationTriangle)

interface CostRow {
    id: number | null
    type: string
    label: string
    amount: number | string | null
    received_at: string | null
    is_na: boolean
    updateRoute: routeType | null
    deleteRoute: routeType | null
}

interface AvailableDeposit {
    id: number
    reference: string | null
    unapplied_amount: number
    currency_code: string
}

const props = defineProps<{
    costing: {
        is_costed: boolean
        currency: string | null
        checklist: CostRow[]
        agent_invoice_missing: boolean
        storeCostRoute: routeType
        deposits: {
            applied: { id: number, amount: number, reference: string | null, deleteRoute: routeType | null }[]
            applied_total: number
            agent_invoice_amount: number
            balance_due: number
            available: AvailableDeposit[]
            applyRoute: routeType
        }
    }
    canEdit: boolean
}>()

const selectedDepositId = ref<number | null>(null)
const applyAmount = ref<string>("")

const applyDeposit = () => {
    if (!selectedDepositId.value || !applyAmount.value) return

    router.post(
        route(props.costing.deposits.applyRoute.name, props.costing.deposits.applyRoute.parameters),
        { aspo_deposit_id: selectedDepositId.value, amount: Number(applyAmount.value) },
        { preserveScroll: true, onError, onSuccess: () => { selectedDepositId.value = null; applyAmount.value = "" } }
    )
}

const unapplyDeposit = (application: { deleteRoute: routeType | null }) => {
    if (!application.deleteRoute) return
    if (!window.confirm(trans("Remove this deposit application? This is audited and visible to org staff and the agent."))) return

    router.delete(route(application.deleteRoute.name, application.deleteRoute.parameters), { preserveScroll: true, onError })
}

const locale = useLocaleStore()
const loading = ref<string | null>(null)
const drafts = ref<{ [key: string]: string }>(
    Object.fromEntries(props.costing.checklist.map(row => [rowKey(row), row.amount == null ? "" : String(row.amount)]))
)

function rowKey(row: CostRow) {
    return row.id ? `id-${row.id}` : `type-${row.type}`
}

const onError = () => {
    notify({ title: trans("Something went wrong"), text: trans("Failed to save the cost"), type: "error" })
}

const save = (row: CostRow, payload: { amount?: string | null, received?: boolean, is_na?: boolean }) => {
    const data: { [key: string]: string | number | boolean | null } = {}

    if ("amount" in payload) {
        data.amount = payload.amount === "" || payload.amount == null ? null : Number(payload.amount)
    }
    if ("received" in payload) {
        data.received_at = payload.received ? new Date().toISOString() : null
    }
    if ("is_na" in payload) {
        data.is_na = payload.is_na
    }

    const key = rowKey(row)
    const options = {
        preserveScroll: true,
        onStart: () => { loading.value = key },
        onFinish: () => { loading.value = null },
        onError,
    }

    if (row.updateRoute) {
        router.patch(route(row.updateRoute.name, row.updateRoute.parameters), data, options)
    } else {
        router.post(route(props.costing.storeCostRoute.name, props.costing.storeCostRoute.parameters), { type: row.type, ...data }, options)
    }
}

const addExtra = () => {
    const label = window.prompt(trans("Extra expense description (e.g. fine, customs storage)"))
    if (!label) return

    router.post(
        route(props.costing.storeCostRoute.name, props.costing.storeCostRoute.parameters),
        { type: "extra", label },
        { preserveScroll: true, onError }
    )
}

const removeExtra = (row: CostRow) => {
    if (!row.deleteRoute) return

    router.delete(route(row.deleteRoute.name, row.deleteRoute.parameters), { preserveScroll: true, onError })
}
</script>

<template>
    <div class="px-4 py-3 border-b border-gray-300 text-gray-600">
        <div class="flex items-center gap-2 mb-2">
            <FontAwesomeIcon
                :icon="costing.is_costed ? 'fas fa-check-circle' : 'fal fa-check-circle'"
                :class="costing.is_costed ? 'text-green-500' : 'text-gray-400'"
                fixed-width
                aria-hidden="true"
            />
            <span class="font-medium">{{ trans("Costing") }}</span>
            <span v-if="costing.is_costed" class="text-sm text-green-600">{{ trans("Done") }}</span>
            <span v-else-if="costing.agent_invoice_missing" class="flex items-center gap-1 text-sm text-orange-500">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ trans("Agent invoice not received") }}
            </span>
        </div>

        <div class="grid gap-1 text-sm">
            <div
                v-for="row in costing.checklist"
                :key="rowKey(row)"
                class="flex items-center gap-3"
                :class="row.is_na ? 'text-gray-400' : ''"
            >
                <label class="flex items-center gap-2 w-40 shrink-0">
                    <input
                        type="checkbox"
                        :checked="!!row.received_at"
                        :disabled="!canEdit || row.is_na || loading === rowKey(row)"
                        @change="save(row, { received: ($event.target as HTMLInputElement).checked })"
                    />
                    <span :class="row.received_at ? '' : 'italic'">{{ row.label }}</span>
                </label>

                <div class="flex items-center gap-1">
                    <input
                        v-model="drafts[rowKey(row)]"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-32 h-7 rounded border-gray-300 text-sm disabled:bg-gray-100"
                        :disabled="!canEdit || row.is_na"
                        @blur="drafts[rowKey(row)] !== (row.amount == null ? '' : String(row.amount)) && save(row, { amount: drafts[rowKey(row)] })"
                    />
                    <span class="text-gray-400">{{ costing.currency }}</span>
                </div>

                <label v-if="row.type !== 'agent_invoice'" class="flex items-center gap-1 text-xs">
                    <input
                        type="checkbox"
                        :checked="row.is_na"
                        :disabled="!canEdit"
                        @change="save(row, { is_na: ($event.target as HTMLInputElement).checked })"
                    />
                    {{ trans("N/A") }}
                </label>

                <button
                    v-if="canEdit && row.deleteRoute"
                    type="button"
                    class="text-gray-400 hover:text-red-500"
                    @click="removeExtra(row)"
                >
                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                </button>
            </div>
        </div>

        <button
            v-if="canEdit"
            type="button"
            class="mt-2 flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700"
            @click="addExtra"
        >
            <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
            {{ trans("Add extra expense") }}
        </button>

        <div class="mt-4 pt-3 border-t border-gray-200 text-sm">
            <div class="font-medium mb-1">{{ trans("Settlement") }}</div>
            <div v-for="application in costing.deposits.applied" :key="application.id" class="flex items-center gap-3">
                <span>{{ application.reference || application.id }}</span>
                <span>{{ application.amount }} {{ costing.currency }}</span>
                <button
                    v-if="canEdit && application.deleteRoute"
                    type="button"
                    class="text-gray-400 hover:text-red-500"
                    :title="trans('Un-apply deposit (audited)')"
                    @click="unapplyDeposit(application)"
                >
                    <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                </button>
            </div>

            <div class="mt-1 grid gap-1">
                <div class="flex items-center gap-2">
                    <span class="text-gray-500">{{ trans("Agent invoice") }}</span>
                    <span>{{ costing.deposits.agent_invoice_amount }} {{ costing.currency }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-gray-500">{{ trans("Deposits applied") }}</span>
                    <span>{{ Number(costing.deposits.applied_total) > 0 ? '-' : '' }}{{ costing.deposits.applied_total }} {{ costing.currency }}</span>
                </div>
                <div class="flex items-center gap-2 font-medium">
                    <span>{{ trans("Balance due") }}</span>
                    <span>{{ costing.deposits.balance_due }} {{ costing.currency }}</span>
                </div>
            </div>

            <div v-if="canEdit && costing.deposits.available.length" class="mt-2 flex items-center gap-2">
                <select v-model="selectedDepositId" class="h-7 rounded border-gray-300 text-sm">
                    <option :value="null">{{ trans("Select a paid deposit") }}</option>
                    <option v-for="deposit in costing.deposits.available" :key="deposit.id" :value="deposit.id">
                        {{ deposit.reference || deposit.id }} ({{ deposit.unapplied_amount }} {{ deposit.currency_code }} {{ trans("unapplied") }})
                    </option>
                </select>
                <input v-model="applyAmount" type="number" min="0" step="0.01" class="w-28 h-7 rounded border-gray-300 text-sm" :placeholder="trans('Amount')" />
                <button type="button" class="text-xs text-gray-500 hover:text-gray-700" @click="applyDeposit">
                    {{ trans("Apply deposit") }}
                </button>
            </div>
        </div>
    </div>
</template>
