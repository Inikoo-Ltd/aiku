<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 04 Apr 2023 11:19:33 Malaysia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import FlatTreeMap from "@/Components/Navigation/FlatTreeMap.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faNarwhal, faBusinessTime, faUserTie, faSignOut as falSignout } from "@fal"
import { faTruck, faSignOut, faDollarSign } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faNarwhal, faBusinessTime, faUserTie, falSignout, faTruck, faSignOut, faDollarSign)

import {
	Chart as ChartJS,
	CategoryScale,
	LinearScale,
	PointElement,
	LineElement,
	Title,
	Tooltip,
	Legend,
} from "chart.js"
import { inject } from "vue"
import { trans } from "laravel-vue-i18n"
import Dashboard from "@/Components/DataDisplay/Dashboard/DashboardOld.vue"
import { Link } from "@inertiajs/vue3"
ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Legend)

const props = defineProps<{
	data: {
		dashboard_stats: {}
		flatTreeMaps: {}
		scheduledActivities: {
			icon: string
			title: string
			description: string
		}[]
	}
}>()

const layout = inject("layout", {})

const dataStats = {
	labels: [
		"January",
		"February",
		"March",
		"April",
		"May",
		"June",
		"July",
		"August",
		"September",
		"October",
		"November",
		"December",
	],
	datasets: [
		{
			label: "Dataset 1",
			data: [34, 55, 47, 33, 42, 55, 37, 45, 43, 54, 49, 53],
			borderColor: layout.app.theme[0],
			backgroundColor: layout.app.theme[3],
		},
		{
			label: "Dataset 1",
			data: [37, 49, 35, 43, 40, 52, 44, 49, 40, 52, 55, 47],
			borderColor: layout.app.theme[2],
			backgroundColor: layout.app.theme[2],
		},
	],
}

function palletRoute(pallet: any) {
    console.log(pallet.title);
    
    if (pallet.title == "pallets") {
        console.log(pallet.title);
		return route("grp.org.fulfilments.show.operations.pallets.current.index", [
			route().params.organisation,
			route().params.fulfilment,
		])
	} else if (pallet.title == "pallet delivery") {
        return route("grp.org.fulfilments.show.operations.pallet-deliveries.index", [
            route().params.organisation,
            route().params.fulfilment,
        ])
    } else if (pallet.title == "pallet returns") {
        return route("grp.org.fulfilments.show.operations.pallet-returns.index", [
            route().params.organisation,
            route().params.fulfilment,
        ])
    }
}
</script>

<template>
	<Dashboard class="p-4" :dashboard="data.dashboard_stats" />

	<div class="mt-2">
		<FlatTreeMap
			class="mx-4"
			v-for="(treeMap, idx) in data.flatTreeMaps"
			:key="idx"
			:nodes="treeMap"
			mode="compact" />
	</div>

	<div class="px-4 py-6">
		<div class="grid grid-cols-3 gap-x-4">
			<!-- <div class="col-span-2 flex flex-col gap-y-8">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-10 text-slate-600 sm:grid-cols-2 sm:gap-y-16 lg:mx-0 lg:max-w-none lg:grid-cols-4">
                    <div v-for="stat in stats" :key="stat.id" class="flex flex-col gap-y-3 border-l border-indigo-700/20 pl-6">
                        <dt class="text-sm leading-6">{{ stat.name }}</dt>
                        <dd class="order-first text-3xl font-semibold tracking-tight" :style="{color: layout.app.theme[3]}">{{ stat.value }}</dd>
                    </div>
                </dl>

                <div class="">
                    <Line :data="dataStats" :options="config" />
                </div>
            </div> -->

			<!-- Section: Scheduled activities -->
			<div class="bg-slate-50 rounded-lg ring-1 ring-slate-300 py-4">
				<div class="px-6 pb-3 border-b border-gray-300">
					<div class="font-semibold text-lg">{{ trans("Pending tasks") }}</div>
				</div>
				<div class="px-6 mt-4 flex flex-col gap-y-4">
					<div
						v-for="activity in data.scheduledActivities"
						class="flex items-center gap-x-3">

						<div
							class="h-11 aspect-square bg-slate-200 text-slate-600 rounded-md flex justify-center items-center">
							<FontAwesomeIcon
								:icon="activity.icon"
								class="text-lg"
								fixed-width
								aria-hidden="true" />
						</div>

						<div class="flex flex-col">
							<Link :href="palletRoute(activity)">
								<div class="capitalize">{{ activity.title }}</div>
								<div class="text-slate-400 text-sm">{{ activity.description }}</div>
							</Link>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</template>
