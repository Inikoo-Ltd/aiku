<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheckCircle } from "@fal"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faCheckCircle)

const storage = computed(() => props.fieldValue?.storage ?? {})

const containerStyle = computed(() => getStyles(storage.value?.container?.properties))

const title = computed(() => storage.value?.title || ctrans("Storage & Shelf Life"))

const description = computed(
	() =>
		storage.value?.description ||
		ctrans(
			"Follow the recommended storage guidelines to maintain product quality and ensure optimal performance."
		)
)

const columns = computed(() => ({
	storage: storage.value?.table?.storage || ctrans("Storage"),
	shelfLife: storage.value?.table?.shelf_life || ctrans("Shelf Life"),
	afterOpening: storage.value?.table?.after_opening || ctrans("POA (After Opening)"),
}))

const values = computed(() => ({
	storage:
		storage.value?.conditions?.storage ||
		ctrans("Store in a cool, dry place away from direct sunlight and heat."),
	shelfLife:
		storage.value?.conditions?.shelf_life ||
		ctrans("24 months from date of manufacture | See Batch number."),
	afterOpening:
		storage.value?.conditions?.after_opening ||
		ctrans("Use within 12 Months of opening."),
}))

const temperatureLabel = computed(
	() => storage.value?.temperature?.label || ctrans("Storage Temperature")
)

const temperatureValue = computed(() => storage.value?.temperature?.value || "15°C – 25°C")

const hasTemperature = computed(() => Boolean(temperatureValue.value))

const defaultGuidelines = [
	ctrans("Keep products in their original packaging."),
	ctrans("Keep containers securely closed when not in use."),
	ctrans("Protect from heat and sunlight."),
	ctrans("Follow any products-specific storage instructions shown on the specification."),
]

const guidelinesTitle = computed(
	() => storage.value?.guidelines?.title || ctrans("Storage Guidelines")
)

const guidelines = computed(() => {
	const source = storage.value?.guidelines?.items

	if (!Array.isArray(source) || source.length === 0) {
		return defaultGuidelines
	}

	return source
})
</script>

<template>
	<div
		class="grid w-full grid-cols-1 gap-8 py-6 md:py-8 lg:grid-cols-[1.6fr_1fr] lg:gap-14 lg:py-10"
		:style="containerStyle">
		<div>
			<h2 class="text-2xl font-semibold text-[#13294B] md:text-[28px] lg:text-[30px]">
				{{ title }}
			</h2>

			<div
				class="mt-3 max-w-2xl text-[13px] leading-[1.8] text-[#13294B] md:text-[14px]"
				v-html="description" />

			<div class="mt-6 overflow-x-auto">
				<table class="w-full min-w-[520px] border-collapse text-left align-top">
					<thead>
						<tr class="bg-[#EDEDED] text-[13px] font-semibold text-[#13294B] md:text-[14px]">
							<th class="px-4 py-3">{{ columns.storage }}</th>
							<th class="px-4 py-3">{{ columns.shelfLife }}</th>
							<th class="px-4 py-3">{{ columns.afterOpening }}</th>
						</tr>
					</thead>

					<tbody>
						<tr class="align-top text-[11px] leading-[1.7] text-[#334155] md:text-[12px]">
							<td class="px-4 py-3">{{ values.storage }}</td>
							<td class="px-4 py-3">{{ values.shelfLife }}</td>
							<td class="px-4 py-3">{{ values.afterOpening }}</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div v-if="hasTemperature" class="mt-6 px-4">
				<p class="text-[12px] font-semibold text-[#13294B] md:text-[13px]">
					{{ temperatureLabel }}
				</p>

				<p class="mt-1 text-[11px] text-[#334155] md:text-[12px]">
					{{ temperatureValue }}
				</p>
			</div>
		</div>

		<div>
			<h3 class="text-lg font-semibold text-[#13294B] md:text-xl">
				{{ guidelinesTitle }}
			</h3>

			<ul class="mt-4 space-y-3">
				<li
					v-for="(guideline, index) in guidelines"
					:key="index"
					class="flex items-start gap-3 text-[12px] leading-[1.7] text-[#13294B] md:text-[13px]">
					<FontAwesomeIcon
						icon="fal fa-check-circle"
						class="mt-[3px] shrink-0 text-[14px] text-[#C0899B]" />

					<span>{{ typeof guideline === "string" ? guideline : guideline?.text }}</span>
				</li>
			</ul>
		</div>
	</div>
</template>

<style scoped>
:deep(p) {
	margin-bottom: 12px;
}

:deep(p:last-child) {
	margin-bottom: 0;
}
</style>
