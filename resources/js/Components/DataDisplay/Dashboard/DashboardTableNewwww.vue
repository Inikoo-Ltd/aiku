<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { ref, computed } from "vue"
import { useLocaleStore } from "@/Stores/locale"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import { Link } from "@inertiajs/vue3"
import { router } from "@inertiajs/vue3"
import { data } from "@/Components/CMS/Website/Product/ProductTemplates/Product1/Descriptor"
import { trans } from "laravel-vue-i18n"
import DeltaItemDashboard from "../../Utils/DeltaItemDashboard.vue"
import LabelItemDashboard from "@/Components/Utils/LabelItemDashboard.vue"

const props = defineProps<{
	tableData: {}[]
	locale: any
	totalAmount: {
		total_invoices: number
		total_sales: number
		total_refunds: number
	}
	total_tooltip: {
		total_sales: string
		total_invoices: string
		total_refunds: string
	}
	currency_code?: string
	tableType?: string
	current?: string
	settings: {}
	dashboardTable: {
		tab_label: string
		tab_slug: string
		type: string // 'table'
		data: {}
	}[]
}>()

function ShopDashboard(shop: any) {
	return route(shop?.route?.name, shop?.route?.parameters)
}

const activeIndexTab = ref(props.current)

const selectedTab = computed(() => {
	return props.dashboardTable.find((tab) => tab.tab_slug === activeIndexTab.value)
})
function useTabChangeDashboard(tab_slug: string) {
	if (tab_slug === activeIndexTab.value) {
		return
	}

	router.reload({
		data: { tab_dashboard_interval: tab_slug },
		// only: ['dashboard_stats'],
		onSuccess: () => {
			activeIndexTab.value = tab_slug
		},
		onError: (error) => {
			console.error("Error reloading dashboard:", error)
		},
	})
}
console.log('ewqewqewq', selectedTab.value.data)

const listColumnInTable = computed(() => {
	
	const resultSet = new Set();  // Create a Set to store unique elements

    // Iterate through each sub-array in the input array
    selectedTab.value.data?.map((e) => Object.keys(e.interval_percentages || {})).forEach(subArray => {
        // Add each element of the sub-array to the Set
        subArray.forEach(item => resultSet.add(item));
    });

    // Convert the Set back to an Array to return the result
    return Array.from(resultSet);
})
</script>

<template>
	<div class="bg-white mb-3 p-4 shadow-md border border-gray-200">
		<div class="">
			<Tabs :value="activeIndexTab" class="overflow-x-auto text-sm md:text-base pb-2">
				<TabList>
					<Tab
						v-for="tab in dashboardTable"
						@click="() => useTabChangeDashboard(tab.tab_slug)"
						:key="tab.tab_slug"
						:value="tab.tab_slug"
						class="qwezxc">
						<FontAwesomeIcon
							:icon="tab.tab_icon"
							class=""
							fixed-width
							aria-hidden="true" />
						{{ tab.tab_label }}</Tab
					>
				</TabList>
			</Tabs>

		</div>
	</div>
</template>
<style scoped>
:deep(.p-tab) {
	/* padding: 0.5rem 1rem; */
	@apply py-2.5 px-3 md:py-4 md:px-4;
}

::v-deep .p-datatable-tbody > tr > td {
	padding: 0.25em !important;
	color: #7c7c7c !important;
}
::v-deep .p-datatable-header-cell {
	padding: 0.25em !important;
	color: #7c7c7c !important;
}
::v-deep .p-datatable-tfoot > tr > td {
	padding: 0.25em !important;
	color: #7c7c7c !important;
	border-top: 1px solid rgba(59, 59, 59, 0.5) !important;
}

::v-deep .p-datatable-column-footer {
	font-weight: 400 !important;
	color: #474545 !important;
}
</style>
