<script setup lang="ts">
import type { Component } from "vue";
import { Head } from "@inertiajs/vue3";
import { computed, inject, ref } from "vue";
import { Tabs as TSTabs } from "@/types/Tabs";
import { capitalize } from "@/Composables/capitalize";
import { useTabChange } from "@/Composables/tab-change";
import SimpleBox from "@/Components/DataDisplay/SimpleBox.vue";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import { layoutStructure } from "@/Composables/useLayoutStructure";
import { PageHeadingTypes } from "@/types/PageHeading";
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure";
import DashboardTable from "@/Components/DataDisplay/Dashboard/DashboardTable.vue";
import DashboardSettings from "@/Components/DataDisplay/Dashboard/DashboardSettings.vue";
import { useFormatTime } from "@/Composables/useFormatTime"
import Coupon from "@/Components/Utils/Coupon.vue"

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

const locale = inject("locale", aikuLocaleStructure);
const layout = inject("layout", layoutStructure);

// const currentTab = ref(props.tabs.current);
// const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

// const component = computed(() => {
//     const components: Component = {
//         dashboard: {}
//     }
//
//     return components[currentTab.value];
// });

console.log("Discounts Dashboard Props: ", props);
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />
<!--    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />-->
<!--    <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />-->
    <SimpleBox v-if="stats && layout.app.environment === 'production'" :box_stats="stats" />
    <DashboardSettings
        :intervals="intervals"
        :settings="settings"
        :currentTab="blocks.current_tab"
    />
    <DashboardTable
        v-if="blocks && (layout.app.environment === 'local' || layout.app.environment === 'staging')"
        class="border-t border-gray-200"
        idTable="discounts_dashboard_tab"
        :tableData="blocks"
        :intervals="intervals"
        :settings="settings"
        :currentTab="blocks.current_tab"
    />
    
    <Coupon v-if="first_order_bonus" :offer="first_order_bonus" :currency_code="data.currency.code" />

    <!-- <pre>{{ first_order_bonus }}</pre> -->
</template>



