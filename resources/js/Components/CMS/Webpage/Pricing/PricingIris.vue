<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import Image from "@/Components/Image.vue"

library.add(faCheck)

const props = defineProps<{
	fieldValue: {}
	theme?: any
	screenType: "mobile" | "tablet" | "desktop"
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()

const getBackgroundStyle = (bg: any): Record<string, string> => {
	if (bg && bg.type === "color" && bg.color) {
		return { backgroundColor: bg.color }
	} else if (bg && bg.type === "image" && bg.image?.original) {
		return { backgroundImage: `url(${bg.image.original})` }
	}
	return {}
}
</script>

<template>
	<div
		class="flex justify-center"
		:style="getStyles(fieldValue?.container?.properties, screenType)">
		<div class="w-full max-w-7xl px-6 py-12 flex flex-col items-center">
			<div v-html="fieldValue.text" />
			<div
				class="isolate mx-auto grid max-w-md mt-5 grid-cols-1 gap-y-8 lg:mx-0 lg:max-w-none lg:grid-cols-3">
				<div
					v-for="tier in fieldValue.tiers"
					:key="tier.id"
					class="relative flex flex-col justify-between rounded-3xl bg-white p-8 shadow-lg"
					:class="tier.mostPopular ? 'ring-4 ring-indigo-500' : ''"
					:style="getBackgroundStyle(tier.background)">
					<p
						v-if="tier.badge.show"
						class="absolute top-2 right-2 z-10 rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">
						{{ tier.badge.text }}
					</p>

					<div class="flex justify-center items-center mb-4">
						<!-- real image -->
						<template v-if="tier?.image?.source">
							<Image
								:src="tier?.image?.source"
								:imageCover="true"
								:alt="tier?.image?.alt"
								:imgAttributes="tier?.image?.attributes"
								:style="getStyles(tier?.image?.properties)" />
						</template>
					</div>
					<div>
						<div v-html="tier?.title" />
						<div v-html="tier?.description" />

						<p v-if="tier?.priceMonthly?.show" class="mt-6 flex items-baseline gap-x-1">
							<span class="text-4xl font-semibold tracking-tight text-gray-900">{{
								tier?.priceMonthly?.text
							}}</span>
							<!-- <span class="text-sm/6 font-semibold text-gray-600">/month</span> -->
						</p>
						<ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
							<li
								v-for="feature in tier?.features"
								:key="feature"
								class="flex gap-x-3">
								<FontAwesomeIcon
									:icon="faCheck"
									class="h-6 w-5 flex-none text-[#C1A027]"
									fixed-width
									aria-hidden="true" />

								{{ feature }}
							</li>
						</ul>
					</div>
					<div class="flex justify-center">
						<div typeof="button" :style="getStyles(tier.button.container.properties)">
							{{ tier.button.text }}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
