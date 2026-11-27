<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Checkbox from "primevue/checkbox"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import type { Image as ImageProxy } from "@/types/Image"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle, faPaperPlane, faCheckCircle, faClock, faTimesCircle } from "@fal"

export interface OrderNowLine {
	id: number
	code: string | null
	name: string | null
	image: ImageProxy | null
	organisation: string
	bucket: "out" | "w1"
	quantity_units: number
	units_per_carton: number | null
	unit_cost: number
	value: number
}

export interface OrderNowProposal {
	id: number
	code: string | null
	name: string | null
	organisation: string
	quantity_units: number
	accepted_quantity_units: number | null
	value: number
	state: "pending" | "accepted" | "rejected"
	state_label: string
	reject_reason: string | null
	proposed_at: string | null
	decided_at: string | null
}

export interface OrderNow {
	currency: string | null
	lines: OrderNowLine[]
	proposals: OrderNowProposal[]
}

const props = defineProps<{
	data?: OrderNow
	submitUrl: string | null
	accent: { colour: string; tint: string }
}>()

const emit = defineEmits<{
	sent: []
}>()

const locale = useLocaleStore()
const current = ref<OrderNow | undefined>(props.data)
watch(() => props.data, (data) => data && (current.value = data))

const selected = ref<number[]>([])
const quantities = ref<Record<number, number | null>>({})
const note = ref("")
const isSending = ref(false)
const error = ref<string | null>(null)
const sentMessage = ref<string | null>(null)

const resetChoices = (lines: OrderNowLine[]) => {
	selected.value = lines.map((line) => line.id)
	quantities.value = Object.fromEntries(lines.map((line) => [line.id, line.quantity_units]))
}
watch(() => current.value?.lines, (lines) => lines && resetChoices(lines), { immediate: true })

const money = (amount: number) => (current.value?.currency ? String(locale.currencyFormat(current.value.currency, amount)) : locale.number(amount))

const bucketLabels = computed<Record<OrderNowLine["bucket"], string>>(() => ({
	out: ctrans("Out of stock"),
	w1: ctrans("Runs out before a new order can arrive"),
}))

const bucketClasses: Record<OrderNowLine["bucket"], string> = {
	out: "bg-red-50 text-red-700",
	w1: "bg-amber-50 text-amber-800",
}

const stateStyles: Record<OrderNowProposal["state"], { icon: object; class: string }> = {
	pending: { icon: faClock, class: "text-amber-600" },
	accepted: { icon: faCheckCircle, class: "text-green-600" },
	rejected: { icon: faTimesCircle, class: "text-red-600" },
}

const quantityOf = (line: OrderNowLine) => quantities.value[line.id] ?? 0
const cartonsOf = (line: OrderNowLine) => (line.units_per_carton && line.units_per_carton > 1 ? quantityOf(line) / line.units_per_carton : null)
const lineValue = (line: OrderNowLine) => Math.round(quantityOf(line) * line.unit_cost * 100) / 100

const chosenLines = computed(() => (current.value?.lines ?? []).filter((line) => selected.value.includes(line.id)))
const chosenValue = computed(() => chosenLines.value.reduce((sum, line) => sum + lineValue(line), 0))
const withoutQuantity = computed(() => chosenLines.value.find((line) => quantityOf(line) <= 0))

const allSelected = computed({
	get: () => !!current.value?.lines.length && selected.value.length === current.value.lines.length,
	set: (value: boolean) => (selected.value = value ? (current.value?.lines ?? []).map((line) => line.id) : []),
})

async function send() {
	if (!props.submitUrl || !chosenLines.value.length || withoutQuantity.value) {
		return
	}
	isSending.value = true
	error.value = null
	sentMessage.value = null
	try {
		const response = await axios.post(
			props.submitUrl,
			{ lines: chosenLines.value.map((line) => ({ id: line.id, quantity_units: quantityOf(line) })), note: note.value.trim() || null },
			{ headers: { Accept: "application/json" } },
		)
		current.value = response.data.order_now
		note.value = ""
		sentMessage.value = ctrans(":count lines sent. The buyer accepts each one onto the next purchase order, or tells you why not, below.", { count: String(response.data.proposed) })
		emit("sent")
	} catch (exception: any) {
		const fieldErrors = exception?.response?.data?.errors
		error.value = fieldErrors ? (Object.values(fieldErrors).flat()[0] as string) : exception?.response?.data?.message ?? ctrans("Something went wrong")
	} finally {
		isSending.value = false
	}
}
</script>

<template>
	<div class="space-y-4">
		<div class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm">
			<FontAwesomeIcon :icon="faInfoCircle" class="mt-0.5 shrink-0" :style="{ color: accent.colour }" fixed-width aria-hidden="true" />
			<div class="space-y-1">
				<p class="font-medium text-gray-800">{{ ctrans("Products our companies are out of, or will run out of before a new order can arrive.") }}</p>
				<p>{{ ctrans("The quantity is what we would order to last until the next delivery. Change it if you need to, tick the lines and send the proposal. A proposal is not an order: the buyer accepts it onto the next purchase order or tells you why not.") }}</p>
			</div>
		</div>

		<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<div v-if="!current" class="space-y-2 p-4">
				<div v-for="index in 4" :key="index" class="skeleton h-12 rounded" />
			</div>
			<p v-else-if="!current.lines.length" class="px-4 py-8 text-center text-sm text-gray-400">{{ ctrans("Nothing to propose right now: no product of yours is out of stock or about to run out before a new order can arrive.") }}</p>
			<template v-else>
				<div class="overflow-x-auto">
					<table class="min-w-full divide-y divide-gray-100 text-sm">
						<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
							<tr>
								<th class="w-10 px-4 py-2.5"><Checkbox v-model="allSelected" binary :aria-label="ctrans('Select all')" /></th>
								<th class="px-4 py-2.5">{{ ctrans("Product") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Company") }}</th>
								<th class="px-4 py-2.5">{{ ctrans("Stock") }}</th>
								<th class="px-4 py-2.5 text-right">{{ ctrans("Quantity (units)") }}</th>
								<th class="px-4 py-2.5 text-right">{{ ctrans("Value at your price") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="line in current.lines" :key="line.id" class="align-top" :class="selected.includes(line.id) ? '' : 'opacity-60'">
								<td class="px-4 py-3"><Checkbox v-model="selected" :value="line.id" :aria-label="line.code ?? ''" /></td>
								<td class="px-4 py-3">
									<div class="flex min-w-[14rem] items-start gap-3">
										<div class="h-10 w-10 shrink-0 overflow-hidden rounded border border-gray-100 bg-gray-50">
											<Image v-if="line.image" :src="line.image" class="h-full w-full object-cover" :alt="line.code ?? ''" />
										</div>
										<div class="min-w-0">
											<div class="whitespace-nowrap font-medium text-gray-900">{{ line.code }}</div>
											<div class="text-xs text-gray-500">{{ line.name }}</div>
										</div>
									</div>
								</td>
								<td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ line.organisation }}</td>
								<td class="px-4 py-3">
									<span class="inline-block whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="bucketClasses[line.bucket]">{{ bucketLabels[line.bucket] }}</span>
								</td>
								<td class="px-4 py-3 text-right">
									<InputNumber
										:modelValue="quantities[line.id]"
										:min="0"
										:maxFractionDigits="3"
										size="small"
										inputClass="w-28 text-right"
										@update:modelValue="(value: number | null) => (quantities[line.id] = value)"
										@input="(event: InputNumberInputEvent) => (quantities[line.id] = typeof event.value === 'number' ? event.value : null)"
									/>
									<div v-if="cartonsOf(line) !== null" class="mt-1 whitespace-nowrap text-xs text-gray-500">{{ ctrans(":cartons cartons of :per", { cartons: locale.number(cartonsOf(line) ?? 0), per: locale.number(line.units_per_carton ?? 0) }) }}</div>
									<div class="mt-0.5 whitespace-nowrap text-xs text-gray-400">{{ ctrans("we suggest :units", { units: locale.number(line.quantity_units) }) }}</div>
								</td>
								<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-800">{{ money(lineValue(line)) }}</td>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="space-y-2 border-t border-gray-100 p-4">
					<label class="flex flex-col gap-1">
						<span class="text-xs font-medium text-gray-600">{{ ctrans("Note for the buyer (optional)") }}</span>
						<Textarea v-model="note" rows="2" autoResize maxlength="2000" class="w-full" />
					</label>
					<div class="flex flex-wrap items-center gap-3">
						<Button type="primary" :label="ctrans('Send proposal')" :loading="isSending" :disabled="!submitUrl || !chosenLines.length || !!withoutQuantity" @click="send">
							<template #icon><FontAwesomeIcon :icon="faPaperPlane" fixed-width aria-hidden="true" /></template>
						</Button>
						<span class="text-sm text-gray-600">{{ ctrans(":count lines · :amount", { count: String(chosenLines.length), amount: money(chosenValue) }) }}</span>
					</div>
					<p v-if="!submitUrl" class="text-xs text-gray-500">{{ ctrans("Preview: the supplier sends the proposal from its own page.") }}</p>
					<p v-if="withoutQuantity" class="text-xs text-red-600">{{ ctrans(":code: enter a quantity, or untick the line", { code: withoutQuantity.code ?? "" }) }}</p>
					<p v-if="error" class="text-xs text-red-600">{{ error }}</p>
					<p v-if="sentMessage" class="text-xs text-green-700">{{ sentMessage }}</p>
				</div>
			</template>
		</section>

		<hr class="border-t border-gray-300 border-dashed pb-4 !mt-8" />

		<section v-if="current?.proposals.length" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
			<h2 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-700">{{ ctrans("Your proposals") }}</h2>
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-100 text-sm">
					<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
						<tr>
							<th class="px-4 py-2.5">{{ ctrans("Sent") }}</th>
							<th class="px-4 py-2.5">{{ ctrans("Product") }}</th>
							<th class="px-4 py-2.5">{{ ctrans("Company") }}</th>
							<th class="px-4 py-2.5 text-right">{{ ctrans("Quantity (units)") }}</th>
							<th class="px-4 py-2.5 text-right">{{ ctrans("Value at your price") }}</th>
							<th class="px-4 py-2.5">{{ ctrans("Answer") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100">
						<tr v-for="proposal in current.proposals" :key="proposal.id" class="align-top">
							<td class="whitespace-nowrap px-4 py-3 text-gray-500">{{ proposal.proposed_at ? useFormatTime(proposal.proposed_at) : "" }}</td>
							<td class="px-4 py-3">
								<div class="whitespace-nowrap font-medium text-gray-900">{{ proposal.code }}</div>
								<div class="text-xs text-gray-500">{{ proposal.name }}</div>
							</td>
							<td class="whitespace-nowrap px-4 py-3 text-gray-700">{{ proposal.organisation }}</td>
							<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">
								<template v-if="proposal.accepted_quantity_units !== null && proposal.accepted_quantity_units !== proposal.quantity_units">
									<s class="text-gray-400">{{ locale.number(proposal.quantity_units) }}</s>
									<span class="ml-1.5 font-semibold text-gray-900">{{ locale.number(proposal.accepted_quantity_units) }}</span>
									<div class="text-xs font-normal text-gray-500">{{ ctrans("changed by the buyer") }}</div>
								</template>
								<template v-else>{{ locale.number(proposal.quantity_units) }}</template>
							</td>
							<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-700">{{ money(proposal.value) }}</td>
							<td class="px-4 py-3">
								<div class="flex items-center gap-1.5 whitespace-nowrap font-medium" :class="stateStyles[proposal.state].class">
									<FontAwesomeIcon :icon="stateStyles[proposal.state].icon" fixed-width aria-hidden="true" />
									{{ proposal.state_label }}
								</div>
								<p v-if="proposal.reject_reason" class="mt-1 max-w-sm whitespace-pre-line text-xs text-gray-600">{{ proposal.reject_reason }}</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</template>
