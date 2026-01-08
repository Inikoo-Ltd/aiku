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
	faCube,
	faFolder,
	faMoneyBillWave,
	faProjectDiagram,
	faTag,
	faUser,
} from "@fal"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref, onMounted } from "vue"
import type { Component } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { faDiagramNext } from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import RetinaSubDepartmentShowcase from "@/Components/Showcases/Retina/Catalouge/RetinaSubDepartmentShowcase.vue"
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue"
import RetinaTableFamilies from "@/Components/Tables/Retina/RetinaTableFamilies.vue"
import RetinaTableCollections from "@/Components/Tables/Retina/RetinaTableCollections.vue"
import AddPortfolio from "@/Components/Retina/AddPortfolioModal.vue"
import { useLayoutStore } from "@/Stores/retinaLayout"
import axios from "axios"

library.add(
	faFolder,
	faCube,
	faCameraRetro,
	faTag,
	faBullhorn,
	faProjectDiagram,
	faUser,
	faMoneyBillWave,
	faDiagramNext
)

const props = defineProps<{
	title: string
	pageHead: object
	tabs: {
		current: string
		navigation: object
	}
	showcase: {}
	customers: {}
	products: {}
	families: {}
	collections: {}
	data: {
		showcase: number
		products: object
	}
}>()

let currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component: Component = computed(() => {
	const components = {
		showcase: RetinaSubDepartmentShowcase,
		products: RetinaTableProducts,
		families: RetinaTableFamilies,
		collections: RetinaTableCollections,
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
				productCategory: props.data.showcase,
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
	const current = Array.isArray(usedCustomerChannels.value)
		? usedCustomerChannels.value
		: Object.values(usedCustomerChannels.value ?? [])

	usedCustomerChannels.value = [...new Set([...current, ...ids])]
}

onMounted(() => {
	getCustomerChannels()
})
</script>

<template>
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead">
		<template #button-to-portfolio>
			<ButtonAddCategoryToPortfolio
				:products="data.products.data"
				:categoryId="data.showcase"
				:routeGetCategoryChannels="{
					name: 'retina.json.product_category.channel_ids.index',
					parameters: { productCategory: data.showcase },
				}"
				:routeAddPortfolios="{
					name: 'retina.models.portfolio.store_to_multi_channels',
					parameters: { productCategory: data.showcase },
				}" />
		</template>

		<template #other>
			<AddPortfolio
				:channels="channelArray"
				:productCategory="data.showcase"
				@submitted="handleChannelsSubmitted" />
		</template>
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
	<component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
</template>
