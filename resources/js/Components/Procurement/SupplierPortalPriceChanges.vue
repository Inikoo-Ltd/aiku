<script setup lang="ts">
import qs from "qs"
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import { useMediaQuery } from "@vueuse/core"
import Dialog from "primevue/dialog"
import Table from "@/Components/Table/Table.vue"
import PillFilterBar from "@/Components/Utils/PillFilterBar.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SupplierPortalForms from "@/Components/Procurement/SupplierPortalForms.vue"
import type { EditedSubmission, PortalForms } from "@/Components/Procurement/SupplierPortalForms.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPen, faTag } from "@fal"

library.add(faPen, faTag)

interface PriceChange extends EditedSubmission {
	state_label: string
	proposed_price: number
	effective_from: string | null
	reason: string | null
	submitted_at: string | null
	editable: boolean
}

interface ProductRow {
	id: number
	code: string
	name: string
	status: "active" | "on_hold" | "discontinued"
	status_label: string
	cost: number
	price_change: PriceChange | null
}

const props = defineProps<{
	data: {
		data?: ProductRow[]
		facets?: { state: { value: string; label: string; count: number }[] }
		active_facets?: { state: string | null }
	}
	tab: string
	forms: PortalForms
	currency: string | null
}>()

const isPhone = useMediaQuery("(max-width: 767px)")
const selected = ref<ProductRow | null>(null)
const editing = computed(() => (selected.value?.price_change?.editable ? selected.value.price_change : null))
const isOpen = computed({
	get: () => selected.value !== null,
	set: (value: boolean) => {
		if (!value) {
			selected.value = null
		}
	},
})

const selectState = (value: string | null) => {
	const query = qs.parse(location.search.substring(1)) as Record<string, any>
	query[`${props.tab}_facets`] = { ...(query[`${props.tab}_facets`] ?? {}), state: value }
	delete query[`${props.tab}Page`]

	router.get(location.pathname + "?" + qs.stringify(query, { skipNulls: true, encodeValuesOnly: true }), {}, { preserveState: true, preserveScroll: true, replace: true })
}

const canChange = (row: ProductRow) => row.status !== "discontinued"
const locale = useLocaleStore()
const price = (amount: number | null) => (amount === null ? "—" : locale.currencyFormatUnitCost(props.currency, amount))
const day = (date: string | null) => (date ? useFormatTime(date) : "—")
const statusClass = { active: "text-green-700", on_hold: "text-amber-700", discontinued: "text-gray-400" }
const stateClass: Record<string, string> = {
	received: "text-amber-700",
	under_review: "text-amber-700",
	more_information_requested: "text-amber-700",
	accepted: "text-green-700",
	not_proceeding: "text-red-600",
}
</script>

<template>
	<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
		<p class="px-4 pt-4 text-sm text-gray-500">
			{{ ctrans('Proposed price changes (PPC) will be notified to your original buyer or contact (copy to buyers). Please note that until the price change is accepted the product status will move to "On Hold" meaning no PO can be raised for this product. You can edit your PPC anytime below, contacts will be re-notified. Thank you for your understanding.') }}
		</p>
		<div v-if="data.facets" class="mx-4 mt-4">
			<PillFilterBar :label="ctrans('Proposal')" :options="data.facets.state" :selected="data.active_facets?.state ?? null" @select="selectState" />
		</div>

		<div class="mt-4">
			<Table :resource="data" :name="tab">
				<template v-if="isPhone" #table>
					<ul class="divide-y divide-gray-100 border-t border-gray-100">
						<li v-for="row in data.data ?? []" :key="row.id" class="space-y-1.5 px-4 py-3">
							<div class="flex items-baseline justify-between gap-3">
								<span class="text-base font-semibold text-gray-900">{{ row.code }}</span>
								<span class="text-base tabular-nums text-gray-900">{{ price(row.cost) }}</span>
							</div>
							<div class="flex items-baseline justify-between gap-3 text-sm">
								<span class="text-gray-500">{{ row.name }}</span>
								<span :class="statusClass[row.status]">{{ row.status_label }}</span>
							</div>
							<template v-if="row.price_change">
								<dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-sm">
									<dt class="text-gray-400">{{ ctrans("Proposed price") }}</dt>
									<dd class="text-right font-semibold tabular-nums text-gray-900">{{ price(row.price_change.proposed_price) }}</dd>
									<template v-if="row.price_change.effective_from">
										<dt class="text-gray-400">{{ ctrans("From") }}</dt>
										<dd class="text-right tabular-nums text-gray-700">{{ day(row.price_change.effective_from) }}</dd>
									</template>
									<dt class="text-gray-400">{{ ctrans("Proposal") }}</dt>
									<dd class="text-right" :class="stateClass[row.price_change.state]">{{ row.price_change.state_label }}</dd>
								</dl>
								<div v-if="row.price_change.feedback" class="rounded bg-gray-50 p-2 text-sm text-gray-700">{{ row.price_change.feedback }}</div>
							</template>
							<Button v-if="canChange(row)" type="tertiary" size="xs" :label="row.price_change?.editable ? ctrans('Edit') : ctrans('Change price')" @click="selected = row">
								<template #icon><FontAwesomeIcon :icon="row.price_change?.editable ? 'fal fa-pen' : 'fal fa-tag'" fixed-width aria-hidden="true" /></template>
							</Button>
						</li>
					</ul>
				</template>

				<template #cell(code)="{ item }: { item: ProductRow }">
					<span class="whitespace-nowrap font-medium text-gray-900">{{ item.code }}</span>
					<div class="text-xs text-gray-500">{{ item.name }}</div>
				</template>
				<template #cell(status)="{ item }: { item: ProductRow }">
					<span class="whitespace-nowrap" :class="statusClass[item.status]">{{ item.status_label }}</span>
				</template>
				<template #cell(cost)="{ item }: { item: ProductRow }">
					<span class="whitespace-nowrap tabular-nums">{{ price(item.cost) }}</span>
				</template>
				<template #cell(proposed_price)="{ item }: { item: ProductRow }">
					<span v-if="item.price_change" class="whitespace-nowrap font-semibold tabular-nums">{{ price(item.price_change.proposed_price) }}</span>
				</template>
				<template #cell(effective_from)="{ item }: { item: ProductRow }">
					<span v-if="item.price_change?.effective_from" class="whitespace-nowrap">{{ day(item.price_change.effective_from) }}</span>
				</template>
				<template #cell(price_change_submitted_at)="{ item }: { item: ProductRow }">
					<span v-if="item.price_change" class="whitespace-nowrap">{{ day(item.price_change.submitted_at) }}</span>
				</template>
				<template #cell(price_change_state)="{ item }: { item: ProductRow }">
					<span v-if="item.price_change" class="whitespace-nowrap" :class="stateClass[item.price_change.state]">{{ item.price_change.state_label }}</span>
				</template>
				<template #cell(feedback)="{ item }: { item: ProductRow }">
					<span class="text-xs text-gray-600">{{ item.price_change?.feedback ?? "" }}</span>
				</template>
				<template #cell(actions)="{ item }: { item: ProductRow }">
					<Button v-if="canChange(item)" type="tertiary" size="xxs" :label="item.price_change?.editable ? ctrans('Edit') : ctrans('Change price')" @click="selected = item">
						<template #icon><FontAwesomeIcon :icon="item.price_change?.editable ? 'fal fa-pen' : 'fal fa-tag'" fixed-width aria-hidden="true" /></template>
					</Button>
				</template>
			</Table>
		</div>

		<Dialog v-model:visible="isOpen" modal :header="selected ? `${ctrans('Change price')} · ${selected.code}` : ''" class="w-[calc(100vw-2rem)] max-w-2xl">
			<SupplierPortalForms
				v-if="selected"
				:key="selected.id"
				:forms="forms"
				:products="[{ id: selected.id, code: selected.code, name: selected.name, price: selected.cost }]"
				:currency="currency"
				:editing="editing"
				locked-type="price_change"
				@done="selected = null" />
		</Dialog>
	</div>
</template>
