<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 23 Sept 2024 22:02:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2024, Raul A Perusquia Flores
-->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import Dashboard from "@/Components/DataDisplay/Dashboard/Dashboard.vue";
import SalesAnalysisTeaser from "@/Components/SalesAnalysis/SalesAnalysisTeaser.vue"
import SalesAnalysisMovers from "@/Components/SalesAnalysis/SalesAnalysisMovers.vue"

const props = defineProps<{
    title: string,
    dashboard: any
    sales_analysis_url?: string
    sales_analysis_teaser?: object
}>();

</script>

<template>
    <Head :title="title" />
    <div v-if="sales_analysis_url" class="grid gap-4 px-4 pt-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
        <SalesAnalysisTeaser :teaser="sales_analysis_teaser" :analysisHref="sales_analysis_url" />
        <SalesAnalysisMovers :teaser="sales_analysis_teaser" />
    </div>
    <Dashboard :dashboard="props.dashboard" />
</template>
