<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 10 Aug 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle, faPlus, faTrashAlt, faUpload, faPaperclip, faTimes, faCheck, faSquare, faPencil, faHistory } from "@fal"
import { faSpinnerThird } from "@fad"
import { faCheckCircle as fasCheckCircle, faExclamationTriangle, faCheckSquare } from "@fas"
import { routeType } from "@/types/route"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import ServiceInvoiceTypeChip from "@/Components/Procurement/ServiceInvoiceTypeChip.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import InputText from "primevue/inputtext"
import Select from "primevue/select"

library.add(faCheckCircle, faPlus, faTrashAlt, faUpload, faPaperclip, fasCheckCircle, faExclamationTriangle, faTimes, faCheck, faSquare, faCheckSquare, faSpinnerThird, faPencil, faHistory)

interface CostRow {
    id: number | null
    type: string
    label: string
    amount: number | string | null
    received_at: string | null
    is_na: boolean
    currency_id: number | null
    exchange: number | string | null
    updateRoute: routeType | null
    deleteRoute: routeType | null
    from_service_invoices: boolean
    attachments: { name: string, ulid: string }[]
    uploadRoute: routeType | null
    category: string | null
    category_label: string | null
    note: string | null
    history: { at: string, user: string, event: string, changes: { field: string, old: string, new: string }[] }[]
    is_waiting_for_invoice: boolean
    is_estimate: boolean
    invoice_overdue: boolean
    waiting_label: string | null
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
        is_partner: boolean
        reopened: { at: string, by: string | null, reason: string, assignee: string | null } | null
        unbalanced: Record<string, { allocated: number, amount: number }>
        currency: string | null
        currency_id: number | null
        currencies: { id: number, code: string }[]
        org_currency: string
        org_currency_id: number
        org_exchange: number | string | null
        updateRoute: routeType
        checklist: CostRow[]
        extra_types: { label: string, value: string }[]
        shipping_basis: { basis: string, missing_count: number, missing_codes: string[] }
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
        partner_invoice: {
            reference: string
            net_amount: number
            refunds: { reference: string, date: string, net_amount: number }[]
            refunded: number
            missing_amount: number
            to_refund: number
        } | null
    }
    canEdit: boolean
    canEditPayments: boolean
    section: "invoice" | "landed"
    hideRows?: boolean
}>()

const isInvoiceSection = computed(() => props.section === "invoice")
const rowCurrencyId = (row: CostRow) => row.currency_id ?? (row.amount == null && row.type !== "agent_invoice" ? props.costing.org_currency_id : props.costing.currency_id)
const quietVisit = { preserveScroll: true, preserveState: true, showProgress: false, only: ["costing", "box_stats", "service_invoices", "invoice_costing", "stock_delivery", "pageHead", "timelines"] }

const optimistic = ref<{ [key: string]: Partial<CostRow> }>({})
const rows = computed(() => props.costing.checklist
    .filter(row => (row.type === "agent_invoice") === isInvoiceSection.value)
    .map(row => ({ ...row, ...optimistic.value[rowKey(row)] })))

const selectedDepositId = ref<number | null>(null)
const applyAmount = ref<string>("")

const applyDeposit = () => {
    if (!selectedDepositId.value || !applyAmount.value) return

    router.post(
        route(props.costing.deposits.applyRoute.name, props.costing.deposits.applyRoute.parameters),
        { aspo_deposit_id: selectedDepositId.value, amount: Number(applyAmount.value) },
        { ...quietVisit, onError, onSuccess: () => { selectedDepositId.value = null; applyAmount.value = "" } }
    )
}

const unapplyDeposit = (application: { deleteRoute: routeType | null }) => {
    if (!application.deleteRoute) return
    if (!window.confirm(ctrans("Remove this deposit application? This is audited and visible to org staff and the agent."))) return

    router.delete(route(application.deleteRoute.name, application.deleteRoute.parameters), { ...quietVisit, onError })
}

const locale = useLocaleStore()
const loading = ref<string | null>(null)
const drafts = ref<{ [key: string]: string }>(
    Object.fromEntries(props.costing.checklist.map(row => [rowKey(row), row.amount == null ? "" : String(row.amount)]))
)

watch(() => props.costing.checklist, (checklist, previous) => {
    const before = Object.fromEntries(previous.map(row => [rowKey(row), row.amount]))

    checklist.forEach(row => {
        const key = rowKey(row)
        if (!(key in before) || String(before[key] ?? "") !== String(row.amount ?? "")) {
            drafts.value[key] = row.amount == null ? "" : String(row.amount)
        }
    })
})

function rowKey(row: CostRow) {
    return row.id ? `id-${row.id}` : `type-${row.type}`
}

const onError = (errors?: Record<string, string>) => {
    notify({ title: ctrans("Something went wrong"), text: String(Object.values(errors ?? {})[0] ?? ctrans("Failed to save the cost")), type: "error" })
}

const save = (row: CostRow, payload: { amount?: string | null, received?: boolean, is_na?: boolean, currency_id?: number, exchange?: string, is_waiting_for_invoice?: boolean, invoice_expected_in_days?: number | null, label?: string | null }) => {
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
    if ("currency_id" in payload) {
        data.currency_id = payload.currency_id
    }
    if ("exchange" in payload) {
        data.exchange = payload.exchange ? Number(payload.exchange) : null
    }
    if ("label" in payload) {
        data.label = payload.label || null
    }
    if ("is_waiting_for_invoice" in payload) {
        data.is_waiting_for_invoice = payload.is_waiting_for_invoice ?? false
    }
    if ("invoice_expected_in_days" in payload) {
        data.invoice_expected_in_days = payload.invoice_expected_in_days ?? null
    }

    const key = rowKey(row)
    optimistic.value[key] = {
        ...("is_na" in payload ? { is_na: !!payload.is_na } : {}),
        ...("is_waiting_for_invoice" in payload ? { is_waiting_for_invoice: !!payload.is_waiting_for_invoice } : {}),
    }
    const options = {
        ...quietVisit,
        onStart: () => { loading.value = key },
        onFinish: () => { loading.value = null; delete optimistic.value[key] },
        onError,
    }

    if (row.updateRoute) {
        router.patch(route(row.updateRoute.name, row.updateRoute.parameters), data, options)
    } else {
        router.post(route(props.costing.storeCostRoute.name, props.costing.storeCostRoute.parameters), { type: row.type, ...data }, options)
    }
}

const shippingBasisText = computed(() => {
    const basis = props.costing.shipping_basis

    if (basis.basis === "by_weight") {
        return ctrans("Shipping split by weight")
    }

    const codes = basis.missing_codes.join(", ") + (basis.missing_count > basis.missing_codes.length ? ", …" : "")

    return ctrans("Shipping split by value: :count products have no weight (:codes)", { count: String(basis.missing_count), codes })
})

const deliveryPerOrg = computed(() => Number(props.costing.org_exchange) > 0 ? Number((1 / Number(props.costing.org_exchange)).toFixed(6)) : null)

const saveOrgExchange = (value: string) => {
    if (!value || Number(value) <= 0) return

    router.patch(
        route(props.costing.updateRoute.name, props.costing.updateRoute.parameters),
        { org_exchange: 1 / Number(value) },
        { ...quietVisit, onError }
    )
}

const isAddingExtra = ref(false)
const extraCategory = ref<string | null>(null)
const extraLabel = ref("")
const canAddExtra = computed(() => !!extraCategory.value && (extraCategory.value !== "other" || !!extraLabel.value.trim()))

const closeExtra = () => {
    isAddingExtra.value = false
    extraCategory.value = null
    extraLabel.value = ""
}

const addExtra = () => {
    if (!canAddExtra.value || adding.value) return

    adding.value = true
    router.post(
        route(props.costing.storeCostRoute.name, props.costing.storeCostRoute.parameters),
        { type: "extra", category: extraCategory.value, label: extraLabel.value.trim() || null },
        { ...quietVisit, onFinish: () => { adding.value = false }, onError, onSuccess: closeExtra }
    )
}

const uploading = ref<string | null>(null)
const fileInputs = ref<{ [key: string]: HTMLInputElement | null }>({})

const uploadInvoice = (row: CostRow, event: Event) => {
    const input = event.target as HTMLInputElement
    const files = Array.from(input.files ?? [])
    if (!row.uploadRoute || !files.length) return

    const key = rowKey(row)

    router.post(
        route(row.uploadRoute.name, row.uploadRoute.parameters),
        { type: row.type, attachments: files },
        {
            forceFormData: true,
            ...quietVisit,
            onStart: () => { uploading.value = key },
            onFinish: () => { uploading.value = null; input.value = "" },
            onError: (errors) => notify({ title: ctrans("Something went wrong"), text: String(Object.values(errors)[0] ?? ""), type: "error" }),
            onSuccess: () => notify({
                title: ctrans("Invoice uploaded"),
                text: row.type === "extra"
                    ? undefined
                    : row.type === "agent_invoice"
                        ? ctrans("The AI is reading it: check and apply its costs under the invoice costing below.")
                        : ctrans("The AI is reading it: check and apply its costs under the service invoices above."),
                type: "success",
            }),
        }
    )
}

const depositOptions = computed(() => props.costing.deposits.available.map(deposit => ({
    id: deposit.id,
    label: `${deposit.reference || deposit.id} (${deposit.unapplied_amount} ${deposit.currency_code} ${ctrans("unapplied")})`,
})))

const editingNote = ref<string | null>(null)
const noteDraft = ref("")
const openHistory = ref<string | null>(null)

const canEditNote = (row: CostRow) => props.canEdit && !row.from_service_invoices && row.type !== "agent_invoice"

const editNote = (row: CostRow) => {
    editingNote.value = rowKey(row)
    noteDraft.value = row.note ?? ""
}

const saveNote = (row: CostRow) => {
    if (editingNote.value !== rowKey(row)) return

    editingNote.value = null
    if (noteDraft.value.trim() !== (row.note ?? "")) {
        save(row, { label: noteDraft.value.trim() })
    }
}

const removing = ref<string | null>(null)
const adding = ref(false)
const isBusy = computed(() => !!loading.value || !!uploading.value || !!removing.value || adding.value)

const removeExtra = (row: CostRow) => {
    if (!row.deleteRoute || removing.value) return

    const key = rowKey(row)
    removing.value = key
    router.delete(route(row.deleteRoute.name, row.deleteRoute.parameters), {
        ...quietVisit,
        onStart: () => { removing.value = key },
        onFinish: () => { removing.value = null },
        onError,
    })
}
</script>

<template>
    <div class="px-4 py-3 border-b border-gray-300 text-gray-600">
        <div class="flex items-center gap-2 mb-2 empty:hidden">
            <FontAwesomeIcon v-if="costing.is_costed" icon="fas fa-check-circle" class="text-green-500" fixed-width aria-hidden="true" />
            <span v-if="costing.is_costed" class="text-sm text-green-600">{{ ctrans("Done") }}</span>
            <span v-else-if="costing.reopened" class="flex items-center gap-1 text-sm text-orange-600">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ costing.reopened.assignee
                    ? ctrans(":by asked :name to update the costing: :reason", { by: costing.reopened.by ?? "", name: costing.reopened.assignee, reason: costing.reopened.reason })
                    : ctrans("Costing being updated by :name: :reason", { name: costing.reopened.by ?? "", reason: costing.reopened.reason }) }}
            </span>
            <span v-else-if="isInvoiceSection && !costing.is_partner && costing.agent_invoice_missing" class="flex items-center gap-1 text-sm text-orange-500">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans("Agent invoice not received") }}
            </span>
            <span v-if="costing.is_partner" class="text-sm text-gray-500">{{ ctrans("Automatic, from the partner's order prices") }}</span>
        </div>

        <div v-if="costing.partner_invoice" class="mb-3 grid gap-1 text-sm">
            <div class="flex gap-3">
                <span class="w-40 shrink-0">{{ ctrans("Partner invoice :reference", { reference: costing.partner_invoice.reference }) }}</span>
                <span>{{ locale.currencyFormat(costing.currency ?? "", costing.partner_invoice.net_amount) }}</span>
            </div>
            <div v-for="refund in costing.partner_invoice.refunds" :key="refund.reference" class="flex gap-3 text-green-700">
                <span class="w-40 shrink-0">{{ ctrans("Refund :reference", { reference: refund.reference }) }}</span>
                <span>-{{ locale.currencyFormat(costing.currency ?? "", refund.net_amount) }}</span>
            </div>
            <div v-if="costing.partner_invoice.missing_amount > 0" class="flex gap-3">
                <span class="w-40 shrink-0">{{ ctrans("Not received") }}</span>
                <span>{{ locale.currencyFormat(costing.currency ?? "", costing.partner_invoice.missing_amount) }}</span>
            </div>
            <div v-if="costing.partner_invoice.to_refund > 0" class="flex items-center gap-1 text-orange-500">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
                {{ ctrans("Still to be refunded by the partner: :amount", { amount: String(locale.currencyFormat(costing.currency ?? "", costing.partner_invoice.to_refund)) }) }}
            </div>
            <div v-else-if="costing.partner_invoice.missing_amount > 0" class="flex items-center gap-1 text-green-700">
                <FontAwesomeIcon icon="fas fa-check-circle" fixed-width aria-hidden="true" />
                {{ ctrans("Refunded by the partner") }}
            </div>
        </div>

        <p v-if="!costing.is_partner" class="mb-3 text-sm text-gray-500">
            {{ isInvoiceSection
                ? ctrans("The invoice of the agent or supplier for the goods and their own charges, with the deposits and payments made to them.")
                : ctrans("Freight, customs, transport and any other cost of bringing the goods in that is paid to someone else, never to the agent or supplier.") }}
        </p>

        <div v-for="(totals, field) in (isInvoiceSection ? {} : costing.unbalanced)" :key="field" class="mb-2 flex items-center gap-1 text-sm text-red-600">
            <FontAwesomeIcon icon="fas fa-exclamation-triangle" fixed-width aria-hidden="true" />
            {{ ctrans("The lines add up to :allocated of :field, the cost is :amount. Change the lines until they match.", { allocated: String(totals.allocated), field: String(field).replace("cost_", ""), amount: String(totals.amount) }) }}
        </div>

        <template v-if="!costing.is_partner">
            <div class="rounded-md border border-gray-200 overflow-hidden" :class="isBusy ? 'pointer-events-none' : ''" :aria-busy="isBusy">
                <table v-if="!hideRows" class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-600 font-medium">
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("N/A") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("Cost") }}</th>
                            <th class="px-3 py-2 text-right font-medium">{{ ctrans("Amount") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("Currency") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("Rate") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("Waiting for invoice") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("Invoice") }}</th>
                            <th class="px-3 py-2 text-left font-medium">{{ ctrans("History") }}</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template v-for="row in rows" :key="rowKey(row)">
                        <tr :class="row.is_na ? 'text-gray-400' : 'text-gray-700'">
                            <td class="px-3 py-2 align-middle">
                                <button
                                    v-if="row.type !== 'agent_invoice'"
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="row.is_na"
                                    :aria-label="ctrans('N/A')"
                                    :disabled="!canEdit || row.from_service_invoices"
                                    class="text-lg leading-none disabled:opacity-40"
                                    :class="row.is_na ? 'text-gray-700' : 'text-gray-400 hover:text-gray-600'"
                                    @click="save(row, { is_na: !row.is_na })"
                                >
                                    <FontAwesomeIcon :icon="row.is_na ? 'fas fa-check-square' : 'fal fa-square'" fixed-width aria-hidden="true" />
                                </button>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <div class="flex items-center gap-2" :class="row.received_at ? '' : 'italic'">
                                    <template v-if="row.category">
                                        <ServiceInvoiceTypeChip :type="row.category" :label="row.category_label ?? ''" />
                                    </template>
                                    <ServiceInvoiceTypeChip v-else-if="row.type !== 'agent_invoice'" :type="row.type === 'extra' ? 'other' : row.type" :label="row.label" />
                                    <template v-else>{{ row.label }}</template>
                                    <FontAwesomeIcon v-if="loading === rowKey(row)" icon="fad fa-spinner-third" class="animate-spin text-gray-400" fixed-width aria-hidden="true" />
                                </div>
                                <InputText
                                    v-if="editingNote === rowKey(row)"
                                    v-model="noteDraft"
                                    size="small"
                                    autofocus
                                    maxlength="255"
                                    class="mt-1 w-full text-xs"
                                    :placeholder="ctrans('Note')"
                                    @keydown.enter.prevent="saveNote(row)"
                                    @keydown.esc.stop="editingNote = null"
                                    @blur="saveNote(row)"
                                />
                                <button
                                    v-else-if="canEditNote(row) && !row.is_na"
                                    type="button"
                                    class="mt-0.5 flex items-center gap-1 text-left text-xs text-gray-500 hover:text-gray-800"
                                    @click="editNote(row)"
                                >
                                    <FontAwesomeIcon icon="fal fa-pencil" fixed-width aria-hidden="true" />
                                    {{ row.note || ctrans("Add note") }}
                                </button>
                                <div v-else-if="row.note" class="mt-0.5 text-xs text-gray-500">{{ row.note }}</div>
                                <div v-if="row.type === 'shipping' && !row.is_na" class="text-xs text-gray-500">{{ shippingBasisText }}</div>
                                <div v-else-if="row.from_service_invoices" class="text-xs text-gray-500">{{ ctrans("From the service invoices") }}</div>
                            </td>
                            <td class="px-3 py-2 align-middle text-right">
                                <InputText
                                    v-if="!row.is_na"
                                    v-model="drafts[rowKey(row)]"
                                    type="number"
                                    size="small"
                                    min="0"
                                    step="0.01"
                                    class="w-32 text-right tabular-nums"
                                    :placeholder="row.is_waiting_for_invoice ? ctrans('Estimate') : undefined"
                                    :disabled="!canEdit || row.is_na || row.from_service_invoices"
                                    @blur="drafts[rowKey(row)] !== (row.amount == null ? '' : String(row.amount)) && save(row, { amount: drafts[rowKey(row)], received: drafts[rowKey(row)] !== '', ...(row.type === 'agent_invoice' || row.currency_id ? {} : { currency_id: rowCurrencyId(row) ?? undefined }) })"
                                />
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <span v-if="row.type === 'agent_invoice'">{{ costing.currency }}</span>
                                <Select
                                    v-else-if="!row.is_na"
                                    size="small"
                                    :modelValue="rowCurrencyId(row)"
                                    :options="costing.currencies"
                                    optionLabel="code"
                                    optionValue="id"
                                    class="w-24"
                                    :disabled="!canEdit || row.is_na || row.from_service_invoices"
                                    @update:modelValue="(id: number) => save(row, { currency_id: id, amount: drafts[rowKey(row)] })"
                                />
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <label v-if="!row.is_na && row.currency_id && row.currency_id !== costing.currency_id && row.currency_id !== costing.org_currency_id" class="flex items-center gap-1 text-xs text-gray-500">
                                    {{ ctrans("Rate to :currency", { currency: costing.currency ?? "" }) }}
                                    <InputText
                                        :modelValue="row.exchange ?? undefined"
                                        type="number"
                                        size="small"
                                        min="0"
                                        step="0.000001"
                                        class="w-24 tabular-nums"
                                        :disabled="!canEdit || row.is_na || row.from_service_invoices"
                                        @change="Number(($event.target as HTMLInputElement).value) > 0 && save(row, { exchange: ($event.target as HTMLInputElement).value })"
                                    />
                                </label>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <div v-if="(!row.received_at || row.is_waiting_for_invoice) && !row.is_na && !row.from_service_invoices" class="flex items-center gap-2">
                                    <button
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="row.is_waiting_for_invoice"
                                    :aria-label="ctrans('Waiting for invoice')"
                                    :disabled="!canEdit"
                                    class="text-lg leading-none disabled:opacity-40"
                                    :class="row.is_waiting_for_invoice ? 'text-gray-700' : 'text-gray-400 hover:text-gray-600'"
                                    @click="save(row, { is_waiting_for_invoice: !row.is_waiting_for_invoice })"
                                >
                                    <FontAwesomeIcon :icon="row.is_waiting_for_invoice ? 'fas fa-check-square' : 'fal fa-square'" fixed-width aria-hidden="true" />
                                </button>
                                    <template v-if="row.is_waiting_for_invoice">
                                        <InputText
                                            type="number"
                                            size="small"
                                            min="0"
                                            max="365"
                                            step="1"
                                            class="w-16 tabular-nums"
                                            :placeholder="ctrans('days')"
                                            :disabled="!canEdit"
                                            v-tooltip="ctrans('In how many days the invoice is expected (optional)')"
                                            @change="save(row, { invoice_expected_in_days: ($event.target as HTMLInputElement).value === '' ? null : Number(($event.target as HTMLInputElement).value) })"
                                        />
                                        <span class="rounded px-1.5 py-0.5 text-xs" :class="row.invoice_overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-800'">{{ row.is_estimate ? ctrans("Estimated") + " · " : "" }}{{ row.waiting_label }}</span>
                                    </template>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <a
                                        v-for="file in row.attachments"
                                        :key="file.ulid"
                                        :href="route('grp.media.download', { id: file.ulid })"
                                        class="flex items-center gap-1 text-gray-600 hover:text-gray-900 hover:underline"
                                    >
                                        <FontAwesomeIcon icon="fal fa-paperclip" fixed-width aria-hidden="true" />
                                        <span class="max-w-[10rem] truncate">{{ file.name }}</span>
                                    </a>
                                    <template v-if="row.uploadRoute && !row.is_na">
                                        <input
                                            :ref="(el) => { fileInputs[rowKey(row)] = el as HTMLInputElement | null }"
                                            type="file"
                                            accept=".pdf,image/*,.xlsx,.xls,.csv"
                                            multiple
                                            class="hidden"
                                            @change="uploadInvoice(row, $event)"
                                        />
                                        <Button
                                            type="tertiary"
                                            size="xs"
                                            icon="fal fa-upload"
                                            :label="ctrans('Upload')"
                                            :loading="uploading === rowKey(row)"
                                            @click="fileInputs[rowKey(row)]?.click()"
                                        />
                                    </template>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <button
                                    v-if="row.history.length"
                                    type="button"
                                    class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-800"
                                    :aria-expanded="openHistory === rowKey(row)"
                                    v-tooltip="ctrans('Last change by :name', { name: row.history[0].user })"
                                    @click="openHistory = openHistory === rowKey(row) ? null : rowKey(row)"
                                >
                                    <FontAwesomeIcon icon="fal fa-history" fixed-width aria-hidden="true" />
                                    {{ row.history.length }}
                                </button>
                            </td>
                            <td class="px-3 py-2 align-middle text-right">
                                <button
                                    v-if="canEdit && row.deleteRoute"
                                    type="button"
                                    class="text-gray-400 hover:text-red-500"
                                    @click="removeExtra(row)"
                                >
                                    <FontAwesomeIcon :icon="removing === rowKey(row) ? 'fad fa-spinner-third' : 'fal fa-trash-alt'" :class="removing === rowKey(row) ? 'animate-spin' : ''" fixed-width aria-hidden="true" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="openHistory === rowKey(row)" class="bg-gray-50">
                            <td colspan="9" class="px-3 py-2">
                                <ul class="space-y-1 text-xs text-gray-600">
                                    <li v-for="(entry, index) in row.history" :key="index" class="flex flex-wrap gap-x-2">
                                        <span class="tabular-nums text-gray-500">{{ useFormatTime(entry.at, { formatTime: "hm" }) }}</span>
                                        <span class="font-medium text-gray-700">{{ entry.user }}</span>
                                        <span v-for="change in entry.changes" :key="change.field">
                                            {{ change.field }}: <span class="text-gray-400 line-through">{{ change.old }}</span> → <span class="text-gray-800">{{ change.new }}</span>
                                        </span>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>

                <div v-if="isInvoiceSection ? costing.org_currency !== costing.currency : canEdit" class="flex items-center justify-between gap-3 border-t border-gray-200 bg-gray-50 px-3 py-2">
                    <form v-if="canEdit && !isInvoiceSection && isAddingExtra" class="flex flex-wrap items-center gap-2" @submit.prevent="addExtra">
                        <Select
                            v-model="extraCategory"
                            size="small"
                            :options="costing.extra_types"
                            optionLabel="label"
                            optionValue="value"
                            class="w-64"
                            :placeholder="ctrans('Kind of expense')"
                        />
                        <InputText
                            v-if="extraCategory"
                            v-model="extraLabel"
                            size="small"
                            maxlength="255"
                            class="w-64"
                            :placeholder="extraCategory === 'other' ? ctrans('What is it?') : ctrans('Note (optional)')"
                            @keydown.esc.stop="closeExtra"
                        />
                        <Button type="tertiary" size="xs" :label="ctrans('Add')" :disabled="!canAddExtra" :loading="adding" @click="addExtra">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                        <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="closeExtra">
                            <template #icon>
                                <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                            </template>
                        </Button>
                    </form>
                    <button
                        v-else-if="canEdit && !isInvoiceSection"
                        type="button"
                        class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700"
                        @click="isAddingExtra = true"
                    >
                        <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                        {{ ctrans("Add extra expense") }}
                    </button>
                    <span v-else></span>

                    <label v-if="isInvoiceSection && costing.org_currency !== costing.currency" class="flex items-center gap-2 text-xs text-gray-500">
                        <span>{{ ctrans("Invoice exchange rate") }}</span>
                        1 {{ costing.org_currency }} =
                        <InputText
                            :modelValue="deliveryPerOrg ?? undefined"
                            type="number"
                            size="small"
                            min="0"
                            step="0.000001"
                            class="w-32 tabular-nums"
                            :disabled="!canEdit"
                            @change="saveOrgExchange(($event.target as HTMLInputElement).value)"
                        />
                        {{ costing.currency }}
                    </label>
                </div>
            </div>

            <div v-if="isInvoiceSection" class="mt-4 rounded-md border border-gray-200 overflow-hidden text-sm">
                <div class="bg-gray-50 px-3 py-2 text-xs font-medium text-gray-600">{{ ctrans("Settlement") }}</div>
                <div class="grid gap-1 px-3 py-2 text-gray-700">
                    <div v-for="application in costing.deposits.applied" :key="application.id" class="flex items-center justify-between gap-3">
                        <span>{{ application.reference || application.id }}</span>
                        <span class="flex items-center gap-2 tabular-nums">
                            {{ locale.currencyFormat(costing.currency ?? "", Number(application.amount)) }}
                            <button
                                v-if="canEditPayments && application.deleteRoute"
                                type="button"
                                class="text-gray-400 hover:text-red-500"
                                :title="ctrans('Un-apply deposit (audited)')"
                                @click="unapplyDeposit(application)"
                            >
                                <FontAwesomeIcon icon="fal fa-trash-alt" fixed-width aria-hidden="true" />
                            </button>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">{{ ctrans("Agent invoice") }}</span>
                        <span class="tabular-nums">{{ locale.currencyFormat(costing.currency ?? "", Number(costing.deposits.agent_invoice_amount)) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500">{{ ctrans("Deposits applied") }}</span>
                        <span class="tabular-nums">{{ Number(costing.deposits.applied_total) > 0 ? "-" : "" }}{{ locale.currencyFormat(costing.currency ?? "", Number(costing.deposits.applied_total)) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 font-medium">
                        <span>{{ ctrans("Balance due") }}</span>
                        <span class="tabular-nums">{{ locale.currencyFormat(costing.currency ?? "", Number(costing.deposits.balance_due)) }}</span>
                    </div>
                </div>

                <div v-if="canEditPayments && costing.deposits.available.length" class="flex items-center gap-2 border-t border-gray-200 px-3 py-2">
                    <Select
                        v-model="selectedDepositId"
                        size="small"
                        :options="depositOptions"
                        optionLabel="label"
                        optionValue="id"
                        showClear
                        :placeholder="ctrans('Select a paid deposit')"
                        class="min-w-64"
                    />
                    <InputText v-model="applyAmount" type="number" size="small" min="0" step="0.01" class="w-28 tabular-nums" :placeholder="ctrans('Amount')" />
                    <button type="button" class="text-xs text-gray-500 hover:text-gray-700" @click="applyDeposit">
                        {{ ctrans("Apply deposit") }}
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>
