<script setup lang="ts">
import { Head } from "@inertiajs/vue3"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faExclamationCircle } from "@fortawesome/free-solid-svg-icons"
import { capitalize } from "@/Composables/capitalize"
import { inject, ref, computed, onMounted, onUnmounted } from "vue"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import {
	faBoothCurtain,
	faBoxes,
	faBoxOpen,
	faBrowser,
	faCoin,
	faDolly,
	faEnvelope,
	faEnvelopeOpenText,
	faExchangeAlt,
	faFilter,
	faForklift,
	faInboxOut,
	faIndustryAlt,
	faInventory,
	faLocationArrow,
	faMailBulk,
	faRoad,
	faTrashAlt,
	faTruckLoading,
	faUser,
	faUserAlien,
	faUserCircle,
	faUserHeadset,
	faUsers,
	faWarehouseAlt,
	faPaperPlane,
	faScrollOld,
	faPhoneVolume,
	faRaygun,
} from "@fal"
import SectionTable from "@/Components/Table/SectionTable.vue"
import { faAngleDown, faAngleUp } from "@far"
import StoreStatsCard from "@/Components/DataDisplay/StoreStatsCard.vue"
import InfoDashboardCard from "@/Components/DataDisplay/InfoDashboardCard.vue"
import ProgressDashboardCard from "@/Components/DataDisplay/ProgressDashboardCard.vue"
import Dashboard from "@/Components/DataDisplay/Dashboard/DashboardOld.vue"

const props = defineProps<{
	title: string
	pageHead: any
	dashboard_stats: {
		
	}
}>()

const locale = inject("locale", aikuLocaleStructure)

library.add(
	faExclamationCircle,
	faInboxOut,
	faUser,
	faFilter,
	faBoxes,
	faAngleDown,
	faAngleUp,
	faBrowser,
	faTrashAlt,
	faCoin,
	faBoothCurtain,
	faRoad,
	faUserAlien,
	faEnvelopeOpenText,
	faEnvelope,
	faUserCircle,
	faExchangeAlt,
	faDolly,
	faBoxOpen,
	faTruckLoading,
	faForklift,
	faUsers,
	faUserHeadset,
	faWarehouseAlt,
	faInventory,
	faLocationArrow,
	faIndustryAlt,
	faMailBulk,
	faPaperPlane,
	faPhoneVolume,
	faRaygun,
	faScrollOld,
	faUserCircle
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
	const totalColumns = props.dashboard?.columns?.length || 1
	const columnSpan = Math.floor(12 / totalColumns)
	return `col-span-${columnSpan}`
})
</script>

<template>
	<!-- Page Header -->
	<Head :title="capitalize(title)" />
	<PageHeading :data="pageHead" />

	<!-- Dashboard Grid -->
	<div class="grid grid-cols-12 gap-4 p-4">
		<!-- Left Column -->
		<div class="col-span-12">
			<!-- Loop through columns for the table -->
			<!-- <template v-for="(column, colIndex) in props.dashboard.columns" :key="colIndex">
				<template v-for="(widget, widgetIndex) in column.widgets" :key="widgetIndex">
					<div v-if="widget.type === 'overview_table'">
						<SectionTable :data="widget.data.data" />
					</div>
				</template>
			</template> -->
			<Dashboard
				:dashboard="dashboard_stats"
			/>
		</div>

		<!-- Middle Column -->
		<div class="col-span-3 space-y-4">
			<!-- Loop through widgets for middle column -->
			<!-- <template v-for="(column, colIndex) in props.dashboard.columns" :key="colIndex">
				<template v-for="(widget, widgetIndex) in column.widgets" :key="widgetIndex">
					<div v-if="widget.type === 'multi_card'">
						<StoreStatsCard :label="widget.label" :data="widget.data" :gridCols="2" />
					</div>
				</template>
			</template> -->
		</div>

		<!-- Right Column -->
		<div class="col-span-3 space-y-4">
			<!-- Loop through widgets for right column -->
		<!-- 	<template v-for="(column, colIndex) in props.dashboard.columns" :key="colIndex">
				<template v-for="(widget, widgetIndex) in column.widgets" :key="widgetIndex">
					<div
						v-if="widget.type === 'card_currency' || widget.type === 'card_percentage'">
						<InfoDashboardCard
							:value="widget.value"
							:description="widget.label"
							:showRedBorder="widget.type === 'card_currency'"
							:showIcon="widget.type === 'card_currency'"
							type="dashboard"
							class="h-full" />
					</div>
				</template>
			</template> -->
		</div>
	</div>
</template>

<style scoped>
.search-container {
	transition: width 0.3s ease;
}
</style>
