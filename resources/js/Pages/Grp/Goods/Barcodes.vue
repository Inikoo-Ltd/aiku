<!-- 
 Author Louis Perez
 Created on 19-06-2026-09h-46m
 GitHub: https://github.com/louis-perez
 Copyright 2026 
-->

<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import PageHeading from "@/Components/Headings/PageHeading.vue";
import TableTradeUnits from "@/Components/Tables/Grp/Goods/TableTradeUnits.vue";
import { capitalize } from "@/Composables/capitalize";
import { PageHeadingTypes } from "@/types/PageHeading";
import { computed, ref } from "vue";
import Tabs from "@/Components/Navigation/Tabs.vue";
import { useTabChange } from "@/Composables/tab-change";
import TableBarcodes from "@/Components/Tables/Grp/Goods/TableBarcodes.vue";

const props = defineProps<{
    title: string;
    pageHead: PageHeadingTypes;
    tabs: {
        current: string;
        navigation: {};
    };
    index?: {};
}>();

const currentTab = ref<string>(props?.tabs?.current ?? "index");
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab);

const component = computed(() => {
    const components: Record<string, any> = {
        index: TableBarcodes,
    };

    return components[currentTab.value];
});
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs?.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :key="currentTab" :tab="currentTab" :data="props[currentTab]" />
</template>