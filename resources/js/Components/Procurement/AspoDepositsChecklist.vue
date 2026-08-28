<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 23:40:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { trans } from "laravel-vue-i18n"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPlus } from "@fal"
import { routeType } from "@/types/route"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faPlus)

interface Application {
    id: number
    amount: number
    stock_delivery_reference: string | null
    created_by_name: string | null
    created_at: string
    is_removed: boolean
    deleted_by_name: string | null
    deleted_at: string | null
}

interface DepositRow {
    id: number
    reference: string | null
    amount: number
    currency_id: number
    currency_code: string
    state: string
    state_label: string
    paid_to_supplier_at: string | null
    unapplied_amount: number
    notes: string | null
    applications: Application[]
    updateRoute: routeType
    stateRoute: routeType
}

const props = defineProps<{
    deposits: {
        can_edit: boolean
        list: DepositRow[]
        storeRoute: routeType
        currency_code: string
        currency_id: number
        currencies: { id: number, code: string }[]
    }
}>()

const showForm = ref(false)
const form = ref<{ amount: string, currency_id: number | null, reference: string, notes: string }>({
    amount: "",
    currency_id: props.deposits.currency_id,
    reference: "",
    notes: "",
})

const onError = () => {
    notify({ title: trans("Something went wrong"), text: trans("Failed to save the deposit"), type: "error" })
}

const submitDeposit = () => {
    if (!form.value.amount || isNaN(Number(form.value.amount))) return

    router.post(
        route(props.deposits.storeRoute.name, props.deposits.storeRoute.parameters),
        {
            amount:      Number(form.value.amount),
            currency_id: form.value.currency_id,
            reference:   form.value.reference || null,
            notes:       form.value.notes || null,
        },
        {
            preserveScroll: true,
            onError,
            onSuccess: () => {
                showForm.value = false
                form.value = { amount: "", currency_id: props.deposits.currency_id, reference: "", notes: "" }
            },
        }
    )
}

const markPaidToSupplier = (row: DepositRow) => {
    router.patch(route(row.stateRoute.name, row.stateRoute.parameters), { state: "paid_to_supplier" }, { preserveScroll: true, onError })
}
</script>

<template>
    <div class="px-4 py-3 border-b border-gray-300 text-gray-600">
        <div class="flex items-center gap-2 mb-2">
            <span class="font-medium">{{ trans("Supplier deposits") }}</span>
        </div>

        <div v-for="row in deposits.list" :key="row.id" class="mb-3 text-sm">
            <div class="flex items-center gap-3">
                <span class="w-28 shrink-0">{{ row.amount }} {{ row.currency_code }}</span>
                <span class="w-32 shrink-0">{{ row.state_label }}</span>
                <span class="text-gray-400">{{ row.unapplied_amount }} {{ trans("unapplied") }}</span>

                <label v-if="deposits.can_edit && row.state === 'pending'" class="flex items-center gap-2 text-xs">
                    <input type="checkbox" @change="markPaidToSupplier(row)" />
                    {{ trans("Mark paid to supplier") }}
                </label>
            </div>

            <div v-if="row.applications.length" class="mt-1 pl-2 border-l-2 border-gray-200 text-xs text-gray-500">
                <div v-for="application in row.applications" :key="application.id" :class="application.is_removed ? 'line-through text-gray-400' : ''">
                    <span>{{ application.stock_delivery_reference }}</span>
                    <span class="ml-2">{{ application.amount }} {{ row.currency_code }}</span>
                    <span class="ml-2">{{ application.created_by_name }} · {{ useFormatTime(application.created_at) }}</span>
                    <span v-if="application.is_removed" class="ml-2 text-red-500">
                        {{ trans("removed by") }} {{ application.deleted_by_name }} · {{ useFormatTime(application.deleted_at as string) }}
                    </span>
                </div>
            </div>
        </div>

        <button
            v-if="deposits.can_edit && !showForm"
            type="button"
            class="mt-2 flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700"
            @click="showForm = true"
        >
            <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
            {{ trans("Add deposit") }}
        </button>

        <div v-if="showForm" class="mt-2 flex items-center gap-2 text-sm">
            <input v-model="form.amount" type="number" min="0" step="0.01" class="w-28 h-7 rounded border-gray-300 text-sm" :placeholder="trans('Amount')" />
            <select v-model="form.currency_id" class="h-7 rounded border-gray-300 text-sm">
                <option v-for="currency in deposits.currencies" :key="currency.id" :value="currency.id">{{ currency.code }}</option>
            </select>
            <input v-model="form.reference" type="text" class="w-32 h-7 rounded border-gray-300 text-sm" :placeholder="trans('Reference')" />
            <input v-model="form.notes" type="text" class="w-40 h-7 rounded border-gray-300 text-sm" :placeholder="trans('Notes')" />
            <button type="button" class="text-xs text-gray-500 hover:text-gray-700" @click="submitDeposit">{{ trans("Save") }}</button>
            <button type="button" class="text-xs text-gray-400 hover:text-gray-600" @click="showForm = false">{{ trans("Cancel") }}</button>
        </div>
    </div>
</template>
