<script setup lang="ts">
import WidgetShops from "./Widget/WidgetShops.vue";
import WidgetOrganisations from "./Widget/WidgetOrganisations.vue";

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
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-3 px-3 sm:px-6">
        <WidgetOrganisations
            v-if="props.tableData?.tables?.organisations && props.tableData?.tables?.organisations.body.length !== 0"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
        <WidgetShops
            v-if="props.tableData?.tables?.shops && props.tableData?.tables?.shops.body.length !== 0"
            :tableData="props.tableData"
            :intervals="props.intervals"
        />
    </div>
</template>
