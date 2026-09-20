<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeadingPublic.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationCircle } from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import { inject, ref, computed, onMounted, onUnmounted } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import {
    faFileInvoiceDollar,
} from "@fal"
import SectionTable from "@/Components/Table/SectionTable.vue"
import { faAngleDown, faAngleUp } from "@far"
import { faReceipt } from "@fas"
import StoreStatsCard from "@/Components/DataDisplay/StoreStatsCard.vue"
import InfoDashboardCard from "@/Components/DataDisplay/InfoDashboardCard.vue"
import ProgressDashboardCard from "@/Components/DataDisplay/ProgressDashboardCard.vue"
import RetinaTable from "@/Components/Table/RetinaTableDashboard.vue"

const props = defineProps<{
	title: string
	pageHead: any
	dashboard_stats: {
		columns: Array<{
			widgets: Array<{
				type: string
				label?: string
				route?: string
				value?: number
				data?: Array<{
					name: string
					icon: string
					route: string
					count: number
				}>
			} | null>
		}>
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

library.add(
faFileInvoiceDollar, faReceipt
)

// Search functionality
const searchQuery = ref("")
const isFilterVisible = ref(false)

const handleClickOutside = (event: Event) => {
	const target = event.target as HTMLElement
	if (!target.closest(".search-container")) {
		isFilterVisible.value = false
	}
}

onMounted(() => {
	document.addEventListener("click", handleClickOutside)
})

onUnmounted(() => {
	document.removeEventListener("click", handleClickOutside)
})

const columnClasses = computed(() => {
	return (colIndex: number) => (colIndex === 0 ? "col-span-6" : "col-span-3")
})
</script>

<template>
	<!-- Page Header -->
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<div class="grid grid-cols-12 gap-4 p-4">
		<!-- Loop through each column -->
		<template v-for="(column, colIndex) in props.dashboard_stats.columns" :key="colIndex">
			<div :class="[columnClasses(colIndex), 'space-y-4']">
				<!-- Loop through widgets in the column -->
				<template v-for="(widget, widgetIndex) in column.widgets" :key="widgetIndex">
					<div v-if="widget">
						<!-- Unpaid Invoices Section -->
						<div v-if="widget.type === 'unpaid_invoices'">
							<RetinaTable :data="widget.data" />
						</div>

						<!-- Card with Number -->
						<div v-if="widget.type === 'card_number'">
							<InfoDashboardCard
								:value="widget.value"
								:description="widget.label"
								:showRedBorder="false"
								:showIcon="false"
                                type="retina"
                                :route="widget.route"
								class="h-full" />
						</div>

						<!-- Progress Dashboard Card -->
						<div v-if="widget.type === 'card_progress_bar'">
							<ProgressDashboardCard
								:label="widget.label"
								:value="widget.value"
								:progressBar="widget.data" />
						</div>
					</div>
				</template>
			</div>
		</template>
	</div>
</template>

<style scoped>
.search-container {
	transition: width 0.3s ease;
}
</style>
