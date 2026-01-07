<script setup lang="ts">
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faExclamation, faInfo, faPlay } from "@fas"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { layoutStructure } from "@/Composables/useLayoutStructure"

library.add(faCheck, faExclamation, faInfo, faPlay)

const props = withDefaults(
	defineProps<{
		showRedBorder: boolean
		widget: Array<{
			label: string
			key: string
			icon: string
			class: string
			color: string
			value: number | null
		}>
		visual?: any
	}>(),
	{
		widget: () => [],
	}
)

const locale = inject("locale", aikuLocaleStructure)
const layoutStore = inject("layout", layoutStructure)

const sentStats = computed(() => {
	const sentCases = props.widget.filter(
		(item) =>
			item.key.includes("sent") || item.key.includes("provider") || item.key.includes("ready")
	)
	const count = sentCases.reduce((sum, item) => sum + (Number(item.value) || 0), 0)
	return {
		label: "Sent",
		count,
		cases: sentCases,
	}
})

const deliveryStats = computed(() => {
	const deliveryCases = props.widget.filter(
		(item) =>
			!(
				item.key.includes("sent") ||
				item.key.includes("provider") ||
				item.key.includes("ready")
			)
	)
	const count = deliveryCases.reduce((sum, item) => sum + (Number(item.value) || 0), 0)
	return {
		label: "Delivery",
		count,
		cases: deliveryCases,
	}
})
</script>

<template>
	<dl class=" mb-2 grid grid-cols-1 md:grid-cols-2 gap-3">
		<!-- Sent Card -->
		<div class="px-6 py-6 rounded-lg bg-white shadow-sm border border-gray-200">
			<dt class="text-lg font-semibold text-gray-500 capitalize mb-3">
				{{ sentStats.label }}
			</dt>
			<dd class="flex flex-col gap-4">
				<!-- Total Count -->
				<div class="flex items-center">
					<span class="text-3xl font-bold text-org-500">
						{{ locale.number(sentStats.count) }}
					</span>
					<span class="ml-2 text-sm text-gray-500">in total</span>
				</div>
				<!-- Breakdown of each case -->
				<div class="flex flex-wrap gap-4">
					<div
						v-for="item in sentStats.cases"
						:key="item.key"
						class="flex items-center gap-2">
						<FontAwesomeIcon
							:icon="item.icon"
							:class="item.class"
							fixed-width
							:title="item.label"
							aria-hidden="true" />
						<span class="text-base font-medium text-gray-700">
							{{ locale.number(item.value || 0) }}
						</span>
					</div>
				</div>
			</dd>
		</div>

		<!-- Delivery Card -->
		<div class="px-6 py-6 rounded-lg bg-white shadow-sm border border-gray-200">
			<dt class="text-lg font-semibold text-gray-500 capitalize mb-3">
				{{ deliveryStats.label }}
			</dt>
			<dd class="flex flex-col gap-4">
				<!-- Total Count -->
				<div class="flex items-center">
					<span class="text-3xl font-bold text-org-500">
						{{ locale.number(deliveryStats.count) }}
					</span>
					<span class="ml-2 text-sm text-gray-500">in total</span>
				</div>
				<!-- Breakdown of each case -->
				<div class="flex flex-wrap gap-4">
					<div
						v-for="item in deliveryStats.cases"
						:key="item.key"
						class="flex items-center gap-2">
						<FontAwesomeIcon
							:icon="item.icon"
							:class="item.class"
							fixed-width
							:title="item.label"
							aria-hidden="true" />
						<span class="text-base font-medium text-gray-700">
							{{ locale.number(item.value || 0) }}
						</span>
					</div>
				</div>
			</dd>
		</div>
	</dl>
</template>

	<!-- <div class="grid grid-cols-4 md:grid-cols-4 gap-2">
		<div class="md:col-span-4 grid sm:grid-cols-1 md:grid-cols-6 gap-2 h-auto mb-3">
			<div
				v-for="item in widget"
				:key="item.key"
				class="flex flex-col justify-between px-6 py-2 rounded-lg border sm:h-auto">
				<div class="flex justify-between items-center mb-2">
					<div>
						<div class="text-lg font-semibold capitalize">{{ item.label }}</div>
					</div>
					<div class="rounded-full p-2">
						<FontAwesomeIcon :icon="item.icon" class="text-xl" />
					</div>
				</div>
				<div>
					<div class="text-2xl font-bold">{{ item.value || 0 }}</div>
				</div>
			</div>
		</div>
	</div> -->
