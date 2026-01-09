<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import { library } from "@fortawesome/fontawesome-svg-core"
import {
	faBullhorn,
	faCameraRetro,
	faClock,
	faCube,
	faCubes,
	faFolder,
	faMoneyBillWave,
	faProjectDiagram,
	faTags,
	faUser,
	faFolders,
	faBrowser,
	faSeedling,
} from "@fal"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref, watch, onMounted } from "vue"
import RetinaDepartmentShowcase from "@/Components/Showcases/Retina/Catalouge/RetinaDepartmentShowcase.vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons"
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue"
import RetinaTableFamilies from "@/Components/Tables/Retina/RetinaTableFamilies.vue"
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from "@/types/PageHeading"
import RetinaTableCollections from "@/Components/Tables/Retina/RetinaTableCollections.vue"
import TableSubDepartments from "@/Components/Tables/Retina/RetinaTableSubDepartments.vue"
import AddPortfolio from "@/Components/Retina/AddPortfolioModal.vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import axios from "axios"

library.add(
	faFolder,
	faCube,
	faCameraRetro,
	faClock,
	faProjectDiagram,
	faBullhorn,
	faTags,
	faUser,
	faMoneyBillWave,
	faDiagramNext,
	faCubes,
	faFolders,
	faBrowser,
	faSeedling
)

const props = defineProps<{
	title: string
	pageHead: PageHeadingTypes
	tabs: {
		current: string
		navigation: object
	}
	products?: object
	families?: object
	collections?: object
	sub_departments?: object
	showcase?: object
	data: {
		department: {
			id: number
		}
		showcase: number
		products: object
	}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
	const components = {
		showcase: RetinaDepartmentShowcase,
		products: RetinaTableProducts,
		families: RetinaTableFamilies,
		collections: RetinaTableCollections,
		sub_departments: TableSubDepartments,
	}
	return components[currentTab.value]
})

const layout = useLayoutStore()
const usedCustomerChannels = ref<Record<string, any>>({})
const isLoadingChannels = ref(false)

const allCustomerChannels = computed(() => {
	return layout.user?.customerSalesChannels ?? {}
})

const getCustomerChannels = async () => {
	try {
		isLoadingChannels.value = true

		const res = await axios.get(
			route("retina.json.dropshipping.product.channels_list_product_category", {
				productCategory: props.data.department.id,
			}),
			{ withCredentials: true }
		)

		usedCustomerChannels.value =
			res.data?.data?.customer_channels ?? res.data?.customer_channels ?? res.data ?? {}
	} catch (error) {
		console.error("Failed to load used channels", error)
	} finally {
		isLoadingChannels.value = false
	}
}

const channelArray = computed(() => {
	const all = allCustomerChannels.value
	const used = usedCustomerChannels.value

	if (!all || typeof all !== "object") return []

	return Object.values(all).map((c: any) => ({
		customer_sales_channel_id: c.customer_sales_channel_id,
		customer_sales_channel_name: c.customer_sales_channel_name,
		platform_name: c.platform_name ?? "-",
		is_used: Array.isArray(used) ? used.includes(c.customer_sales_channel_id) : false,
	}))
})

const handleChannelsSubmitted = (ids: number[]) => {
	usedCustomerChannels.value = [...new Set([...(usedCustomerChannels.value ?? []), ...ids])]
}

onMounted(() => {
	getCustomerChannels()
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #other>
			<AddPortfolio
				:channels="channelArray"
				:productCategory="data.department.id"
				@submitted="handleChannelsSubmitted" />
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
	<component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
