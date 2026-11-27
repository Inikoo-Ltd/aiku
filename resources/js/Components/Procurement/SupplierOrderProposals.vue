<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import { router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import Dialog from "primevue/dialog"
import InputNumber, { type InputNumberInputEvent } from "primevue/inputnumber"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import type { Image as ImageProxy } from "@/types/Image"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faExclamationTriangle, faCartPlus } from "@fal"

export interface SupplierOrderProposalLine {
	id: number
	code: string | null
	name: string | null
	image: ImageProxy | null
	supplier: string
	currency: string | null
	bucket: string | null
	bucket_label: string | null
	suggested_quantity_units: number
	quantity_units: number
	units_per_carton: number | null
	unit_cost: number
	supplier_note: string | null
	proposed_by: string | null
	proposed_at: string | null
}

export interface SupplierOrderProposalsData {
	lines: SupplierOrderProposalLine[]
	accept_url: string
	reject_url: string
}

const props = defineProps<{
	data: SupplierOrderProposalsData
	canDecide: boolean
	showSupplier?: boolean
}>()

const locale = useLocaleStore()

const selected = ref<number[]>([])
const decidedIds = ref<number[]>([])
const lines = computed(() => props.data.lines.filter((line) => !decidedIds.value.includes(line.id)))
const quantities = ref<Record<number, number | null>>({})
watch(
	() => props.data.lines,
	(lines) => {
		decidedIds.value = decidedIds.value.filter((id) => lines.some((line) => line.id === id))
		selected.value = selected.value.filter((id) => lines.some((line) => line.id === id))
		quantities.value = Object.fromEntries(lines.map((line) => [line.id, quantities.value[line.id] ?? line.quantity_units]))
	},
	{ immediate: true },
)

const allSelected = computed({
	get: () => !!lines.value.length && selected.value.length === lines.value.length,
	set: (value: boolean) => (selected.value = value ? lines.value.map((line) => line.id) : []),
})

const quantityOf = (line: SupplierOrderProposalLine) => quantities.value[line.id] ?? 0
const money = (line: SupplierOrderProposalLine, amount: number) => (line.currency ? String(locale.currencyFormat(line.currency, amount)) : locale.number(amount))
const bucketClass = (bucket: string | null) => (bucket === "out" ? "bg-red-50 text-red-700" : "bg-amber-50 text-amber-800")

const isAccepting = ref(false)
const acceptError = ref<string | null>(null)
const acceptResult = ref<{ accepted: number; purchase_orders: { reference: string; url: string }[]; skipped: { code: string | null; reason: string }[] } | null>(null)

const reload = () => router.reload({ only: ["supplierOrderProposals"] })

const errorOf = (exception: any) => {
	const fieldErrors = exception?.response?.data?.errors
	return fieldErrors ? (Object.values(fieldErrors).flat()[0] as string) : exception?.response?.data?.message ?? ctrans("Something went wrong")
}

async function accept() {
	const chosen = lines.value.filter((line) => selected.value.includes(line.id))
	if (!chosen.length) {
		return
	}
	isAccepting.value = true
	acceptError.value = null
	acceptResult.value = null
	try {
		const response = await axios.post(
			props.data.accept_url,
			{ lines: chosen.map((line) => ({ id: line.id, quantity_units: quantityOf(line) })) },
			{ headers: { Accept: "application/json" } },
		)
		acceptResult.value = response.data
		decidedIds.value = [...decidedIds.value, ...chosen.map((line) => line.id)]
		selected.value = []
		reload()
	} catch (exception) {
		acceptError.value = errorOf(exception)
	} finally {
		isAccepting.value = false
	}
}

const rejectOpen = ref(false)
const rejectReason = ref("")
const isRejecting = ref(false)
const rejectError = ref<string | null>(null)

const openReject = () => {
	rejectReason.value = ""
	rejectError.value = null
	rejectOpen.value = true
}

async function reject() {
	isRejecting.value = true
	rejectError.value = null
	try {
		await axios.post(props.data.reject_url, { ids: selected.value, reason: rejectReason.value.trim() }, { headers: { Accept: "application/json" } })
		rejectOpen.value = false
		decidedIds.value = [...decidedIds.value, ...selected.value]
		selected.value = []
		acceptResult.value = null
		reload()
	} catch (exception) {
		rejectError.value = errorOf(exception)
	} finally {
		isRejecting.value = false
	}
}
</script>

<template>
	<section v-if="lines.length || acceptResult" class="rounded-lg border border-gray-200 bg-white">
		<div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-3">
			<div>
				<h2 class="flex items-center gap-2 text-sm font-semibold text-gray-800">
					<FontAwesomeIcon :icon="faCartPlus" class="text-gray-400" fixed-width aria-hidden="true" />
					{{ ctrans("Proposed by the supplier") }}
					<span class="rounded-full bg-[var(--app-accent-soft)] px-2 py-0.5 text-xs font-medium text-[var(--app-accent-strong)]">{{ lines.length }}</span>
				</h2>
				<p class="mt-0.5 text-xs text-gray-500">
					{{ ctrans("Sent from the supplier portal for products we are out of, or will run out of before a new order can arrive. Accept puts the lines on the draft purchase order; a product already on it keeps the larger quantity. Lines you leave keep waiting here.") }}
				</p>
			</div>
			<div v-if="canDecide" class="flex shrink-0 items-center gap-2">
				<Button type="tertiary" :label="ctrans('Reject')" :disabled="!selected.length" @click="openReject">
					<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="tertiary" :label="ctrans('Accept onto draft order')" :loading="isAccepting" :disabled="!selected.length" @click="accept">
					<template #icon><FontAwesomeIcon :icon="faCheck" class="text-green-600" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</div>

		<div v-if="acceptError || acceptResult" class="space-y-1 border-b border-gray-100 px-4 py-2 text-xs">
			<p v-if="acceptError" class="text-red-600">{{ acceptError }}</p>
			<p v-if="acceptResult?.accepted" class="text-green-700">
				{{ ctrans(":count lines accepted onto", { count: String(acceptResult.accepted) }) }}
				<template v-for="(purchaseOrder, index) in acceptResult.purchase_orders" :key="purchaseOrder.reference">
					<span v-if="index">, </span>
					<a :href="purchaseOrder.url" class="whitespace-nowrap font-medium text-[var(--app-accent)] hover:underline">{{ purchaseOrder.reference }}</a>
				</template>
			</p>
			<p v-for="skip in acceptResult?.skipped ?? []" :key="`${skip.code}-${skip.reason}`" class="text-red-600">{{ skip.code }}: {{ skip.reason }}</p>
		</div>

		<p v-if="!lines.length" class="px-4 py-6 text-center text-sm text-gray-400">{{ ctrans("No line from the supplier waits for you") }}</p>
		<div v-else class="overflow-x-auto">
			<table class="min-w-full divide-y divide-gray-100 text-sm">
				<thead class="whitespace-nowrap bg-gray-50 text-left text-xs font-medium text-gray-500">
					<tr>
						<th v-if="canDecide" class="w-10 px-4 py-2"><Checkbox v-model="allSelected" binary :aria-label="ctrans('Select all')" /></th>
						<th class="px-4 py-2">{{ ctrans("Product") }}</th>
						<th v-if="showSupplier" class="px-4 py-2">{{ ctrans("Supplier") }}</th>
						<th class="px-4 py-2">{{ ctrans("Stock when proposed") }}</th>
						<th class="px-4 py-2 text-right">{{ ctrans("Suggested") }}</th>
						<th class="px-4 py-2 text-right">{{ ctrans("Quantity (units)") }}</th>
						<th class="px-4 py-2 text-right">{{ ctrans("Value") }}</th>
						<th class="px-4 py-2">{{ ctrans("Proposed") }}</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-gray-100">
					<tr v-for="line in lines" :key="line.id" class="align-top">
						<td v-if="canDecide" class="px-4 py-3"><Checkbox v-model="selected" :value="line.id" :aria-label="line.code ?? ''" /></td>
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
						<td v-if="showSupplier" class="whitespace-nowrap px-4 py-3 text-gray-700">{{ line.supplier }}</td>
						<td class="px-4 py-3">
							<span v-if="line.bucket_label" class="inline-block whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-medium" :class="bucketClass(line.bucket)">{{ line.bucket_label }}</span>
						</td>
						<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-500">{{ locale.number(line.suggested_quantity_units) }}</td>
						<td class="px-4 py-3 text-right">
							<InputNumber
								v-if="canDecide"
								:modelValue="quantities[line.id]"
								:min="0"
								:maxFractionDigits="3"
								size="small"
								inputClass="w-28 text-right"
								@update:modelValue="(value: number | null) => (quantities[line.id] = value)"
								@input="(event: InputNumberInputEvent) => (quantities[line.id] = typeof event.value === 'number' ? event.value : null)"
							/>
							<span v-else class="tabular-nums text-gray-800">{{ locale.number(line.quantity_units) }}</span>
							<div v-if="line.units_per_carton && line.units_per_carton > 1" class="mt-1 whitespace-nowrap text-xs text-gray-500">
								{{ ctrans(":cartons cartons of :per", { cartons: locale.number(quantityOf(line) / line.units_per_carton), per: locale.number(line.units_per_carton) }) }}
							</div>
							<div v-if="quantityOf(line) !== line.quantity_units" class="mt-0.5 whitespace-nowrap text-xs text-gray-400">{{ ctrans("supplier proposed :units", { units: locale.number(line.quantity_units) }) }}</div>
						</td>
						<td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-gray-800">{{ money(line, quantityOf(line) * line.unit_cost) }}</td>
						<td class="px-4 py-3 text-xs text-gray-500">
							<div class="whitespace-nowrap">{{ line.proposed_at ? useFormatTime(line.proposed_at) : "" }}<template v-if="line.proposed_by"> · {{ line.proposed_by }}</template></div>
							<p v-if="line.supplier_note" class="mt-1 max-w-xs whitespace-pre-line text-gray-700">{{ line.supplier_note }}</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<Dialog v-model:visible="rejectOpen" modal :header="ctrans('Reject the chosen lines (:count)', { count: String(selected.length) })" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
			<label class="flex flex-col gap-1 text-sm">
				<span class="text-xs font-medium text-gray-600">{{ ctrans("Why, for the supplier") }} <span class="text-red-500">*</span></span>
				<Textarea v-model="rejectReason" rows="3" autoResize maxlength="2000" class="w-full" />
				<span class="text-xs text-gray-500">{{ ctrans("The supplier reads this on its page, and is not offered these products again for 14 days.") }}</span>
			</label>
			<p v-if="rejectError" class="mt-2 text-xs text-red-600">{{ rejectError }}</p>
			<template #footer>
				<div class="flex w-full gap-2">
					<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="rejectOpen = false">
						<template #icon><FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" /></template>
					</Button>
					<Button type="tertiary" full :label="ctrans('Reject')" :loading="isRejecting" :disabled="!rejectReason.trim()" @click="reject">
						<template #icon><FontAwesomeIcon :icon="faExclamationTriangle" class="text-amber-500" fixed-width aria-hidden="true" /></template>
					</Button>
				</div>
			</template>
		</Dialog>
	</section>
</template>
