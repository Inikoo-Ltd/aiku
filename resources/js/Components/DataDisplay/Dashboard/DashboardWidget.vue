<script setup lang="ts">
import { inject, computed } from "vue";
import WidgetShops from "./Widget/WidgetShops.vue";
import WidgetOrganisations from "./Widget/WidgetOrganisations.vue";
import RegistrationsWithOrders from "@/Components/DataDisplay/Dashboard/Widget/RegistrationsWithOrders.vue";
import RegistrationsWithoutOrders from "@/Components/DataDisplay/Dashboard/Widget/RegistrationsWithoutOrders.vue";

const props = defineProps<{
	intervals: {
		options: {
			label: string
			value: string
			labelShort: string
		}[]
		value: string
	}
    tableData: {}
}>()

const layout = inject('layout')

const totalsColumns = computed(() => {
    if (props.tableData?.tables?.organisations?.totals?.columns) {
        return props.tableData.tables.organisations.totals.columns
    }
    if (props.tableData?.tables?.shops?.totals?.columns) {
        return props.tableData.tables.shops.totals.columns
    }
    return null
})

const hasRegistrationsWithOrders = computed(() => {
    const value = totalsColumns.value?.registrations_with_orders?.[props.intervals.value]?.raw_value
    return value && Number(value) > 0
})

const hasRegistrationsWithoutOrders = computed(() => {
    const value = totalsColumns.value?.registrations_without_orders?.[props.intervals.value]?.raw_value
    return value && Number(value) > 0
})
</script>

<template>
    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-3 px-4">
        <WidgetOrganisations
            v-if="props.tableData?.tables?.organisations"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
        <WidgetShops
            v-if="props.tableData?.tables?.shops"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
        <RegistrationsWithOrders
            v-if="hasRegistrationsWithOrders"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
        <RegistrationsWithoutOrders
            v-if="hasRegistrationsWithoutOrders"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
    </div>
</template>
