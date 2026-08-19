<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faFilePdf, faDownload } from "@fal"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faFilePdf, faDownload)

const labelingGuide = computed(() => props.fieldValue?.labeling_guide ?? {})

const containerStyle = computed(() => getStyles(labelingGuide.value?.container?.properties))

const title = computed(() => labelingGuide.value?.title || ctrans("Labeling Guide"))

const description = computed(
	() =>
		labelingGuide.value?.description ||
		ctrans(
			"Ensure your product is labeled correctly and complies with all applicable regulations."
		)
)

const cardTitle = computed(
	() => labelingGuide.value?.card?.title || ctrans("Labeling & Compliance Guide")
)

const cardDescription = computed(
	() =>
		labelingGuide.value?.card?.description ||
		ctrans(
			"Download the full guide with mandatory warnings, precautions, labeling requirements and reseller responsibilities."
		)
)

const buttonLabel = computed(
	() => labelingGuide.value?.card?.button?.text || ctrans("Download PDF")
)

const includesTitle = computed(
	() => labelingGuide.value?.includes?.title || ctrans("The Guide Includes:")
)

const defaultIncludes = [
	ctrans("Mandatory warnings and hazard statements"),
	ctrans("Precautions for safe use"),
	ctrans("Labeling requirements and examples"),
	ctrans("Reseller responsibilities and compliance obligations"),
]

const includes = computed(() => {
	const source = labelingGuide.value?.includes?.items

	if (!Array.isArray(source) || source.length === 0) {
		return defaultIncludes
	}

	return source
})

const note = computed(
	() =>
		labelingGuide.value?.note ||
		ctrans("Staying compliant protects your brand and builds trust.")
)
</script>

<template>
	<div class="w-full py-6 md:py-8 lg:py-10" :style="containerStyle">
		<h2 class="text-2xl font-semibold text-[#13294B] md:text-[28px] lg:text-[30px]">
			{{ title }}
		</h2>

		<div
			class="mt-2 max-w-2xl text-[12px] leading-[1.8] text-[#C0899B] md:text-[13px]"
			v-html="description" />

		<div class="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-[1.2fr_1fr] lg:gap-14">
			<div class="rounded-[10px] border border-[#DADADA] bg-[#EDEDED]/60 p-5 md:p-6">
				<div class="flex items-start gap-4">
					<div
						class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-[8px] border border-[#DADADA] bg-white text-[#13294B]">
						<FontAwesomeIcon icon="fal fa-file-pdf" class="text-xl md:text-2xl" />
						<span class="mt-1 text-[9px] font-semibold tracking-wide">PDF</span>
					</div>

					<div>
						<h3 class="text-lg font-semibold text-[#13294B] md:text-xl">
							{{ cardTitle }}
						</h3>

						<div
							class="mt-2 text-[12px] leading-[1.8] text-[#C0899B] md:text-[13px]"
							v-html="cardDescription" />
					</div>
				</div>

				<button
					class="mt-5 inline-flex items-center gap-6 rounded-[6px] bg-[#0F1E2E] px-5 py-3 text-[13px] text-white transition hover:bg-[#1c2f43] md:text-[14px]"
					:style="getStyles(labelingGuide?.card?.button?.container?.properties)">
					<span>{{ buttonLabel }}</span>
					<FontAwesomeIcon icon="fal fa-download" class="text-[14px]" />
				</button>
			</div>

			<div>
				<h3 class="text-[15px] font-semibold text-[#13294B] md:text-[16px]">
					{{ includesTitle }}
				</h3>

				<ul class="mt-4 space-y-3">
					<li
						v-for="(item, index) in includes"
						:key="index"
						class="flex items-start gap-3 text-[12px] leading-[1.7] text-[#13294B] md:text-[13px]">
						<span class="mt-[6px] h-[7px] w-[7px] shrink-0 rounded-full bg-[#C0899B]" />

						<span>{{ typeof item === "string" ? item : item?.text }}</span>
					</li>
				</ul>

				<div
					v-if="note"
					class="mt-6 rounded-[6px] border border-[#C0899B] bg-[#C0899B]/10 px-4 py-3 text-[12px] leading-[1.7] text-[#13294B] md:text-[13px]"
					v-html="note" />
			</div>
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
