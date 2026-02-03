<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { Tabs as TSTabs } from "@/types/Tabs";
import { capitalize } from "@/Composables/capitalize";
import SimpleBox from "@/Components/DataDisplay/SimpleBox.vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { PageHeadingTypes } from "@/types/PageHeading";
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue";
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue";


const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    intervals: any;
    settings: any;
    tabs: TSTabs;
    blocks: any;
    stats: {
        label: string
        count: number
        icon: string
    }[];
    first_order_bonus: {
        name: string
        state: string
        status: string
        trigger_data: {
            min_amount: number
            order_number: 1
        }
        duration: string  // 'permanent'
    }[]
    data: {
        currency: {
            code: string
        }
    }
}>();


console.log("Discounts Dashboard Props: ", props);
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
