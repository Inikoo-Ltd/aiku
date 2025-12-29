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
    

    <div class="p-8 flex flex-wrap gap-2">
        <section v-for="offer in first_order_bonus" class="card w-80 bg-gradient-to-r from-green-500 to-teal-500 text-white">
            <div class="text-center  text-base w-[88px] flex flex-col justify-center px-1">
                {{ locale.currencyFormat(data?.currency?.code, offer.trigger_data.min_amount) }}
                <span class="text-[0.55rem] leading-[0.5rem]">Min. quantity: {{ offer.trigger_data.order_number }}</span>
            </div>
            <div class="card-right">
                <p class="card-info">{{ offer.name }}</p>
                <strong class="text-xxs italic font-normal opacity-70">{{ useFormatTime(offer.created_at)}} - {{ offer.end_at ? useFormatTime(offer.end_at) : 'Not described' }}</strong>
            </div>
        </section>
    </div>

    <!-- <pre>{{ first_order_bonus }}</pre> -->
</template>


<style lang="scss" scoped>
.card{
    display: flex;
    align-items: center;
    border-radius: 8px;
    -webkit-mask-image: radial-gradient(circle at 88px 4px, transparent 4px, red 4.5px), radial-gradient(closest-side circle at 50%, red 99%, transparent 100%);
    -webkit-mask-size: 100%, 2px 4px;
    -webkit-mask-repeat: repeat, repeat-y;
    -webkit-mask-position: 0 -4px, 87px;
    -webkit-mask-composite: source-out;
    mask-composite: subtract;
}

.card-right{
    padding: 16px 12px;
    display: flex;
    flex: 1;
    flex-direction: column;
}
.card-info{
    margin: 0;
    font-size: 14px;
    line-height: 20px;
}
</style>
