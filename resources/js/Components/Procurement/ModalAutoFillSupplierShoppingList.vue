<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 31 Aug 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import { useLocaleStore } from "@/Stores/locale"

const locale = useLocaleStore()

interface ProposalLine {
    org_supplier_product_id: number
    code: string
    name: string
    quantity: number
    cost_per_unit: number
    cost: number
    cartons: number | null
    minimum_carton_order: number | null
    reason: string
    selected?: boolean
}

const props = defineProps<{
    orgSupplierSlug: string
    currency: string
    bucket?: string | null
    rank?: string | null
    scopeLabel?: string | null
}>()

const model = defineModel()

const budget = ref<number | null>(1000)
const isGenerating = ref(false)
const isCommitting = ref(false)
const proposal = ref<ProposalLine[] | null>(null)

watch(model, (open) => {
    if (open && props.bucket) {
        proposal.value = null
        generate()
    }
})

const closeModal = () => {
    model.value = false
    proposal.value = null
}

const selectedLines = computed(() => (proposal.value ?? []).filter((line) => line.selected && line.quantity > 0))

const selectedTotal = computed(() =>
    Math.round(selectedLines.value.reduce((sum, line) => sum + line.quantity * line.cost_per_unit, 0) * 100) / 100
)

const overBudget = computed(() => budget.value !== null && selectedTotal.value > budget.value)

const belowMoq = (line: ProposalLine) =>
    line.minimum_carton_order !== null && line.cartons !== null && line.cartons < line.minimum_carton_order

async function generate() {
    if (!budget.value) return
    isGenerating.value = true

    try {
        const response = await axios.post(
            route("grp.org.procurement.org_suppliers.show.shopping.suggest", [
                route().params["organisation"],
                props.orgSupplierSlug,
            ]),
            { budget: budget.value, bucket: props.bucket ?? null, rank: props.rank ?? null }
        )
        proposal.value = (response.data.lines ?? []).map((line: ProposalLine) => ({ ...line, selected: true }))
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Could not generate a proposal"),
            type: "error",
        })
    } finally {
        isGenerating.value = false
    }
}

async function commit() {
    if (!selectedLines.value.length) return
    isCommitting.value = true

    try {
        await axios.post(
            route("grp.org.procurement.shopping_list.bulk_store", [route().params["organisation"]]),
            {
                lines: selectedLines.value.map((line) => ({
                    org_supplier_product_id: line.org_supplier_product_id,
                    quantity_units: line.quantity,
                    notes: line.reason || null,
                })),
            }
        )
        notify({
            title: trans("Success"),
            text: `${selectedLines.value.length} ${trans("items added to the shopping list")}`,
            type: "success",
        })
        closeModal()
        router.reload()
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error?.response?.data?.message || trans("Could not add the items"),
            type: "error",
        })
    } finally {
        isCommitting.value = false
    }
}
</script>

<template>
    <Modal :isOpen="model" @onClose="closeModal" :closeButton="true" width="w-full max-w-2xl md:max-w-4xl">
        <div class="flex h-[600px] flex-col px-4">
            <div class="mb-3 flex justify-center py-2 font-medium text-gray-600">
                <h2>{{ trans("Auto-fill shopping list") }}</h2>
            </div>
            <div v-if="scopeLabel" class="mb-2 text-center text-sm text-gray-500">
                {{ trans("Limited to") }}: <span class="font-medium text-gray-700">{{ scopeLabel }}</span>
            </div>

            <div class="flex items-end gap-3">
                <div>
                    <label class="text-sm text-gray-500">{{ trans("Budget") }} ({{ currency }})</label>
                    <input v-model.number="budget" type="number" min="1" step="1" class="block w-32 rounded border-gray-300" />
                </div>
                <div class="flex-1 text-xs text-gray-500">
                    {{ trans("Quantities are the forecaster's recommended order, rounded up to whole cartons, tightest cover first.") }}
                </div>
                <Button
                    type="create"
                    :label="isGenerating ? trans('Generating…') : proposal ? trans('Regenerate') : trans('Generate proposal')"
                    :loading="isGenerating"
                    :disabled="!budget"
                    @click="generate"
                />
            </div>

            <div v-if="isGenerating" class="mt-8 text-center text-gray-500">
                {{ trans("Working out what we need from this supplier…") }}
            </div>

            <template v-else-if="proposal">
                <div v-if="!proposal.length" class="mt-8 text-center text-gray-500">
                    {{ trans("Nothing to suggest: no usage history for the products this supplier sells us") }}
                </div>

                <template v-else>
                    <div class="mt-4 flex-1 overflow-y-auto">
                        <table class="w-full text-xs">
                            <thead class="sticky top-0 bg-white">
                                <tr class="border-b border-gray-200 text-left text-gray-500">
                                    <th class="py-1"></th>
                                    <th class="py-1">{{ trans("Product") }}</th>
                                    <th class="py-1">{{ trans("Reason") }}</th>
                                    <th class="py-1 text-right">{{ trans("Units") }}</th>
                                    <th class="py-1 text-right">{{ trans("Cartons") }}</th>
                                    <th class="py-1 text-right">{{ trans("Amount") }} ({{ currency }})</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in proposal" :key="line.org_supplier_product_id" class="border-b border-gray-100" :class="line.selected ? '' : 'opacity-40'">
                                    <td class="py-1"><input v-model="line.selected" type="checkbox" /></td>
                                    <td class="py-1"><span class="font-medium">{{ line.code }}</span> <span class="text-gray-500">{{ line.name }}</span></td>
                                    <td class="py-1 italic text-gray-500">{{ line.reason }}</td>
                                    <td class="py-1 text-right">
                                        <input v-model.number="line.quantity" type="number" min="1" step="1" class="w-16 rounded border-gray-300 py-0.5 text-right text-xs" />
                                    </td>
                                    <td class="py-1 text-right tabular-nums" :class="belowMoq(line) ? 'text-amber-600' : 'text-gray-500'">
                                        <template v-if="line.cartons !== null">
                                            {{ line.cartons }}
                                            <span v-if="line.minimum_carton_order" class="text-gray-400">/ {{ line.minimum_carton_order }} {{ trans("min") }}</span>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="py-1 text-right tabular-nums" :title="`${locale.currencyFormat(currency, line.cost_per_unit)} / ${trans('unit')}`">
                                        {{ locale.currencyFormat(currency, Math.round(line.quantity * line.cost_per_unit * 100) / 100) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-200 bg-white py-3">
                        <div :class="overBudget ? 'font-medium text-red-600' : 'text-gray-600'">
                            {{ trans("Total") }}: {{ locale.currencyFormat(currency, selectedTotal) }}
                            <span class="text-gray-400">/ {{ locale.currencyFormat(currency, budget ?? 0) }}</span>
                            <span v-if="overBudget"> — {{ trans("over budget") }}</span>
                        </div>
                        <Button
                            type="save"
                            :label="`${trans('Add')} ${selectedLines.length} ${trans('items to shopping list')}`"
                            :loading="isCommitting"
                            :disabled="!selectedLines.length"
                            @click="commit"
                        />
                    </div>
                </template>
            </template>
        </div>
    </Modal>
</template>
