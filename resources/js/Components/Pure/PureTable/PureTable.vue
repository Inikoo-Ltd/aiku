<script setup lang="ts">
import { routeType } from "@/types/route"
import { onMounted, ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"
import DataTable from "primevue/datatable"
import Column from "primevue/column"
import axios from "axios"

const props = defineProps<{
	blueprint: Array<{ key: string; header: string }>
	route: routeType
	tableProps: any
}>()

const loading = ref(false)
const data = ref([])
const meta = ref({
	current_page: 1,
	from: 1,
	last_page: 1,
	per_page: 10,
	to: 2,
	total: 2,
})

const fetchData = async (pageNumber = 1, pageSize = 10, showLoading = false) => {
	if (showLoading) loading.value = true // Only show loading if needed
	try {
		const response = await axios.get(
			route(props.route.name, {
				...props.route.parameters,
				page: pageNumber,
				per_page: pageSize,
			})
		)
		if (response.data?.data) {
			data.value = response.data.data.map((item) => ({
				...item,
				refund: "0",
			}))
			meta.value = response.data.meta
		}
	} catch (error) {
		console.log(error)
		notify({
			title: trans("Something went wrong"),
			text: trans("Failed to fetch data"),
			type: "error",
		})
	} finally {
		if (showLoading) loading.value = false // Only reset loading if it was set
	}
}

// Handle pagination
const onPage = (event: { page: number; rows: number }) => {
	fetchData(event.page + 1, event.rows, true)
}

// Fetch data on mount (with loading)
onMounted(() => fetchData(meta.value.current_page, meta.value.per_page, true))

defineExpose({
	data,
	fetchData
})
</script>

<template>
	<!-- Slot Header -->
	<slot name="header"></slot>

	<DataTable :value="data" v-bind="tableProps" :paginator="meta.last_page != 1" :rows="meta.per_page"
		:totalRecords="meta.total" lazy :loading="loading" paginatorPosition="bottom" class="custom-paginator"
		@page="onPage">

		<template v-for="(col, index) in blueprint" :key="col.key">
			<Column :field="col.key" :style="{ width: col.width || 'auto' }">
				<template #header>
					<slot :name="`${col.key}-header`" :index="index">
						<span class="font-bold">{{ col.header }}</span>
					</slot>
				</template>
				<template #body="slotProps">
					<slot :name="col.key" :data="slotProps.data" :index="slotProps.index">
						<span class="p-2">{{ slotProps.data[col.key] }}</span>
					</slot>
				</template>
			</Column>
		</template>


		<slot name="footer" :data="data"></slot>
	</DataTable>
</template>
