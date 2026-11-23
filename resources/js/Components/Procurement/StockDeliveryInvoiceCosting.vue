<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 28 Sep 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router, usePoll } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Checkbox from "primevue/checkbox"
import Dialog from "primevue/dialog"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import { faFileInvoiceDollar, faCheck, faTimes, faExchange, faPaperclip } from "@fal"
import { faSpinnerThird } from "@fad"
import { faExclamationTriangle, faCheckCircle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StockDeliveryInvoiceDifferences from "@/Components/Procurement/StockDeliveryInvoiceDifferences.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

interface ReviewItem {
    id: number
    code: string | null
    name: string | null
    quantity: number
    expected_amount: number
    current_cost: number
    invoice_quantity: number | null
    invoice_amount: number | null
    quantity_differs: boolean
    amount_differs: boolean
    proposed_cost: number | null
    needs_check: boolean
    matched_by: string[]
}

interface InvoiceLine {
    index: number
    code: string | null
    barcode?: string | null
    description: string | null
    suggested_item_id?: number | null
    quantity: number | null
    unit_price: number | null
    amount: number | null
}

interface Reading {
    state: "reading" | "read" | "failed"
    error?: string
    read_at?: string
    applied_at?: string | null
    is_invoice?: boolean
    issuer?: string | null
    invoice_number?: string | null
    invoice_date?: string | null
    currency?: string | null
    total?: number | null
    charges?: { label: string; amount: number }[]
    charge_types?: { label: string; value: string }[]
    shipping_typed_by_hand?: boolean
    replaces_typed?: { amount: number; currency: string | null } | null
    not_received?: { amount: number; codes: (string | null)[] } | null
    not_invoiced?: { amount: number; codes: (string | null)[] } | null
    delivery_currency?: string | null
    currency_mismatch?: boolean
    exchange?: number | null
    follow_blockers?: string[]
    lines_total?: number
    charges_total?: number
    problems?: string[]
    needs_manual_check?: boolean
    delivery_confirmation?: { phrase: string; reference: string; printed: string[]; dates_fit: boolean | null; left_at: string | null } | null
    can_apply?: boolean
    unmatched?: InvoiceLine[]
    not_in_delivery?: { index: number; label: string; amount: number | null }[]
    items?: ReviewItem[]
}

interface Invoice {
    media_id: number
    ulid: string
    name: string
    scope: string
    readRoute: routeType
    applyRoute: routeType
    reading: Reading | null
}

interface Draft {
    assignments: Record<number, number | "not_in_delivery" | null>
    chargeTypes: (string | null)[]
    checked: boolean
    confirmation: string
}

const props = defineProps<{
    invoices: Invoice[]
    canEdit: boolean
}>()

const locale = useLocaleStore()
const fieldFocusClass = "[&.p-focus]:!border-[--app-accent]"
const drafts = reactive<Record<string, Draft>>({})
const openInvoice = ref<number | null>(null)
const busy = ref<number | null>(null)
const choosing = ref<Invoice | null>(null)
const exchange = ref("")

const draftKey = (invoice: Invoice) => `${invoice.media_id}-${invoice.reading?.read_at}`

const money = (amount: number | null | undefined, currency?: string | null) =>
    amount == null ? "—" : currency ? locale.currencyFormat(currency, amount) : locale.number(amount)

watch(
    () => props.invoices,
    (invoices) => {
        invoices.forEach((invoice) => {
            const reading = invoice.reading
            if (reading?.state !== "read" || drafts[draftKey(invoice)]) {
                return
            }

            drafts[draftKey(invoice)] = {
                assignments: Object.fromEntries((reading.unmatched ?? []).map((line) => [line.index, line.suggested_item_id ?? null])),
                chargeTypes: (reading.charges ?? []).map(() => null),
                checked: false,
                confirmation: "",
            }
        })
    },
    { immediate: true, deep: true }
)

const isReading = computed(() => props.invoices.some((invoice) => invoice.reading?.state === "reading"))

const { start: startPolling, stop: stopPolling } = usePoll(3000, { only: ["invoice_costing"] }, { autoStart: false })

watch(isReading, (reading) => (reading ? startPolling() : stopPolling()), { immediate: true })

const read = (invoice: Invoice) => {
    router.post(route(invoice.readRoute.name, invoice.readRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => (busy.value = invoice.media_id),
        onFinish: () => (busy.value = null),
        onSuccess: () => (openInvoice.value = invoice.media_id),
    })
}

const assignmentOptions = (reading: Reading) => [
    { label: ctrans("Invoiced but not in the delivery"), value: "not_in_delivery" },
    ...(reading.items ?? []).map((item) => ({ label: `${ctrans("Add to")} ${item.code}`, value: item.id })),
]

const matchedByLabel = (matchedBy: string[]) =>
    matchedBy
        .filter((by) => by !== "supplier_code")
        .map((by) => ({ sko_code: ctrans("matched by our code"), barcode: ctrans("matched by barcode"), reviewer: ctrans("placed by you") })[by] ?? by)
        .join(", ")

const TOLERANCE = 0.01
const isMissing = (item: ReviewItem) => item.invoice_amount == null
const quantityDifference = (item: ReviewItem) => (item.invoice_quantity ?? 0) - item.quantity
const amountDifference = (item: ReviewItem) => (item.invoice_amount ?? 0) - item.expected_amount
const signed = (value: number, format: (amount: number) => string) => `${value > 0 ? "+" : value < 0 ? "−" : ""}${format(Math.abs(value))}`

const sortedItems = (reading: Reading) => [...(reading.items ?? [])].sort((first, second) => Number(isMissing(first)) - Number(isMissing(second)))

const typed = (text: string) => text.trim().replace(/\s+/g, " ").replace(/\.$/, "").toLowerCase()

const canApply = (invoice: Invoice) => {
    const draft = drafts[draftKey(invoice)]
    const reading = invoice.reading as Reading

    return Boolean(reading.can_apply)
        && Object.values(draft.assignments).every((value) => value !== null)
        && draft.chargeTypes.every((type) => type)
        && !(reading.shipping_typed_by_hand && draft.chargeTypes.includes("freight"))
        && (!reading.needs_manual_check || draft.checked)
        && (!reading.delivery_confirmation || typed(draft.confirmation) === typed(reading.delivery_confirmation.phrase))
}

const startApply = (invoice: Invoice) => {
    if (!invoice.reading?.currency_mismatch) {
        return apply(invoice)
    }

    exchange.value = invoice.reading.exchange ? String(invoice.reading.exchange) : ""
    choosing.value = invoice
}

const choose = (followInvoice: boolean) => {
    const invoice = choosing.value as Invoice

    choosing.value = null
    apply(invoice, followInvoice ? { follow_invoice: true } : { follow_invoice: false, exchange: Number(exchange.value) })
}

const apply = (invoice: Invoice, currencyChoice: { follow_invoice?: boolean; exchange?: number } = {}) => {
    const draft = drafts[draftKey(invoice)]

    router.post(route(invoice.applyRoute.name, invoice.applyRoute.parameters), {
        read_at: invoice.reading?.read_at,
        assignments: Object.entries(draft.assignments).map(([index, value]) => ({ index: Number(index), item_id: value === "not_in_delivery" ? null : value })),
        charge_types: draft.chargeTypes,
        checked: draft.checked,
        confirmation: draft.confirmation || null,
        ...currencyChoice,
    }, {
        preserveScroll: true,
        onStart: () => (busy.value = invoice.media_id),
        onFinish: () => (busy.value = null),
        onSuccess: () => notify({ title: ctrans("The invoice is in the costing"), type: "success" }),
        onError: (errors) => notify({ title: ctrans("The costs were not applied"), text: Object.values(errors).join(" "), type: "error" }),
    })
}
</script>

<template>
    <div class="border-b border-gray-300 px-4 py-3 text-sm text-gray-600">
        <div class="mb-2 flex items-center gap-2">
            <FontAwesomeIcon :icon="faFileInvoiceDollar" class="text-gray-400" fixed-width />
            <span class="font-medium">{{ ctrans("Costs from the supplier invoice") }}</span>
        </div>

        <p v-if="!invoices.length" class="text-gray-500">
            {{ ctrans("Attach the supplier's invoice as Invoice or Proforma to read its costs.") }}
        </p>

        <div v-for="invoice in invoices" :key="invoice.media_id" class="mb-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-gray-800">{{ invoice.name }}</span>
                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ invoice.scope }}</span>

                <span v-if="invoice.reading?.state === 'reading'" class="flex items-center gap-1 text-gray-500">
                    <FontAwesomeIcon :icon="faSpinnerThird" spin fixed-width />{{ ctrans("Reading… two readers check it against each other, this takes up to a minute.") }}
                </span>
                <span v-else-if="invoice.reading?.state === 'failed'" class="text-red-600">{{ invoice.reading.error }}</span>
                <span v-else-if="invoice.reading?.applied_at" class="flex items-center gap-1 text-green-600">
                    <FontAwesomeIcon :icon="faCheckCircle" fixed-width />{{ ctrans("In the costing") }}
                </span>
                <span v-else-if="invoice.reading?.state === 'read' && invoice.reading.problems?.length" class="text-amber-700">{{ ctrans("Enter it by hand") }}</span>

                <Button v-if="canEdit && invoice.reading?.state !== 'reading' && !invoice.reading?.applied_at"
                    :label="invoice.reading?.state === 'read' ? ctrans('Read again') : ctrans('Read invoice')"
                    type="tertiary" size="xs" :loading="busy === invoice.media_id" @click="read(invoice)" />
                <Button v-if="invoice.reading?.state === 'read' && invoice.reading.items"
                    :label="openInvoice === invoice.media_id ? ctrans('Hide') : ctrans('Review invoice')"
                    type="tertiary" size="xs" @click="openInvoice = openInvoice === invoice.media_id ? null : invoice.media_id" />
            </div>

            <ul v-if="invoice.reading?.state === 'read' && !invoice.reading.items && invoice.reading.problems?.length" class="mt-1 text-amber-700">
                <li v-for="problem in invoice.reading.problems" :key="problem">{{ problem }}</li>
            </ul>

            <div v-if="invoice.reading?.state === 'read' && invoice.reading.items && openInvoice === invoice.media_id && drafts[draftKey(invoice)]" class="mt-2 space-y-3 rounded border border-gray-200 p-3">
                <ul v-if="invoice.reading.problems?.length" class="space-y-1 text-amber-700">
                    <li v-for="problem in invoice.reading.problems" :key="problem" class="flex gap-1.5">
                        <FontAwesomeIcon :icon="faExclamationTriangle" class="mt-0.5" fixed-width aria-hidden="true" />
                        <span>{{ problem }}</span>
                    </li>
                    <li class="text-gray-600">{{ ctrans("A reading that fails a check is never corrected here: enter the invoice and its costs by hand.") }}</li>
                </ul>

                <a :href="route('grp.media.download', { id: invoice.ulid })" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:underline">
                    <FontAwesomeIcon :icon="faPaperclip" fixed-width aria-hidden="true" />{{ ctrans("Open the document") }}
                </a>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-5">
                    <div><dt class="text-gray-500">{{ ctrans("Issued by") }}</dt><dd class="text-gray-800">{{ invoice.reading.issuer ?? "—" }}</dd></div>
                    <div><dt class="text-gray-500">{{ ctrans("Invoice reference") }}</dt><dd class="text-gray-800">{{ invoice.reading.invoice_number ?? "—" }}</dd></div>
                    <div><dt class="text-gray-500">{{ ctrans("Invoice date") }}</dt><dd class="text-gray-800">{{ invoice.reading.invoice_date ? useFormatTime(invoice.reading.invoice_date) : "—" }}</dd></div>
                    <div><dt class="text-gray-500">{{ ctrans("Currency") }}</dt><dd class="text-gray-800">{{ invoice.reading.currency ?? "—" }}</dd></div>
                    <div><dt class="text-gray-500">{{ ctrans("Total") }}</dt><dd class="font-medium text-gray-800">{{ money(invoice.reading.total, invoice.reading.currency) }}</dd></div>
                </dl>

                <StockDeliveryInvoiceDifferences :reading="invoice.reading" class="text-xs" />

                <p v-if="invoice.reading.currency_mismatch" class="text-amber-700">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    {{ ctrans("The invoice is in :invoice and the delivery in :delivery: when you apply it you choose whether the delivery follows the invoice.", { invoice: invoice.reading.currency ?? "", delivery: invoice.reading.delivery_currency ?? "" }) }}
                </p>

                <table class="w-full text-left text-xs">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="whitespace-nowrap py-1">{{ ctrans("Product") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Received") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Invoiced") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Order amount") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Invoice amount") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Difference") }}</th>
                            <th class="whitespace-nowrap py-1 text-right">{{ ctrans("Goods cost") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(item, itemIndex) in sortedItems(invoice.reading)" :key="item.id">
                        <tr v-if="isMissing(item) && (itemIndex === 0 || !isMissing(sortedItems(invoice.reading)[itemIndex - 1]))" class="border-t border-gray-200">
                            <td colspan="7" class="pb-1 pt-2 font-medium text-amber-700">{{ ctrans("Not on the invoice: not sent or not charged by the supplier") }}</td>
                        </tr>
                        <tr class="border-t border-gray-100" :class="item.needs_check || isMissing(item) ? 'bg-amber-50' : ''">
                            <td class="py-1">
                                <span class="font-medium">{{ item.code }}</span> {{ item.name }}
                                <span v-if="matchedByLabel(item.matched_by)" class="text-gray-400">· {{ matchedByLabel(item.matched_by) }}</span>
                            </td>
                            <td class="py-1 text-right tabular-nums">{{ locale.number(item.quantity) }}</td>
                            <td class="py-1 text-right tabular-nums" :class="item.quantity_differs ? 'font-medium text-amber-700' : ''">
                                {{ locale.number(item.invoice_quantity ?? 0) }}
                            </td>
                            <td class="py-1 text-right tabular-nums">{{ money(item.expected_amount, invoice.reading.delivery_currency) }}</td>
                            <td class="py-1 text-right tabular-nums" :class="item.amount_differs ? 'font-medium text-amber-700' : ''">{{ money(item.invoice_amount, invoice.reading.currency) }}</td>
                            <td class="whitespace-nowrap py-1 text-right tabular-nums" :class="item.quantity_differs || item.amount_differs || isMissing(item) ? 'font-medium text-amber-700' : 'text-gray-400'">
                                <template v-if="Math.abs(quantityDifference(item)) > TOLERANCE">{{ signed(quantityDifference(item), (amount) => locale.number(amount)) }}</template>
                                <template v-if="!invoice.reading.currency_mismatch && Math.abs(amountDifference(item)) > TOLERANCE">
                                    <template v-if="Math.abs(quantityDifference(item)) > TOLERANCE"> · </template>{{ signed(amountDifference(item), (amount) => String(money(amount, invoice.reading!.currency))) }}
                                </template>
                                <template v-if="Math.abs(quantityDifference(item)) <= TOLERANCE && (invoice.reading.currency_mismatch || Math.abs(amountDifference(item)) <= TOLERANCE)">—</template>
                            </td>
                            <td class="py-1 text-right font-medium tabular-nums">{{ money(item.proposed_cost, invoice.reading.currency) }}</td>
                        </tr>
                        </template>
                    </tbody>
                </table>

                <p class="text-xs text-gray-500">
                    {{ ctrans("The amounts are the ones printed on the invoice and cannot be changed here. The goods cost of a line is the invoice price times what was received.") }}
                </p>

                <div v-if="invoice.reading.unmatched?.length">
                    <div class="mb-1 font-medium text-amber-700">{{ ctrans("Invoice lines that match no product of this delivery: say where each goes") }}</div>
                    <div v-for="line in invoice.reading.unmatched" :key="line.index" class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-medium">{{ line.code ?? "—" }}</span>
                        <span v-if="line.barcode" class="text-gray-500">{{ line.barcode }}</span>
                        <span>{{ line.description }}</span>
                        <span>{{ line.quantity == null ? "" : "× " + locale.number(line.quantity) }}</span>
                        <span class="font-medium tabular-nums">{{ money(line.amount, invoice.reading.currency) }}</span>
                        <Select v-model="drafts[draftKey(invoice)].assignments[line.index]" :options="assignmentOptions(invoice.reading)"
                            optionLabel="label" optionValue="value" size="small" class="w-72" :class="fieldFocusClass"
                            :placeholder="ctrans('Choose')" :invalid="drafts[draftKey(invoice)].assignments[line.index] === null" :disabled="!canEdit || !invoice.reading.can_apply" />
                        <span v-if="line.suggested_item_id && drafts[draftKey(invoice)].assignments[line.index] === line.suggested_item_id" class="text-amber-700">
                            {{ ctrans("suggested from the description, check it") }}
                        </span>
                    </div>
                </div>

                <div v-if="invoice.reading.charges?.length">
                    <div class="mb-1 font-medium">{{ ctrans("Other charges on the invoice, added as extra expenses") }}</div>
                    <div v-for="(charge, index) in invoice.reading.charges" :key="index" class="flex flex-wrap items-center gap-2 text-xs">
                        <span>{{ charge.label }}</span>
                        <span class="font-medium tabular-nums">{{ money(charge.amount, invoice.reading.currency) }}</span>
                        <Select v-model="drafts[draftKey(invoice)].chargeTypes[index]" :options="invoice.reading.charge_types" optionLabel="label" optionValue="value"
                            size="small" class="w-60" :class="fieldFocusClass" :placeholder="ctrans('What kind of expense?')"
                            :invalid="!drafts[draftKey(invoice)].chargeTypes[index]" :disabled="!canEdit || !invoice.reading.can_apply" />
                    </div>
                </div>

                <p v-if="invoice.reading.not_received" class="text-amber-700">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    {{ ctrans("The invoice charges :amount for goods that were not received (:codes): it stays out of the goods cost, ask the supplier for a credit.", { amount: String(money(invoice.reading.not_received.amount, invoice.reading.currency)), codes: invoice.reading.not_received.codes.join(", ") }) }}
                </p>
                <p v-if="invoice.reading.not_invoiced" class="text-amber-700">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    {{ ctrans("More was received than invoiced (:codes): the extra goods are costed at the invoice price, :amount.", { amount: String(money(invoice.reading.not_invoiced.amount, invoice.reading.currency)), codes: invoice.reading.not_invoiced.codes.join(", ") }) }}
                </p>

                <p v-if="invoice.reading.replaces_typed && invoice.reading.can_apply" class="text-amber-700">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    {{ ctrans("The invoice typed by hand says :typed, applying replaces it with :total.", { typed: String(money(invoice.reading.replaces_typed.amount, invoice.reading.replaces_typed.currency)), total: String(money(invoice.reading.total, invoice.reading.currency)) }) }}
                </p>

                <p v-if="invoice.reading.shipping_typed_by_hand && drafts[draftKey(invoice)].chargeTypes.includes('freight')" class="text-red-600">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                    {{ ctrans("Shipping is already entered by hand on this delivery: put the invoice freight there, or remove it, before applying the invoice.") }}
                </p>

                <label v-if="invoice.reading.needs_manual_check && invoice.reading.can_apply" class="flex items-start gap-2 text-xs text-amber-700">
                    <Checkbox v-model="drafts[draftKey(invoice)].checked" binary :inputId="`supplier-invoice-reading-checked-${invoice.media_id}`" />
                    <span>{{ ctrans("This document could not be checked automatically. I have checked every amount, and that it is of this delivery, against the document.") }}</span>
                </label>

                <div v-if="invoice.reading.delivery_confirmation && invoice.reading.can_apply" class="space-y-2 rounded border p-3"
                    :class="invoice.reading.delivery_confirmation.dates_fit ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-red-200 bg-red-50 text-red-700'">
                    <p>
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                        {{ ctrans("The document does not mention :reference or its purchase orders.", { reference: invoice.reading.delivery_confirmation.reference }) }}
                        <template v-if="invoice.reading.delivery_confirmation.printed.length">
                            {{ ctrans("It prints these references:") }}
                            <span class="font-medium">{{ invoice.reading.delivery_confirmation.printed.join(", ") }}</span>
                        </template>
                    </p>
                    <label v-if="canEdit" class="block space-y-1">
                        <span>{{ ctrans("To add it, type: :phrase", { phrase: invoice.reading.delivery_confirmation.phrase }) }}</span>
                        <InputText v-model="drafts[draftKey(invoice)].confirmation" size="small" class="w-full" :class="fieldFocusClass" autocomplete="off" :placeholder="invoice.reading.delivery_confirmation.phrase" />
                    </label>
                </div>

                <div v-if="canEdit && invoice.reading.can_apply" class="flex justify-end">
                    <Button :label="ctrans('Apply to costing')" type="tertiary" size="xs" :disabled="!canApply(invoice)"
                        :loading="busy === invoice.media_id" @click="startApply(invoice)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </div>

        <Dialog :visible="!!choosing" modal :header="ctrans('Should the stock delivery follow the invoice?')" :style="{ width: '34rem', maxWidth: 'calc(100vw - 2rem)' }" @update:visible="choosing = null">
            <div v-if="choosing?.reading" class="space-y-4 text-sm text-gray-600">
                <p>{{ ctrans("The invoice is in :invoice and the stock delivery in :delivery.", { invoice: choosing.reading.currency ?? "", delivery: choosing.reading.delivery_currency ?? "" }) }}</p>

                <div>
                    <div class="font-medium text-gray-800">{{ ctrans("Yes, follow the invoice") }}</div>
                    <p>{{ ctrans("The stock delivery changes to :invoice and takes the costs exactly as invoiced. Its purchase orders stay in :delivery.", { invoice: choosing.reading.currency ?? "", delivery: choosing.reading.delivery_currency ?? "" }) }}</p>
                    <p v-if="choosing.reading.follow_blockers?.length" class="mt-1 text-orange-600">
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width aria-hidden="true" />
                        {{ ctrans("Not possible on this delivery:") }} {{ choosing.reading.follow_blockers.join(" ") }}
                    </p>
                </div>

                <div>
                    <div class="font-medium text-gray-800">{{ ctrans("No, keep :delivery", { delivery: choosing.reading.delivery_currency ?? "" }) }}</div>
                    <p>{{ ctrans("The stock delivery stays in :delivery. The invoice is recorded in :invoice and its costs are converted at this rate.", { invoice: choosing.reading.currency ?? "", delivery: choosing.reading.delivery_currency ?? "" }) }}</p>
                    <label class="mt-2 flex items-center gap-2">
                        <span>1 {{ choosing.reading.currency }} =</span>
                        <InputText v-model="exchange" type="number" size="small" min="0" step="0.000001" class="w-32 tabular-nums" />
                        <span>{{ choosing.reading.delivery_currency }}</span>
                    </label>
                </div>

                <div class="flex flex-wrap justify-end gap-2 pt-2">
                    <Button type="tertiary" :label="ctrans('Cancel')" @click="choosing = null">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('No, keep :delivery', { delivery: choosing.reading.delivery_currency ?? '' })" :disabled="!(Number(exchange) > 0)" @click="choose(false)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faExchange" class="text-amber-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" :label="ctrans('Yes, follow the invoice')" :disabled="!!choosing.reading.follow_blockers?.length" @click="choose(true)">
                        <template #icon>
                            <FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </Dialog>
    </div>
</template>
