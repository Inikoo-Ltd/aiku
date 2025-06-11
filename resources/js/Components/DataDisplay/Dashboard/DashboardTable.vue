<script setup lang="ts">
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import Row from "primevue/row"
import ColumnGroup from "primevue/columngroup"
import { ref, computed, inject } from "vue"
import Tabs from "primevue/tabs"
import TabList from "primevue/tablist"
import Tab from "primevue/tab"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faYinYang, faShoppingBasket, faSitemap, faStore } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import axios from "axios"
import { debounce } from 'lodash-es'
import DashboardCell from "./DashboardCell.vue"
library.add(faYinYang, faShoppingBasket, faSitemap, faStore)

interface Column {
	formatted_value: string  // "€0.00"
	raw_value: string  // "0.00"
	tooltip: string
	sortable?: boolean
	align?: string
} 

interface Header {
	slug: string
	columns: {
		[key: string]: {  // key: 'baskets_created_org_currency', 'baskets_created_grp_currency', 'baskets_created_grp_currency_minified'
			[key: string]: Column  // key: '1y', '3m', '1m', '7d', '1d'
		}
	} | Column
}
interface Tables {
	[key: string]: {
		body: {

		}
		header: Header
		total: {

		}
		slug: string
		state: string
		columns: {
			baskets_created_org_currency: {
				'1y': {
					formatted_value: string  // "€0.00"
					raw_value: string  // "0.00"
					tooltip: string
				} 
			}
		}
	}[]
}

const props = defineProps<{
	idTable: string  //  organisation_dashboard_tab
	tableData: {
		charts: []
		current_tab: string  // 'shops'
		id: string  // 'sales_table'
		tables: Tables
		tabs: {
			[key: string]: {
				icon: string
				title: string
			}
		}
	}
	intervals: {
		options: {
            label: string
            value: string
            labelShort: string
        }[]
		value: string
		range_interval: string
	}
	settings: {
		[key: string]: {  // 'data_display_type'
			align: string
			id: string
			options: {
				label: string
				value: string
			}[]
			type: string
			value: string
		}
	}
	currentTab: string
}>()

const emits = defineEmits<(e: "onChangeTab", val: string) => void>()

const isLoadingOnTable = inject("isLoadingOnTable", ref(false))

console.log('%c Tables ', 'background: red; color: white', props.tableData.tables);
console.log('%c Settings ', 'background: blue; color: white', props.settings);


const compTableBody = computed(() => {
	if (props.settings.model_state_type?.value === 'open') {
		return props.tableData.tables[props.tableData.current_tab].body?.filter(row => row.state === 'active')
	}

	return props.tableData.tables[props.tableData.current_tab].body;
})


const showDashboardColumn = (column: any) => {

  const data_display_type=props.settings.data_display_type.value;
  const currency_type=props.settings.currency_type?.value;


  let show=true;


  if(column.data_display_type!='always'){
   if( column.data_display_type!==data_display_type){
      return false;
    }
  }

  if(column.currency_type!='always'){
    if(column.currency_type!==currency_type){
      return false;
    }
  }

  return show;

}

// Section: update Tab of the table
const debStoreTab = debounce((tab: string) => {
	axios.patch(route("grp.models.profile.update"), {
		settings: {
			[props.idTable]: tab,
		},
	}).then(() => {
		// isLoadingOnTable.value = false
	}).catch(() => {
		// isLoadingOnTable.value = false
	})
}, 800)
const updateTab = (value: string) => {
	emits('onChangeTab', value)
	debStoreTab(value)
}

</script>

<template>
	<div class="relative bg-white mb-3 px-4">

		<div class="">
			<!-- Section: Tabs -->
			<Tabs :value="tableData.current_tab" class="overflow-x-auto text-xs md:text-base pb-2">
				<TabList>
					<Tab
						v-for="(tab, tabSlug) in tableData.tabs"
						@click="() => (
							updateTab(tabSlug),
							tableData.current_tab = tabSlug
						)"
						:key="tabSlug"
						:value="tabSlug"
					>
						<FontAwesomeIcon :icon="tab.icon" class="" fixed-width aria-hidden="true" />
						{{ tab.title }}
					</Tab>
				</TabList>
			</Tabs>

			<!-- Section: Table -->
			<DataTable :value="compTableBody" removableSort scrollable >
				<template #empty>
					<div class="flex items-center justify-center h-full text-center">
						{{ trans("No data available.") }}
					</div>
				</template>
				
				<!-- Column (looping) -->
				<template
					v-for="(columnHeader, colSlug, colIndex) in props.tableData?.tables?.[props.tableData?.current_tab]?.header?.columns"
					:key="colSlug"
				>
					<Column
						v-if="showDashboardColumn(columnHeader)"
						:sortable="columnHeader.sortable"
						:sortField="`columns.${colSlug}.${intervals.value}.raw_value`"
						:field="colSlug"
						:frozen="columnHeader.frozen"
						:alignFrozen="columnHeader.alignFrozen"
					>
						<template #header>
							<div class="px-2 text-xs md:text-base flex items-center w-full gap-x-2 font-semibold text-gray-600"
								:class="columnHeader.align === 'left' ? '' : 'justify-end text-right'"
								v-tooltip="columnHeader.tooltip"
							>
								<FontAwesomeIcon v-if="columnHeader.icon" :icon="columnHeader.icon" class="" fixed-width aria-hidden="true" />
								<span class="leading-5">{{ columnHeader.formatted_value }}</span>
								<FontAwesomeIcon v-if="columnHeader.iconRight" :icon="columnHeader.iconRight" class="" fixed-width aria-hidden="true" />
							</div>
						</template>
						
						<template #body="{ data: dataBody }">
							<div class="px-2 flex relative" :class="[ columnHeader.align === 'left' ? '' : 'justify-end text-right', ]" >
								<DashboardCell
									:interval="intervals"
									:cell="dataBody.columns?.[colSlug]?.[intervals.value] ?? dataBody.columns[colSlug]"
								/>
							</div>
						</template>
					</Column>
				</template>
			
				<!-- Row: Total (footer) -->
				<ColumnGroup type="footer">
					<Row>
						<template
							v-for="(column, colSlug) in props.tableData?.tables?.[props.tableData?.current_tab]?.header?.columns"
							:key="colSlug"
						>
							<Column
								v-if="showDashboardColumn(column)"
							>
								<template #footer>
									<div class="px-2 flex relative"
										:class="props.tableData.tables?.[props.tableData?.current_tab]?.header?.columns?.[colSlug]?.align === 'left' ? '' : 'justify-end text-right'"
									>
										<DashboardCell
											:interval="intervals"
											:cell="
												props.tableData?.tables?.[props.tableData?.current_tab]?.totals?.columns?.[colSlug]?.[intervals.value]
												?? props.tableData?.tables?.[props.tableData?.current_tab]?.totals?.columns?.[colSlug]
											"
										/>
									</div>
								</template>
							</Column>
						</template>
					</Row>
				</ColumnGroup>
			</DataTable>

			<div v-if="isLoadingOnTable" class="z-10 absolute inset-0 bg-white/50 flex justify-center items-center text-5xl">
				<LoadingIcon />
			</div>


		</div>
	</div>
</template>
<style scoped>
:deep(.p-tab) {
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

:deep(.p-datatable-scrollable .p-datatable-frozen-column) {
    position: sticky;
    background: var(--p-datatable-header-cell-background);
}
</style>
