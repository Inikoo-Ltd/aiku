<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "@/Components/CMS/Webpage/Pricing/Blueprint"

library.add(faCheck)

const props = defineProps<{
	modelValue: any
	webpageData?: any
	blockData?: Object
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
		class="container flex flex-wrap justify-between"
		:style="getStyles(modelValue?.container?.properties)">
		<div class="mx-auto max-w-7xl px-6 lg:px-8">
			<Editor v-model="modelValue.text" @update:modelValue="() => emits('autoSave')" />
			<div
				class="isolate mx-auto mt-5 grid max-w-md grid-cols-1 gap-y-8 lg:mx-0 lg:max-w-none lg:grid-cols-3">
				<div
					v-for="(tier, tierIdx) in modelValue?.tiers"
					:key="tier.id"
					:class="[
						tier.mostPopular ? 'lg:z-10 lg:rounded-b-none' : 'lg:mt-8',
						tierIdx === 0 ? 'lg:rounded-r-none' : '',
						tierIdx === modelValue?.tiers?.length - 1 ? 'lg:rounded-l-none' : '',
						`flex flex-col justify-between rounded-3xl p-8 ring-1 ring-gray-200 xl:p-10`,
					]"
					:style="getBackgroundStyle(tier.background)">
					<div>
						<div class="flex items-center justify-between gap-x-4">
							<h3
								:id="tier.id"
								:class="[
									tier.mostPopular ? 'text-indigo-600' : 'text-gray-900',
									'text-lg/8 font-semibold',
								]">
								{{ tier.name }}
							</h3>
							<p
								v-if="tier.mostPopular"
								class="rounded-full bg-indigo-600/10 px-2.5 py-1 text-xs/5 font-semibold text-indigo-600">
								Most popular
							</p>
						</div>
						<Editor
							v-model="tier.description"
							@update:modelValue="() => emits('autoSave')" />
						<p class="mt-6 flex items-baseline gap-x-1">
							<span class="text-4xl font-semibold tracking-tight text-gray-900">{{
								tier.priceMonthly
							}}</span>
							<span class="text-sm/6 font-semibold text-gray-600">/month</span>
						</p>
						<ul role="list" class="mt-8 space-y-3 text-sm/6 text-gray-600">
							<li
								v-for="feature in tier.features"
								:key="feature"
								class="flex gap-x-3">
								<FontAwesomeIcon
									:icon="faCheck"
									class="h-6 w-5 flex-none text-indigo-600"
									fixed-width
									aria-hidden="true" />

								{{ feature }}
							</li>
						</ul>
					</div>
					<div class="flex justify-center">
						<div
							@click="
								() =>
									sendMessageToParent(
										'activeChildBlock',
										Blueprint?.blueprint?.[1]?.key?.join('-')
									)
							"
							typeof="button"
							:style="getStyles(tier.button.container.properties)"
							>
							{{ tier.button.text }}
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
