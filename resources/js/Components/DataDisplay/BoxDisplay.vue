<script setup lang="ts">
import { Pie } from "vue-chartjs"
import { trans } from "laravel-vue-i18n"
import { capitalize } from "@/Composables/capitalize"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { useLocaleStore } from "@/Stores/locale"
import { FulfilmentCustomerStats, PieCustomer } from "@/types/Pallet"

import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faInfoCircle, faExclamationTriangle, faCheck, faTombstone } from "@fal"

import { library } from "@fortawesome/fontawesome-svg-core"
import CountUp from "vue-countup-v3"
import index from "@/Components/Banners/SlidesWorkshop/Fields/index.vue"
library.add(faCheckCircle, faInfoCircle, faExclamationTriangle, faCheck, faTombstone)

ChartJS.register(ArcElement, Tooltip, Legend, Colors)
const locale = useLocaleStore()

const props = defineProps<{
	data: {
		label: string
		count: number
		description?: string
		full?: boolean
		orther_counts: any
	}[]
}>()
</script>

<template>
	<div class="grid grid-cols-2 gap-y-2.5 gap-x-3 text-gray-600">
		<!-- Box: Pallets -->
		<div
			v-for="box in data"
			class="flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums"
			:class="[box.full ? 'col-span-2' : '']">
			<div class="">
				<dt class="text-base font-medium text-gray-400">{{ box.label }}</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold">
						<!-- In Total -->
						<div class="flex gap-x-2 items-end">
							<CountUp
								:endVal="box.count || 0"
								:duration="1.5"
								:scrollSpyOnce="true"
								:options="{
                                formattingFn: (value: number) => locale.number(value || 0)
                            }" />
							<span
								v-if="box.description"
								class="text-sm font-medium leading-4 text-gray-400">
								{{ box.description }}
							</span>
						</div>

						<div class="">
							<div
								v-for="(item, index) in box.orther_counts"
								:key="index"
								class="text-sm border rounded px-2 py-2 font-normal flex justify-between items-center">
								<!-- Count on the left, icon on the right -->
								<span>{{ item.count || 0 }}</span>
								<FontAwesomeIcon
									v-tooltip="item.icon.title"
									:icon="item.icon.icon"
									:class="item.icon.class"
									fixed-width
									aria-hidden="true" />
							</div>
						</div>
					</div>
				</dd>
			</div>
		</div>
	</div>
</template>
