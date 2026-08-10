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

const props = defineProps<{
    costing: {
        is_costed: boolean
        currency: string | null
        checklist: CostRow[]
        agent_invoice_missing: boolean
        storeCostRoute: routeType
    }
    canEdit: boolean
}>()

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
    </div>
</template>
