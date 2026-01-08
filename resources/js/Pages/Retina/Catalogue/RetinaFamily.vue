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
	faBrowser,
} from "@fal"
import { faExclamationTriangle } from "@fas"

import PageHeading from "@/Components/Headings/PageHeading.vue"
import { computed, ref, onMounted } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import ModelDetails from "@/Components/ModelDetails.vue"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { capitalize } from "@/Composables/capitalize"
import RetinaFamilyShowcase from "@/Components/Showcases/Retina/Catalouge/RetinaFamilyShowcase.vue"
import RetinaTableProducts from "@/Components/Tables/Retina/RetinaTableProducts.vue"
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
	faBrowser,
	faExclamationTriangle
)

const props = defineProps<{
	title: string
	pageHead: object
	tabs: {
		current: string
		navigation: object
	}
	showcase: object
	products: object
	data: object
}>()

const currentTab = ref(props.tabs.current)
const handleTabUpdate = (tabSlug: string) => {
	useTabChange(tabSlug, currentTab)
}

const component = computed(() => {
	const components = {
		showcase: RetinaFamilyShowcase,
		products: RetinaTableProducts,
	}
	return components[currentTab.value] ?? ModelDetails
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
		<template #other>
			<AddPortfolio
				:channels="channelArray"
				:productCategory="data.showcase"
				@submitted="handleChannelsSubmitted" />
		</template>
		<!--  <template #button-to-portfolio>
            <ButtonAddCategoryToPortfolio :products="data.products.data" :categoryId="data.showcase"
                :routeGetCategoryChannels="{ name: 'retina.json.product_category.channel_ids.index', parameters: { productCategory: data.showcase } }"
                :routeAddPortfolios="{ name: 'retina.models.portfolio.store_to_multi_channels', parameters: { productCategory: data.showcase } }" />
        </template> -->
	</PageHeading>
	<Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
	<component :is="component" :data="props[currentTab]" :tab="currentTab" />
</template>
