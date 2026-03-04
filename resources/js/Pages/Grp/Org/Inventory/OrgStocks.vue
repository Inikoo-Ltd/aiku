<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:55:18 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableOrgStocks from "@/Components/Tables/Grp/Org/Inventory/TableOrgStocks.vue";
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
        index: TableOrgStocks,
        sales: TableOrgStocks,
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
