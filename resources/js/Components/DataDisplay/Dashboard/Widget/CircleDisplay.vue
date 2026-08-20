<script setup lang="ts">
import { inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faPaperPlane,
	faInboxIn,
	faEnvelopeOpen,
	faMousePointer,
	faSpellCheck,
	faExclamationCircle,
	faVirus,
	faExclamationTriangle,
	faDumpster,
	faHandPaper,
	faSquare,
} from "@fal"
import { trans } from "laravel-vue-i18n"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"

library.add(
	faPaperPlane,
	faInboxIn,
	faEnvelopeOpen,
	faMousePointer,
	faSpellCheck,
	faExclamationCircle,
	faVirus,
	faExclamationTriangle,
	faDumpster,
	faHandPaper,
	faSquare
)

interface FunnelStep {
	key: string
	label: string
	icon: string
	value: number
	percentage?: number | null
}

const props = defineProps<{
	widget: {
		funnel: FunnelStep[]
		issues: FunnelStep[]
	}
	visual?: any
}>()

const locale = inject("locale", aikuLocaleStructure)

const barWidth = (step: FunnelStep) => {
	const max = props.widget.funnel[0]?.value || 0
	if (!max) return 0
	return Math.max((step.value / max) * 100, step.value > 0 ? 4 : 0)
}
</script>

<template>
	<div class="px-6 py-5 rounded-lg bg-white shadow border border-gray-200">
		<div class="flex flex-col gap-3">
			<div
				v-for="step in widget.funnel"
				:key="step.key"
				class="grid grid-cols-[8rem_1fr_5rem] items-center gap-3">
				<div class="flex items-center gap-2 text-gray-600">
					<FontAwesomeIcon :icon="step.icon" fixed-width aria-hidden="true" />
					<span class="text-sm">{{ step.label }}</span>
				</div>
				<div class="h-5 rounded bg-gray-100 overflow-hidden">
					<div
						class="h-full rounded bg-indigo-500 transition-all"
						:style="{ width: barWidth(step) + '%' }" />
				</div>
				<div class="flex items-baseline whitespace-nowrap">
					<span class="flex-1 text-right text-base font-semibold text-gray-700">
						{{ locale.number(step.value) }}
					</span>
					<span class="w-12 text-right text-xs text-gray-400">
						<template v-if="step.percentage != null">{{ step.percentage }}%</template>
					</span>
				</div>
			</div>

			<div
				v-if="!widget.funnel[0]?.value"
				class="text-sm text-gray-400">
				{{ trans("No emails have been sent from this outbox yet") }}
			</div>

			<div
				v-if="widget.issues?.length"
				class="flex flex-wrap gap-x-4 gap-y-1 pt-2 border-t border-gray-100">
				<div
					v-for="issue in widget.issues"
					:key="issue.key"
					class="flex items-center gap-1.5 text-sm text-gray-500"
					:title="issue.label">
					<FontAwesomeIcon :icon="issue.icon" fixed-width aria-hidden="true" />
					<span>{{ issue.label }}:</span>
					<span class="font-medium text-gray-700">{{ locale.number(issue.value) }}</span>
				</div>
			</div>
		</div>
	</div>
</template>
