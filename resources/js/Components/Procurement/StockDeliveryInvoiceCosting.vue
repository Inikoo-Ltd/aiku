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
import { faFileInvoiceDollar } from "@fal"
import { faSpinnerThird } from "@fad"
import { faExclamationTriangle, faCheckCircle } from "@fas"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
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
    proposed_cost: number
    needs_check: boolean
}

interface InvoiceLine {
    code: string | null
    description: string | null
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
    invoice_number?: string | null
    invoice_date?: string | null
    currency?: string | null
    total?: number | null
    charges?: { label: string; amount: number }[]
    delivery_currency?: string | null
    currency_mismatch?: boolean
    total_mismatch?: boolean
    lines_total?: number
    charges_total?: number
    can_apply?: boolean
    unmatched?: InvoiceLine[]
    items?: ReviewItem[]
}

interface Invoice {
    media_id: number
    name: string
    scope: string
    readRoute: routeType
    applyRoute: routeType
    reading: Reading | null
}

interface Draft {
    invoice_number: string
    invoice_date: string
    invoice_total: string
    costs: Record<number, string>
    charges: Record<number, boolean>
    assignments: Record<number, number | null>
}

const props = defineProps<{
    invoices: Invoice[]
    canEdit: boolean
}>()

const locale = useLocaleStore()
const drafts = reactive<Record<string, Draft>>({})
const openInvoice = ref<number | null>(null)
const busy = ref<number | null>(null)

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
                invoice_number: reading.invoice_number ?? "",
                invoice_date: reading.invoice_date ?? "",
                invoice_total: reading.total == null ? "" : String(reading.total),
                costs: Object.fromEntries((reading.items ?? []).map((item) => [item.id, String(item.proposed_cost)])),
                charges: Object.fromEntries((reading.charges ?? []).map((charge, index) => [index, charge.amount > 0])),
                assignments: Object.fromEntries((reading.unmatched ?? []).map((_, index) => [index, null])),
            }
        })
    },
    { immediate: true, deep: true }
)

const isReading = computed(() => props.invoices.some((invoice) => invoice.reading?.state === "reading"))

const { start: startPolling, stop: stopPolling } = usePoll(3000, { only: ["invoice_costing"] }, { autoStart: false })

watch(isReading, (reading) => (reading ? startPolling() : stopPolling()), { immediate: true })

const proposedCost = (invoice: Invoice, item: ReviewItem): number => {
    const draft = drafts[draftKey(invoice)]
    const assigned = (invoice.reading?.unmatched ?? []).reduce(
        (sum, line, index) => sum + (draft.assignments[index] === item.id ? Number(line.amount ?? 0) : 0),
        0
    )

    return Number(draft.costs[item.id] || 0) + assigned
}

const read = (invoice: Invoice) => {
    router.post(route(invoice.readRoute.name, invoice.readRoute.parameters), {}, {
        preserveScroll: true,
        onStart: () => (busy.value = invoice.media_id),
        onFinish: () => (busy.value = null),
        onSuccess: () => (openInvoice.value = invoice.media_id),
    })
}

const apply = (invoice: Invoice) => {
    const draft = drafts[draftKey(invoice)]
    const reading = invoice.reading as Reading

    router.post(route(invoice.applyRoute.name, invoice.applyRoute.parameters), {
        invoice_number: draft.invoice_number || null,
        invoice_date: draft.invoice_date,
        invoice_total: Number(draft.invoice_total),
        items: (reading.items ?? []).map((item) => ({ id: item.id, cost: Math.round(proposedCost(invoice, item) * 100) / 100 })),
        charges: (reading.charges ?? []).filter((_, index) => draft.charges[index]),
    }, {
        preserveScroll: true,
        onStart: () => (busy.value = invoice.media_id),
        onFinish: () => (busy.value = null),
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
                    <FontAwesomeIcon :icon="faSpinnerThird" spin fixed-width />{{ ctrans("Reading…") }}
                </span>
                <span v-else-if="invoice.reading?.state === 'failed'" class="text-red-600">{{ invoice.reading.error }}</span>
                <span v-else-if="invoice.reading?.applied_at" class="flex items-center gap-1 text-green-600">
                    <FontAwesomeIcon :icon="faCheckCircle" fixed-width />{{ ctrans("In the costing") }}
                </span>

                <Button v-if="canEdit && invoice.reading?.state !== 'reading' && !invoice.reading?.applied_at"
                    :label="invoice.reading?.state === 'read' ? ctrans('Read again') : ctrans('Read invoice')"
                    type="tertiary" size="xs" :loading="busy === invoice.media_id" @click="read(invoice)" />
                <Button v-if="invoice.reading?.state === 'read'"
                    :label="openInvoice === invoice.media_id ? ctrans('Hide') : ctrans('Review')"
                    type="tertiary" size="xs" @click="openInvoice = openInvoice === invoice.media_id ? null : invoice.media_id" />
            </div>

            <div v-if="invoice.reading?.state === 'read' && openInvoice === invoice.media_id && drafts[draftKey(invoice)]" class="mt-2 space-y-3 rounded border border-gray-200 p-3">
                <div v-if="invoice.reading.is_invoice === false || invoice.reading.currency_mismatch || invoice.reading.total_mismatch" class="space-y-1 text-orange-600">
                    <div v-if="invoice.reading.is_invoice === false"><FontAwesomeIcon :icon="faExclamationTriangle" fixed-width /> {{ ctrans("This does not look like an invoice.") }}</div>
                    <div v-if="invoice.reading.currency_mismatch">
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
                        {{ ctrans("The invoice is in :invoice and the delivery in :delivery, change the currency of the delivery first.", { invoice: invoice.reading.currency ?? "", delivery: invoice.reading.delivery_currency ?? "" }) }}
                    </div>
                    <div v-if="invoice.reading.total_mismatch">
                        <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
                        {{ ctrans("Lines and charges add up to :sum, the invoice total says :total.", { sum: String(money((invoice.reading.lines_total ?? 0) + (invoice.reading.charges_total ?? 0), invoice.reading.currency)), total: String(money(invoice.reading.total, invoice.reading.currency)) }) }}
                    </div>
                </div>

                <div v-if="invoice.reading.items?.some((item) => item.needs_check)" class="text-orange-600">
                    <FontAwesomeIcon :icon="faExclamationTriangle" fixed-width />
                    {{ ctrans("Some goods costs are far from the order amount, check them before applying: the invoice may count packs or cartons rather than units.") }}
                </div>

                <div class="flex flex-wrap gap-3">
                    <label class="flex items-center gap-1">{{ ctrans("Number") }}
                        <input v-model="drafts[draftKey(invoice)].invoice_number" class="h-7 w-36 rounded border-gray-300 text-sm" />
                    </label>
                    <label class="flex items-center gap-1">{{ ctrans("Date") }}
                        <input v-model="drafts[draftKey(invoice)].invoice_date" type="date" class="h-7 rounded border-gray-300 text-sm" />
                    </label>
                    <label class="flex items-center gap-1">{{ ctrans("Total") }}
                        <input v-model="drafts[draftKey(invoice)].invoice_total" type="number" min="0" step="0.01" class="h-7 w-32 rounded border-gray-300 text-sm" />
                        <span class="text-gray-400">{{ invoice.reading.delivery_currency }}</span>
                    </label>
                </div>

                <table class="w-full text-left text-xs">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-1">{{ ctrans("Product") }}</th>
                            <th class="py-1 text-right">{{ ctrans("Received") }}</th>
                            <th class="py-1 text-right">{{ ctrans("Invoiced") }}</th>
                            <th class="py-1 text-right">{{ ctrans("Order amount") }}</th>
                            <th class="py-1 text-right">{{ ctrans("Invoice amount") }}</th>
                            <th class="py-1 text-right">{{ ctrans("Goods cost") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in invoice.reading.items" :key="item.id" class="border-t border-gray-100" :class="item.needs_check ? 'bg-orange-50' : ''">
                            <td class="py-1"><span class="font-medium">{{ item.code }}</span> {{ item.name }}</td>
                            <td class="py-1 text-right">{{ locale.number(item.quantity) }}</td>
                            <td class="py-1 text-right" :class="item.quantity_differs ? 'font-medium text-orange-600' : ''">
                                {{ item.invoice_quantity == null ? ctrans("not on invoice") : locale.number(item.invoice_quantity) }}
                            </td>
                            <td class="py-1 text-right">{{ money(item.expected_amount, invoice.reading.delivery_currency) }}</td>
                            <td class="py-1 text-right" :class="item.amount_differs ? 'font-medium text-orange-600' : ''">{{ money(item.invoice_amount, invoice.reading.delivery_currency) }}</td>
                            <td class="py-1 text-right">
                                <input v-model="drafts[draftKey(invoice)].costs[item.id]" type="number" min="0" step="0.01" class="h-7 w-28 rounded border-gray-300 text-right text-xs" />
                                <div v-if="proposedCost(invoice, item) !== Number(drafts[draftKey(invoice)].costs[item.id] || 0)" class="text-gray-500">
                                    = {{ money(proposedCost(invoice, item), invoice.reading.delivery_currency) }}
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="invoice.reading.unmatched?.length">
                    <div class="mb-1 font-medium text-orange-600">{{ ctrans("Invoice lines not matched to a product of this delivery") }}</div>
                    <div v-for="(line, index) in invoice.reading.unmatched" :key="index" class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-medium">{{ line.code ?? "—" }}</span>
                        <span>{{ line.description }}</span>
                        <span>{{ line.quantity == null ? "" : "× " + locale.number(line.quantity) }}</span>
                        <span class="font-medium">{{ money(line.amount, invoice.reading.delivery_currency) }}</span>
                        <select v-if="line.amount != null" v-model="drafts[draftKey(invoice)].assignments[index]" class="h-7 rounded border-gray-300 py-0 text-xs">
                            <option :value="null">{{ ctrans("Leave out") }}</option>
                            <option v-for="item in invoice.reading.items" :key="item.id" :value="item.id">{{ ctrans("Add to") }} {{ item.code }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="invoice.reading.charges?.length">
                    <div class="mb-1 font-medium">{{ ctrans("Other charges on the invoice") }}</div>
                    <label v-for="(charge, index) in invoice.reading.charges" :key="index" class="flex items-center gap-2 text-xs">
                        <input v-model="drafts[draftKey(invoice)].charges[index]" type="checkbox" :disabled="charge.amount <= 0" />
                        <span>{{ charge.label }}</span>
                        <span class="font-medium">{{ money(charge.amount, invoice.reading.delivery_currency) }}</span>
                        <span v-if="charge.amount > 0" class="text-gray-400">{{ ctrans("add as extra expense") }}</span>
                        <span v-else class="text-gray-400">{{ ctrans("discounts go into the goods cost of the lines") }}</span>
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <Button v-if="canEdit" :label="ctrans('Apply to costing')" type="primary" size="xs"
                        :disabled="!invoice.reading.can_apply || invoice.reading.currency_mismatch || !drafts[draftKey(invoice)].invoice_date || drafts[draftKey(invoice)].invoice_total === ''"
                        :loading="busy === invoice.media_id" @click="apply(invoice)" />
                    <span v-if="!invoice.reading.can_apply" class="text-xs text-gray-500">{{ ctrans("Costs can be applied once the costing has started and until it is done.") }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
