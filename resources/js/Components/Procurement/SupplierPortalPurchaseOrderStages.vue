<!--
    * Author: Vika Aqordi
    * Created on: 2026-11-04 11:11
    * Github: https://github.com/aqordeon
    * Copyright: 2026
-->

<script setup lang="ts">
import { computed, ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import PurchaseOrderJourneyRibbon, { type JourneyRibbon } from "@/Components/SupplyChain/PurchaseOrderJourneyRibbon.vue"
import { useLocaleStore } from "@/Stores/locale"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faList, faClipboardList, faPeopleArrows } from "@fal"

library.add(faRocket, faCoins, faIndustryAlt, faClipboardCheck, faBox, faList, faClipboardList, faPeopleArrows)

type Stage = { key: string; label: string; description: string; markable: boolean }
type Filter = "open" | "needs_action" | "completed"

const props = defineProps<{
	data: { stages: Stage[]; ribbons: JourneyRibbon[] }
}>()

const emit = defineEmits<{ list: [] }>()

const locale = useLocaleStore()

const stageIcons: Record<string, string> = {
	submitted: "fal fa-rocket",
	deposit_paid: "fal fa-coins",
	in_production: "fal fa-industry-alt",
	ready: "fal fa-clipboard-check",
	handed_over: "fal fa-box",
}

const legend = [
	{ label: ctrans("Complete"), class: "border-emerald-300 bg-emerald-50" },
	{ label: ctrans("Current"), class: "border-2 border-gray-700 bg-white" },
	{ label: ctrans("At risk (due in 3 days)"), class: "border-2 border-amber-400 bg-amber-50" },
	{ label: ctrans("Target missed"), class: "border-2 border-red-400 bg-red-50" },
	{ label: ctrans("Upcoming (struck through once the target has passed)"), class: "border-gray-200 bg-gray-50" },
	{ label: ctrans("Not recorded"), class: "border-dashed border-gray-300 bg-white" },
]

const inFilter = (ribbon: JourneyRibbon, filter: Filter): boolean => {
	if (filter === "needs_action") {
		return ["overdue", "at_risk"].includes(ribbon.status)
	}
	if (filter === "completed") {
		return ribbon.status === "completed"
	}
	return ribbon.status !== "completed"
}

const filter = ref<Filter>("open")

const filterOptions = computed(() =>
	([
		{ value: "open", label: ctrans("Open") },
		{ value: "needs_action", label: ctrans("Needs action") },
		{ value: "completed", label: ctrans("Completed") },
	] as { value: Filter; label: string }[]).map((option) => ({ ...option, count: props.data.ribbons.filter((ribbon) => inFilter(ribbon, option.value)).length })),
)

const shownRibbons = computed(() => props.data.ribbons.filter((ribbon) => inFilter(ribbon, filter.value)))

const amount = (ribbon: JourneyRibbon): string => (ribbon.amount !== null && ribbon.currency_code ? String(locale.currencyFormat(ribbon.currency_code, ribbon.amount)) : "")
</script>

<template>
	<div class="space-y-3">
		<div class="flex flex-wrap items-center gap-3">
			<SegmentedToggle v-model="filter" :options="filterOptions" :aria-label="ctrans('Purchase orders')" />
			<p class="min-w-0 flex-1 text-sm text-gray-500">{{ ctrans("Where each order we sent you is now, stage by stage. Finished orders stay here for 60 days.") }}</p>
			<Button type="tertiary" size="xs" :label="ctrans('Purchase order list')" @click="emit('list')">
				<template #icon><FontAwesomeIcon icon="fal fa-list" fixed-width aria-hidden="true" /></template>
			</Button>
		</div>

		<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
			<span v-for="item in legend" :key="item.label" class="flex items-center gap-1.5">
				<span class="inline-block h-3.5 w-5 shrink-0 rounded border" :class="item.class" />
				{{ item.label }}
			</span>
		</div>

		<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm">
			<table class="stages-table w-full min-w-[68rem] table-fixed border-collapse">
				<colgroup>
					<col class="w-[16rem]" />
					<col v-for="stage in data.stages" :key="stage.key" class="w-[8.5rem]" />
					<col class="w-[9rem]" />
				</colgroup>
				<thead class="whitespace-nowrap">
					<tr class="border-b border-gray-200 text-xs text-gray-500">
						<th class="py-2 pl-3 pr-3 text-left text-sm font-semibold text-gray-700">{{ ctrans("Order") }}</th>
						<th v-for="(stage, index) in data.stages" :key="stage.key" class="px-1 py-2 text-center font-normal" :title="stage.description">
							<div class="flex items-center justify-center gap-1.5">
								<span class="flex h-5 w-5 items-center justify-center rounded-full bg-gray-600 text-[11px] font-semibold text-white">{{ index + 1 }}</span>
								<FontAwesomeIcon :icon="stageIcons[stage.key]" class="text-base text-gray-600" fixed-width aria-hidden="true" />
							</div>
							<div class="mt-1 text-sm font-semibold text-gray-800">{{ stage.label }}</div>
							<div>{{ stage.description }}</div>
						</th>
						<th class="py-2 pl-3 pr-3 text-left text-sm font-semibold text-gray-700">{{ ctrans("Status") }}</th>
					</tr>
				</thead>
				<PurchaseOrderJourneyRibbon
					v-for="ribbon in shownRibbons"
					:key="ribbon.key"
					:ribbon="ribbon"
					:stages="data.stages"
					:group-currency="ribbon.currency_code"
					:can-mark="false"
					:expand-all="false">
					<template #details>
						<div class="min-w-0 flex-1 space-y-1 text-xs text-gray-600">
							<div class="flex items-center gap-1.5">
								<FontAwesomeIcon icon="fal fa-clipboard-list" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
								<span class="min-w-0 truncate whitespace-nowrap text-sm font-semibold text-gray-900" :title="ribbon.reference">{{ ribbon.reference }}</span>
								<span class="ml-auto whitespace-nowrap text-sm font-semibold text-gray-700">{{ amount(ribbon) }}</span>
							</div>
							<div>{{ ctrans("for :organisation", { organisation: ribbon.organisation_name }) }}</div>
							<div v-if="ribbon.agent_name" class="flex items-center gap-1">
								<FontAwesomeIcon v-tooltip="ctrans('Agent')" icon="fal fa-people-arrows" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
								{{ ribbon.agent_name }}
							</div>
						</div>
					</template>
				</PurchaseOrderJourneyRibbon>
			</table>
			<div v-if="!shownRibbons.length" class="py-10 text-center text-sm text-gray-500">
				{{ ctrans("No purchase orders here.") }}
			</div>
		</div>
	</div>
</template>

<style scoped>
.stages-table thead th:first-child,
.stages-table :deep(tbody tr:first-child td:first-child) {
	position: sticky;
	left: 0;
	z-index: 2;
	background-color: white;
	box-shadow: 2px 0 0 #e5e7eb;
}

.stages-table thead th {
	box-shadow: inset 0 -2px 0 #d1d5db;
}

.stages-table thead th:first-child {
	z-index: 3;
	box-shadow: inset 0 -2px 0 #d1d5db, 2px 0 0 #e5e7eb;
}
</style>
