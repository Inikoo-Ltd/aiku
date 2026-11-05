<!--
    * Author: Vika Aqordi
    * Created on: 2026-10-16 10:05
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Checkbox from "primevue/checkbox"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInboxIn, faClipboardCheck, faChevronDown, faChevronRight, faImage, faBoxCheck, faFilePdf, faPencil } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ZoomableImage from "@/Components/Utils/ZoomableImage.vue"
import AgentReceiveGoodsButton from "@/Components/Procurement/AgentReceiveGoodsButton.vue"
import AgentGoodsReceiptItemCheckDialog, { type CheckableItem } from "@/Components/Procurement/AgentGoodsReceiptItemCheckDialog.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"
import { routeType } from "@/types/route"

type ScopeOption = { value: number; label: string; count: number }

type ReceiptItem = {
	id: number
	reference: string
	show_route: routeType
	received_at: string
	quantity: number
	quantity_rejected: number | null
	is_checked: boolean
	reject_reason: string | null
	received_by: string | null
	checked_by: string | null
	is_packed: boolean
	photos: { id: number; name: string; url: string }[]
	check_route: routeType | null
}

type Line = {
	id: number
	code: string | null
	name: string | null
	image_thumbnail: object | null
	image_full: { original_url: string; download_url: string } | null
	state: string
	units_per_pack: number
	ordered: number
	received: number
	rejected: number
	accepted: number
	outstanding: number
	unchecked: number
	items: ReceiptItem[]
}

const props = defineProps<{
	receiving: {
		can_edit: boolean
		check_many_route: routeType | null
		lines: Line[]
		receive: { scopes: { suppliers: ScopeOption[]; purchase_orders: ScopeOption[] }; purchase_order_id: number } | null
		goods_receipts: { reference: string; received_at: string; show_route: routeType; receipt_note_route: routeType }[]
	}
}>()

const locale = useLocaleStore()

const formatQuantity = (value: number | null) => locale.number(Math.round(Number(value ?? 0) * 1000) / 1000)
const skos = (line: Line, units: number | null) => formatQuantity(Number(units ?? 0) / (line.units_per_pack || 1))
const toReplace = (line: Line) => Math.min(line.outstanding, line.rejected)

const totals = computed(() => props.receiving.lines.reduce(
	(sum, line) => ({
		received: sum.received + line.received,
		rejected: sum.rejected + line.rejected,
		outstanding: sum.outstanding + line.outstanding,
		unchecked: sum.unchecked + line.unchecked,
	}),
	{ received: 0, rejected: 0, outstanding: 0, unchecked: 0 },
))

const isSectionOpen = ref(totals.value.unchecked > 0)

const checkingItem = ref<CheckableItem | null>(null)

const checkableItemIds = computed(() => props.receiving.lines.flatMap((line) =>
	line.items.filter((item) => item.check_route && !item.is_checked).map((item) => item.id)
))

const selectedItemIds = ref<number[]>([])

watch(checkableItemIds, (ids) => {
	selectedItemIds.value = selectedItemIds.value.filter((id) => ids.includes(id))
})

const isAllSelected = computed({
	get: () => checkableItemIds.value.length > 0 && checkableItemIds.value.every((id) => selectedItemIds.value.includes(id)),
	set: (selectAll: boolean) => {
		selectedItemIds.value = selectAll ? [...checkableItemIds.value] : []
	},
})

const isCheckingMany = ref(false)
const checkManyError = ref<string | null>(null)

function checkSelected() {
	const checkManyRoute = props.receiving.check_many_route
	if (!checkManyRoute || !selectedItemIds.value.length) {
		return
	}

	router.post(route(checkManyRoute.name, checkManyRoute.parameters), { items: selectedItemIds.value }, {
		preserveScroll: true,
		onStart: () => {
			isCheckingMany.value = true
			checkManyError.value = null
		},
		onSuccess: () => (selectedItemIds.value = []),
		onError: (errors) => (checkManyError.value = errors.items ?? Object.values(errors)[0] ?? ctrans("Could not check the goods")),
		onFinish: () => (isCheckingMany.value = false),
	})
}

function openCheck(line: Line, item: ReceiptItem) {
	checkingItem.value = { ...item, code: line.code, units_per_sko: line.units_per_pack }
}

function itemStatus(item: ReceiptItem) {
	if (!item.is_checked) {
		return { label: ctrans("Not checked yet"), class: "bg-amber-50 text-amber-700 ring-amber-200" }
	}

	if (Number(item.quantity_rejected) > 0) {
		return { label: ctrans(":units rejected", { units: formatQuantity(item.quantity_rejected) }), class: "bg-red-50 text-red-700 ring-red-200" }
	}

	return { label: ctrans("No rejects"), class: "bg-green-50 text-green-700 ring-green-200" }
}
</script>

<template>
	<section class="border-b border-gray-300 px-4 py-3">
		<div class="flex flex-wrap items-center justify-between gap-2">
			<div class="flex items-center gap-2">
				<Button
					type="tertiary"
					size="xs"
					:tooltip="isSectionOpen ? ctrans('Hide the received goods') : ctrans('Show the received goods')"
					:aria-expanded="isSectionOpen"
					@click="isSectionOpen = !isSectionOpen"
				>
					<template #icon>
						<FontAwesomeIcon :icon="isSectionOpen ? faChevronDown : faChevronRight" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<h3 class="flex cursor-pointer select-none items-center gap-2 font-semibold text-gray-800" @click="isSectionOpen = !isSectionOpen">
					<FontAwesomeIcon :icon="faInboxIn" class="text-[var(--app-accent)]" fixed-width aria-hidden="true" />
					{{ ctrans("Received at our warehouse") }}
					<span v-if="!isSectionOpen" class="text-xs font-normal text-gray-500">({{ ctrans(":count products", { count: receiving.lines.length }) }})</span>
				</h3>
			</div>
			<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600">
				<span>{{ ctrans("Received") }}: <span class="font-medium tabular-nums text-gray-800">{{ formatQuantity(totals.received) }}</span></span>
				<span>{{ ctrans("Rejected") }}: <span class="font-medium tabular-nums" :class="totals.rejected > 0 ? 'text-red-600' : 'text-gray-800'">{{ formatQuantity(totals.rejected) }}</span></span>
				<span>{{ ctrans("Still to come") }}: <span class="font-medium tabular-nums text-gray-800">{{ formatQuantity(totals.outstanding) }}</span></span>
				<span v-if="totals.unchecked" class="rounded-full bg-amber-50 px-2 text-amber-700 ring-1 ring-inset ring-amber-200">
					{{ ctrans(":count not checked yet", { count: totals.unchecked }) }}
				</span>
				<AgentReceiveGoodsButton v-if="receiving.receive" :scopes="receiving.receive.scopes" :purchaseOrderId="receiving.receive.purchase_order_id" size="xs" />
			</div>
		</div>
		<div v-show="isSectionOpen">
			<p class="mt-1 text-xs text-gray-500">
				{{ ctrans("Each delivery from the supplier is a goods receipt. Check its lines for rejects: rejected units go back to In production, the supplier still owes them. Quantities are in units.") }}
			</p>

			<div v-if="receiving.goods_receipts.length" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
				<span class="text-gray-500">{{ ctrans("Goods receipts") }}:</span>
				<span v-for="receipt in receiving.goods_receipts" :key="receipt.reference" class="inline-flex items-center gap-1 rounded border border-gray-200 px-1.5 py-0.5">
					<Link :href="route(receipt.show_route.name, receipt.show_route.parameters)" class="secondaryLink whitespace-nowrap">{{ receipt.reference }}</Link>
					<span class="text-gray-400">{{ receipt.received_at }}</span>
					<a :href="route(receipt.receipt_note_route.name, receipt.receipt_note_route.parameters)" target="_blank" rel="noopener" class="text-red-500 hover:text-red-700" v-tooltip="ctrans('Receipt note PDF for the supplier')">
						<FontAwesomeIcon :icon="faFilePdf" fixed-width aria-hidden="true" />
					</a>
				</span>
			</div>

			<div v-if="receiving.check_many_route && checkableItemIds.length" class="mt-3 flex flex-wrap items-center gap-3 text-sm">
				<label class="flex cursor-pointer items-center gap-2 text-gray-700">
					<Checkbox v-model="isAllSelected" binary size="small" />
					{{ ctrans("Select all :count not checked yet", { count: checkableItemIds.length }) }}
				</label>
				<Button
					type="tertiary"
					size="xs"
					:label="ctrans('Check selected: no rejects (:count)', { count: selectedItemIds.length })"
					:tooltip="ctrans('Mark the selected goods as checked with no rejects. Check goods with rejects one by one.')"
					:disabled="!selectedItemIds.length"
					:loading="isCheckingMany"
					@click="checkSelected"
				>
					<template #icon>
						<FontAwesomeIcon :icon="faClipboardCheck" class="text-green-600" fixed-width aria-hidden="true" />
					</template>
				</Button>
				<span v-if="checkManyError" class="text-xs text-red-600">{{ checkManyError }}</span>
			</div>

			<div class="mt-3 overflow-x-auto">
				<table class="min-w-full text-sm">
					<thead class="whitespace-nowrap">
						<tr class="border-b border-gray-200 text-left text-xs text-gray-500">
							<th class="w-6"></th>
							<th class="py-1.5 pr-3 font-normal">{{ ctrans("Product") }}</th>
							<th class="px-3 py-1.5 text-right font-normal">{{ ctrans("Ordered") }}</th>
							<th class="px-3 py-1.5 text-right font-normal">{{ ctrans("Received") }}</th>
							<th class="px-3 py-1.5 text-right font-normal">{{ ctrans("Rejected") }}</th>
							<th class="px-3 py-1.5 text-right font-normal">{{ ctrans("Still to come") }}</th>
							<th class="py-1.5 pl-3"></th>
						</tr>
					</thead>
					<tbody>
						<template v-for="line in receiving.lines" :key="line.id">
							<tr class="border-b border-gray-100" :class="{ 'opacity-50': line.state === 'cancelled' }">
								<td></td>
								<td class="py-2 pr-3">
									<div class="flex items-center gap-2">
										<ZoomableImage :src="line.image_thumbnail" :full="line.image_full" :alt="line.code" class="h-10 w-10" />
										<div>
											<div class="whitespace-nowrap font-medium text-gray-800">{{ line.code }}</div>
											<div class="text-xs text-gray-500">{{ line.name }}</div>
										</div>
										<span v-if="line.unchecked" class="whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700 ring-1 ring-inset ring-amber-200">
											{{ ctrans("Not checked yet") }}
										</span>
									</div>
								</td>
								<td class="px-3 py-2 text-right tabular-nums" v-tooltip="ctrans(':skos SKO', { skos: skos(line, line.ordered) })">{{ formatQuantity(line.ordered) }}</td>
								<td class="px-3 py-2 text-right tabular-nums" v-tooltip="ctrans(':skos SKO', { skos: skos(line, line.received) })">{{ formatQuantity(line.received) }}</td>
								<td class="px-3 py-2 text-right tabular-nums" :class="line.rejected > 0 ? 'text-red-600' : ''">{{ formatQuantity(line.rejected) }}</td>
								<td class="px-3 py-2 text-right font-medium tabular-nums" v-tooltip="ctrans(':skos SKO', { skos: skos(line, line.outstanding) })">
									<FontAwesomeIcon v-if="line.outstanding === 0 && line.received > 0" :icon="faBoxCheck" class="mr-1 text-green-600" fixed-width aria-hidden="true" />
									{{ formatQuantity(line.outstanding) }}
									<div v-if="toReplace(line) > 0" class="text-xs font-normal text-red-600">
										{{ ctrans(":units to replace rejects", { units: formatQuantity(toReplace(line)) }) }}
									</div>
								</td>
								<td class="py-2 pl-3"></td>
							</tr>

							<tr v-for="item in line.items" :key="`item-${item.id}`" class="border-b border-gray-100 bg-gray-50/60 text-xs">
								<td class="text-center align-top pt-1.5">
									<Checkbox v-if="receiving.check_many_route && item.check_route && !item.is_checked" v-model="selectedItemIds" :value="item.id" size="small" :aria-label="ctrans('Select :reference', { reference: item.reference })" />
								</td>
								<td class="py-1.5 pr-3" colspan="2">
									<div class="text-gray-700">
										<Link :href="route(item.show_route.name, item.show_route.parameters)" class="secondaryLink whitespace-nowrap">{{ item.reference }}</Link>
										· {{ ctrans(":units units on :date", { units: formatQuantity(item.quantity), date: item.received_at }) }}
										<span class="text-gray-400">({{ skos(line, item.quantity) }} SKO)</span>
									</div>
									<div v-if="item.received_by" class="text-gray-400">{{ ctrans("Received by :name", { name: item.received_by }) }}</div>
								</td>
								<td class="px-3 py-1.5" colspan="3">
									<span class="whitespace-nowrap rounded-full px-2 py-0.5 ring-1 ring-inset" :class="itemStatus(item).class">
										{{ itemStatus(item).label }}
									</span>
									<span v-if="item.is_packed" class="ml-2 text-gray-500">{{ ctrans("Packed for a container") }}</span>
									<div v-if="item.reject_reason" class="mt-1 whitespace-pre-line text-gray-600">{{ item.reject_reason }}</div>
									<div v-if="item.photos.length" class="mt-1 flex flex-wrap gap-2">
										<a v-for="photo in item.photos" :key="photo.id" :href="photo.url" target="_blank" rel="noopener" class="secondaryLink inline-flex items-center gap-1">
											<FontAwesomeIcon :icon="faImage" fixed-width aria-hidden="true" />
											{{ photo.name }}
										</a>
									</div>
									<div v-if="item.checked_by" class="mt-0.5 text-gray-400">{{ ctrans("Checked by :name", { name: item.checked_by }) }}</div>
								</td>
								<td class="py-1.5 pl-3 text-right">
									<Button
										v-if="item.check_route && item.is_checked"
										type="transparent"
										size="xs"
										:label="ctrans('Check again')"
										:tooltip="ctrans('Click to change the check of these goods')"
										@click="openCheck(line, item)"
									>
										<template #icon>
											<FontAwesomeIcon :icon="faPencil" class="text-gray-500" fixed-width aria-hidden="true" />
										</template>
									</Button>
									<Button
										v-else-if="item.check_route"
										type="secondary"
										size="xs"
										:label="ctrans('Check')"
										:tooltip="ctrans('Click to check these goods for rejects')"
										@click="openCheck(line, item)"
									>
										<template #icon>
											<FontAwesomeIcon :icon="faClipboardCheck" fixed-width aria-hidden="true" />
										</template>
									</Button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
		</div>

		<AgentGoodsReceiptItemCheckDialog :item="checkingItem" @close="checkingItem = null" />
	</section>
</template>
