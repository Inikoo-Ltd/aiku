<script setup lang="ts">
import { inject } from "vue";
import WidgetOrganisations from "./Widget/WidgetOrganisations.vue"
import WidgetShops from "./Widget/WidgetShops.vue"
import CustomerClv from "@/Components/CustomerCLV.vue";

const props = defineProps<{
	intervals: {
		options: {
			label: string
			value: string
			labelShort: string
		}[]
		value: string
	}
    tableData: {

    }
}>()

const layout = inject('layout')
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
       <CustomerClv v-if="layout?.app?.environment === 'local'" />
    </div>
</template>
