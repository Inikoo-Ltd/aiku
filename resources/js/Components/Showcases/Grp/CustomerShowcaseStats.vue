<script setup lang="ts">
import { Chart as ChartJS, ArcElement, Tooltip, Legend, Colors } from "chart.js"
import { useLocaleStore } from "@/Stores/locale"
import { FulfilmentCustomerStats } from "@/types/Pallet"
import "@/Composables/Icon/PalletStateEnum.ts"
import "@/Composables/Icon/PalletDeliveryStateEnum.ts"
import "@/Composables/Icon/PalletReturnStateEnum.ts"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckCircle, faInfoCircle, faExclamationTriangle, faCheck } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import CountUp from "vue-countup-v3"
import { Link } from "@inertiajs/vue3"
import { RouteParams } from "@/types/route-params";
library.add(faCheckCircle, faInfoCircle, faExclamationTriangle, faCheck)

ChartJS.register(ArcElement, Tooltip, Legend, Colors)
const locale = useLocaleStore()

defineProps<{
	stats: {
		[key: string]: FulfilmentCustomerStats
	}
}>()

function routePallet(storageData: any) {
    console.log(storageData,'ssx');
    
	if (storageData == "Deliveries") {
		return route("grp.org.fulfilments.show.crm.customers.show.pallet_deliveries.index", [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            (route().params as RouteParams).fulfilmentCustomer,
		])
	} else if (storageData == "Returns") {
        return route("grp.org.fulfilments.show.crm.customers.show.pallet_returns.index", [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            (route().params as RouteParams).fulfilmentCustomer,
        ])
    } else if (storageData == "Invoice") {
        return route("grp.org.fulfilments.show.crm.customers.show.invoices.index", [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            (route().params as RouteParams).fulfilmentCustomer,
        ])
    } else if (storageData == "Pallets") {
        return route("grp.org.fulfilments.show.crm.customers.show.pallets.index", [
            (route().params as RouteParams).organisation,
            (route().params as RouteParams).fulfilment,
            (route().params as RouteParams).fulfilmentCustomer,
        ])
    } 
}
</script>

<template>
	<div class="grid grid-cols-2 gap-y-2.5 gap-x-3 text-gray-600">
		<!-- Box: Pallets -->
		<div
			class="flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums col-span-2">
			<dl>
				<dt class="text-base font-medium text-gray-400 capitalize">
					{{ stats.pallets.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- In Total -->
						<div class="flex gap-x-2 items-end">
							<Link :href="routePallet(stats.pallets.label) as string">
							<CountUp
								class="primaryLink"
								:endVal="stats.pallets.count"
								:duration="1.5"
								:scrollSpyOnce="true"
								:options="{
                                formattingFn: (value: number) => locale.number(value)
                            }" />
							</Link>

							<span class="text-sm font-medium leading-4 text-gray-400">
								{{ stats.pallets.description }}
							</span>
						</div>

						<div
							v-if="
								stats.pallets.state?.damaged?.count ||
								stats.pallets.state?.lost?.count ||
								stats.pallets.state?.['other_incident'].count
							"
							class="">
							<div
								class="text-sm text-red-400 border border-red-300 bg-red-50 rounded px-2 py-2 font-normal">
								<div v-if="stats.pallets.state?.damaged?.count">
									<FontAwesomeIcon
										:icon="stats.pallets.state?.damaged?.icon.icon"
										:class="stats.pallets.state?.damaged?.icon.class"
										fixed-width
										aria-hidden="true" />
									<!-- Damaged: -->
									{{ stats.pallets.state?.damaged?.count }}
								</div>
								<div v-if="stats.pallets.state?.lost?.count">
									<FontAwesomeIcon
										:icon="stats.pallets.state?.lost?.icon.icon"
										:class="stats.pallets.state?.lost?.icon.class"
										fixed-width
										aria-hidden="true" />
									<!-- Lost: -->
									{{ stats.pallets.state?.lost?.count }}
								</div>
								<div v-if="stats.pallets.state['other_incident'].count">
									<FontAwesomeIcon
										:icon="stats.pallets.state?.['other_incident']?.icon?.icon"
										:class="
											stats.pallets.state?.['other_incident']?.icon?.class
										"
										fixed-width
										aria-hidden="true" />
									<!-- Other incident: -->
									{{ stats.pallets.state?.["other_incident"].count }}
								</div>
							</div>
						</div>
					</div>
				</dd>
			</dl>
		</div>

		<!-- Box: Pallet Deliveries -->
		<div
			class="flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums">
			<dl>
				<dt class="text-base font-medium text-gray-400 capitalize">
					{{ stats.pallet_deliveries.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- In Total -->
						<div class="flex gap-x-2 items-end">
							<Link :href="routePallet(stats.pallet_deliveries.label) as string">
								<CountUp
									class="primaryLink"
									:endVal="stats.pallet_deliveries.count"
									:duration="1.5"
									:scrollSpyOnce="true"
									:options="{
                                    formattingFn: (value: number) => locale.number(value)
                                }" />
							</Link>

							<span class="text-sm font-medium leading-4 text-gray-400">
								{{ stats.pallet_deliveries.description }}
							</span>
						</div>
					</div>
				</dd>
			</dl>
		</div>

		<!-- Box: Pallet Returns -->
		<div
			class="flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums">
			<dl>
				<dt class="text-base font-medium text-gray-400 capitalize">
					{{ stats.pallet_returns.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- In Total -->
						<div class="flex gap-x-2 items-end">
							<Link :href="routePallet(stats.pallet_returns.label) as string">
								<CountUp
									class="primaryLink"
									:endVal="stats.pallet_returns.count"
									:duration="1.5"
									:scrollSpyOnce="true"
									:options="{
                                    formattingFn: (value: number) => locale.number(value)
                                }" />
							</Link>

							<span class="text-sm font-medium leading-4 text-gray-400">{{
								stats.pallet_returns.description
							}}</span>
						</div>
					</div>
				</dd>
			</dl>
		</div>

		<!-- Box: Invoice -->
		<div
			class="col-span-2 flex justify-between px-4 py-5 sm:p-6 rounded-lg bg-white border border-gray-300 tabular-nums">
			<dl>
				<dt class="text-base font-medium text-gray-400 capitalize">
					{{ stats.invoice.label }}
				</dt>
				<dd class="mt-2 flex justify-between gap-x-2">
					<div
						class="flex flex-col gap-x-2 gap-y-3 leading-none items-baseline text-2xl font-semibold text-org-500">
						<!-- In Total -->
						<div class="flex gap-x-2 items-end">
							<Link :href="routePallet(stats.invoice.label) as string">
								<CountUp
									class="primaryLink"
									:endVal="stats.invoice.count"
									:duration="1.5"
									:scrollSpyOnce="true"
									:options="{
                                    formattingFn: (value: number) => locale.number(value)
                                }" />
							</Link>

							<span class="text-sm font-medium leading-4 text-gray-400">
								{{ stats.invoice.description }}
							</span>
						</div>
					</div>
				</dd>
			</dl>
		</div>
	</div>
</template>
