<script setup lang="ts">
import { ref, computed } from "vue"
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { Pie } from "vue-chartjs"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from "@inertiajs/vue3"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(faUsers, faUserCheck, faUserSlash, faUserPlus, faMoneyBillWave)

ChartJS.register(ArcElement, Tooltip, Legend, Colors)
import { capitalize } from 'lodash-es'
import { useLocaleStore } from "@/Stores/locale"

const locale = useLocaleStore()

const dummyData = {
	prospectStats: {
		customers: {
			label: "Customers",
			count: 209099,
			cases: {
				in_process: {
					value: "in_process",
					icon: {
						tooltip: "In process",
						icon: "fal fa-circle-notch",
						class: "text-lime-500",
						color: "lime",
					},
					count: 0,
					label: "In Process",
					route: {
						name: "grp.org.shops.show.crm.customers.index",
						parameters: {
							organisation: "aw",
							shop: "uk",
							"customers_elements[state]": "in_process",
							tab: "customers",
						},
					},
				},
				registered: {
					value: "registered",
					icon: {
						tooltip: "Registered",
						icon: "fas fa-exclamation-circle",
						class: "text-orange-500",
						color: "orange",
					},
					count: 0,
					label: "Registered",
					route: {
						name: "grp.org.shops.show.crm.customers.index",
						parameters: {
							organisation: "aw",
							shop: "uk",
							"customers_elements[state]": "registered",
							tab: "customers",
						},
					},
				},
				active: {
					value: "active",
					icon: {
						tooltip: "Active",
						icon: "fas fa-circle",
						class: "text-emerald-500",
						color: "emerald",
					},
					count: 146880,
					label: "Active",
					route: {
						name: "grp.org.shops.show.crm.customers.index",
						parameters: {
							organisation: "aw",
							shop: "uk",
							"customers_elements[state]": "active",
							tab: "customers",
						},
					},
				},
				losing: {
					value: "losing",
					icon: {
						tooltip: "Potential Comebacks",
						icon: "fas fa-circle",
						class: "text-orange-500",
						color: "orange",
					},
					count: 7079,
					label: "Potential Comebacks",
					route: {
						name: "grp.org.shops.show.crm.customers.index",
						parameters: {
							organisation: "aw",
							shop: "uk",
							"customers_elements[state]": "losing",
							tab: "customers",
						},
					},
				},
				lost: {
					value: "lost",
					icon: {
						tooltip: "Dormant",
						icon: "fas fa-circle",
						class: "text-red-500",
						color: "red",
					},
					count: 55140,
					label: "Dormant",
					route: {
						name: "grp.org.shops.show.crm.customers.index",
						parameters: {
							organisation: "aw",
							shop: "uk",
							"customers_elements[state]": "lost",
							tab: "customers",
						},
					},
				},
			},
		},
	},
}

const customerStats = computed(() => {
	const customers = dummyData.prospectStats.customers
	return {
		label: customers.label,
		count: customers.count,
		cases: Object.values(customers.cases).map((caseItem) => ({
			value: caseItem.value,
			count: caseItem.count,
			label: caseItem.label,
			route: caseItem.route,
			icon: {
				icon: caseItem.icon.icon,
				tooltip: caseItem.icon.tooltip,
				class: caseItem.icon.class,
				color: caseItem.icon.color,
			},
		})),
	}
})

const options = {
	responsive: true,
	plugins: {
		legend: { display: false },
		tooltip: {
			titleFont: { size: 10, weight: "lighter" },
			bodyFont: { size: 11, weight: "bold" },
		},
	},
}

const isLoadingVisit = ref<number | null>(null)
</script>
<template>
	<div>
		<dl class="">
			<div class="px-4 py-5 sm:p-6 rounded-lg bg-white shadow tabular-nums">
				<dt class="text-base font-medium text-gray-400 capitalize">
					{{ customerStats.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- Total Count -->
						<div class="flex gap-x-2 items-end">
							{{ locale.number(customerStats.count) }}
							<span class="text-sm font-medium leading-4 text-gray-500">
								{{ trans("in total") }}
							</span>
						</div>

						<!-- Case Breakdown -->
						<div
							class="text-sm text-gray-500 flex gap-x-5 gap-y-1 items-center flex-wrap">
							<template
								v-for="(dCase, idxCase) in customerStats.cases"
								:key="dCase.value">
								<component
									:is="dCase.route?.name ? Link : 'div'"
									:href="
										dCase.route?.name
											? route(dCase.route.name, dCase.route.parameters)
											: null
									"
									:class="
										dCase.route?.name
											? 'hover:bg-gray-200 px-1 py-0.5 rounded'
											: ''
									"
									class="flex gap-x-0.5 items-center font-normal"
									v-tooltip="capitalize(dCase.icon.tooltip)"
									@start="() => (isLoadingVisit = idxCase)"
									@finish="() => (isLoadingVisit = null)">
									<LoadingIcon
										v-if="isLoadingVisit === idxCase"
										class="text-gray-500" />
									<FontAwesomeIcon
										v-else
										:icon="dCase.icon.icon"
										:class="dCase.icon.class"
										fixed-width
										:title="dCase.icon.tooltip"
										aria-hidden="true" />
									<span class="font-semibold">{{
										locale.number(dCase.count)
									}}</span>
								</component>
							</template>
						</div>
					</div>

					<!-- Pie Chart -->
					<div class="w-20">
						<Pie
							:data="{
								labels: customerStats.cases.map((c) => c.label),
								datasets: [
									{
										data: customerStats.cases.map((c) => c.count),
										hoverOffset: 4,
									},
								],
							}"
							:options="options" />
					</div>
				</dd>
			</div>
		</dl>
	</div>
</template>
