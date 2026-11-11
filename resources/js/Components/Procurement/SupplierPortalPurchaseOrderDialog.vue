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
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import { useLocaleStore } from "@/Stores/locale"
import { useFormatTime } from "@/Composables/useFormatTime"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck } from "@fal"

library.add(faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faTag, faCheck)

type SegmentState = "done" | "skipped" | "untracked" | "overdue" | "at_risk" | "on_track" | "future"

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

const stageClass: Record<SegmentState, string> = {
	done: "border-emerald-300 bg-emerald-50 text-emerald-800",
	skipped: "border-emerald-200 bg-emerald-50/50 text-emerald-700",
	untracked: "border-dashed border-gray-300 bg-white text-gray-400",
	overdue: "border-2 border-red-400 bg-red-50 text-red-800",
	at_risk: "border-2 border-amber-400 bg-amber-50 text-amber-800",
	on_track: "border-2 border-gray-700 bg-white text-gray-900",
	future: "border-gray-200 bg-gray-50 text-gray-500",
}

const segmentsByKey = computed(() => Object.fromEntries((detail.value?.journey?.ribbon.segments ?? []).map((segment) => [segment.key, segment])))

const facts = computed(() => {
	const order = detail.value?.order
	if (!order) {
		return []
	}

	return [
		{ label: ctrans("Ordered by"), value: order.organisation_name },
		...(order.agent_name ? [{ label: ctrans("Agent"), value: order.agent_name }] : []),
		{ label: ctrans("Sent"), value: day(order.submitted_at) },
		...(order.confirmed_at ? [{ label: ctrans("Accepted"), value: day(order.confirmed_at) }] : []),
		...(order.estimated_received_at ? [{ label: ctrans("Expected"), value: day(order.estimated_received_at) }] : []),
		...(order.settled_at ? [{ label: ctrans("Received"), value: day(order.settled_at) }] : []),
		...order.terms,
	]
})

const day = (date: string | null) => (date ? useFormatTime(date) : "—")
const quantity = (value: number | null) => (value === null ? "" : String(locale.number(value)))
const stateClass = (order: PurchaseOrderDetail["order"]) => (order.open ? "text-amber-700" : order.state === "settled" ? "text-green-700" : "text-gray-500")
</script>

<template>
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

			<section v-if="detail.journey">
				<h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ ctrans("Stages") }}</h3>
				<ol class="grid grid-cols-2 gap-2 sm:grid-cols-5">
					<li
						v-for="(stage, index) in detail.journey.stages"
						:key="stage.key"
						class="rounded-md border px-2.5 py-2"
						:class="segmentsByKey[stage.key] ? stageClass[segmentsByKey[stage.key].state] : stageClass.future"
						:title="stage.description">
						<div class="flex items-center gap-1.5 text-xs">
							<span class="font-semibold">{{ index + 1 }}</span>
							<FontAwesomeIcon :icon="stageIcons[stage.key]" fixed-width aria-hidden="true" />
							<span class="truncate font-semibold">{{ stage.label }}</span>
						</div>
						<div v-if="segmentsByKey[stage.key]" class="mt-1 text-xs">
							<template v-if="segmentsByKey[stage.key].state === 'done'">
								<FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" />
								{{ segmentsByKey[stage.key].done_at ? day(segmentsByKey[stage.key].done_at) : ctrans("Done") }}
							</template>
							<template v-else-if="segmentsByKey[stage.key].state === 'skipped'">{{ ctrans("Done") }}</template>
							<template v-else-if="segmentsByKey[stage.key].state === 'untracked'">—</template>
							<template v-else-if="segmentsByKey[stage.key].state === 'overdue'">
								{{ ctrans(":days d late", { days: String(segmentsByKey[stage.key].days_overdue) }) }}
							</template>
							<template v-else>{{ day(segmentsByKey[stage.key].planned_at) }} {{ ctrans("target") }}</template>
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
</template>
