<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 20 Mar 2023 23:18:59 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->
  
<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { Website } from "@/types/website"
import Icon from "@/Components/Icon.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSkull } from "@fal"
import axios from "axios"
import Chart from "primevue/chart"
import { ref } from "vue"

library.add(faSkull)

const props = defineProps<{
	data: object
	tab?: string
}>()

function websiteShopRoute(website: Website) {
	return route("grp.org.shops.show.web.websites.show", [
		website.organisation_slug,
		website.shop_slug,
		website.slug,
	])
}

function websiteFulfilmentRoute(website: Website) {
	return route("grp.org.fulfilments.show.web.websites.show", [
		website.organisation_slug,
		website.fulfilment_slug,
		website.slug,
	])
}

const chartDataMap = ref(new Map())

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  aspectRatio: 2,
  plugins: {
    legend: {
      display: false,
    },
  },
  scales: {
    x: {
      display: false,
    },
    y: {
      display: false,
    },
  },
  elements: {
    point: {
      radius: 0,
    },
  },
}


const fetchUniqueVisitorData = async (website:any) => {
	if (!chartDataMap.value.has(website.rowIndex)) {
		try {
			const response = await axios.get(route(website.routeUniqueVisitor.name, website.routeUniqueVisitor.parameters))
			const byDayData = response.data.data.viewer.zones[0]?.byDay || []

			if (byDayData.length === 0) {
				chartDataMap.value.set(website.rowIndex, { labels: [], datasets: [], totalUniques: 0 })
				return
			}

			const uniqueVisitorData = byDayData.map((entry) => ({
				date: entry.dimensions.ts,
				uniques: entry.uniq.uniques
			}))
			
			const totalUniques = uniqueVisitorData.reduce((total, item) => total + item.uniques, 0)

			const labels = uniqueVisitorData.map((item) => item.date)
			const data = uniqueVisitorData.map((item) => item.uniques)

			chartDataMap.value.set(website.rowIndex, {
				labels: labels,
				datasets: [
					{
						data: data,
						borderColor: "#4285F4",
						backgroundColor: "rgba(237,244,255)",
						fill: true,
            borderWidth: 1,
						tension: 0
					},
				],
				totalUniques: totalUniques, 
			})
		} catch (error) {
			// Log the error and set a fallback entry in chartDataMap
			console.error(error)
			chartDataMap.value.set(website.rowIndex, { labels: [], datasets: [], totalUniques: 0 })
		}
	}
}

</script>

<template>
	<Table :resource="data" :name="tab" class="mt-5">
		<template #cell(code)="{ item: website }">
			<Link
				v-if="website.shop_type === 'fulfilment'"
				:href="websiteFulfilmentRoute(website)"
				class="primaryLink">
				{{ website["code"] }}
			</Link>
			<Link v-else :href="websiteShopRoute(website)" class="primaryLink">
				{{ website["code"] }}
			</Link>
		</template>

		<template #cell(state)="{ item: website }">
			<Icon :data="website['state_icon']" class="px-1" />
		</template>

		<template #cell(routeUniqueVisitor)="{ item: website }">
			<div v-if="website.routeUniqueVisitor" class="flex min-w-7 max-w-11 items-center space-x-2">
				<div v-if="fetchUniqueVisitorData(website)">
					<Chart
						v-if="chartDataMap.get(website.rowIndex)?.datasets?.length > 0"
						type="line"
						:data="chartDataMap.get(website.rowIndex)"
						:options="chartOptions"
						class="h-[50px]"
					/>
				</div>
				<div>
					{{ chartDataMap.get(website.rowIndex)?.totalUniques || "" }}
				</div>
			</div>
		</template>
	</Table>
</template>
