<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed } from "vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type Item = { code: string | null; name: string | null; quantity: number; ordered_quantity?: number; expected_amount: number; invoice_quantity: number | null; invoice_amount: number | null }
type Line = { code: string | null; description: string | null; amount: number | null }
type Reason = { text: string; amount: number | null }

const props = defineProps<{
    reading: {
        total?: number | null
        currency?: string | null
        currency_mismatch?: boolean
        charges_total?: number
        items?: Item[]
        unmatched?: Line[]
        not_in_delivery?: { label: string; amount: number | null }[]
    }
}>()

const TOLERANCE = 0.01
const locale = useLocaleStore()
const money = (amount: number) => (props.reading.currency ? locale.currencyFormat(props.reading.currency, amount) : locale.number(amount))
const signed = (amount: number) => `${amount > 0 ? "+" : amount < 0 ? "−" : ""}${money(Math.abs(amount))}`
const round = (amount: number) => Math.round(amount * 100) / 100
const sum = (values: (number | null | undefined)[]) => round(values.reduce<number>((total, value) => total + (value ?? 0), 0))
const label = (code: string | null, name: string | null) => [code, name].map((part) => part?.trim()).filter(Boolean).join(" ")

const explanation = computed(() => {
    const { reading } = props
    if (reading.currency_mismatch || !reading.items || reading.total == null) {
        return null
    }

    const items = reading.items
    const isMissing = (item: Item) => item.invoice_amount == null
    const quantityDifference = (item: Item) => (item.invoice_quantity ?? 0) - (item.ordered_quantity ?? item.quantity)
    const amountDifference = (item: Item) => (item.invoice_amount ?? 0) - item.expected_amount
    const codes = (rows: Item[]) => rows.map((item) => item.code).join(", ")

    const invoiced = items.filter((item) => !isMissing(item))
    const more = invoiced.filter((item) => quantityDifference(item) > TOLERANCE)
    const fewer = invoiced.filter((item) => quantityDifference(item) < -TOLERANCE)
    const repriced = invoiced.filter((item) => Math.abs(quantityDifference(item)) <= TOLERANCE && Math.abs(amountDifference(item)) > TOLERANCE)
    const missing = items.filter(isMissing)
    const unmatched = reading.unmatched ?? []
    const orderTotal = sum(items.map((item) => item.expected_amount))
    const difference = round(reading.total - orderTotal)
    const missingAmount = sum(missing.map((item) => item.expected_amount))
    const unmatchedAmount = sum(unmatched.map((line) => line.amount))

    const reasons: Reason[] = []
    if (more.length) {
        reasons.push({ text: ctrans(":count products invoiced in larger quantities than ordered (:codes)", { count: String(more.length), codes: codes(more) }), amount: sum(more.map(amountDifference)) })
    }
    if (fewer.length) {
        reasons.push({ text: ctrans(":count products invoiced in smaller quantities than ordered (:codes)", { count: String(fewer.length), codes: codes(fewer) }), amount: sum(fewer.map(amountDifference)) })
    }
    if (repriced.length) {
        reasons.push({ text: ctrans(":count products at a different price than ordered (:codes)", { count: String(repriced.length), codes: codes(repriced) }), amount: sum(repriced.map(amountDifference)) })
    }
    if (missing.length && unmatched.length) {
        reasons.push({
            text: ctrans("aiku could not match :lines invoice lines (:invoiceLines) to the products :missing, probably the same products under other codes. Say where each invoice line goes to see the real difference.", {
                lines: String(unmatched.length),
                invoiceLines: unmatched.map((line) => label(line.code, line.description)).join(", "),
                missing: missing.map((item) => label(item.code, item.name)).join(", "),
            }),
            amount: Math.abs(unmatchedAmount - missingAmount) > TOLERANCE ? round(unmatchedAmount - missingAmount) : null,
        })
    } else if (missing.length) {
        reasons.push({ text: ctrans(":count products of the delivery are not on the invoice, so the supplier did not send or did not charge them (:codes)", { count: String(missing.length), codes: codes(missing) }), amount: -missingAmount })
    } else if (unmatched.length) {
        reasons.push({ text: ctrans("Invoice lines not matched to a product yet (:lines)", { lines: unmatched.map((line) => label(line.code, line.description)).join(", ") }), amount: unmatchedAmount })
    }
    if ((reading.not_in_delivery ?? []).length) {
        reasons.push({ text: ctrans("Invoiced but not in the delivery"), amount: sum((reading.not_in_delivery ?? []).map((line) => line.amount)) })
    }
    if (Math.abs(reading.charges_total ?? 0) > TOLERANCE) {
        reasons.push({ text: ctrans("Supplier charges on the invoice"), amount: reading.charges_total ?? 0 })
    }

    const other = round(difference - sum(reasons.map((reason) => reason.amount)))
    if (Math.abs(other) > TOLERANCE) {
        reasons.push({ text: ctrans("Other differences, such as rounding on the invoice"), amount: other })
    }

    return { orderTotal, difference, reasons }
})
</script>

<template>
    <div v-if="explanation" class="flex gap-2 rounded-lg border border-pink-200 bg-pink-50/60 px-3 py-2 text-pink-950">
        <span class="aura-tag h-fit shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold text-white shadow-sm">Aura</span>
        <div class="min-w-0 flex-1">
        <p v-if="!explanation.reasons.length">
            {{ ctrans("The invoice matches the delivery: :amount.", { amount: money(reading.total!) }) }}
        </p>
        <template v-else>
            <p v-if="Math.abs(explanation.difference) <= TOLERANCE">
                {{ ctrans("The invoice total is the same as ordered, :amount, but its lines differ from the delivery. What you owe is the invoice:", { amount: money(reading.total!) }) }}
            </p>
            <p v-else>
                {{ ctrans("The invoice is :difference :direction than the delivery as ordered (:order ordered, :invoice invoiced). What you owe is the invoice: the supplier charges what they sent, the order is only what we asked for. The difference comes from:", {
                    difference: money(Math.abs(explanation.difference)),
                    direction: explanation.difference > 0 ? ctrans("more") : ctrans("less"),
                    order: money(explanation.orderTotal),
                    invoice: money(reading.total!),
                }) }}
            </p>
            <ul class="mt-1 space-y-0.5">
                <li v-for="reason in explanation.reasons" :key="reason.text" class="flex justify-between gap-4">
                    <span>{{ reason.text }}</span>
                    <span v-if="reason.amount !== null" class="whitespace-nowrap tabular-nums">{{ signed(reason.amount) }}</span>
                </li>
            </ul>
        </template>
        </div>
    </div>
</template>

<style scoped>
.aura-tag {
    background: linear-gradient(110deg, #db2777, #c026d3, #a855f7, #ec4899);
}
</style>
