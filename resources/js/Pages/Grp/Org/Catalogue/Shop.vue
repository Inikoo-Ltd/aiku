<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Sept 2024 22:02:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { onMounted } from "vue"
import SalesAnalysis from "@/Components/SalesAnalysis/SalesAnalysis.vue"
import ShopCustomers from "@/Components/DataDisplay/Dashboard/ShopCustomers.vue"
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue";

const props = defineProps<{
    title: string,
    dashboard: any
    sales_analysis?: object
    customers_dashboard?: object
}>();

const lazySectionProps: Record<string, "sales_analysis" | "customers_dashboard"> = {
    sales_analysis: "sales_analysis",
    customers: "customers_dashboard",
}

const loadSectionData = (section: string) => {
    const prop = lazySectionProps[section]
    if (prop && !props[prop]) {
        router.reload({ only: [prop] })
    }
}

onMounted(() => loadSectionData(props.dashboard?.super_blocks?.[0]?.sections?.current))

</script>

<template>
    <Head :title="title" />
    <Dashboard :dashboard="props.dashboard" @sectionChanged="loadSectionData">
        <template #customers>
            <ShopCustomers :data="customers_dashboard" />
        </template>
        <template #salesAnalysis>
            <SalesAnalysis :data="sales_analysis" />
        </template>
    </Dashboard>
</template>
