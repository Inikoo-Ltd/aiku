<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { Tabs as TSTabs } from "@/types/Tabs";
import { capitalize } from "@/Composables/capitalize";
import SimpleBox from "@/Components/DataDisplay/SimpleBox.vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue";
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue";
import { faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faSortAmountUp, faHandHoldingUsd, faChartNetwork } from "@fal";
import { library } from "@fortawesome/fontawesome-svg-core";

library.add(faYinYang, faShoppingBasket, faSitemap, faStore, faRepeat, faPercentage, faFlag, faUsers, faTags, faBox, faSortAmountUp, faHandHoldingUsd, faChartNetwork);

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    intervals: any;
    settings: any;
    tabs: TSTabs;
    blocks: any;
    data: {
        currency: {
            code: string
        }
    }
}>();


</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
     <DashboardSettings
        :intervals="intervals"
        :settings="settings"
        :currentTab="blocks.current_tab"
    />
    <DashboardTable
        v-if="blocks"
        class="border-t border-gray-200"
        idTable="discounts_dashboard_tab"
        :tableData="blocks"
        :intervals="intervals"
        :settings="settings"
        :currentTab="blocks.current_tab"
    />

</template>
