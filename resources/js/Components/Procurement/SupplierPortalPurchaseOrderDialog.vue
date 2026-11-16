<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-06 14:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck, faPencil, faTimes, faLock, faExclamationCircle, faReceipt, faDownload } from "@fal"
import { faCheckCircle } from "@fas"

library.add(faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck, faPencil, faTimes, faLock, faExclamationCircle, faReceipt, faDownload, faCheckCircle)

type SegmentState = "done" | "skipped" | "untracked" | "overdue" | "at_risk" | "on_track" | "future"

interface Payment {
	date: string
	amount: number
	original_amount: number | null
	original_currency_code: string | null
	reference: string | null
	paid_by: string | null
	receipt: { file_name: string | null; view_url: string; download_url: string } | null
}

interface PurchaseOrderDetail {
	order: {
		id: number
		reference: string
		organisation_name: string
		agent_name: string | null
		state: string
		state_label: string
		delivery_label: string | null
		open: boolean
		submitted_at: string | null
		confirmed_at: string | null
		estimated_received_at: string | null
		settled_at: string | null
		currency_code: string
		total: number
		terms: { label: string; value: string }[]
		labels: Record<"carton" | "sko" | "unit", string> | null
	}
	journey: {
		stages: { key: string; label: string; description: string }[]
		ribbon: {
			current_stage: string | null
			current_label: string | null
			status: string
			days_overdue: number
			eta: string | null
			segments: { key: string; state: SegmentState; done_at: string | null; planned_at: string; days_overdue: number }[]
		}
	} | null
	payments: {
		currency_code: string
		total: number
		paid: number
		left: number
		deposit_paid_at: string | null
		balance_paid_at: string | null
		deposit: Payment[]
		balance: Payment[]
	}
	production: {
		started_at: string | null
		expected_finish_at: string | null
		editable: boolean
		blocked: string | null
	}
	lines: {
		id: number
		code: string | null
		our_code: string | null
		name: string | null
		image: object | null
		units_per_carton: number | null
		cartons: number | null
		quantity: number
		unit: string | null
		unit_price: number
		amount: number
		currency_code: string
		cancelled: boolean
	}[]
}

const props = defineProps<{
	purchaseOrderRoute: string
	productionRoute?: string
}>()

const purchaseOrderId = defineModel<number | null>({ required: true })

const locale = useLocaleStore()

const detail = ref<PurchaseOrderDetail | null>(null)
const isLoading = ref(false)
const loadError = ref<string | null>(null)

const isVisible = computed({
	get: () => purchaseOrderId.value !== null,
	set: (visible: boolean) => {
		if (!visible) {
			purchaseOrderId.value = null
		}
	},
})

watch(purchaseOrderId, async (id) => {
	detail.value = null
	loadError.value = null
	if (id === null) {
		return
	}

	isLoading.value = true
	try {
		const { data } = await axios.get<PurchaseOrderDetail>(props.purchaseOrderRoute.replace(/\/0$/, `/${id}`))
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
	} catch {
		loadError.value = ctrans("This order could not be opened. Try again.")
	} finally {
		isLoading.value = false
	}
})

const stageIcons: Record<string, string> = {
	submitted: "fal fa-rocket",
	deposit_paid: "fal fa-coins",
	in_production: "fal fa-industry-alt",
	ready: "fal fa-clipboard-check",
	handed_over: "fal fa-box",
}

const segmentsByKey = computed(() => Object.fromEntries((detail.value?.journey?.ribbon.segments ?? []).map((segment) => [segment.key, segment])))

const isStageComplete = (key: string) => ["done", "skipped"].includes(segmentsByKey.value[key]?.state ?? "")
const isStageCurrent = (key: string) => detail.value?.journey?.ribbon.current_stage === key

const fromIsoDate = (iso: string): Date => {
	const [year, month, dayOfMonth] = iso.slice(0, 10).split("-").map(Number)
	return new Date(year, month - 1, dayOfMonth)
}
const toLocalIsoDate = (date: Date): string => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`

const isEditingProduction = ref(false)
const productionStartedAt = ref<Date | null>(null)
const productionFinishAt = ref<Date | null>(null)
const isSavingProduction = ref(false)
const productionError = ref<string | null>(null)

const openProductionEditor = () => {
	const production = detail.value?.production
	productionStartedAt.value = production?.started_at ? fromIsoDate(production.started_at) : null
	productionFinishAt.value = production?.expected_finish_at ? fromIsoDate(production.expected_finish_at) : null
	productionError.value = null
	isEditingProduction.value = true
}

const saveProduction = async () => {
	const id = purchaseOrderId.value
	if (!props.productionRoute || id === null) {
		return
	}

	isSavingProduction.value = true
	productionError.value = null
	try {
		const { data } = await axios.post<PurchaseOrderDetail>(props.productionRoute.replace(/\/0\/production$/, `/${id}/production`), {
			production_started_at: productionStartedAt.value ? toLocalIsoDate(productionStartedAt.value) : null,
			estimated_production_date: productionFinishAt.value ? toLocalIsoDate(productionFinishAt.value) : null,
		})
		if (purchaseOrderId.value === id) {
			detail.value = data
		}
		isEditingProduction.value = false
	} catch (error: any) {
		const errors = error?.response?.data?.errors as Record<string, string[]> | undefined
		productionError.value = (errors && Object.values(errors)[0]?.[0]) ?? ctrans("The dates could not be saved. Try again.")
	} finally {
		isSavingProduction.value = false
	}
}

const tomorrow = computed(() => {
	const date = new Date()
	date.setDate(date.getDate() + 1)
	return date
})

const facts = computed(() => {
	const order = detail.value?.order
	if (!order) {
		return []
	}

	return [
		{ label: ctrans("Ordered by"), value: order.organisation_name },
		...(order.agent_name ? [{ label: ctrans("Agent"), value: order.agent_name }] : []),
		{ label: ctrans("Sent"), value: day(order.submitted_at) },
		...(order.confirmed_at ? [{ label: ctrans("Agreed"), value: day(order.confirmed_at) }] : []),
		...(order.estimated_received_at ? [{ label: ctrans("Expected"), value: day(order.estimated_received_at) }] : []),
		...(order.settled_at ? [{ label: ctrans("Received"), value: day(order.settled_at) }] : []),
		...order.terms,
	]
})

const day = (date: string | null) => (date ? useFormatTime(date) : "—")
const quantity = (value: number | null) => (value === null ? "" : String(locale.number(value)))
const money = (amount: number) => locale.currencyFormat(detail.value?.payments.currency_code ?? "", amount)
const stateClass = (order: PurchaseOrderDetail["order"]) => (order.open ? "text-amber-700" : order.state === "settled" ? "text-green-700" : "text-gray-500")
</script>

<template>
	<div>
	<Dialog
		v-model:visible="isVisible"
		modal
		dismissableMask
		:header="detail?.order.reference ?? ctrans('Purchase order')"
		:style="{ width: '72rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div v-if="isLoading" class="flex justify-center py-16"><LoadingIcon /></div>
		<p v-else-if="loadError" class="py-6 text-center text-xs text-red-600">{{ loadError }}</p>

		<div v-else-if="detail" class="space-y-5 text-sm">
			<div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
				<span class="whitespace-nowrap text-lg font-semibold text-gray-900">{{ detail.order.reference }}</span>
				<span class="font-semibold" :class="stateClass(detail.order)">{{ detail.order.state_label }}</span>
				<span v-if="detail.order.delivery_label" class="text-gray-400">· {{ detail.order.delivery_label }}</span>
				<span class="ml-auto whitespace-nowrap text-lg font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(detail.order.currency_code, detail.order.total) }}</span>
			</div>

			<dl class="grid grid-cols-2 gap-x-6 gap-y-2 sm:grid-cols-3 lg:grid-cols-4">
				<div v-for="fact in facts" :key="fact.label">
					<dt class="text-xs text-gray-400">{{ fact.label }}</dt>
					<dd class="text-gray-800">{{ fact.value }}</dd>
				</div>
			</dl>

			<section v-if="detail.journey" class="overflow-hidden rounded-lg border border-gray-200">
				<div class="border-b border-gray-200 bg-gray-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ ctrans("Stages") }}</div>
				<ol class="grid grid-cols-1 gap-px bg-gray-200 sm:grid-cols-2 lg:grid-cols-5">
					<li
						v-for="(stage, index) in detail.journey.stages"
						:key="stage.key"
						class="min-w-0 p-3"
						:class="isStageComplete(stage.key) ? 'bg-green-50' : 'bg-white'">
						<div class="flex items-center gap-2 font-medium" :class="isStageComplete(stage.key) || isStageCurrent(stage.key) ? 'text-gray-800' : 'text-gray-400'" v-tooltip="stage.description">
							<FontAwesomeIcon v-if="isStageComplete(stage.key)" icon="fas fa-check-circle" class="text-green-600" fixed-width aria-hidden="true" />
							<span v-else class="w-4 text-center tabular-nums">{{ index + 1 }}</span>
							<FontAwesomeIcon :icon="stageIcons[stage.key]" class="text-gray-400" fixed-width aria-hidden="true" />
							<span class="truncate">{{ stage.label }}</span>
						</div>
						<div class="text-xs italic text-gray-400">{{ stage.description }}</div>

						<div v-if="segmentsByKey[stage.key]" class="mt-1 tabular-nums">
							<template v-if="segmentsByKey[stage.key].state === 'done'">
								<span class="text-gray-700">{{ segmentsByKey[stage.key].done_at ? day(segmentsByKey[stage.key].done_at) : ctrans("Done") }}</span>
							</template>
							<span v-else-if="segmentsByKey[stage.key].state === 'skipped'" class="text-green-700">{{ ctrans("Done") }}</span>
							<span v-else-if="segmentsByKey[stage.key].state === 'untracked'" class="text-gray-400">-</span>
							<template v-else>
								<span class="text-gray-400">-</span>
								<div class="text-xs" :class="segmentsByKey[stage.key].state === 'overdue' ? 'text-red-600' : segmentsByKey[stage.key].state === 'at_risk' ? 'text-amber-700' : 'text-gray-500'">
									{{ ctrans("Expected :date", { date: day(segmentsByKey[stage.key].planned_at) }) }}
									<template v-if="segmentsByKey[stage.key].state === 'overdue'"> · {{ ctrans(":days days late", { days: String(segmentsByKey[stage.key].days_overdue) }) }}</template>
								</div>
							</template>
						</div>

						<div v-if="stage.key === 'deposit_paid'" class="mt-2 text-xs">
							<div class="mb-1 font-semibold text-gray-500">{{ ctrans("Deposit") }}</div>
							<ul v-if="detail.payments.deposit.length" class="space-y-1.5">
								<li v-for="(payment, paymentIndex) in detail.payments.deposit" :key="paymentIndex">
									<div class="flex justify-between gap-2">
										<span class="tabular-nums text-gray-700">{{ day(payment.date) }}</span>
										<span class="whitespace-nowrap font-medium tabular-nums text-gray-900">{{ money(payment.amount) }}</span>
									</div>
									<div v-if="payment.original_amount !== null && payment.original_currency_code" class="text-right tabular-nums text-gray-400">
										{{ locale.currencyFormat(payment.original_currency_code, payment.original_amount) }}
									</div>
									<div class="break-words text-gray-500">{{ payment.paid_by }}<template v-if="payment.reference"> · {{ payment.reference }}</template></div>
									<div v-if="payment.receipt" class="mt-0.5 flex items-center gap-3">
										<a :href="payment.receipt.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" v-tooltip="payment.receipt.file_name">
											<FontAwesomeIcon icon="fal fa-receipt" fixed-width aria-hidden="true" />
											<span class="truncate">{{ ctrans("Transfer receipt") }}</span>
										</a>
										<a :href="payment.receipt.download_url" class="text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
											<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
										</a>
									</div>
								</li>
							</ul>
							<p v-else class="text-gray-400">{{ ctrans("No deposit paid yet") }}</p>
						</div>

						<div v-else-if="stage.key === 'in_production'" class="mt-2 text-xs">
							<dl class="space-y-0.5">
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Production started") }}</dt>
									<dd class="tabular-nums" :class="detail.production.started_at ? 'text-gray-700' : 'text-gray-400'">{{ detail.production.started_at ? day(detail.production.started_at) : "-" }}</dd>
								</div>
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Expected finish") }}</dt>
									<dd class="tabular-nums" :class="detail.production.expected_finish_at ? 'text-gray-700' : 'text-gray-400'">{{ detail.production.expected_finish_at ? day(detail.production.expected_finish_at) : "-" }}</dd>
								</div>
							</dl>
							<div v-if="productionRoute && (detail.production.editable || detail.production.blocked)" class="mt-2">
								<span v-if="!detail.production.editable" class="block" v-tooltip="detail.production.blocked">
									<Button type="tertiary" size="xs" full disabled :label="ctrans('Set production dates')">
										<template #icon><FontAwesomeIcon icon="fal fa-lock" class="text-gray-400" fixed-width aria-hidden="true" /></template>
									</Button>
								</span>
								<Button v-else type="tertiary" size="xs" full :label="detail.production.started_at ? ctrans('Change production dates') : ctrans('Set production dates')" @click="openProductionEditor">
									<template #icon>
										<FontAwesomeIcon :icon="detail.production.started_at ? 'fal fa-pencil' : 'fal fa-check'" :class="detail.production.started_at ? 'text-gray-500' : 'text-green-600'" fixed-width aria-hidden="true" />
									</template>
								</Button>
							</div>
						</div>

						<div v-else-if="stage.key === 'ready'" class="mt-2 text-xs">
							<template v-if="detail.payments.balance.length">
								<div class="mb-1 font-semibold text-gray-500">{{ ctrans("Balance") }}</div>
								<ul class="space-y-1.5">
									<li v-for="(payment, paymentIndex) in detail.payments.balance" :key="paymentIndex">
										<div class="flex justify-between gap-2">
											<span class="tabular-nums text-gray-700">{{ day(payment.date) }}</span>
											<span class="whitespace-nowrap font-medium tabular-nums text-gray-900">{{ money(payment.amount) }}</span>
										</div>
										<div v-if="payment.original_amount !== null && payment.original_currency_code" class="text-right tabular-nums text-gray-400">
											{{ locale.currencyFormat(payment.original_currency_code, payment.original_amount) }}
										</div>
										<div class="break-words text-gray-500">{{ payment.paid_by }}<template v-if="payment.reference"> · {{ payment.reference }}</template></div>
										<div v-if="payment.receipt" class="mt-0.5 flex items-center gap-3">
											<a :href="payment.receipt.view_url" target="_blank" rel="noopener" class="flex min-w-0 items-center gap-1 text-[var(--app-accent)] hover:text-[var(--app-accent-strong)] hover:underline" v-tooltip="payment.receipt.file_name">
												<FontAwesomeIcon icon="fal fa-receipt" fixed-width aria-hidden="true" />
												<span class="truncate">{{ ctrans("Transfer receipt") }}</span>
											</a>
											<a :href="payment.receipt.download_url" class="text-gray-500 hover:text-[var(--app-accent)]" v-tooltip="ctrans('Download')" :aria-label="ctrans('Download')">
												<FontAwesomeIcon icon="fal fa-download" fixed-width aria-hidden="true" />
											</a>
										</div>
									</li>
								</ul>
							</template>
							<dl class="space-y-0.5" :class="detail.payments.balance.length ? 'mt-2 border-t border-gray-200 pt-1.5' : ''">
								<div class="flex justify-between gap-2">
									<dt class="text-gray-500">{{ ctrans("Paid") }}</dt>
									<dd class="whitespace-nowrap tabular-nums text-gray-700">{{ money(detail.payments.paid) }} / {{ money(detail.payments.total) }}</dd>
								</div>
								<div v-if="detail.payments.left > 0" class="flex justify-between gap-2">
									<dt class="text-amber-700">{{ ctrans("Left to pay") }}</dt>
									<dd class="whitespace-nowrap font-medium tabular-nums text-amber-700">{{ money(detail.payments.left) }}</dd>
								</div>
								<div v-else-if="detail.payments.total > 0" class="flex items-center gap-1 text-green-700">
									<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
									{{ ctrans("Paid in full") }}
								</div>
							</dl>
						</div>
					</li>
				</ol>
			</section>

			<section>
				<h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Products") }}</h3>
				<div class="overflow-x-auto rounded-lg border border-gray-200">
					<table class="w-full min-w-[48rem] text-sm">
						<thead class="whitespace-nowrap bg-gray-50 text-xs text-gray-500">
							<tr>
								<th class="w-8 px-2 py-2 text-left font-semibold">#</th>
								<th class="w-12 px-2 py-2" />
								<th class="px-2 py-2 text-left font-semibold">{{ ctrans("Code") }}</th>
								<th class="px-2 py-2 text-left font-semibold">{{ ctrans("Description") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Units / carton") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Cartons") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Units") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Unit price") }}</th>
								<th class="px-2 py-2 text-right font-semibold">{{ ctrans("Amount") }}</th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-100">
							<tr v-for="(line, index) in detail.lines" :key="line.id" :class="{ 'text-gray-400 line-through': line.cancelled }">
								<td class="px-2 py-2 tabular-nums text-gray-400">{{ index + 1 }}</td>
								<td class="px-2 py-1">
									<ZoomableImage v-if="line.image" :src="line.image" :alt="line.code" class="h-10 w-10" />
								</td>
								<td class="whitespace-nowrap px-2 py-2">
									<div class="font-semibold text-gray-900">{{ line.code }}</div>
									<div v-if="line.our_code" class="text-xs text-gray-400">{{ line.our_code }}</div>
								</td>
								<td class="px-2 py-2 text-gray-700">{{ line.name }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.units_per_carton) }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.cartons) }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">{{ quantity(line.quantity) }}<span v-if="line.unit" class="text-gray-400"> {{ line.unit }}</span></td>
								<td class="whitespace-nowrap px-2 py-2 text-right tabular-nums">
									{{ locale.currencyFormatUnitCost(line.currency_code, line.unit_price) }}<span v-if="line.unit" class="text-gray-400"> / {{ line.unit }}</span>
								</td>
								<td class="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums">{{ locale.currencyFormat(line.currency_code, line.amount) }}</td>
							</tr>
						</tbody>
						<tfoot class="border-t border-gray-200">
							<tr>
								<td colspan="8" class="px-2 py-2 text-right font-semibold text-gray-700">{{ ctrans("Total") }}</td>
								<td class="whitespace-nowrap px-2 py-2 text-right font-semibold tabular-nums text-gray-900">{{ locale.currencyFormat(detail.order.currency_code, detail.order.total) }}</td>
							</tr>
						</tfoot>
					</table>
				</div>
			</section>

			<section v-if="detail.order.labels" class="flex flex-wrap items-center gap-2">
				<span class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Labels") }}</span>
				<a v-for="(label, level) in { carton: ctrans('Cartons'), sko: ctrans('SKOs'), unit: ctrans('Units') }" :key="level" :href="detail.order.labels[level]" target="_blank">
					<Button type="tertiary" size="xs" :label="label">
						<template #icon><FontAwesomeIcon icon="fal fa-tag" fixed-width aria-hidden="true" /></template>
					</Button>
				</a>
			</section>
		</div>
	</Dialog>

	<Dialog v-model:visible="isEditingProduction" modal :header="ctrans('Production dates')" :style="{ width: '24rem', maxWidth: 'calc(100vw - 2rem)' }">
		<div class="space-y-3 text-sm">
			<label class="block">
				<span class="mb-1 block text-xs font-semibold text-gray-600">{{ ctrans("Production started") }}</span>
				<DatePicker v-model="productionStartedAt" :maxDate="tomorrow" dateFormat="d M yy" showIcon iconDisplay="input" showButtonBar fluid class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]" />
			</label>
			<label class="block">
				<span class="mb-1 block text-xs font-semibold text-gray-600">{{ ctrans("Expected finish") }}</span>
				<DatePicker v-model="productionFinishAt" :minDate="productionStartedAt ?? undefined" dateFormat="d M yy" showIcon iconDisplay="input" showButtonBar fluid class="[&.p-focus]:!border-[var(--app-accent)] [&_input:focus]:!border-[var(--app-accent)]" />
			</label>
			<p v-if="productionError" class="text-xs text-red-600">{{ productionError }}</p>
		</div>
		<template #footer>
			<div class="flex w-full gap-2">
				<Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isEditingProduction = false">
					<template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
				</Button>
				<Button type="primary" full :label="ctrans('Save')" :loading="isSavingProduction" @click="saveProduction">
					<template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
				</Button>
			</div>
		</template>
	</Dialog>
	</div>
</template>
