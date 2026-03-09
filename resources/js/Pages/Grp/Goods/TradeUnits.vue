<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 24 Mar 2024 21:06:12 Malaysia Time, Mexico City, Mexico
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { computed, ref } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    tabs: {
        current: string;
        navigation: {};
    };
    index?: {};
    sales?: {};
}>();

const currentTab = ref<string>(props?.tabs?.current ?? "index");
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Record<string, any> = {
        index: TableTradeUnits,
        sales: TableTradeUnits,
    };

    return components[currentTab.value];
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead"></PageHeading>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <component
        :is="component"
        :key="currentTab"
        :tab="currentTab"
        :data="props[currentTab]"
    />
</template>
