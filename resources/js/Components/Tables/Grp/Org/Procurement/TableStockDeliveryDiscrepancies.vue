<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 09 Oct 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Table from '@/Components/Table/Table.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SegmentedToggle from '@/Components/Utils/SegmentedToggle.vue'
import { useLocaleStore } from '@/Stores/locale'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faBalanceScale, faClipboardCheck, faRulerTriangle, faFileInvoiceDollar, faBoxCheck, faCheck } from '@fal'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DatePicker from 'primevue/datepicker'
import FileUpload from 'primevue/fileupload'

library.add(faBalanceScale, faClipboardCheck, faRulerTriangle, faFileInvoiceDollar, faBoxCheck, faCheck)

const props = defineProps<{
    data: { data?: any[] }
    tab?: string
}>()

const locale = useLocaleStore()

function formatQuantity(value: number) {
    return locale.number(Math.round(value * 1000) / 1000)
}

function quantityBreakdown(item: any, units: number) {
    const pack = Number(item.units_per_pack) || 1
    const supplier = item.supplier_unit ? ` | ${formatQuantity(units / (Number(item.units_per_supplier_unit) || 1))} ${item.supplier_unit}` : ''

    return `${formatQuantity(units)}u. | ${formatQuantity(units / pack)}sko.${supplier}`
}

function money(item: any, value: number | null) {
    return value === null ? '-' : locale.currencyFormat(item.currency_code ?? 'EUR', Number(value))
}

function differenceClass(item: any, value: number | null) {
    if (value === null || Number(value) === 0 || item.discrepancy === 'within_tolerance' || item.discrepancy === 'possible_unit_mismatch') {
        return 'text-gray-500'
    }

    return Number(value) < 0 ? 'text-red-600' : 'text-amber-600'
}

const discrepancyClass: Record<string, string> = {
    under: 'bg-red-50 text-red-700 ring-red-200',
    over: 'bg-amber-50 text-amber-700 ring-amber-200',
    possible_unit_mismatch: 'bg-amber-50 text-amber-800 ring-amber-300',
    within_tolerance: 'bg-gray-50 text-gray-600 ring-gray-200',
}

const reloadOnly = () => router.reload({ only: [props.tab ?? 'under_over_delivered', 'box_stats', 'timelines'] })

const resolvingItem = ref<any | null>(null)

const resolveForm = useForm<{
    outcome: string
    unit_quantity: number | null
    net_amount: number | null
    claim_quantity: number | null
    claim_amount: number | null
    notes: string | null
    photos: File[]
}>({
    outcome: 'recount_requested',
    unit_quantity: null,
    net_amount: null,
    claim_quantity: null,
    claim_amount: null,
    notes: null,
    photos: [],
})

const missingUnits = (item: any) => Math.max(Number(item.unit_quantity) - Number(item.unit_quantity_checked), 0)
const unitPrice = (item: any) => Number(item.net_amount) / (Number(item.unit_quantity) || 1)

const outcomeOptions = computed(() => {
    const item = resolvingItem.value
    if (!item) {
        return []
    }

    const isShort = Number(item.unit_quantity_checked) < Number(item.unit_quantity)

    return [
        { value: 'recount_requested', label: ctrans('Recount'), icon: 'fal fa-clipboard-check' },
        { value: 'unit_error_corrected', label: ctrans('Unit error'), icon: 'fal fa-ruler-triangle' },
        ...(isShort || item.claim ? [{ value: 'supplier_claim', label: ctrans('Supplier claim'), icon: 'fal fa-file-invoice-dollar' }] : []),
        ...(!isShort ? [{ value: 'surplus_accepted', label: ctrans('Accept surplus'), icon: 'fal fa-box-check' }] : []),
    ]
})

function openResolve(item: any) {
    resolvingItem.value = item
    resolveForm.reset()
    resolveForm.clearErrors()
    resolveForm.outcome = item.discrepancy === 'possible_unit_mismatch' ? 'unit_error_corrected' : (item.outcome && item.outcome !== 'recount_requested' ? item.outcome : 'recount_requested')
    resolveForm.unit_quantity = Number(item.unit_quantity)
    resolveForm.net_amount = Number(item.net_amount)
    resolveForm.claim_quantity = item.claim?.quantity ?? missingUnits(item)
    resolveForm.claim_amount = item.claim?.amount ?? Math.round(missingUnits(item) * unitPrice(item) * 100) / 100
}

const supplierUnitQuantity = computed({
    get: () => resolvingItem.value?.supplier_unit ? Number(resolveForm.unit_quantity) / (Number(resolvingItem.value.units_per_supplier_unit) || 1) : null,
    set: (value: number | null) => {
        resolveForm.unit_quantity = value === null ? null : value * (Number(resolvingItem.value?.units_per_supplier_unit) || 1)
    },
})

function submitResolve() {
    const item = resolvingItem.value
    resolveForm
        .transform((data) => ({
            outcome: data.outcome,
            ...(data.outcome === 'unit_error_corrected' ? { unit_quantity: data.unit_quantity, net_amount: data.net_amount } : {}),
            ...(data.outcome === 'supplier_claim' && !item.claim ? { claim_quantity: data.claim_quantity, claim_amount: data.claim_amount, photos: data.photos } : {}),
            ...(data.notes ? { notes: data.notes } : {}),
        }))
        .post(route(item.resolveRoute.name, item.resolveRoute.parameters), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                resolvingItem.value = null
                reloadOnly()
            },
        })
}

const editingClaimItem = ref<any | null>(null)

const claimStateOptions = [
    { value: 'open', label: ctrans('Open') },
    { value: 'sent', label: ctrans('Sent to supplier') },
    { value: 'credit_received', label: ctrans('Credit received') },
    { value: 'rejected', label: ctrans('Rejected') },
]

const claimForm = useForm<{
    state: string
    quantity: number | null
    amount: number | null
    credit_note_reference: string | null
    credit_note_amount: number | null
    credit_note_date: Date | null
    notes: string | null
    photos: File[]
}>({
    state: 'open',
    quantity: null,
    amount: null,
    credit_note_reference: null,
    credit_note_amount: null,
    credit_note_date: null,
    notes: null,
    photos: [],
})

function openClaim(item: any) {
    editingClaimItem.value = item
    claimForm.clearErrors()
    claimForm.state = item.claim.state
    claimForm.quantity = item.claim.quantity
    claimForm.amount = item.claim.amount
    claimForm.credit_note_reference = item.claim.credit_note_reference
    claimForm.credit_note_amount = item.claim.credit_note_amount
    claimForm.credit_note_date = item.claim.credit_note_date ? new Date(item.claim.credit_note_date) : null
    claimForm.notes = item.claim.notes
    claimForm.photos = []
}

function isoDate(date: Date | null) {
    if (!date) {
        return null
    }

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function submitClaim() {
    const claimRoute = editingClaimItem.value.claim.updateRoute
    claimForm
        .transform((data) => ({ ...data, credit_note_date: isoDate(data.credit_note_date) }))
        .post(route(claimRoute.name, claimRoute.parameters), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                editingClaimItem.value = null
                reloadOnly()
            },
        })
}

function orgStockRoute(item: any) {
    return item.org_stock_id ? route('grp.majordomo.redirect_org_stock', [item.org_stock_id]) : ''
}
</script>

<template>
    <div>
        <Table :resource="data" :name="tab" class="mt-5">
            <template #cell(part)="{ item }">
                <Link v-if="orgStockRoute(item)" :href="orgStockRoute(item)" class="primaryLink">{{ item.org_stock_code }}</Link>
                <span v-else>{{ item.org_stock_code }}</span>
            </template>

            <template #cell(description)="{ item }">
                <div>{{ item.name ?? item.org_stock_name }}</div>
                <div v-if="item.supplier_unit" class="text-xs text-gray-500">
                    {{ ctrans('Supplier sells in :unit, :units units each', { unit: item.supplier_unit, units: formatQuantity(Number(item.units_per_supplier_unit)) }) }}
                </div>
                <div v-for="(correction, index) in item.unit_corrections" :key="index" class="text-xs text-gray-500">
                    {{ ctrans('Expected was :quantity (:amount) before a unit correction', { quantity: formatQuantity(Number(correction.unit_quantity)), amount: money(item, correction.net_amount) }) }}
                </div>
            </template>

            <template #cell(delivered_quantity)="{ item }">
                <span class="text-gray-600">{{ quantityBreakdown(item, Number(item.unit_quantity)) }}</span>
            </template>

            <template #cell(checked_quantity)="{ item }">
                <span class="text-gray-600">{{ quantityBreakdown(item, Number(item.unit_quantity_checked)) }}</span>
            </template>

            <template #cell(difference_percentage)="{ item }">
                <span :class="differenceClass(item, item.difference_percentage)">
                    {{ item.difference_percentage === null ? '-' : `${locale.number(item.difference_percentage)}%` }}
                </span>
            </template>

            <template #cell(difference_units)="{ item }">
                <span :class="differenceClass(item, item.difference_units)">{{ formatQuantity(Number(item.difference_units)) }}</span>
            </template>

            <template #cell(difference_skos)="{ item }">
                <span :class="differenceClass(item, item.difference_skos)">
                    {{ item.difference_skos === null ? '-' : formatQuantity(Number(item.difference_skos)) }}
                </span>
            </template>

            <template #cell(difference_amount)="{ item }">
                <span class="tabular-nums" :class="differenceClass(item, item.difference_amount)">{{ money(item, item.difference_amount) }}</span>
            </template>

            <template #cell(discrepancy)="{ item }">
                <span
                    v-if="item.discrepancy"
                    v-tooltip="item.discrepancy === 'possible_unit_mismatch' ? ctrans('The count is a clean multiple of what was expected: the supplier probably invoiced in another unit. Check the unit before claiming anything.') : undefined"
                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                    :class="discrepancyClass[item.discrepancy]"
                >
                    <FontAwesomeIcon v-if="item.discrepancy === 'possible_unit_mismatch'" icon="fal fa-balance-scale" fixed-width aria-hidden="true" />
                    {{ item.discrepancy_label }}
                </span>
            </template>

            <template #cell(outcome)="{ item }">
                <div class="flex flex-col items-start gap-1 text-sm">
                    <span v-if="item.outcome" class="inline-flex items-center gap-1 font-medium" :class="item.resolved_at ? 'text-green-700' : 'text-amber-700'">
                        <FontAwesomeIcon v-if="item.resolved_at" icon="fal fa-check" fixed-width aria-hidden="true" />
                        {{ item.outcome_label }}
                    </span>
                    <span v-if="item.recount_task && item.outcome === 'recount_requested'" class="text-xs text-gray-500">
                        {{ item.recount_task.reference }} · {{ item.recount_task.is_open ? ctrans('waiting for the warehouse') : ctrans('counted, pick the outcome') }}
                    </span>
                    <button v-if="item.claim" type="button" class="text-left text-xs text-gray-600 underline decoration-dotted hover:text-gray-900" @click="openClaim(item)">
                        {{ formatQuantity(item.claim.quantity) }}u. · {{ money(item.claim, item.claim.amount) }} · {{ item.claim.state_label }}
                        <template v-if="item.claim.credit_note_reference"> · {{ item.claim.credit_note_reference }} {{ money(item.claim, item.claim.credit_note_amount) }}</template>
                    </button>
                    <Button
                        v-if="!item.resolved_at && item.discrepancy !== 'within_tolerance'"
                        :label="item.outcome ? ctrans('Change') : ctrans('Resolve')"
                        type="tertiary"
                        size="xs"
                        @click="openResolve(item)"
                    />
                    <Button v-else-if="item.resolved_at && item.outcome !== 'supplier_claim'" :label="ctrans('Change')" type="tertiary" size="xs" @click="openResolve(item)" />
                </div>
            </template>
        </Table>

        <Dialog
            :visible="!!resolvingItem"
            modal
            :header="ctrans('Resolve :code', { code: resolvingItem?.org_stock_code ?? '' })"
            :style="{ width: '34rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @update:visible="(visible) => !visible && (resolvingItem = null)"
        >
            <div v-if="resolvingItem" class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-2 rounded-md bg-gray-50 p-3">
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans('Expected') }}</div>
                        <div class="font-medium">{{ quantityBreakdown(resolvingItem, Number(resolvingItem.unit_quantity)) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">{{ ctrans('Counted at goods in') }}</div>
                        <div class="font-medium">{{ quantityBreakdown(resolvingItem, Number(resolvingItem.unit_quantity_checked)) }}</div>
                    </div>
                </div>

                <SegmentedToggle v-model="resolveForm.outcome" :options="outcomeOptions" :ariaLabel="ctrans('Outcome')" />

                <p v-if="resolveForm.outcome === 'recount_requested'" class="text-gray-600">
                    {{ ctrans('The warehouse gets a task to count this again. You are told when they finish, then come back and pick the outcome.') }}
                </p>

                <template v-if="resolveForm.outcome === 'unit_error_corrected'">
                    <p class="text-gray-600">{{ ctrans('Correct what was expected. What was counted stays as it is; if a real difference is left the line stays flagged so you can claim it.') }}</p>
                    <div class="grid grid-cols-2 gap-3">
                        <label v-if="resolvingItem.supplier_unit" class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Expected in :unit', { unit: resolvingItem.supplier_unit }) }}</span>
                            <InputNumber v-model="supplierUnitQuantity" :maxFractionDigits="4" :min="0" fluid />
                        </label>
                        <label class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Expected units') }}</span>
                            <InputNumber v-model="resolveForm.unit_quantity" :maxFractionDigits="4" :min="0" fluid />
                        </label>
                        <label class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Line value (:currency)', { currency: resolvingItem.currency_code }) }}</span>
                            <InputNumber v-model="resolveForm.net_amount" :maxFractionDigits="2" :min="0" fluid />
                        </label>
                    </div>
                    <p v-if="!resolvingItem.supplier_unit" class="text-xs text-gray-500">{{ ctrans('If this supplier always invoices in another unit, set "Supplier sells in" on the supplier product so the next order is right.') }}</p>
                    <div v-if="resolveForm.errors.unit_quantity" class="text-xs text-red-600">{{ resolveForm.errors.unit_quantity }}</div>
                </template>

                <template v-if="resolveForm.outcome === 'supplier_claim'">
                    <p v-if="resolvingItem.claim" class="text-gray-600">{{ ctrans('This line already has a claim; change it from the outcome column.') }}</p>
                    <div v-else class="grid grid-cols-2 gap-3">
                        <label class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Units claimed') }}</span>
                            <InputNumber v-model="resolveForm.claim_quantity" :maxFractionDigits="4" :min="0" fluid @update:modelValue="(value) => resolveForm.claim_amount = Math.round(Number(value) * unitPrice(resolvingItem) * 100) / 100" />
                        </label>
                        <label class="space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Value (:currency)', { currency: resolvingItem.currency_code }) }}</span>
                            <InputNumber v-model="resolveForm.claim_amount" :maxFractionDigits="2" :min="0" fluid />
                        </label>
                        <div class="col-span-2 space-y-1">
                            <span class="text-xs text-gray-500">{{ ctrans('Photos from goods in') }}</span>
                            <FileUpload mode="basic" multiple accept="image/*,application/pdf" :auto="false" :chooseLabel="ctrans('Add photos')" @select="(event) => resolveForm.photos = event.files" />
                        </div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3 text-xs text-gray-600">
                        <template v-if="resolvingItem.customs">
                            <div v-if="resolvingItem.customs.duty_rate === 0">{{ ctrans('Tariff :code is duty free: there is no duty to reclaim, so no customs amendment is needed.', { code: resolvingItem.customs.tariff_code }) }}</div>
                            <div v-else>{{ ctrans('Tariff :code pays :rate% duty: about :amount of duty was paid on the missing units.', { code: resolvingItem.customs.tariff_code, rate: locale.number(resolvingItem.customs.duty_rate), amount: money(resolvingItem, resolvingItem.customs.duty_on_missing) }) }}</div>
                        </template>
                        <div v-else>{{ ctrans('No customs line is assigned to this item yet, so the duty on the missing units is not known.') }}</div>
                        <div>{{ ctrans('Import VAT is deducted in the VAT return: there is nothing to reclaim from customs for it.') }}</div>
                    </div>
                </template>

                <p v-if="resolveForm.outcome === 'surplus_accepted'" class="text-gray-600">
                    {{ ctrans('Keep the extra units. They are already in stock at the same cost per unit.') }}
                </p>

                <label class="block space-y-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Notes') }}</span>
                    <Textarea v-model="resolveForm.notes" rows="2" autoResize fluid />
                </label>

                <div v-if="resolveForm.errors.outcome" class="text-xs text-red-600">{{ resolveForm.errors.outcome }}</div>

                <div class="flex justify-end gap-2">
                    <Button :label="ctrans('Cancel')" type="tertiary" @click="resolvingItem = null" />
                    <Button :label="ctrans('Save')" type="save" :loading="resolveForm.processing" @click="submitResolve" />
                </div>
            </div>
        </Dialog>

        <Dialog
            :visible="!!editingClaimItem"
            modal
            :header="ctrans('Claim for :code', { code: editingClaimItem?.org_stock_code ?? '' })"
            :style="{ width: '32rem' }"
            :breakpoints="{ '640px': '95vw' }"
            @update:visible="(visible) => !visible && (editingClaimItem = null)"
        >
            <div v-if="editingClaimItem" class="space-y-3 text-sm">
                <label class="block space-y-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Status') }}</span>
                    <Select v-model="claimForm.state" :options="claimStateOptions" optionLabel="label" optionValue="value" fluid />
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Units claimed') }}</span>
                        <InputNumber v-model="claimForm.quantity" :maxFractionDigits="4" :min="0" fluid />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Value (:currency)', { currency: editingClaimItem.claim.currency_code }) }}</span>
                        <InputNumber v-model="claimForm.amount" :maxFractionDigits="2" :min="0" fluid />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Credit note number') }}</span>
                        <InputText v-model="claimForm.credit_note_reference" fluid />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Credit note amount') }}</span>
                        <InputNumber v-model="claimForm.credit_note_amount" :maxFractionDigits="2" :min="0" fluid />
                    </label>
                    <label class="space-y-1">
                        <span class="text-xs text-gray-500">{{ ctrans('Credit note date') }}</span>
                        <DatePicker v-model="claimForm.credit_note_date" dateFormat="yy-mm-dd" showIcon fluid />
                    </label>
                </div>
                <div class="space-y-1">
                    <span class="text-xs text-gray-500">{{ ctrans('More photos') }}</span>
                    <FileUpload mode="basic" multiple accept="image/*,application/pdf" :auto="false" :chooseLabel="ctrans('Add photos')" @select="(event) => claimForm.photos = event.files" />
                </div>
                <label class="block space-y-1">
                    <span class="text-xs text-gray-500">{{ ctrans('Notes') }}</span>
                    <Textarea v-model="claimForm.notes" rows="2" autoResize fluid />
                </label>
                <div v-for="(error, field) in claimForm.errors" :key="field" class="text-xs text-red-600">{{ error }}</div>
                <div class="flex justify-end gap-2">
                    <Button :label="ctrans('Cancel')" type="tertiary" @click="editingClaimItem = null" />
                    <Button :label="ctrans('Save')" type="save" :loading="claimForm.processing" @click="submitClaim" />
                </div>
            </div>
        </Dialog>
    </div>
</template>
