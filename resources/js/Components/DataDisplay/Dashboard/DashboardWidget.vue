<script setup lang="ts">
import { inject } from "vue";
import WidgetOrganisations from "./Widget/WidgetOrganisations.vue"
import WidgetShops from "./Widget/WidgetShops.vue"
import CustomerClv from "@/Components/CustomerCLV.vue";
import SalesVsRefunds from "@/Components/DataDisplay/Dashboard/Widget/SalesVsRefunds.vue";

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
    stats: {
        revenue_amount: number
        lost_revenue_other_amount: number
        number_invoices: number
        number_invoices_type_refund: number
    }
    currencyCode: { code: string }
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
        <SalesVsRefunds v-if="props.stats" :data="props.stats" :currencyCode="props.currencyCode" />
    </div>
</template>
