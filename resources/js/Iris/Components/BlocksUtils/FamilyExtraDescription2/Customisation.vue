<script setup lang="ts">
import { computed } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen, faCheck } from "@fal"
import Image from "@common/Components/Image.vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
	fieldValue: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

library.add(faBox, faSmoke, faTint, faFlask, faTags, faBoxOpen, faCheck)

const customisation = computed(() => props.fieldValue?.customisation ?? {})

const containerStyle = computed(() => getStyles(customisation.value?.container?.properties))

const title = computed(() => customisation.value?.title || ctrans("Customisations"))

const description = computed(
	() =>
		customisation.value?.description ||
		ctrans(
			"Create a product that represents your brand. Our white label range can be tailored to your brand and market."
		)
)

const linkLabel = computed(
	() => customisation.value?.link?.text || ctrans("More about bespoke made products")
)

const linkUrl = computed(() => customisation.value?.link?.url || "#")

const image = computed(() => customisation.value?.image)

const hasImage = computed(() => Boolean(image.value?.original))

const defaultIcons = [
	{ key: "packaging", icon: "fal fa-box", label: ctrans("Packaging") },
	{ key: "fragrance", icon: "fal fa-smoke", label: ctrans("Fragrance") },
	{ key: "colour", icon: "fal fa-tint", label: ctrans("Colour") },
	{ key: "formulation", icon: "fal fa-flask", label: ctrans("Formulation") },
	{ key: "labeling", icon: "fal fa-tags", label: ctrans("Labeling") },
	{ key: "pack_sizes", icon: "fal fa-box-open", label: ctrans("Pack Sizes") },
]

const highlights = computed(() => {
	const source = customisation.value?.highlights

	if (!Array.isArray(source) || source.length === 0) {
		return defaultIcons
	}

	return source
})

const defaultRows = [
	{
		option: ctrans("Packaging"),
		available: true,
		moq: "£500+",
		notes: ctrans("Multiple Packaging format and sizes, see our packaging options."),
	},
	{
		option: ctrans("Labeling"),
		available: true,
		moq: "£500+",
		notes: ctrans("Customer supplied or printed by us."),
	},
	{
		option: ctrans("Fragrance"),
		available: true,
		moq: "£1000 - £1500",
		notes: ctrans("Choose from our fragrance department or create your own."),
	},
	{
		option: ctrans("Formulation"),
		available: true,
		moq: "£1000 - £1500",
		notes: ctrans("Speak to our team to explore the formulation options available."),
	},
	{
		option: ctrans("Outer Packaging"),
		available: true,
		moq: "£500+",
		notes: ctrans("Multiple Packaging format and sizes."),
	},
]

const rows = computed(() => {
	const source = customisation.value?.options

	if (!Array.isArray(source) || source.length === 0) {
		return defaultRows
	}

	return source
})

const columns = computed(() => ({
	option: customisation.value?.table?.option || ctrans("Option"),
	available: customisation.value?.table?.available || ctrans("Available"),
	moq: customisation.value?.table?.moq || ctrans("MOQ"),
	notes: customisation.value?.table?.notes || ctrans("Notes"),
}))

const contactTitle = computed(
	() => customisation.value?.contact?.title || ctrans("Have Project in mind?")
)

const contactDescription = computed(
	() =>
		customisation.value?.contact?.description ||
		ctrans(
			"Looking for something we don't currently offer? Speak to our team. We can help you explore the development of a new product, even if there is nothing similar in our existing range."
		)
)

const contactButtonLabel = computed(
	() => customisation.value?.contact?.button?.text || ctrans("Contact Us")
)

const contactButtonUrl = computed(() => customisation.value?.contact?.button?.url || "#")

const isMobile = computed(() => props.screenType === "mobile")
</script>

<template>
	<div class="w-full py-6 md:py-8 lg:py-10" :style="containerStyle">
		<div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between lg:gap-10">
			<div class="flex-1">
				<h2 class="text-2xl font-semibold text-[#13294B] md:text-[28px] lg:text-[32px]">
					{{ title }}
				</h2>

				<div
					class="mt-3 max-w-xl text-[13px] leading-[1.7] text-[#334155] md:text-[14px]"
					v-html="description" />

				<a
					:href="linkUrl"
					class="mt-4 inline-block text-[13px] text-[#13294B] underline underline-offset-4 md:text-[14px]">
					{{ linkLabel }}
				</a>
			</div>

			<div v-if="hasImage" class="w-full max-w-[260px] shrink-0 self-center lg:self-start">
				<Image
					:src="image"
					:srcset="image?.srcset"
					sizes="(min-width: 1024px) 260px, 60vw"
					:image-cover="false"
					class="h-auto w-full object-contain"
					:alt="title" />
			</div>
		</div>

		<div
			class="mt-8 grid gap-3 md:gap-4"
			:class="isMobile ? 'grid-cols-2' : 'grid-cols-3 lg:grid-cols-6'">
			<div v-for="highlight in highlights" :key="highlight.key ?? highlight.label">
				<div
					class="flex h-[52px] items-center justify-center rounded-[6px] bg-[#C0899B] text-white md:h-[58px]"
					:style="getStyles(customisation?.highlight?.container?.properties)">
					<FontAwesomeIcon :icon="highlight.icon" class="text-xl md:text-2xl" />
				</div>

				<p class="mt-2 text-center text-[12px] text-[#13294B] md:text-[13px]">
					{{ highlight.label }}
				</p>
			</div>
		</div>

		<div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[1.7fr_1fr] lg:gap-12">
			<div class="overflow-x-auto">
				<table class="w-full min-w-[520px] border-collapse text-left">
					<thead>
						<tr class="bg-[#EDEDED] text-[12px] font-semibold text-[#13294B] md:text-[13px]">
							<th class="border border-[#DADADA] px-3 py-2">{{ columns.option }}</th>
							<th class="border border-[#DADADA] px-3 py-2 text-center">
								{{ columns.available }}
							</th>
							<th class="border border-[#DADADA] px-3 py-2 text-center">
								{{ columns.moq }}
							</th>
							<th class="border border-[#DADADA] px-3 py-2">{{ columns.notes }}</th>
						</tr>
					</thead>

					<tbody>
						<tr
							v-for="(row, index) in rows"
							:key="index"
							class="text-[11px] text-[#334155] md:text-[12px]">
							<td class="border border-[#DADADA] px-3 py-2 text-[#C0899B]">
								{{ row.option }}
							</td>

							<td class="border border-[#DADADA] px-3 py-2 text-center">
								<span
									v-if="row.available"
									class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#C0899B] text-[10px] text-white">
									<FontAwesomeIcon icon="fal fa-check" />
								</span>

								<span v-else class="text-[#9a9a9a]">—</span>
							</td>

							<td class="border border-[#DADADA] px-3 py-2 text-center">
								{{ row.moq }}
							</td>

							<td class="border border-[#DADADA] px-3 py-2" v-html="row.notes" />
						</tr>
					</tbody>
				</table>
			</div>

			<div>
				<h3 class="text-xl font-semibold text-[#13294B] md:text-2xl">
					{{ contactTitle }}
				</h3>

				<div
					class="mt-3 text-[12px] leading-[1.8] text-[#334155] md:text-[13px]"
					v-html="contactDescription" />

				<a :href="contactButtonUrl">
					<button
						class="mt-5 inline-flex w-full items-center justify-between gap-6 rounded-[6px] bg-[#0F1E2E] px-5 py-3 text-[14px] text-white transition hover:bg-[#1c2f43] md:w-auto md:text-[15px]"
						:style="getStyles(customisation?.contact?.button?.container?.properties)">
						<span>{{ contactButtonLabel }}</span>
						<span class="text-lg">›</span>
					</button>
				</a>
			</div>
		</div>
	</div>
</template>

<style scoped>
:deep(a) {
	color: #c0899b;
	text-decoration: underline;
	text-underline-offset: 2px;
}

:deep(p) {
	margin-bottom: 12px;
}

:deep(p:last-child) {
	margin-bottom: 0;
}
</style>
