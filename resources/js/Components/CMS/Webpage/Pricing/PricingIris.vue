<script setup lang="ts">
import Editor from "@/Components/Forms/Fields/BubleTextEditor/EditorV2.vue"
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck } from "@fal"

library.add(faCheck)

const props = defineProps<{
	fieldValue: {}
	theme?: any
}>()

const emits = defineEmits<{
	(e: "update:modelValue", value: any): void
	(e: "autoSave"): void
}>()


</script>

<template>
	<div
		class="container flex flex-wrap justify-between"
		:style="getStyles(fieldValue?.container?.properties)">
		<div class="mx-auto max-w-7xl px-6 lg:px-8">
			<div v-html="fieldValue.text" />
			<div
				class="isolate mx-auto grid max-w-md mt-5 grid-cols-1 gap-y-8 lg:mx-0 lg:max-w-none lg:grid-cols-3">
				<div
					v-for="(tier, tierIdx) in fieldValue?.tiers"
					:key="tier.id"
					:class="[
						tier.mostPopular ? 'lg:z-10 lg:rounded-b-none' : 'lg:mt-8',
						tierIdx === 0 ? 'lg:rounded-r-none' : '',
						tierIdx === fieldValue?.tiers?.length - 1 ? 'lg:rounded-l-none' : '',
						'flex flex-col justify-between rounded-3xl bg-white p-8 ring-1 ring-gray-200 xl:p-10',
					]">
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
								v-if="tier.badge.show"
								class="rounded-full bg-indigo-600/10 px-2.5 py-1 text-xs/5 font-semibold text-indigo-600">
								{{tier.badge.text}}
							</p>
						</div>
						<p class="mt-4 text-sm/6 text-gray-600">{{ tier.description }}</p>
						<p v-if="tier.priceMonthly.show" class="mt-6 flex items-baseline gap-x-1">
							<span class="text-4xl font-semibold tracking-tight text-gray-900">{{
								tier.priceMonthly.text
							}}</span>
							<!-- <span class="text-sm/6 font-semibold text-gray-600">/month</span> -->
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
					<a
						v-if="tier.link"
						:href="tier.link.href"
						:aria-describedby="tier.id"
						:target="tier.link.target"
						:class="[
							tier.mostPopular
								? 'bg-indigo-600 text-white shadow-xs hover:bg-indigo-500'
								: 'text-indigo-600 ring-1 ring-indigo-200 ring-inset hover:ring-indigo-300',
							'mt-8 block rounded-md px-3 py-2 text-center text-sm/6 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
						]">
						Buy plan
					</a>
				</div>
			</div>
		</div>
	</div>
</template>
