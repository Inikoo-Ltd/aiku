<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faArrowRight } from "@fal"
import SegmentedToggle from "@/Components/Utils/SegmentedToggle.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faArrowRight)

type Figures = { annual_used: number; medical_used: number; unpaid_used: number }
type Period = { start: string | null; end: string | null; has_contract: boolean }

export interface LeaveBalanceChange {
	key: string
	kind: "created" | "changed" | "adjusted" | "deleted"
	period_before: Period | null
	period_after: Period | null
	allowance_before: number | null
	allowance_after: number | null
	before: Figures | null
	after: Figures | null
	recorded: Figures
	has_choice: boolean
	choice: "carry" | "recorded" | null
	options: { carry: Figures; recorded: Figures } | null
	destination: Period | null
}

const props = defineProps<{
	entries: LeaveBalanceChange[]
	loading?: boolean
}>()

const choices = defineModel<Record<string, "carry" | "recorded">>("choices", { default: () => ({}) })

const counters = computed(() => [
	{ key: "annual_used" as const, label: ctrans("Annual") },
	{ key: "medical_used" as const, label: ctrans("Medical") },
	{ key: "unpaid_used" as const, label: ctrans("Unpaid") },
])

const kindLabels = computed<Record<string, { label: string; class: string }>>(() => ({
	created: { label: ctrans("New balance"), class: "bg-green-100 text-green-800" },
	changed: { label: ctrans("Changes"), class: "bg-amber-100 text-amber-800" },
	adjusted: { label: ctrans("Also affected"), class: "bg-gray-100 text-gray-700" },
	deleted: { label: ctrans("Removed"), class: "bg-red-100 text-red-800" },
}))

const days = (value: number | null | undefined): string => String(Number(value ?? 0))

const periodLabel = (period: Period | null): string => {
	if (!period) {
		return "—"
	}

	if (!period.start) {
		return period.has_contract ? ctrans("Contract balance without a period") : ctrans("Balance without a contract")
	}

	return `${useFormatTime(period.start)} – ${period.end ? useFormatTime(period.end) : ctrans("open-ended")}`
}

const periodChanged = (entry: LeaveBalanceChange): boolean =>
	!!entry.period_before && !!entry.period_after && periodLabel(entry.period_before) !== periodLabel(entry.period_after)

const changeClass = (before: number | null | undefined, after: number | null | undefined): string => {
	const difference = Number(after ?? 0) - Number(before ?? 0)

	if (difference > 0) {
		return "font-semibold text-red-600"
	}

	return difference < 0 ? "font-semibold text-green-600" : "text-gray-900"
}

const choiceOptions = (entry: LeaveBalanceChange) => [
	{ label: entry.kind === "deleted" ? ctrans("Carry over") : ctrans("Keep current figure"), value: "carry" as const },
	{ label: ctrans("Recorded leaves only"), value: "recorded" as const },
]

const figuresText = (figures: Figures): string => counters.value.map((counter) => `${counter.label} ${days(figures[counter.key])}`).join(" · ")

const choiceHelp = (entry: LeaveBalanceChange): string => {
	if (!entry.options) {
		return ""
	}

	if (entry.kind === "deleted") {
		return choices.value[entry.key] === "recorded"
			? ctrans("Only the leaves recorded in Aiku keep counting. The rest of this balance's figures are dropped.")
			: ctrans("Days used beyond the recorded leaves (:figures) move to :destination as an opening adjustment.", {
					figures: figuresText(entry.options.carry),
					destination: periodLabel(entry.destination),
			  })
	}

	return choices.value[entry.key] === "recorded"
		? ctrans("Days used become what the approved leaves in this period add up to.")
		: ctrans("The current figure is kept as an opening adjustment, so later recalculations do not change it.")
}

const setChoice = (key: string, value: "carry" | "recorded" | undefined) => {
	if (value) {
		choices.value = { ...choices.value, [key]: value }
	}
}
</script>

<template>
	<div class="space-y-3 transition-opacity" :class="{ 'opacity-60': loading }" :aria-busy="loading">
		<div class="flex items-center gap-2 text-sm text-gray-600">
			<span>{{ ctrans("Nothing is saved until you confirm.") }}</span>
			<LoadingIcon v-if="loading" class="text-[--app-accent]" />
		</div>

		<section v-for="entry in entries" :key="entry.key" class="rounded-lg border border-gray-200 bg-white">
			<header class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-gray-100 bg-gray-50 px-3 py-2 text-sm">
				<span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="kindLabels[entry.kind].class">{{ kindLabels[entry.kind].label }}</span>
				<span class="font-medium tabular-nums text-gray-900">{{ periodLabel(entry.period_before ?? entry.period_after) }}</span>
				<template v-if="periodChanged(entry)">
					<FontAwesomeIcon :icon="faArrowRight" class="text-gray-400" fixed-width aria-hidden="true" />
					<span class="font-medium tabular-nums text-gray-900">{{ periodLabel(entry.period_after) }}</span>
				</template>
			</header>

			<div class="px-3 py-2">
				<table class="w-full text-sm">
					<thead>
						<tr class="text-left text-xs uppercase tracking-wide text-gray-500">
							<th class="py-1 pr-2 font-medium">{{ ctrans("Days used") }}</th>
							<th class="px-2 py-1 text-right font-medium">{{ ctrans("Now") }}</th>
							<th class="px-2 py-1 text-right font-medium">{{ ctrans("After") }}</th>
							<th class="py-1 pl-2 text-right font-medium">{{ ctrans("Recorded") }}</th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-100 tabular-nums">
						<tr v-for="counter in counters" :key="counter.key">
							<td class="py-1 pr-2 text-gray-700">{{ counter.label }}</td>
							<td class="px-2 py-1 text-right text-gray-900">{{ entry.before ? days(entry.before[counter.key]) : "—" }}</td>
							<td class="px-2 py-1 text-right" :class="entry.after ? changeClass(entry.before?.[counter.key], entry.after[counter.key]) : 'text-gray-400'">
								{{ entry.after ? days(entry.after[counter.key]) : "—" }}
							</td>
							<td class="py-1 pl-2 text-right text-gray-500">{{ days(entry.recorded[counter.key]) }}</td>
						</tr>
						<tr v-if="entry.allowance_before !== entry.allowance_after && entry.allowance_after !== null && entry.allowance_before !== null">
							<td class="py-1 pr-2 text-gray-700">{{ ctrans("Annual allowance") }}</td>
							<td class="px-2 py-1 text-right text-gray-900">{{ days(entry.allowance_before) }}</td>
							<td class="px-2 py-1 text-right font-semibold" :class="Number(entry.allowance_after) < Number(entry.allowance_before) ? 'text-red-600' : 'text-green-600'">
								{{ days(entry.allowance_after) }}
							</td>
							<td class="py-1 pl-2" />
						</tr>
					</tbody>
				</table>

				<div v-if="entry.has_choice" class="mt-3 border-t border-gray-100 pt-3">
					<SegmentedToggle
						:modelValue="choices[entry.key] ?? 'carry'"
						:options="choiceOptions(entry)"
						:ariaLabel="ctrans('What happens to this balance')"
						@update:modelValue="(value) => setChoice(entry.key, value)" />
					<p class="mt-2 text-xs text-gray-600">{{ choiceHelp(entry) }}</p>
				</div>
				<p v-else-if="entry.kind === 'deleted'" class="mt-2 text-xs text-gray-600">
					{{ ctrans("Its figures all come from recorded leaves, which keep counting on the balance that covers their dates.") }}
				</p>
			</div>
		</section>
	</div>
</template>
